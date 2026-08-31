<?php
/**
 * Регистрация и вход клиентов.
 *
 *   POST ?action=register  {email, password, name, phone, company, inn}
 *   POST ?action=login     {email, password}
 *   POST ?action=logout
 *   GET  ?action=me
 *
 * Токен сессии живёт в HttpOnly-cookie: JavaScript его не читает, поэтому
 * украсть сессию через XSS нельзя. Пароли хранятся только хешами.
 * Общие функции сессий — в client-auth-lib.php.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/client-auth-lib.php';
require_once __DIR__ . '/mail.php';

const ASMBOT_MIN_PASSWORD = 8;

function asmbot_site_origin()
{
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'asmbot.ru';
    $scheme = asmbot_is_https() ? 'https' : 'http';
    return $scheme . '://' . $host;
}

/**
 * Отправляет письмо со ссылкой подтверждения. Ошибка отправки не должна
 * ломать регистрацию — аккаунт уже создан, письмо можно запросить повторно.
 */
function asmbot_send_verification(PDO $pdo, $clientId, $email, $name = '')
{
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare('UPDATE clients SET verify_token = ?, verify_sent_at = NOW() WHERE id = ?');
    $stmt->execute([$token, $clientId]);

    $link = asmbot_site_origin() . '/client-auth.php?action=verify&token=' . $token;
    $greeting = $name !== '' ? htmlspecialchars($name, ENT_QUOTES, 'UTF-8') : 'Здравствуйте';

    $html = '<p>' . $greeting . ', спасибо за регистрацию на сайте АльфаСмарт.</p>'
        . '<p>Чтобы подтвердить адрес почты, откройте ссылку:</p>'
        . '<p><a href="' . $link . '">' . $link . '</a></p>'
        . '<p style="color:#666;font-size:13px">Если вы не регистрировались, просто удалите это письмо.</p>';

    $text = "Спасибо за регистрацию на сайте АльфаСмарт.\n\n"
        . "Подтвердите адрес почты, открыв ссылку:\n" . $link . "\n\n"
        . "Если вы не регистрировались, просто удалите это письмо.";

    try {
        asmbot_send_mail($email, 'Подтверждение регистрации — АльфаСмарт', $html, $text, $name);
        return true;
    } catch (RuntimeException $e) {
        return false;
    }
}

function asmbot_json_input()
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function asmbot_fail($code, $message)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function asmbot_str($data, $key, $limit = 190)
{
    $value = isset($data[$key]) ? trim((string) $data[$key]) : '';
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $limit);
    }
    return substr($value, 0, $limit);
}

try {
    $pdo = asmbot_db();
} catch (RuntimeException $e) {
    asmbot_fail(500, 'База данных недоступна.');
}

$action = isset($_GET['action']) ? trim((string) $_GET['action']) : '';
$method = $_SERVER['REQUEST_METHOD'];

// ------------------------------------------------------------ verify
// Переход по ссылке из письма: подтверждаем адрес и уводим человека в кабинет.
if ($action === 'verify') {
    $token = isset($_GET['token']) ? (string) $_GET['token'] : '';
    $ok = false;

    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        $stmt = $pdo->prepare(
            'UPDATE clients SET email_verified_at = NOW(), verify_token = NULL
             WHERE verify_token = ? AND email_verified_at IS NULL'
        );
        $stmt->execute([$token]);
        $ok = $stmt->rowCount() > 0;

        // Повторный переход по уже использованной ссылке — не ошибка.
        if (!$ok) {
            $check = $pdo->prepare('SELECT id FROM clients WHERE verify_token IS NULL AND email_verified_at IS NOT NULL LIMIT 1');
            $check->execute();
        }
    }

    header('Location: /cabinet?verified=' . ($ok ? '1' : '0'));
    exit;
}

