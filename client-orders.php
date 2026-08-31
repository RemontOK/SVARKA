<?php
/**
 * Заказы клиента.
 *
 *   GET  ?action=list          — свои заказы (только для вошедшего клиента)
 *   POST ?action=create        — оформить заказ {items:[{productId,name,qty,price}], comment, contact*}
 *
 * Клиент видит исключительно свои заказы: выборка всегда ограничена
 * client_id из его сессии, никаких идентификаторов из запроса.
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
require_once __DIR__ . '/mail.php';

/** Таблица позиций для письма. */
function asmbot_order_rows_html(array $items)
{
    $rows = '';
    foreach ($items as $item) {
        $sum = $item['price'] * $item['qty'];
        $rows .= '<tr>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #eee">' . htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;white-space:nowrap">' . $item['qty'] . ' шт.</td>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;white-space:nowrap">' . number_format($item['price'], 2, ',', ' ') . ' ₽</td>'
            . '<td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;white-space:nowrap"><b>' . number_format($sum, 2, ',', ' ') . ' ₽</b></td>'
            . '</tr>';
    }
    return $rows;
}

function asmbot_order_rows_text(array $items)
{
    $lines = [];
    foreach ($items as $item) {
        $lines[] = sprintf(
            '%s — %d шт. × %s ₽ = %s ₽',
            $item['name'],
            $item['qty'],
            number_format($item['price'], 2, ',', ' '),
            number_format($item['price'] * $item['qty'], 2, ',', ' ')
        );
    }
    return implode("\n", $lines);
}

function asmbot_orders_fail($code, $message)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = asmbot_db();
} catch (RuntimeException $e) {
    asmbot_orders_fail(500, 'База данных недоступна.');
}

$client = asmbot_current_client($pdo);
if (!$client) {
    asmbot_orders_fail(401, 'Требуется вход.');
}

$action = isset($_GET['action']) ? trim((string) $_GET['action']) : 'list';

// ------------------------------------------------------------- list
if ($action === 'list') {
    $stmt = $pdo->prepare(
        'SELECT id, number, status, total, discount_pct, comment, created_at
         FROM orders WHERE client_id = ? ORDER BY created_at DESC LIMIT 200'
    );
    $stmt->execute([$client['id']]);
    $orders = $stmt->fetchAll();

    if ($orders) {
        $ids = array_column($orders, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $itemStmt = $pdo->prepare(
            "SELECT order_id, product_id, name, qty, price FROM order_items WHERE order_id IN ($in)"
        );
        $itemStmt->execute($ids);

        $byOrder = [];
        foreach ($itemStmt->fetchAll() as $item) {
            $byOrder[$item['order_id']][] = [
                'productId' => $item['product_id'],
                'name' => $item['name'],
                'qty' => (int) $item['qty'],
                'price' => (float) $item['price'],
            ];
        }

        foreach ($orders as &$order) {
            $order['id'] = (int) $order['id'];
            $order['total'] = (float) $order['total'];
            $order['discountPct'] = (float) $order['discount_pct'];
            unset($order['discount_pct']);
            $order['items'] = isset($byOrder[$order['id']]) ? $byOrder[$order['id']] : [];
        }
        unset($order);
    }

    echo json_encode(['ok' => true, 'orders' => $orders], JSON_UNESCAPED_UNICODE);
    exit;
}

// ----------------------------------------------------------- create
if ($action !== 'create' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    asmbot_orders_fail(400, 'Неизвестное действие.');
}

$raw = file_get_contents('php://input');
$data = $raw ? json_decode($raw, true) : null;
if (!is_array($data)) {
    asmbot_orders_fail(400, 'Некорректный запрос.');
}

$items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
if (!$items) {
    asmbot_orders_fail(400, 'В заказе нет позиций.');
}
if (count($items) > 200) {
    asmbot_orders_fail(400, 'Слишком много позиций в одном заказе.');
}

$discount = (float) $client['discount_pct'];

/**
 * Цены берём из опубликованного каталога, а не из запроса.
 * Иначе браузер может прислать «цена = 1 рубль» и купить что угодно.
 */
$catalogPrices = [];
$catalogNames = [];
$catalogFile = __DIR__ . '/runtime-catalog.json';
if (is_file($catalogFile)) {
    $raw = @file_get_contents($catalogFile);
    $catalog = $raw ? json_decode($raw, true) : null;
    if (is_array($catalog) && isset($catalog['products']) && is_array($catalog['products'])) {
        foreach ($catalog['products'] as $product) {
            $id = isset($product['id']) ? (string) $product['id'] : '';
            if ($id === '') continue;
            $catalogPrices[$id] = isset($product['price']) ? (float) $product['price'] : 0.0;
            $catalogNames[$id] = isset($product['name']) ? (string) $product['name'] : '';
        }
    }
}
if (!$catalogPrices) {
    asmbot_orders_fail(503, 'Каталог временно недоступен, попробуйте позже.');
}

$prepared = [];
$total = 0.0;
$rejected = [];

foreach ($items as $item) {
    if (!is_array($item)) continue;

    $productId = mb_substr((string) ($item['productId'] ?? ''), 0, 190);
    if ($productId === '' || !isset($catalogPrices[$productId])) {
        $rejected[] = $productId !== '' ? $productId : '(без артикула)';
        continue;
    }

    $qty = isset($item['qty']) ? (int) $item['qty'] : 1;
    if ($qty < 1) $qty = 1;
    if ($qty > 10000) $qty = 10000;

    $basePrice = $catalogPrices[$productId];
    $price = $discount > 0 ? round($basePrice * (1 - $discount / 100), 2) : $basePrice;

    $prepared[] = [
        'productId' => $productId,
        'name' => mb_substr($catalogNames[$productId] !== '' ? $catalogNames[$productId] : (string) ($item['name'] ?? ''), 0, 255),
        'qty' => $qty,
        'price' => $price,
    ];
    $total += $price * $qty;
}

if ($rejected) {
    asmbot_orders_fail(400, 'Не найдены в каталоге: ' . implode(', ', array_slice($rejected, 0, 5)));
}

if (!$prepared) {
    asmbot_orders_fail(400, 'В заказе нет корректных позиций.');
}

$number = 'A' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));

