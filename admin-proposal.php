<?php
/**
 * Коммерческое предложение: письмо клиенту по заказу.
 *
 *   POST ?action=send {orderId, note}          — КП по существующему заказу
 *   POST ?action=send-custom {email, name, items:[{productId,qty}], note}
 *
 * Цены всегда берутся из каталога и профиля клиента, а не из запроса.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/admin-auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail.php';

asmbot_require_admin();

function asmbot_proposal_fail($code, $message)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function asmbot_catalog_index()
{
    $file = __DIR__ . '/runtime-catalog.json';
    if (!is_file($file)) {
        return [];
    }
    $raw = @file_get_contents($file);
    $catalog = $raw ? json_decode($raw, true) : null;
    if (!is_array($catalog) || empty($catalog['products'])) {
        return [];
    }
    $index = [];
    foreach ($catalog['products'] as $product) {
        $id = isset($product['id']) ? (string) $product['id'] : '';
        if ($id === '') continue;
        $index[$id] = [
            'name' => isset($product['name']) ? (string) $product['name'] : '',
            'price' => isset($product['price']) ? (float) $product['price'] : 0.0,
        ];
    }
    return $index;
}

function asmbot_money($value)
{
    return number_format((float) $value, 2, ',', ' ') . ' ₽';
}

/** Вёрстка письма с КП. */
function asmbot_proposal_html(array $items, $total, $discount, $note, $number = '')
{
    $rows = '';
    $index = 1;
    foreach ($items as $item) {
        $rows .= '<tr>'
            . '<td style="padding:8px 10px;border-bottom:1px solid #eee;color:#888">' . $index++ . '</td>'
            . '<td style="padding:8px 10px;border-bottom:1px solid #eee">' . htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:8px 10px;border-bottom:1px solid #eee;text-align:right;white-space:nowrap">' . $item['qty'] . '</td>'
            . '<td style="padding:8px 10px;border-bottom:1px solid #eee;text-align:right;white-space:nowrap">' . asmbot_money($item['price']) . '</td>'
            . '<td style="padding:8px 10px;border-bottom:1px solid #eee;text-align:right;white-space:nowrap"><b>' . asmbot_money($item['price'] * $item['qty']) . '</b></td>'
            . '</tr>';
    }

    $title = $number !== '' ? 'Коммерческое предложение по заказу № ' . htmlspecialchars($number, ENT_QUOTES, 'UTF-8') : 'Коммерческое предложение';
    $discountLine = $discount > 0
        ? '<p style="color:#2e9e5b;margin:4px 0"><b>Ваша скидка ' . $discount . '% уже учтена в ценах.</b></p>'
        : '';
    $noteBlock = $note !== ''
        ? '<div style="margin:16px 0;padding:12px 16px;background:#fff8e6;border-left:3px solid #f8c400">' . nl2br(htmlspecialchars($note, ENT_QUOTES, 'UTF-8')) . '</div>'
        : '';

    return '<div style="font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;max-width:680px">'
        . '<h2 style="margin:0 0 4px">' . $title . '</h2>'
        . '<p style="color:#666;margin:0 0 16px">АльфаСмарт — сварочное оборудование и оснащение участков</p>'
        . $discountLine
        . $noteBlock
        . '<table style="border-collapse:collapse;width:100%;font-size:14px">'
        . '<thead><tr style="background:#f5f5f5">'
        . '<th style="padding:8px 10px;text-align:left">#</th>'
        . '<th style="padding:8px 10px;text-align:left">Наименование</th>'
        . '<th style="padding:8px 10px;text-align:right">Кол-во</th>'
        . '<th style="padding:8px 10px;text-align:right">Цена</th>'
        . '<th style="padding:8px 10px;text-align:right">Сумма</th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<p style="font-size:20px;margin:16px 0">Итого: <b>' . asmbot_money($total) . '</b></p>'
        . '<p style="color:#666;font-size:13px;margin-top:24px">'
        . 'Предложение носит информационный характер и не является публичной офертой.<br>'
        . 'Готовы обсудить сроки, доставку и условия оплаты — просто ответьте на это письмо.'
        . '</p>'
        . '<p style="color:#666;font-size:13px">+7 (982) 698-42-80 · asmbot.ru</p>'
        . '</div>';
}

function asmbot_proposal_text(array $items, $total, $number = '')
{
    $lines = [];
    $lines[] = $number !== '' ? "Коммерческое предложение по заказу № $number" : 'Коммерческое предложение';
    $lines[] = 'АльфаСмарт — сварочное оборудование';
    $lines[] = '';
    foreach ($items as $item) {
        $lines[] = sprintf(
            '%s — %d шт. × %s = %s',
            $item['name'],
            $item['qty'],
            asmbot_money($item['price']),
            asmbot_money($item['price'] * $item['qty'])
        );
    }
    $lines[] = '';
    $lines[] = 'Итого: ' . asmbot_money($total);
    $lines[] = '';
    $lines[] = '+7 (982) 698-42-80 · asmbot.ru';
    return implode("\n", $lines);
}

