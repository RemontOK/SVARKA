<?php
/**
 * Приём заявок с сайта.
 *
 * Раньше формы уходили на сторонний formsubmit.co: он требует подтверждать
 * адрес письмом, и пока подтверждение не нажато, заявки не доходят вовсе —
 * отвечает сообщением про активацию. Своя почта на сервере уже настроена и
 * используется для кодов входа и счетов, так что заявкам нечего делать на
 * чужом сервисе.
 *
 * Адрес получателя задаётся здесь и клиентом не управляется: иначе форма
 * превратилась бы в открытый ретранслятор писем.
 */

require_once __DIR__ . '/mail.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

const ASMBOT_LEAD_TO = 'ekat@asmbot.ru';
const ASMBOT_LEAD_LIMIT = 12;      // заявок с одного адреса
const ASMBOT_LEAD_WINDOW = 3600;   // за час

function asmbot_lead_fail($code, $message)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    asmbot_lead_fail(405, 'Только POST.');
}

$raw = file_get_contents('php://input');
if (strlen($raw) > 20000) {
    asmbot_lead_fail(413, 'Слишком длинная заявка.');
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    asmbot_lead_fail(400, 'Не разобрали заявку.');
}

// Ловушка для ботов: поле скрыто в разметке, человек его не заполняет.
if (!empty($data['website'])) {
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

$clean = static function ($value, $limit = 500) {
    $text = is_array($value) ? implode(', ', $value) : (string) $value;
    $text = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text));
    return mb_substr($text, 0, $limit, 'UTF-8');
};

$name = $clean($data['name'] ?? '', 200);
$phone = $clean($data['phone'] ?? '', 60);
$comment = $clean($data['comment'] ?? '', 4000);
$topic = $clean($data['topic'] ?? '', 200);
$source = $clean($data['source'] ?? 'site', 60);
$pageUrl = $clean($data['pageUrl'] ?? '', 500);
$contact = $clean($data['contact'] ?? '', 200);

if ($name === '' && $phone === '' && $contact === '') {
    asmbot_lead_fail(422, 'Не хватает имени и телефона.');
}

// --- Ограничение частоты -----------------------------------------------------

$ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
$stateDir = __DIR__ . '/lead-rate';
if (!is_dir($stateDir)) {
    @mkdir($stateDir, 0755, true);
}
$stateFile = $stateDir . '/' . sha1($ip) . '.json';
$now = time();
$hits = [];
if (is_file($stateFile)) {
    $saved = json_decode((string) @file_get_contents($stateFile), true);
    if (is_array($saved)) {
        $hits = array_values(array_filter($saved, static function ($time) use ($now) {
            return is_int($time) && $time > $now - ASMBOT_LEAD_WINDOW;
        }));
    }
}
if (count($hits) >= ASMBOT_LEAD_LIMIT) {
    asmbot_lead_fail(429, 'Слишком много заявок подряд. Позвоните нам: +7 (982) 698-42-80');
}
$hits[] = $now;
@file_put_contents($stateFile, json_encode($hits), LOCK_EX);

// --- Письмо ------------------------------------------------------------------

$productName = $clean($data['product']['name'] ?? '', 300);
$productBrand = $clean($data['product']['brand'] ?? '', 120);
$productPrice = $clean($data['product']['price'] ?? '', 60);

$rows = [
    ['Имя, компания', $name],
    ['Телефон', $phone],
    ['Контакт', $contact],
    ['Задача', $comment],
    ['Товар', $productName],
    ['Бренд', $productBrand],
    ['Цена', $productPrice],
    ['Страница', $pageUrl],
    ['Форма', $source],
];

$html = '<table cellpadding="6" cellspacing="0" border="0" style="border-collapse:collapse;font:14px/1.5 Arial,sans-serif">';
$text = '';
foreach ($rows as [$title, $value]) {
    if ($value === '') {
        continue;
    }
    $safe = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html .= '<tr>'
        . '<td style="color:#666;border-bottom:1px solid #eee;white-space:nowrap">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</td>'
        . '<td style="border-bottom:1px solid #eee"><b>' . nl2br($safe) . '</b></td>'
        . '</tr>';
    $text .= $title . ': ' . $value . "\n";
}
$html .= '</table>';

$subject = $topic !== '' ? $topic : 'Заявка с сайта asmbot.ru';
if ($productName !== '') {
    $subject .= ' — ' . $productName;
}

// Сначала на диск, потом почтой. Почта может отвалиться по любой причине —
// сменили пароль ящика, лёг сервер провайдера, — и заявка тогда пропадает
// бесследно. Файл лежит рядом и закрыт от веба в .htaccess.
$record = [
    'time' => date('c'),
    'ip' => $ip,
    'name' => $name,
    'phone' => $phone,
    'contact' => $contact,
    'comment' => $comment,
    'topic' => $topic,
    'source' => $source,
    'page' => $pageUrl,
    'product' => $productName,
    'price' => $productPrice,
];
@file_put_contents(
    __DIR__ . '/runtime-leads.jsonl',
    json_encode($record, JSON_UNESCAPED_UNICODE) . "\n",
    FILE_APPEND | LOCK_EX
);

$mailed = true;
$mailError = '';
try {
    asmbot_send_mail(ASMBOT_LEAD_TO, $subject, $html, $text);
} catch (Throwable $error) {
    $mailed = false;
    $mailError = $error->getMessage();
}

// Для посетителя заявка принята в любом случае: она сохранена. Признак
// `mailed` нужен нам, чтобы видеть, что почта не работает.
echo json_encode(
    ['ok' => true, 'mailed' => $mailed, 'mailError' => $mailError],
    JSON_UNESCAPED_UNICODE
);