try {
    $pdo->beginTransaction();

    // Реквизиты плательщика: из формы заказа, иначе из профиля клиента.
    $buyerName = mb_substr(trim((string) ($data['buyerName'] ?? $client['company'])), 0, 255);
    $buyerInn = mb_substr(trim((string) ($data['buyerInn'] ?? $client['inn'])), 0, 20);
    $buyerKpp = mb_substr(trim((string) ($data['buyerKpp'] ?? ($client['kpp'] ?? ''))), 0, 20);
    $buyerAddress = mb_substr(trim((string) ($data['buyerAddress'] ?? ($client['legal_address'] ?? ''))), 0, 400);

    $stmt = $pdo->prepare(
        'INSERT INTO orders (number, client_id, status, contact_name, contact_phone, contact_email, comment, discount_pct, total,
                             buyer_name, buyer_inn, buyer_kpp, buyer_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $number,
        $client['id'],
        'new',
        mb_substr(trim((string) ($data['contactName'] ?? $client['name'])), 0, 190),
        mb_substr(trim((string) ($data['contactPhone'] ?? $client['phone'])), 0, 40),
        mb_substr(trim((string) ($data['contactEmail'] ?? $client['email'])), 0, 190),
        mb_substr(trim((string) ($data['comment'] ?? '')), 0, 2000),
        $discount,
        round($total, 2),
        $buyerName,
        $buyerInn,
        $buyerKpp,
        $buyerAddress,
    ]);

    // Идентификатор читаем сразу: любой следующий запрос обнуляет lastInsertId,
    // и позиции заказа тогда уходят с order_id = 0.
    $orderId = (int) $pdo->lastInsertId();

    // Заодно запоминаем реквизиты в профиле, чтобы не спрашивать их снова.
    if ($buyerInn !== '' || $buyerKpp !== '' || $buyerAddress !== '') {
        $save = $pdo->prepare(
            "UPDATE clients SET
                company = CASE WHEN company = '' THEN ? ELSE company END,
                inn = CASE WHEN inn = '' THEN ? ELSE inn END,
                kpp = CASE WHEN kpp = '' THEN ? ELSE kpp END,
                legal_address = CASE WHEN legal_address = '' THEN ? ELSE legal_address END
             WHERE id = ?"
        );
        $save->execute([$buyerName, $buyerInn, $buyerKpp, $buyerAddress, $client['id']]);
    }

    $itemStmt = $pdo->prepare(
        'INSERT INTO order_items (order_id, product_id, name, qty, price) VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($prepared as $item) {
        $itemStmt->execute([$orderId, $item['productId'], $item['name'], $item['qty'], $item['price']]);
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Настоящую причину показываем только с админским паролем: клиенту
    // незачем видеть внутренности, а без неё такие сбои не отладить.
    $isAdmin = isset($_SERVER['HTTP_X_ADMIN_PASSWORD'])
        && hash_equals(asmbot_admin_password(), (string) $_SERVER['HTTP_X_ADMIN_PASSWORD']);
    asmbot_orders_fail(
        500,
        $isAdmin
            ? 'Ошибка БД: ' . $e->getMessage()
            : 'Не удалось сохранить заказ. Попробуйте ещё раз.'
    );
}