try {
    $pdo = asmbot_db();
} catch (RuntimeException $e) {
    asmbot_proposal_fail(500, 'База данных недоступна.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    asmbot_proposal_fail(405, 'Метод не поддерживается.');
}

$raw = file_get_contents('php://input');
$data = $raw ? json_decode($raw, true) : null;
if (!is_array($data)) {
    asmbot_proposal_fail(400, 'Некорректный запрос.');
}

$action = isset($_GET['action']) ? trim((string) $_GET['action']) : 'send';
$note = mb_substr(trim((string) ($data['note'] ?? '')), 0, 2000);

// -------------------------------------------------- КП по заказу
if ($action === 'send') {
    $orderId = isset($data['orderId']) ? (int) $data['orderId'] : 0;
    if ($orderId <= 0) {
        asmbot_proposal_fail(400, 'Не указан заказ.');
    }

    $stmt = $pdo->prepare(
        'SELECT o.*, c.email AS client_email, c.name AS client_name
         FROM orders o LEFT JOIN clients c ON c.id = o.client_id
         WHERE o.id = ?'
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        asmbot_proposal_fail(404, 'Заказ не найден.');
    }

    $to = $order['client_email'] ?: $order['contact_email'];
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        asmbot_proposal_fail(400, 'У заказа нет корректного адреса почты.');
    }

    $itemStmt = $pdo->prepare('SELECT name, qty, price FROM order_items WHERE order_id = ?');
    $itemStmt->execute([$orderId]);
    $items = array_map(static function ($row) {
        return ['name' => $row['name'], 'qty' => (int) $row['qty'], 'price' => (float) $row['price']];
    }, $itemStmt->fetchAll());

    if (!$items) {
        asmbot_proposal_fail(400, 'В заказе нет позиций.');
    }

    $total = 0.0;
    foreach ($items as $item) {
        $total += $item['price'] * $item['qty'];
    }

    try {
        asmbot_send_mail(
            $to,
            'Коммерческое предложение № ' . $order['number'] . ' — АльфаСмарт',
            asmbot_proposal_html($items, $total, (float) $order['discount_pct'], $note, $order['number']),
            asmbot_proposal_text($items, $total, $order['number']),
            $order['client_name'] ?: $order['contact_name']
        );
    } catch (RuntimeException $e) {
        asmbot_proposal_fail(502, 'Не удалось отправить письмо: ' . $e->getMessage());
    }

    echo json_encode(['ok' => true, 'sentTo' => $to, 'total' => round($total, 2)], JSON_UNESCAPED_UNICODE);
    exit;
}

// ------------------------------------- КП без заказа, вручную
if ($action === 'send-custom') {
    $to = strtolower(trim((string) ($data['email'] ?? '')));
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        asmbot_proposal_fail(400, 'Укажите корректный адрес почты.');
    }

    $rawItems = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
    if (!$rawItems) {
        asmbot_proposal_fail(400, 'Добавьте хотя бы одну позицию.');
    }

    // Скидка — из профиля клиента, если он у нас зарегистрирован.
    $discount = 0.0;
    $clientName = mb_substr(trim((string) ($data['name'] ?? '')), 0, 190);
    $lookup = $pdo->prepare('SELECT name, discount_pct FROM clients WHERE email = ?');
    $lookup->execute([$to]);
    if ($found = $lookup->fetch()) {
        $discount = (float) $found['discount_pct'];
        if ($clientName === '') {
            $clientName = $found['name'];
        }
    }

    $index = asmbot_catalog_index();
    if (!$index) {
        asmbot_proposal_fail(503, 'Каталог временно недоступен.');
    }

    $items = [];
    $total = 0.0;
    foreach ($rawItems as $item) {
        if (!is_array($item)) continue;
        $productId = (string) ($item['productId'] ?? '');
        if ($productId === '' || !isset($index[$productId])) continue;

        $qty = isset($item['qty']) ? max(1, min(10000, (int) $item['qty'])) : 1;
        $base = $index[$productId]['price'];
        $price = $discount > 0 ? round($base * (1 - $discount / 100), 2) : $base;

        $items[] = ['name' => $index[$productId]['name'], 'qty' => $qty, 'price' => $price];
        $total += $price * $qty;
    }

    if (!$items) {
        asmbot_proposal_fail(400, 'Ни одна позиция не найдена в каталоге.');
    }

    try {
        asmbot_send_mail(
            $to,
            'Коммерческое предложение — АльфаСмарт',
            asmbot_proposal_html($items, $total, $discount, $note),
            asmbot_proposal_text($items, $total),
            $clientName
        );
    } catch (RuntimeException $e) {
        asmbot_proposal_fail(502, 'Не удалось отправить письмо: ' . $e->getMessage());
    }

    echo json_encode(['ok' => true, 'sentTo' => $to, 'total' => round($total, 2)], JSON_UNESCAPED_UNICODE);
    exit;
}

asmbot_proposal_fail(400, 'Неизвестное действие.');
