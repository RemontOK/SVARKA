<?php
/**
 * Общие функции клиентских сессий.
 *
 * Вынесены отдельно, чтобы другие скрипты (заказы, чат) могли узнать
 * текущего клиента, не запуская обработку запросов входа/регистрации.
 */

if (!defined('ASMBOT_COOKIE')) {
    define('ASMBOT_COOKIE', 'asmbot_session');
}
if (!defined('ASMBOT_SESSION_DAYS')) {
    define('ASMBOT_SESSION_DAYS', 30);
}

function asmbot_is_https()
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https';
}

function asmbot_set_session_cookie($token, $expiresAt)
{
    setcookie(ASMBOT_COOKIE, $token, [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => asmbot_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function asmbot_clear_session_cookie()
{
    setcookie(ASMBOT_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => asmbot_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** Публичный профиль клиента — без хеша пароля. */
function asmbot_public_client(array $row)
{
    return [
        'id' => (int) $row['id'],
        'email' => $row['email'],
        'name' => $row['name'],
        'phone' => $row['phone'],
        'company' => $row['company'],
        'inn' => $row['inn'],
        'discountPct' => (float) $row['discount_pct'],
        'emailVerified' => !empty($row['email_verified_at']),
        'createdAt' => $row['created_at'],
    ];
}

/** Клиент текущей сессии или null. */
function asmbot_current_client(PDO $pdo)
{
    $token = isset($_COOKIE[ASMBOT_COOKIE]) ? (string) $_COOKIE[ASMBOT_COOKIE] : '';
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT c.* FROM client_sessions s
         JOIN clients c ON c.id = s.client_id
         WHERE s.token = ? AND s.expires_at > NOW()'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function asmbot_start_session(PDO $pdo, $clientId)
{
    $token = bin2hex(random_bytes(32));
    $expires = time() + ASMBOT_SESSION_DAYS * 86400;

    $stmt = $pdo->prepare('INSERT INTO client_sessions (token, client_id, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$token, $clientId, date('Y-m-d H:i:s', $expires)]);

    asmbot_set_session_cookie($token, $expires);
    return $token;
}