/**
 * Уведомления. Письма отправляем после сохранения заказа: если почта
 * недоступна, заказ всё равно принят — терять его из-за письма нельзя.
 */
$totalText = number_format(round($total, 2), 2, ',', ' ') . ' ₽';
$contactName = mb_substr(trim((string) ($data['contactName'] ?? $client['name'])), 0, 190);
$contactPhone = mb_substr(trim((string) ($data['contactPhone'] ?? $client['phone'])), 0, 40);
$comment = mb_substr(trim((string) ($data['comment'] ?? '')), 0, 2000);

$itemsHtml = asmbot_order_rows_html($prepared);
$itemsText = asmbot_order_rows_text($prepared);
$discountLine = $discount > 0 ? '<p>Скидка клиента: <b>' . $discount . '%</b> (уже учтена в ценах)</p>' : '';

// 1) Клиенту — подтверждение.
try {
    asmbot_send_mail(
        $client['email'],
        'Заказ № ' . $number . ' принят — АльфаСмарт',
        '<p>Спасибо за заказ! Мы свяжемся с вами для подтверждения.</p>'
        . '<p><b>Заказ № ' . $number . '</b></p>'
        . $discountLine
        . '<table style="border-collapse:collapse;width:100%;max-width:640px">' . $itemsHtml . '</table>'
        . '<p style="font-size:18px">Итого: <b>' . $totalText . '</b></p>'
        . '<p style="color:#666;font-size:13px">Статус заказа виден в личном кабинете на сайте.</p>',
        "Спасибо за заказ! Мы свяжемся с вами для подтверждения.\n\n"
        . "Заказ № $number\n\n$itemsText\n\nИтого: $totalText",
        $client['name']
    );
} catch (RuntimeException $e) {
    // Клиент увидит заказ в кабинете даже без письма.
}

// 2) Менеджеру — новый заказ.
try {
    $mailCfg = asmbot_mail_config();
    if ($mailCfg !== null) {
        asmbot_send_mail(
            $mailCfg['from'],
            'Новый заказ № ' . $number . ' на ' . $totalText,
            '<p><b>Новый заказ № ' . $number . '</b></p>'
            . '<p>Клиент: ' . htmlspecialchars($client['name'] ?: $client['email'], ENT_QUOTES, 'UTF-8')
            . ($client['company'] ? ', ' . htmlspecialchars($client['company'], ENT_QUOTES, 'UTF-8') : '') . '<br>'
            . 'Почта: ' . htmlspecialchars($client['email'], ENT_QUOTES, 'UTF-8') . '<br>'
            . 'Телефон: ' . htmlspecialchars($contactPhone ?: '—', ENT_QUOTES, 'UTF-8') . '</p>'
            . $discountLine
            . ($comment !== '' ? '<p>Комментарий: ' . htmlspecialchars($comment, ENT_QUOTES, 'UTF-8') . '</p>' : '')
            . '<table style="border-collapse:collapse;width:100%;max-width:640px">' . $itemsHtml . '</table>'
            . '<p style="font-size:18px">Итого: <b>' . $totalText . '</b></p>',
            "Новый заказ № $number\n\nКлиент: {$client['email']}\nТелефон: $contactPhone\n\n$itemsText\n\nИтого: $totalText"
        );
    }
} catch (RuntimeException $e) {
    // Заказ уже в базе и виден в админке.
}

echo json_encode([
    'ok' => true,
    'order' => [
        'id' => $orderId,
        'number' => $number,
        'status' => 'new',
        'total' => round($total, 2),
        'discountPct' => $discount,
        'items' => $prepared,
    ],
], JSON_UNESCAPED_UNICODE);
