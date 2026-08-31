<?php
/**
 * Модерация сообщества: статьи читателей и комментарии.
 *
 *   GET  ?action=list                            — что ждёт решения и что уже вышло
 *   GET  ?action=article&slug=<адрес>            — полный текст присланной статьи
 *   POST ?action=article-status {slug, status, note}
 *   POST ?action=comment-status {id, status}
 *
 * Комментарии выходят сразу и снимаются задним числом; статьи, наоборот, ждут
 * решения — они попадают в общий список материалов и говорят от лица сайта.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/admin-auth.php';
require_once __DIR__ . '/db.php';

asmbot_require_admin();

function asmbot_mod_fail($code, $message)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function asmbot_mod_ok(array $data = [])
{
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Любая неожиданность в базе — это всё равно ответ JSON, а не страница
// ошибки: вызывающая сторона разбирает только JSON.
set_exception_handler(function () {
    asmbot_mod_fail(503, 'База временно недоступна.');
});

try {
    $pdo = asmbot_db();
} catch (Throwable $e) {
    // Голого 500 быть не должно: страница ждёт JSON и молча прячет обсуждение.
    asmbot_mod_fail(503, 'База недоступна.');
}

$action = isset($_GET['action']) ? (string) $_GET['action'] : 'list';
$method = $_SERVER['REQUEST_METHOD'];

$input = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $decoded = $raw ? json_decode($raw, true) : null;
    $input = is_array($decoded) ? $decoded : [];
}

if ($action === 'list') {
    $articles = $pdo->query(
        "SELECT a.slug, a.title, a.excerpt, a.author, a.status, a.moderator_note,
                a.created_at, a.updated_at, c.email,
                CHAR_LENGTH(a.body) AS body_length
         FROM community_articles a
         LEFT JOIN clients c ON c.id = a.client_id
         ORDER BY (a.status = 'pending') DESC, a.created_at DESC
         LIMIT 200"
    )->fetchAll(PDO::FETCH_ASSOC);

    $comments = $pdo->query(
        "SELECT id, article, author, body, status, created_at
         FROM article_comments
         ORDER BY created_at DESC
         LIMIT 200"
    )->fetchAll(PDO::FETCH_ASSOC);

    asmbot_mod_ok(['articles' => $articles, 'comments' => $comments]);
}

if ($action === 'article') {
    $slug = isset($_GET['slug']) ? (string) $_GET['slug'] : '';
    $stmt = $pdo->prepare('SELECT slug, title, excerpt, body, author, status, moderator_note, created_at FROM community_articles WHERE slug = ?');
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        asmbot_mod_fail(404, 'Статья не найдена.');
    }
    asmbot_mod_ok(['article' => $row]);
}

if ($action === 'article-status') {
    if ($method !== 'POST') {
        asmbot_mod_fail(405, 'Только POST.');
    }
    $slug = (string) ($input['slug'] ?? '');
    $status = (string) ($input['status'] ?? '');
    $note = mb_substr(trim((string) ($input['note'] ?? '')), 0, 400, 'UTF-8');

    if (!in_array($status, ['pending', 'published', 'rejected'], true)) {
        asmbot_mod_fail(422, 'Неизвестный статус.');
    }

    $stmt = $pdo->prepare('UPDATE community_articles SET status = ?, moderator_note = ? WHERE slug = ?');
    $stmt->execute([$status, $note, $slug]);
    if (!$stmt->rowCount()) {
        // rowCount = 0 бывает и когда значение то же самое: проверяем наличие.
        $check = $pdo->prepare('SELECT COUNT(*) FROM community_articles WHERE slug = ?');
        $check->execute([$slug]);
        if (!(int) $check->fetchColumn()) {
            asmbot_mod_fail(404, 'Статья не найдена.');
        }
    }

    asmbot_mod_ok(['slug' => $slug, 'status' => $status]);
}

if ($action === 'comment-status') {
    if ($method !== 'POST') {
        asmbot_mod_fail(405, 'Только POST.');
    }
    $id = (int) ($input['id'] ?? 0);
    $status = (string) ($input['status'] ?? '');
    if (!in_array($status, ['published', 'hidden'], true)) {
        asmbot_mod_fail(422, 'Неизвестный статус.');
    }

    $stmt = $pdo->prepare('UPDATE article_comments SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);

    asmbot_mod_ok(['id' => $id, 'status' => $status]);
}

asmbot_mod_fail(400, 'Неизвестное действие.');
