<?php
/**
 * Автоматический обмен с 1С (Управление торговлей / Бухгалтерия).
 *
 * Протокол — упрощённый CommerceML: 1С сама ходит сюда по расписанию.
 *
 *   GET  ?mode=checkauth              — авторизация, отдаёт cookie сессии обмена
 *   GET  ?mode=init                   — параметры обмена
 *   GET  ?type=sale&mode=query        — выгрузка заказов в XML (CommerceML)
 *   POST ?type=sale&mode=file         — приём статусов/оплат из 1С
 *   GET  ?type=sale&mode=success      — пометить выгруженные заказы
 *
 * Доступ — по логину и паролю обмена из настроек (CRM → Реквизиты).
 * Пароль админки здесь не используется: у 1С отдельная учётка.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings-lib.php';

// 1С ожидает text/plain для служебных ответов и XML для данных.
header('Cache-Control: no-store, no-cache, must-revalidate');

const ASMBOT_1C_COOKIE = 'asmbot_1c';

function asmbot_1c_say($line)
{
    header('Content-Type: text/plain; charset=utf-8');
    echo $line . "\n";
    exit;
}

function asmbot_1c_fail($message)
{
    asmbot_1c_say("failure\n" . $message);
}

try {
    $pdo = asmbot_db();
} catch (RuntimeException $e) {
    asmbot_1c_fail('База данных недоступна.');
}

$settings = asmbot_get_settings($pdo);
$login = isset($settings['exchange_1c_login']) ? (string) $settings['exchange_1c_login'] : '';
$password = isset($settings['exchange_1c_password']) ? (string) $settings['exchange_1c_password'] : '';

if ($login === '' || $password === '') {
    asmbot_1c_fail('Обмен не настроен: задайте логин и пароль в админке (CRM → Реквизиты).');
}

$mode = isset($_GET['mode']) ? (string) $_GET['mode'] : '';
$type = isset($_GET['type']) ? (string) $_GET['type'] : '';

/** Токен сессии обмена — производная от пароля, отдельная от клиентских сессий. */
function asmbot_1c_token($login, $password)
{
    return hash('sha256', 'asmbot-1c|' . $login . '|' . $password);
}

$expectedToken = asmbot_1c_token($login, $password);

// ------------------------------------------------------------ checkauth
if ($mode === 'checkauth') {
    $user = '';
    $pass = '';

    if (isset($_SERVER['PHP_AUTH_USER'])) {
        $user = (string) $_SERVER['PHP_AUTH_USER'];
        $pass = isset($_SERVER['PHP_AUTH_PW']) ? (string) $_SERVER['PHP_AUTH_PW'] : '';
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        // Некоторые хостинги не заполняют PHP_AUTH_*, разбираем заголовок сами.
        $header = (string) $_SERVER['HTTP_AUTHORIZATION'];
        if (stripos($header, 'basic ') === 0) {
            $decoded = base64_decode(substr($header, 6), true);
            if ($decoded !== false && strpos($decoded, ':') !== false) {
                [$user, $pass] = explode(':', $decoded, 2);
            }
        }
    }

    if (!hash_equals($login, $user) || !hash_equals($password, $pass)) {
        asmbot_1c_fail('Неверный логин или пароль обмена.');
    }

    asmbot_1c_say("success\n" . ASMBOT_1C_COOKIE . "\n" . $expectedToken);
}

// Дальше — только с валидным токеном сессии обмена.
$provided = isset($_COOKIE[ASMBOT_1C_COOKIE]) ? (string) $_COOKIE[ASMBOT_1C_COOKIE] : '';
if ($provided === '' || !hash_equals($expectedToken, $provided)) {
    asmbot_1c_fail('Требуется авторизация обмена (mode=checkauth).');
}

// ----------------------------------------------------------------- init
if ($mode === 'init') {
    // Файлы не дробим: заказы отдаются одним XML.
    asmbot_1c_say("zip=no\nfile_limit=10485760");
}

if ($type !== 'sale') {
    asmbot_1c_fail('Поддерживается только обмен заказами (type=sale).');
}

