<?php
/**
 * Проверка настроек SMTP. Доступно только с админским паролем.
 *
 *   GET  ?action=config          — что прочитано из mail-config.php (без пароля)
 *   POST ?action=send&to=адрес   — отправить тестовое письмо
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/admin-auth.php';
require_once __DIR__ . '/mail.php';

asmbot_require_admin();

$action = isset($_GET['action']) ? trim((string) $_GET['action']) : 'config';
$cfg = asmbot_mail_config();

if ($cfg === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'mail-config.php не найден или заполнен неверно.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'config') {
    echo json_encode([
        'ok' => true,
        'host' => $cfg['host'],
        'port' => $cfg['port'],
        'secure' => $cfg['secure'],
        'user' => $cfg['user'],
        'from' => $cfg['from'],
        'fromName' => $cfg['fromName'],
        'passwordSet' => $cfg['password'] !== '',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action !== 'send' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Используйте ?action=config или POST ?action=send&to=адрес'], JSON_UNESCAPED_UNICODE);
    exit;
}

$to = isset($_GET['to']) ? trim((string) $_GET['to']) : $cfg['from'];

try {
    asmbot_send_mail(
        $to,
        'Проверка отправки писем — АльфаСмарт',
        '<p>Это тестовое письмо с сайта <b>asmbot.ru</b>.</p>'
        . '<p>Если оно дошло — отправка коммерческих предложений и уведомлений о заказах настроена верно.</p>',
        "Это тестовое письмо с сайта asmbot.ru.\nЕсли оно дошло — отправка настроена верно."
    );
    echo json_encode(['ok' => true, 'sentTo' => $to], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
