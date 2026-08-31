<?php
/**
 * Счёт на оплату по заказу — печатная HTML-форма (Ctrl+P → PDF).
 *
 *   /invoice.php?order=123            — клиент, только свой заказ (по сессии)
 *   /invoice.php?order=123 + пароль   — менеджер, любой заказ
 *
 * Клиент никогда не видит чужие заказы: если сессия не совпадает с
 * владельцем заказа и админский пароль не передан — отказ.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/client-auth-lib.php';
require_once __DIR__ . '/admin-auth.php';
require_once __DIR__ . '/settings-lib.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function asmbot_invoice_stop($code, $message)
{
    http_response_code($code);
    echo '<!doctype html><meta charset="utf-8"><title>Счёт</title>'
        . '<div style="font-family:Arial,sans-serif;padding:40px;color:#333">'
        . '<h1 style="font-size:20px">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h1>'
        . '</div>';
    exit;
}

function asmbot_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function asmbot_rub($value)
{
    return number_format((float) $value, 2, ',', ' ');
}

/** Сумма прописью — для счёта это обязательный реквизит. */
function asmbot_amount_in_words($amount)
{
    $rubles = (int) floor($amount);
    $kopecks = (int) round(($amount - $rubles) * 100);

    $ones = ['', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'];
    $onesF = ['', 'одна', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'];
    $teens = ['десять', 'одиннадцать', 'двенадцать', 'тринадцать', 'четырнадцать', 'пятнадцать', 'шестнадцать', 'семнадцать', 'восемнадцать', 'девятнадцать'];
    $tens = ['', '', 'двадцать', 'тридцать', 'сорок', 'пятьдесят', 'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто'];
    $hundreds = ['', 'сто', 'двести', 'триста', 'четыреста', 'пятьсот', 'шестьсот', 'семьсот', 'восемьсот', 'девятьсот'];

    $chunk = static function ($num, $feminine) use ($ones, $onesF, $teens, $tens, $hundreds) {
        $words = [];
        $h = (int) ($num / 100);
        $t = (int) (($num % 100) / 10);
        $o = $num % 10;
        if ($h) $words[] = $hundreds[$h];
        if ($t === 1) {
            $words[] = $teens[$o];
        } else {
            if ($t) $words[] = $tens[$t];
            if ($o) $words[] = $feminine ? $onesF[$o] : $ones[$o];
        }
        return implode(' ', $words);
    };

    $plural = static function ($num, $one, $few, $many) {
        $n = $num % 100;
        if ($n >= 11 && $n <= 14) return $many;
        switch ($num % 10) {
            case 1: return $one;
            case 2:
            case 3:
            case 4: return $few;
            default: return $many;
        }
    };

    $parts = [];
    $millions = (int) ($rubles / 1000000);
    $thousands = (int) (($rubles % 1000000) / 1000);
    $rest = $rubles % 1000;

    if ($millions) $parts[] = $chunk($millions, false) . ' ' . $plural($millions, 'миллион', 'миллиона', 'миллионов');
    if ($thousands) $parts[] = $chunk($thousands, true) . ' ' . $plural($thousands, 'тысяча', 'тысячи', 'тысяч');
    if ($rest || !$parts) $parts[] = $chunk($rest, false);

    $text = trim(preg_replace('/\s+/', ' ', implode(' ', $parts)));
    if ($text === '') $text = 'ноль';

    return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1)
        . ' ' . $plural($rubles, 'рубль', 'рубля', 'рублей')
        . ' ' . sprintf('%02d', $kopecks) . ' '
        . $plural($kopecks, 'копейка', 'копейки', 'копеек');
}

try {
    $pdo = asmbot_db();
} catch (RuntimeException $e) {
    asmbot_invoice_stop(500, 'База данных недоступна.');
}

$orderId = isset($_GET['order']) ? (int) $_GET['order'] : 0;
if ($orderId <= 0) {
    asmbot_invoice_stop(400, 'Не указан заказ.');
}

$stmt = $pdo->prepare(
    'SELECT o.*, c.email AS client_email, c.name AS client_name,
            c.company AS client_company, c.inn AS client_inn
     FROM orders o LEFT JOIN clients c ON c.id = o.client_id
     WHERE o.id = ?'
);
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order) {
    asmbot_invoice_stop(404, 'Заказ не найден.');
}

// Доступ: менеджер по паролю или клиент — владелец заказа.
$isAdmin = isset($_SERVER['HTTP_X_ADMIN_PASSWORD'])
    && hash_equals(asmbot_admin_password(), (string) $_SERVER['HTTP_X_ADMIN_PASSWORD']);
if (!$isAdmin && isset($_GET['key'])) {
    $isAdmin = hash_equals(asmbot_admin_password(), (string) $_GET['key']);
}

if (!$isAdmin) {
    $client = asmbot_current_client($pdo);
    if (!$client || (int) $client['id'] !== (int) $order['client_id']) {
        asmbot_invoice_stop(403, 'Счёт доступен только по своему заказу.');
    }
}

$itemStmt = $pdo->prepare('SELECT name, qty, price FROM order_items WHERE order_id = ?');
$itemStmt->execute([$orderId]);
$items = $itemStmt->fetchAll();

$company = asmbot_company_info($pdo);
$ready = asmbot_company_is_ready($company);

$total = 0.0;
foreach ($items as $item) {
    $total += (float) $item['price'] * (int) $item['qty'];
}

$date = date('d.m.Y', strtotime($order['created_at']));

