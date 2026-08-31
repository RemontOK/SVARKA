<?php
/**
 * Переписка клиента с менеджером.
 *
 * Клиент (по сессии):
 *   GET  ?action=list                 — своя переписка
 *   POST ?action=send {body}          — написать менеджеру
 *
 * Менеджер (по админскому паролю):
 *   GET  ?action=threads              — список диалогов с числом непрочитанных
 *   GET  ?action=thread&clientId=..   — переписка с клиентом
 *   POST ?action=reply {clientId, body}
 *
 * Клиент всегда работает только со своей перепиской: clientId берётся
 * из сессии, а не из запроса.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/client-auth-lib.php';
require_once __DIR__ . '/admin-auth.php';

const ASMBOT_MESSAGE_MAX = 4000;

function asmbot_msg_fail($code, $message)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function asmbot_is_admin_request()
{
    $provided = isset($_SERVER['HTTP_X_ADMIN_PASSWORD'])
        ? (string) $_SERVER['HTTP_X_ADMIN_PASSWORD']
        : '';
    return $provided !== '' && hash_equals(asmbot_admin_password(), $provided);
}

function asmbot_shape_message(array $row)
{
    return [
        'id' => (int) $row['id'],
        'author' => $row['author'],
        'body' => $row['body'],
        'createdAt' => $row['created_at'],
        'read' => !empty($row['read_at']),
    ];
}

try {
    $pdo = asmbot_db();
} catch (RuntimeException $e) {
    asmbot_msg_fail(500, 'База данных недоступна.');
}

$action = isset($_GET['action']) ? trim((string) $_GET['action']) : '';
$method = $_SERVER['REQUEST_METHOD'];

$input = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $decoded = $raw ? json_decode($raw, true) : null;
    $input = is_array($decoded) ? $decoded : [];
}

$isAdmin = asmbot_is_admin_request();

// ============================================================ менеджер
if ($isAdmin) {
    if ($action === 'threads') {
        $rows = $pdo->query(
            "SELECT c.id, c.email, c.name, c.company,
                    COUNT(m.id) AS total,
                    SUM(CASE WHEN m.author = 'client' AND m.read_at IS NULL THEN 1 ELSE 0 END) AS unread,
                    MAX(m.created_at) AS last_at,
                    SUBSTRING_INDEX(GROUP_CONCAT(m.body ORDER BY m.created_at DESC SEPARATOR '\\n---\\n'), '\\n---\\n', 1) AS last_body
             FROM clients c
             JOIN messages m ON m.client_id = c.id
             GROUP BY c.id
             ORDER BY last_at DESC
             LIMIT 200"
        )->fetchAll();

        $threads = array_map(static function ($row) {
            return [
                'clientId' => (int) $row['id'],
                'email' => $row['email'],
                'name' => $row['name'],
                'company' => $row['company'],
                'total' => (int) $row['total'],
                'unread' => (int) $row['unread'],
                'lastAt' => $row['last_at'],
                'lastBody' => mb_substr((string) $row['last_body'], 0, 160),
            ];
        }, $rows);

        echo json_encode(['ok' => true, 'threads' => $threads], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'thread') {
        $clientId = isset($_GET['clientId']) ? (int) $_GET['clientId'] : 0;
        if ($clientId <= 0) {
            asmbot_msg_fail(400, 'Не указан клиент.');
        }

        $stmt = $pdo->prepare('SELECT * FROM messages WHERE client_id = ? ORDER BY created_at ASC LIMIT 500');
        $stmt->execute([$clientId]);
        $messages = array_map('asmbot_shape_message', $stmt->fetchAll());

        // Открыли диалог — сообщения клиента считаются прочитанными.
        $mark = $pdo->prepare(
            "UPDATE messages SET read_at = NOW() WHERE client_id = ? AND author = 'client' AND read_at IS NULL"
        );
        $mark->execute([$clientId]);

        echo json_encode(['ok' => true, 'messages' => $messages], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'reply' && $method === 'POST') {
        $clientId = isset($input['clientId']) ? (int) $input['clientId'] : 0;
        $body = mb_substr(trim((string) ($input['body'] ?? '')), 0, ASMBOT_MESSAGE_MAX);

        if ($clientId <= 0) {
            asmbot_msg_fail(400, 'Не указан клиент.');
        }
        if ($body === '') {
            asmbot_msg_fail(400, 'Пустое сообщение.');
        }

        $stmt = $pdo->prepare("INSERT INTO messages (client_id, author, body) VALUES (?, 'manager', ?)");
        $stmt->execute([$clientId, $body]);

        echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    asmbot_msg_fail(400, 'Неизвестное действие.');
}

// ============================================================== клиент
$client = asmbot_current_client($pdo);
if (!$client) {
    asmbot_msg_fail(401, 'Требуется вход.');
}

if ($action === 'list') {
    $stmt = $pdo->prepare('SELECT * FROM messages WHERE client_id = ? ORDER BY created_at ASC LIMIT 500');
    $stmt->execute([$client['id']]);
    $messages = array_map('asmbot_shape_message', $stmt->fetchAll());

    echo json_encode(['ok' => true, 'messages' => $messages], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'send' && $method === 'POST') {
    $body = mb_substr(trim((string) ($input['body'] ?? '')), 0, ASMBOT_MESSAGE_MAX);
    if ($body === '') {
        asmbot_msg_fail(400, 'Введите сообщение.');
    }

    // Простая защита от флуда: не чаще одного сообщения в 3 секунды.
    $recent = $pdo->prepare(
        "SELECT COUNT(*) AS n FROM messages
         WHERE client_id = ? AND author = 'client' AND created_at > (NOW() - INTERVAL 3 SECOND)"
    );
    $recent->execute([$client['id']]);
    if ((int) $recent->fetch()['n'] > 0) {
        asmbot_msg_fail(429, 'Слишком часто. Подождите пару секунд.');
    }

    $stmt = $pdo->prepare("INSERT INTO messages (client_id, author, body) VALUES (?, 'client', ?)");
    $stmt->execute([$client['id'], $body]);

    echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
    exit;
}

asmbot_msg_fail(400, 'Неизвестное действие.');