// ---------------------------------------------------------------- query
// Выгрузка заказов в 1С. Отдаём подтверждённые и далее по циклу — «новые»
// в 1С не нужны, менеджер их ещё не принял.
if ($mode === 'query') {
    $stmt = $pdo->query(
        "SELECT o.*, c.email AS client_email, c.name AS client_name,
                c.company AS client_company, c.inn AS client_inn, c.kpp AS client_kpp,
                c.legal_address AS client_address, c.phone AS client_phone
         FROM orders o
         LEFT JOIN clients c ON c.id = o.client_id
         WHERE o.status IN ('confirmed','invoiced','paid','shipped','done')
         ORDER BY o.created_at ASC
         LIMIT 500"
    );
    $orders = $stmt->fetchAll();

    $itemsByOrder = [];
    if ($orders) {
        $ids = array_column($orders, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id IN ($in)");
        $itemStmt->execute($ids);
        foreach ($itemStmt->fetchAll() as $item) {
            $itemsByOrder[$item['order_id']][] = $item;
        }
    }

    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = true;

    $root = $doc->createElement('КоммерческаяИнформация');
    $root->setAttribute('ВерсияСхемы', '2.05');
    $root->setAttribute('ДатаФормирования', date('Y-m-d\TH:i:s'));
    $doc->appendChild($root);

    foreach ($orders as $order) {
        $docEl = $doc->createElement('Документ');

        $add = static function ($parent, $name, $value) use ($doc) {
            $node = $doc->createElement($name);
            $node->appendChild($doc->createTextNode((string) $value));
            $parent->appendChild($node);
            return $node;
        };

        $add($docEl, 'Ид', $order['number']);
        $add($docEl, 'Номер', $order['number']);
        $add($docEl, 'Дата', date('Y-m-d', strtotime($order['created_at'])));
        $add($docEl, 'Время', date('H:i:s', strtotime($order['created_at'])));
        $add($docEl, 'ХозОперация', 'Заказ товара');
        $add($docEl, 'Роль', 'Продавец');
        $add($docEl, 'Валюта', 'руб');
        $add($docEl, 'Курс', '1');
        $add($docEl, 'Сумма', number_format((float) $order['total'], 2, '.', ''));

        // Контрагент
        $counterparties = $doc->createElement('Контрагенты');
        $cp = $doc->createElement('Контрагент');
        $buyerName = $order['buyer_name'] !== ''
            ? $order['buyer_name']
            : ($order['client_company'] ?: ($order['client_name'] ?: $order['contact_name']));
        $add($cp, 'Ид', 'client-' . (int) $order['client_id']);
        $add($cp, 'Наименование', $buyerName);
        $add($cp, 'ПолноеНаименование', $buyerName);
        $inn = $order['buyer_inn'] !== '' ? $order['buyer_inn'] : (string) $order['client_inn'];
        if ($inn !== '') $add($cp, 'ИНН', $inn);
        $kpp = $order['buyer_kpp'] !== '' ? $order['buyer_kpp'] : (string) $order['client_kpp'];
        if ($kpp !== '') $add($cp, 'КПП', $kpp);
        $address = $order['buyer_address'] !== '' ? $order['buyer_address'] : (string) $order['client_address'];
        if ($address !== '') {
            $addr = $doc->createElement('АдресРегистрации');
            $add($addr, 'Представление', $address);
            $cp->appendChild($addr);
        }
        $contacts = $doc->createElement('Контакты');
        if ($order['client_email'] || $order['contact_email']) {
            $contact = $doc->createElement('Контакт');
            $add($contact, 'Тип', 'Почта');
            $add($contact, 'Значение', $order['client_email'] ?: $order['contact_email']);
            $contacts->appendChild($contact);
        }
        if ($order['contact_phone'] || $order['client_phone']) {
            $contact = $doc->createElement('Контакт');
            $add($contact, 'Тип', 'ТелефонРабочий');
            $add($contact, 'Значение', $order['contact_phone'] ?: $order['client_phone']);
            $contacts->appendChild($contact);
        }
        if ($contacts->hasChildNodes()) $cp->appendChild($contacts);
        $counterparties->appendChild($cp);
        $docEl->appendChild($counterparties);

        // Позиции
        $goods = $doc->createElement('Товары');
        foreach ($itemsByOrder[$order['id']] ?? [] as $item) {
            $g = $doc->createElement('Товар');
            $add($g, 'Ид', $item['product_id']);
            $add($g, 'Наименование', $item['name']);
            $baseUnit = $doc->createElement('БазоваяЕдиница');
            $baseUnit->setAttribute('Код', '796');
            $baseUnit->setAttribute('НаименованиеПолное', 'Штука');
            $baseUnit->appendChild($doc->createTextNode('шт'));
            $g->appendChild($baseUnit);
            $add($g, 'ЦенаЗаЕдиницу', number_format((float) $item['price'], 2, '.', ''));
            $add($g, 'Количество', (int) $item['qty']);
            $add($g, 'Сумма', number_format((float) $item['price'] * (int) $item['qty'], 2, '.', ''));
            $goods->appendChild($g);
        }
        $docEl->appendChild($goods);

        // Статус и скидка — реквизитами документа
        $props = $doc->createElement('ЗначенияРеквизитов');
        $addProp = static function ($name, $value) use ($doc, $props, $add) {
            $p = $doc->createElement('ЗначениеРеквизита');
            $add($p, 'Наименование', $name);
            $add($p, 'Значение', $value);
            $props->appendChild($p);
        };
        $addProp('Статус заказа', $order['status']);
        $addProp('Скидка, %', (float) $order['discount_pct']);
        if ($order['comment']) $addProp('Комментарий', $order['comment']);
        $addProp('Метод оплаты', 'Безналичный расчёт');
        $docEl->appendChild($props);

        $root->appendChild($docEl);
    }

    header('Content-Type: text/xml; charset=utf-8');
    echo $doc->saveXML();
    exit;
}

// -------------------------------------------------------------- success
// 1С подтверждает, что заказы приняты — помечаем дату выгрузки.
if ($mode === 'success') {
    $pdo->exec(
        "UPDATE orders SET exported_at = NOW()
         WHERE exported_at IS NULL AND status IN ('confirmed','invoiced','paid','shipped','done')"
    );
    asmbot_1c_say('success');
}

// ----------------------------------------------------------------- file
// Приём изменений из 1С: статусы и отметки об оплате.
if ($mode === 'file') {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        asmbot_1c_fail('Пустой файл обмена.');
    }

    $previous = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($raw);
    libxml_use_internal_errors($previous);

    if ($xml === false) {
        asmbot_1c_fail('Не удалось разобрать XML.');
    }

    // Сопоставление статусов 1С с нашими.
    $statusMap = [
        'оплачен' => 'paid',
        'оплаченный' => 'paid',
        'отгружен' => 'shipped',
        'выполнен' => 'done',
        'закрыт' => 'done',
        'отменен' => 'cancelled',
        'отменён' => 'cancelled',
        'счет выставлен' => 'invoiced',
        'счёт выставлен' => 'invoiced',
    ];

    $updated = 0;
    $update = $pdo->prepare('UPDATE orders SET status = ? WHERE number = ?');

    foreach ($xml->Документ as $docNode) {
        $number = trim((string) $docNode->Номер);
        if ($number === '') {
            $number = trim((string) $docNode->Ид);
        }
        if ($number === '') continue;

        $status = '';
        foreach ($docNode->ЗначенияРеквизитов->ЗначениеРеквизита ?? [] as $prop) {
            $name = mb_strtolower(trim((string) $prop->Наименование));
            $value = trim((string) $prop->Значение);
            if ($name === 'статус заказа' || $name === 'статус') {
                $key = mb_strtolower($value);
                if (isset($statusMap[$key])) $status = $statusMap[$key];
                elseif (in_array($value, ['new','confirmed','invoiced','paid','shipped','done','cancelled'], true)) $status = $value;
            }
            if (($name === 'проведен' || $name === 'проведён') && mb_strtolower($value) === 'true') {
                if ($status === '') $status = 'paid';
            }
        }

        if ($status !== '') {
            $update->execute([$status, $number]);
            $updated += $update->rowCount();
        }
    }

    asmbot_1c_say($updated > 0 ? "success\nОбновлено заказов: $updated" : 'success');
}

asmbot_1c_fail('Неизвестный режим обмена.');