// Реквизиты плательщика: указанные при оформлении важнее профиля.
$buyer = $order['buyer_name'] !== ''
    ? $order['buyer_name']
    : ($order['client_company'] ?: ($order['client_name'] ?: $order['contact_name']));
$buyerInn = $order['buyer_inn'] !== '' ? $order['buyer_inn'] : (string) $order['client_inn'];
$buyerKpp = (string) $order['buyer_kpp'];
$buyerAddress = (string) $order['buyer_address'];
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Счёт № <?= asmbot_h($order['number']) ?></title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; color: #111; max-width: 820px; margin: 0 auto; padding: 24px; font-size: 13px; }
    h1 { font-size: 20px; margin: 24px 0 4px; }
    table { width: 100%; border-collapse: collapse; }
    .req td { padding: 4px 8px; border: 1px solid #999; vertical-align: top; }
    .items th, .items td { padding: 6px 8px; border: 1px solid #999; }
    .items th { background: #f0f0f0; text-align: left; font-size: 12px; }
    .right { text-align: right; white-space: nowrap; }
    .muted { color: #666; }
    .warn { padding: 12px 16px; border: 1px solid #d9822b; background: #fff6e8; margin-bottom: 16px; border-radius: 6px; }
    .sign { margin-top: 32px; display: flex; gap: 48px; }
    .sign div { flex: 1; }
    .line { margin-top: 28px; border-bottom: 1px solid #333; }
    @media print { .noprint { display: none } body { padding: 0 } }
</style>
</head>
<body>

<?php if (!$ready): ?>
<div class="warn">
    <b>Реквизиты компании не заполнены.</b><br>
    Счёт сформирован, но без реквизитов он не имеет силы. Заполните их в админке:
    CRM → Реквизиты компании.
</div>
<?php endif; ?>

<div class="noprint" style="margin-bottom:16px">
    <button onclick="window.print()" style="padding:8px 16px;cursor:pointer">Печать / Сохранить в PDF</button>
</div>

<table class="req">
    <tr>
        <td style="width:60%">
            <b><?= asmbot_h($company['bank_name'] ?: '—') ?></b>
            <div class="muted">Банк получателя</div>
        </td>
        <td>БИК</td>
        <td><?= asmbot_h($company['bank_bik'] ?: '—') ?></td>
    </tr>
    <tr>
        <td rowspan="2"></td>
        <td>Сч. №</td>
        <td><?= asmbot_h($company['bank_corr_account'] ?: '—') ?></td>
    </tr>
    <tr>
        <td>Сч. №</td>
        <td><?= asmbot_h($company['bank_account'] ?: '—') ?></td>
    </tr>
    <tr>
        <td>
            <b><?= asmbot_h($company['company_name'] ?: '—') ?></b>
            <div class="muted">
                ИНН <?= asmbot_h($company['company_inn'] ?: '—') ?>
                <?php if ($company['company_kpp']): ?> · КПП <?= asmbot_h($company['company_kpp']) ?><?php endif; ?>
            </div>
            <div class="muted">Получатель</div>
        </td>
        <td colspan="2"></td>
    </tr>
</table>

<h1>Счёт на оплату № <?= asmbot_h($order['number']) ?> от <?= asmbot_h($date) ?></h1>

<p>
    <b>Поставщик:</b> <?= asmbot_h($company['company_name'] ?: '—') ?><?php
    if ($company['company_address']): ?>, <?= asmbot_h($company['company_address']) ?><?php endif; ?><br>
    <b>Покупатель:</b> <?= asmbot_h($buyer ?: '—') ?><?php
    if ($buyerInn !== ''): ?>, ИНН <?= asmbot_h($buyerInn) ?><?php endif;
    if ($buyerKpp !== ''): ?>, КПП <?= asmbot_h($buyerKpp) ?><?php endif;
    if ($buyerAddress !== ''): ?>, <?= asmbot_h($buyerAddress) ?><?php endif; ?>
</p>

<table class="items">
    <thead>
        <tr>
            <th style="width:36px">№</th>
            <th>Наименование</th>
            <th style="width:70px" class="right">Кол-во</th>
            <th style="width:110px" class="right">Цена</th>
            <th style="width:120px" class="right">Сумма</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $i => $item): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><?= asmbot_h($item['name']) ?></td>
            <td class="right"><?= (int) $item['qty'] ?></td>
            <td class="right"><?= asmbot_rub($item['price']) ?></td>
            <td class="right"><?= asmbot_rub((float) $item['price'] * (int) $item['qty']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<p class="right" style="font-size:15px;margin-top:12px">
    <?php if ((float) $order['discount_pct'] > 0): ?>
        <span class="muted">Цены указаны со скидкой <?= asmbot_h($order['discount_pct']) ?>%</span><br>
    <?php endif; ?>
    <b>Итого к оплате: <?= asmbot_rub($total) ?> руб.</b>
</p>

<p>
    Всего наименований <?= count($items) ?>, на сумму <?= asmbot_rub($total) ?> руб.<br>
    <b><?= asmbot_h(asmbot_amount_in_words($total)) ?></b><br>
    <?php if ($company['vat_note']): ?><span class="muted"><?= asmbot_h($company['vat_note']) ?></span><?php endif; ?>
</p>

<div class="sign">
    <div>
        <div class="line"></div>
        <div class="muted">Руководитель <?= asmbot_h($company['director_name']) ?></div>
    </div>
    <div>
        <div class="line"></div>
        <div class="muted">Бухгалтер <?= asmbot_h($company['accountant_name']) ?></div>
    </div>
</div>

</body>
</html>