// ---------------------------------------------- resend verification
if ($action === 'resend-verification') {
    if ($method !== 'POST') {
        asmbot_fail(405, 'Метод не поддерживается.');
    }
    $client = asmbot_current_client($pdo);
    if (!$client) {
        asmbot_fail(401, 'Требуется вход.');
    }
    if (!empty($client['email_verified_at'])) {
        echo json_encode(['ok' => true, 'alreadyVerified' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Не чаще одного письма в 2 минуты.
    if (!empty($client['verify_sent_at']) && strtotime($client['verify_sent_at']) > time() - 120) {
        asmbot_fail(429, 'Письмо уже отправлено. Проверьте почту и папку «Спам».');
    }

    $sent = asmbot_send_verification($pdo, (int) $client['id'], $client['email'], $client['name']);
    echo json_encode(['ok' => true, 'sent' => $sent], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------- me
if ($action === 'me') {
    $client = asmbot_current_client($pdo);
    echo json_encode(
        ['ok' => true, 'client' => $client ? asmbot_public_client($client) : null],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

// ------------------------------------------------------------ logout
if ($action === 'logout') {
    if ($method !== 'POST') {
        asmbot_fail(405, 'Метод не поддерживается.');
    }
    $token = isset($_COOKIE[ASMBOT_COOKIE]) ? (string) $_COOKIE[ASMBOT_COOKIE] : '';
    if ($token !== '') {
        $stmt = $pdo->prepare('DELETE FROM client_sessions WHERE token = ?');
        $stmt->execute([$token]);
    }
    asmbot_clear_session_cookie();
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST') {
    asmbot_fail(405, 'Метод не поддерживается.');
}

$data = asmbot_json_input();
$email = strtolower(asmbot_str($data, 'email'));
$password = isset($data['password']) ? (string) $data['password'] : '';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    asmbot_fail(400, 'Укажите корректный адрес почты.');
}

// ---------------------------------------------------------- register
if ($action === 'register') {
    if (strlen($password) < ASMBOT_MIN_PASSWORD) {
        asmbot_fail(400, 'Пароль должен быть не короче ' . ASMBOT_MIN_PASSWORD . ' символов.');
    }

    $exists = $pdo->prepare('SELECT id FROM clients WHERE email = ?');
    $exists->execute([$email]);
    if ($exists->fetch()) {
        asmbot_fail(409, 'Этот адрес уже зарегистрирован. Попробуйте войти.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO clients (email, password_hash, name, phone, company, inn)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        asmbot_str($data, 'name'),
        asmbot_str($data, 'phone', 40),
        asmbot_str($data, 'company'),
        asmbot_str($data, 'inn', 20),
    ]);

    $clientId = (int) $pdo->lastInsertId();
    asmbot_start_session($pdo, $clientId);

    // Письмо с подтверждением. Если почта временно недоступна — регистрация
    // всё равно состоялась, письмо можно запросить повторно из кабинета.
    $mailSent = asmbot_send_verification($pdo, $clientId, $email, asmbot_str($data, 'name'));

    $fetch = $pdo->prepare('SELECT * FROM clients WHERE id = ?');
    $fetch->execute([$clientId]);

    echo json_encode(
        ['ok' => true, 'client' => asmbot_public_client($fetch->fetch()), 'verificationSent' => $mailSent],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

// ------------------------------------------------------------- login
if ($action === 'login') {
    $stmt = $pdo->prepare('SELECT * FROM clients WHERE email = ?');
    $stmt->execute([$email]);
    $client = $stmt->fetch();

    // Одинаковый ответ и текст ошибки, есть такая почта или нет —
    // иначе форма входа превращается в проверку существования аккаунта.
    $hash = $client && $client['password_hash'] ? $client['password_hash'] : null;
    $valid = $hash !== null && password_verify($password, $hash);

    if (!$valid) {
        usleep(300000); // тормозим перебор
        asmbot_fail(401, 'Неверная почта или пароль.');
    }

    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $upd = $pdo->prepare('UPDATE clients SET password_hash = ? WHERE id = ?');
        $upd->execute([password_hash($password, PASSWORD_DEFAULT), $client['id']]);
    }

    // Чистим просроченные сессии этого клиента, чтобы таблица не пухла.
    $clean = $pdo->prepare('DELETE FROM client_sessions WHERE client_id = ? AND expires_at <= NOW()');
    $clean->execute([$client['id']]);

    asmbot_start_session($pdo, (int) $client['id']);

    echo json_encode(
        ['ok' => true, 'client' => asmbot_public_client($client)],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

asmbot_fail(400, 'Неизвестное действие.');
