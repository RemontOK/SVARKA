<?php

if (!function_exists('asmbot_offer_stock')) {
    /** Остаток по складу: выгрузки называют его count, quantity, stock, «Остаток». */
    function asmbot_offer_stock($offer)
    {
        $names = ['count', 'quantity', 'stock', 'amount', 'inStock', 'quantity_in_stock',
            'Остаток', 'Количество', 'Наличие', 'Склад'];

        foreach ($offer->children() as $child) {
            $local = strtolower($child->getName());
            foreach ($names as $name) {
                if ($local !== strtolower($name)) {
                    continue;
                }
                $raw = trim((string) $child);
                if ($raw === '') {
                    continue;
                }
                $raw = str_replace([' ', "\xC2\xA0", ','], ['', '', '.'], $raw);
                if (preg_match('/-?\d+(?:\.\d+)?/', $raw, $m)) {
                    return (float) $m[0];
                }
            }
        }

        return null;
    }
}

if (!function_exists('asmbot_compose_offer_name')) {
    /** Имя предложения там, где выгрузка отдаёт только модель. */
    function asmbot_compose_offer_name($typePrefix, $vendor, $model)
    {
        $base = trim((string) $model);
        $lowerBase = function_exists('mb_strtolower') ? mb_strtolower($base, 'UTF-8') : strtolower($base);

        $parts = [];
        foreach ([$typePrefix, $vendor] as $piece) {
            $piece = trim((string) $piece);
            if ($piece === '') {
                continue;
            }
            // Производитель часто уже входит в модель — не дублируем.
            $lowerPiece = function_exists('mb_strtolower') ? mb_strtolower($piece, 'UTF-8') : strtolower($piece);
            if ($lowerBase !== '' && strpos($lowerBase, $lowerPiece) !== false) {
                continue;
            }
            $parts[] = $piece;
        }
        if ($base !== '') {
            $parts[] = $base;
        }

        return trim(implode(' ', $parts));
    }
}

/**
 * Автоматическое обновление товаров из выгрузок поставщиков.
 *
 * Запускается по расписанию (cron) или вручную из админки.
 * Правило то же, что и при ручном обновлении: у товара, который уже лежит
 * в каталоге, обновляется только содержимое — цена, наличие, фотографии,
 * характеристики, документы. Категория и подкатегория не трогаются никогда.
 *
 * Новые позиции складываются в pendingProducts и на сайт не выходят,
 * пока сотрудник не разложит их по разделам.
 *
 * Вызов:
 *   /feed-auto-refresh.php?token=…        — запуск (cron)
 *   /feed-auto-refresh.php?token=…&dry=1  — прогон без записи
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/catalog-guard.php';

$dataFile = __DIR__ . '/runtime-catalog.json';
$logFile = __DIR__ . '/catalog-backups/feed-refresh.log';

/**
 * Токен для cron выводится из админского пароля — отдельный секрет заводить
 * не нужно, а сам пароль в адресе не светится.
 */
require_once __DIR__ . '/admin-auth.php';

function asmbot_refresh_token()
{
    return hash('sha256', 'feed-refresh:' . asmbot_admin_password());
}

// Пускаем двумя путями: cron — по токену в адресе, админка — по паролю
// в заголовке, как остальные внутренние запросы.
$expected = asmbot_refresh_token();
$given = isset($_GET['token']) ? (string) $_GET['token'] : '';
$header = isset($_SERVER['HTTP_X_ADMIN_PASSWORD']) ? (string) $_SERVER['HTTP_X_ADMIN_PASSWORD'] : '';
$allowed = ($expected !== '' && hash_equals($expected, $given))
    || ($header !== '' && hash_equals(asmbot_admin_password(), $header));
if (!$allowed) {
    http_response_code(403);
    echo json_encode(['error' => 'Нет доступа: нужен token или пароль администратора.'], JSON_UNESCAPED_UNICODE);
    exit;
}
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Admin-Password');

$dryRun = !empty($_GET['dry']);

/** Тот же slug, что и в браузерном разборе — иначе id товаров разойдутся. */
function asmbot_slugify($value)
{
    $text = mb_strtolower(trim((string) $value), 'UTF-8');
    $text = preg_replace('/[^a-z0-9а-яё]+/iu', '-', $text);
    $text = trim((string) $text, '-');
    return str_replace('ё', 'e', (string) $text);
}

/**
 * Ключ для поиска дублей: имя без регистра, пробелов и знаков.
 * Нужен, чтобы выгрузка с другой нумерацией не завела второй такой же товар.
 */
function asmbot_dup_key($name, $brand = '')
{
    $text = mb_strtolower(trim((string) $name . ' ' . (string) $brand), 'UTF-8');
    $text = preg_replace('/[^a-z0-9а-я]+/iu', '', $text);
    return str_replace('ё', 'е', (string) $text);
}

function asmbot_product_id($feedId, $offerId, $offerName = '')
{
    $tail = asmbot_slugify($offerId !== '' ? $offerId : $offerName);
    if ($tail === '') {
        $tail = (string) time();
    }
    return 'yml-' . asmbot_slugify($feedId) . '-' . $tail;
}

function asmbot_fetch($url, $timeout = 120)
{
    $ctx = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'follow_location' => 1,
            'max_redirects' => 5,
            'header' => "User-Agent: AsmbotFeedRefresh/1.0 (+https://asmbot.ru)\r\n",
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? '' : $body;
}

/** Текст узла с расшифровкой сущностей и нормализацией пробелов. */
function asmbot_node_text($node)
{
    if ($node === null) {
        return '';
    }
    $text = html_entity_decode((string) $node, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\xC2\xA0", ' ', $text);
    $text = preg_replace('/[ \t]{2,}/u', ' ', $text);
    return trim((string) $text);
}

/** Разбор YML/XML: берём только то, что обновляем. */
function asmbot_parse_offers($xmlText)
{
    if (trim($xmlText) === '') {
        return [];
    }

    $previous = libxml_use_internal_errors(true);
    // Внешний DTD (shops.dtd) вешает разбор — отключаем.
    $xml = simplexml_load_string($xmlText, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOENT);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if ($xml === false) {
        return [];
    }

    $offers = $xml->xpath('//offer');
    if (!$offers) {
        return [];
    }

    $result = [];
    foreach ($offers as $offer) {
        $id = trim((string) $offer['id']);
        $name = asmbot_node_text($offer->name);
        if ($name === '') {
            // Выгрузки типа vendor.model (например FoxWeld) тега <name> не имеют:
            // имя собирается из <typePrefix>, <vendor> и <model>.
            $name = asmbot_compose_offer_name(
                asmbot_node_text($offer->typePrefix),
                asmbot_node_text($offer->vendor),
                asmbot_node_text($offer->model)
            );
        }
        if ($name === '') {
            continue;
        }

        // Картинки лежат и прямо в offer, и внутри <pictures> — берём отовсюду.
        $pictures = [];
        $pictureNodes = [];
        foreach ($offer->picture as $picture) {
            $pictureNodes[] = $picture;
        }
        if (isset($offer->pictures)) {
            foreach ($offer->pictures->picture as $picture) {
                $pictureNodes[] = $picture;
            }
        }
        foreach ($pictureNodes as $picture) {
            $url = asmbot_node_text($picture);
            if ($url === '') {
                $url = trim((string) $picture['url']);
            }
            if ($url !== '' && !in_array($url, $pictures, true)) {
                $pictures[] = $url;
            }
        }

        $specs = [];
        foreach ($offer->param as $param) {
            $title = asmbot_node_text($param['name']);
            $value = asmbot_node_text($param);
            if ($title === '' || $value === '') {
                continue;
            }
            $specs[] = [
                'id' => null,
                'title' => $title,
                'units' => asmbot_node_text($param['unit']),
                'value' => [$value],
            ];
        }

        $videos = [];
        $videoNodes = [];
        foreach ($offer->video as $video) {
            $videoNodes[] = $video;
        }
        if (isset($offer->videos)) {
            foreach ($offer->videos->video as $video) {
                $videoNodes[] = $video;
            }
        }
        foreach ($videoNodes as $video) {
            $url = asmbot_node_text($video);
            if ($url !== '' && !in_array($url, $videos, true)) {
                $videos[] = $url;
            }
        }

        $price = asmbot_node_text($offer->price);
        $available = trim((string) $offer['available']);
        $stock = asmbot_offer_stock($offer);

        $result[] = [
            'offerId' => $id,
            'name' => $name,
            'description' => asmbot_node_text($offer->description),
            'price' => $price === '' ? null : (float) str_replace(',', '.', $price),
            'images' => $pictures,
            'specs' => $specs,
            'videos' => $videos,
            'brand' => asmbot_node_text($offer->vendor),
            'sku' => asmbot_node_text($offer->vendorCode),
            'stock' => $stock,
            // Остаток важнее флага available: он у поставщиков часто не обновляется.
            'availability' => $stock !== null
                ? ($stock > 0 ? 'В наличии' : 'Нет в наличии')
                : ($available === 'false' ? 'Под заказ' : 'В наличии'),
            'subcategory' => asmbot_node_text($offer->categoryId),
        ];
    }

    return $result;
}

$raw = is_file($dataFile) ? @file_get_contents($dataFile) : '';
$catalog = $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($catalog)) {
    http_response_code(500);
    echo json_encode(['error' => 'Каталог недоступен.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$feeds = isset($catalog['catalogFeeds']) && is_array($catalog['catalogFeeds'])
    ? $catalog['catalogFeeds']
    : [];
$products = isset($catalog['products']) && is_array($catalog['products']) ? $catalog['products'] : [];
$pending = isset($catalog['pendingProducts']) && is_array($catalog['pendingProducts'])
    ? $catalog['pendingProducts']
    : [];

$byId = [];
foreach ($products as $index => $product) {
    $id = isset($product['id']) ? (string) $product['id'] : '';
    if ($id !== '') {
        $byId[$id] = $index;
    }
}
// Указатель «артикул → индекс товара»: самый надёжный способ узнать товар,
// он переживает смену нумерации в выгрузке.
//
// Артикул лежит либо в поле sku, либо в sourceFeed.sku — раньше смотрели
// только первое, и у товаров, импортированных из YML, указатель оставался
// пустым. Из-за этого сопоставление шло по одному лишь id, а поставщик мог
// сменить нумерацию: имена и цены уезжали на чужие товары.
//
// Указатель раздельный по поставщикам: один и тот же артикул у разных
// поставщиков — обычное дело. Неоднозначные артикулы выбрасываем совсем.
$bySkuByFeed = [];
$ambiguousSku = [];
foreach ($products as $index => $product) {
    $source = isset($product['sourceFeed']) && is_array($product['sourceFeed'])
        ? $product['sourceFeed']
        : [];
    $productFeed = isset($source['feedId']) ? (string) $source['feedId'] : '';
    $sku = '';
    if (isset($product['sku']) && trim((string) $product['sku']) !== '') {
        $sku = trim((string) $product['sku']);
    } elseif (isset($source['sku']) && trim((string) $source['sku']) !== '') {
        $sku = trim((string) $source['sku']);
    }
    if ($sku === '' || $productFeed === '') {
        continue;
    }
    $key = mb_strtolower($sku, 'UTF-8');
    if (isset($bySkuByFeed[$productFeed][$key])) {
        $ambiguousSku[$productFeed][$key] = true;
        continue;
    }
    $bySkuByFeed[$productFeed][$key] = $index;
}

// Указатель «имя+бренд → индекс товара»: страхует от повторов, когда
// поставщик сменил нумерацию и id перестали совпадать.
$byDupKey = [];
foreach ($products as $index => $product) {
    $key = asmbot_dup_key(
        isset($product['name']) ? $product['name'] : '',
        isset($product['brand']) ? $product['brand'] : ''
    );
    if ($key !== '' && !isset($byDupKey[$key])) {
        $byDupKey[$key] = $index;
    }
}

$pendingIds = [];
$pendingDupKeys = [];
foreach ($pending as $item) {
    $id = isset($item['id']) ? (string) $item['id'] : '';
    if ($id !== '') {
        $pendingIds[$id] = true;
    }
    $key = asmbot_dup_key(
        isset($item['name']) ? $item['name'] : '',
        isset($item['brand']) ? $item['brand'] : ''
    );
    if ($key !== '') {
        $pendingDupKeys[$key] = true;
    }
}

$report = [];
$totalUpdated = 0;
$totalNew = 0;
$totalMissing = 0;
$totalDuplicates = 0;

foreach ($feeds as $feed) {
    $feedId = isset($feed['id']) ? (string) $feed['id'] : '';
    if ($feedId === '') {
        continue;
    }

    // Выгрузка может состоять из нескольких файлов — например, когда
    // поставщик отдаёт каталог по разделам, а не одним каталогом.
    $urls = [];
    if (!empty($feed['sourceUrls']) && is_array($feed['sourceUrls'])) {
        foreach ($feed['sourceUrls'] as $one) {
            $one = trim((string) $one);
            if ($one !== '') {
                $urls[] = $one;
            }
        }
    }
    $single = isset($feed['sourceUrl']) ? trim((string) $feed['sourceUrl']) : '';
    if ($single !== '' && !in_array($single, $urls, true)) {
        array_unshift($urls, $single);
    }
    if (!$urls) {
        continue;
    }

    // Поставщика можно временно отключить, не удаляя ссылки.
    if (array_key_exists('autoRefresh', $feed) && !$feed['autoRefresh']) {
        $report[] = ['feed' => $feedId, 'skipped' => 'обновление выключено'];
        continue;
    }

    // Частичная выгрузка покрывает не весь ассортимент, поэтому отсутствие
    // товара в ней ничего не значит — «пропавшими» такие не помечаем.
    $isPartial = !empty($feed['partial']);

    $offers = [];
    $failed = [];
    foreach ($urls as $one) {
        $body = asmbot_fetch($one);
        if ($body === '') {
            $failed[] = $one;
            continue;
        }
        $part = asmbot_parse_offers($body);
        if ($part) {
            $offers = array_merge($offers, $part);
        }
    }

    if (!$offers) {
        $report[] = [
            'feed' => $feedId,
            'error' => $failed ? 'не удалось загрузить' : 'в выгрузке нет товаров',
        ];
        continue;
    }

    $updated = 0;
    $added = 0;
    $unchanged = 0;
    $seenIds = [];
    $duplicates = 0;
    $matchedBySku = 0;

    foreach ($offers as $offer) {
        $productId = asmbot_product_id($feedId, $offer['offerId'], $offer['name']);
        $seenIds[$productId] = true;

        // Артикул важнее нашего id: id собран из номера позиции в той выгрузке,
        // по которой товар когда-то завели, а поставщик эту нумерацию меняет.
        // Артикул же остаётся за товаром навсегда.
        $index = null;
        $sku = mb_strtolower(trim((string) $offer['sku']), 'UTF-8');
        if (
            $sku !== ''
            && isset($bySkuByFeed[$feedId][$sku])
            && !isset($ambiguousSku[$feedId][$sku])
        ) {
            $index = $bySkuByFeed[$feedId][$sku];
            $seenIds[$products[$index]['id']] = true;
            $matchedBySku++;
        } elseif (isset($byId[$productId])) {
            $index = $byId[$productId];
        }

        if ($index !== null) {
            $current = $products[$index];
            $next = $current;
            $changed = false;

            // Обновляем только содержимое. Раскладку не трогаем.
            $scalars = ['name' => 'name', 'description' => 'description', 'price' => 'price',
                'brand' => 'brand', 'availability' => 'availability', 'stock' => 'stock'];
            foreach ($scalars as $from => $to) {
                $value = $offer[$from];
                if ($value === null || $value === '' || $value === []) {
                    continue;
                }
                if (!isset($next[$to]) || $next[$to] !== $value) {
                    $next[$to] = $value;
                    $changed = true;
                }
            }
            foreach (['images', 'specs', 'videos'] as $listField) {
                if (empty($offer[$listField])) {
                    continue;
                }
                $before = isset($next[$listField]) ? $next[$listField] : [];
                if (json_encode($before) !== json_encode($offer[$listField])) {
                    $next[$listField] = $offer[$listField];
                    $changed = true;
                }
            }
            if (!empty($next['images']) && empty($next['image'])) {
                $next['image'] = $next['images'][0];
                $changed = true;
            }

            // Раскладка возвращается на место при любых условиях.
            $next['category'] = isset($current['category']) ? $current['category'] : '';
            $next['subcategory'] = isset($current['subcategory']) ? $current['subcategory'] : '';

            if ($changed) {
                $products[$index] = $next;
                $updated++;
            } else {
                $unchanged++;
            }
            continue;
        }

        if (isset($pendingIds[$productId])) {
            continue;
        }

        // Тот же товар под другим номером: обновляем существующий,
        // а не заводим второй такой же.
        $dupKey = asmbot_dup_key($offer['name'], $offer['brand']);
        if ($dupKey !== '' && isset($byDupKey[$dupKey])) {
            $index = $byDupKey[$dupKey];
            $seenIds[$products[$index]['id']] = true;
            $duplicates++;
            continue;
        }
        if ($dupKey !== '' && isset($pendingDupKeys[$dupKey])) {
            $duplicates++;
            continue;
        }

        // Новый товар: ждёт распределения, на сайт не выходит.
        $pending[] = [
            'id' => $productId,
            'name' => $offer['name'],
            'description' => $offer['description'],
            'price' => $offer['price'],
            'brand' => $offer['brand'],
            'availability' => $offer['availability'],
            'stock' => $offer['stock'],
            'images' => $offer['images'],
            'image' => $offer['images'] ? $offer['images'][0] : '',
            'specs' => $offer['specs'],
            'videos' => $offer['videos'],
            'category' => '',
            'subcategory' => '',
            'suggestedSubcategory' => $offer['subcategory'],
            'pendingFeedId' => $feedId,
            'pendingSince' => gmdate('c'),
            'sourceFeed' => ['feedId' => $feedId, 'offerId' => $offer['offerId']],
        ];
        $pendingIds[$productId] = true;
        if ($dupKey !== '') {
            $pendingDupKeys[$dupKey] = true;
        }
        $added++;
    }

    // Товары этой выгрузки, которых в ней больше нет: помечаем, но не удаляем.
    // Решение принимает человек в админке.
    $missing = 0;
    $returned = 0;
    foreach ($isPartial ? [] : $products as $index => $product) {
        $sourceFeed = isset($product['sourceFeed']['feedId']) ? (string) $product['sourceFeed']['feedId'] : '';
        if ($sourceFeed !== $feedId) {
            continue;
        }
        $id = isset($product['id']) ? (string) $product['id'] : '';
        if ($id === '') {
            continue;
        }
        if (isset($seenIds[$id])) {
            if (!empty($product['missingFromFeed'])) {
                unset($products[$index]['missingFromFeed']);
                $returned++;
            }
            continue;
        }
        if (empty($product['missingFromFeed'])) {
            $products[$index]['missingFromFeed'] = [
                'feedId' => $feedId,
                'since' => gmdate('c'),
            ];
            $missing++;
        }
    }

    $totalUpdated += $updated;
    $totalNew += $added;
    $totalMissing += $missing;
    $totalDuplicates += $duplicates;
    $report[] = [
        'feed' => $feedId,
        'updated' => $updated,
        'new' => $added,
        'unchanged' => $unchanged,
        'missing' => $missing,
        'returned' => $returned,
        'duplicates' => $duplicates,
        'bySku' => $matchedBySku,
        'files' => count($urls),
        'partial' => $isPartial,
        'failedFiles' => count($failed),
    ];
}

$result = [
    'ok' => true,
    'dryRun' => $dryRun,
    'updated' => $totalUpdated,
    'new' => $totalNew,
    'missing' => $totalMissing,
    'duplicatesSkipped' => $totalDuplicates,
    'pendingTotal' => count($pending),
    'feeds' => $report,
];

if ($dryRun || ($totalUpdated === 0 && $totalNew === 0 && $totalMissing === 0)) {
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

// Предохранитель. Если выгрузка вдруг даёт лавину «новых» — это почти всегда
// значит, что поставщик сменил нумерацию, и мы вот-вот наплодим дубли.
// В таком случае не пишем ничего и просим разобраться руками.
$catalogSize = max(1, count($products));
$newShare = $totalNew / $catalogSize;
if ($totalNew > 200 && $newShare > 0.25) {
    asmbot_guard_log($dataFile, [
        'event' => 'auto-refresh-flood',
        'new' => $totalNew,
        'catalog' => $catalogSize,
    ]);
    http_response_code(409);
    $result['ok'] = false;
    $result['error'] = 'Обновление остановлено: выгрузка предлагает ' . $totalNew
        . ' новых товаров при каталоге в ' . $catalogSize
        . '. Похоже, у поставщика сменилась нумерация — иначе появятся дубли.';
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

$next = $catalog;
$next['products'] = array_values($products);
$next['pendingProducts'] = array_values($pending);

// Страховка: обновление не имеет права терять разделы или товары.
$diff = asmbot_guard_diff($catalog, $next);
if (asmbot_guard_is_destructive($diff, PHP_INT_MAX)) {
    asmbot_guard_log($dataFile, [
        'event' => 'auto-refresh-blocked',
        'changes' => asmbot_guard_describe($diff),
    ]);
    http_response_code(409);
    $result['ok'] = false;
    $result['error'] = 'Обновление остановлено: оно теряет части каталога.';
    $result['changes'] = asmbot_guard_describe($diff);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

$backup = asmbot_guard_backup($dataFile);
$next['updatedAt'] = gmdate('c');

// Отметка «тексты уже починены» ставится только браузером, который прогоняет
// каталог через свою чистку кодировок. Сюда же попадают названия и описания
// прямо из выгрузок поставщиков, эту чистку не проходившие, — поэтому отметку
// снимаем, и сайт обработает каталог заново.
unset($next['shapeVersion']);

$json = json_encode($next, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$tmp = $dataFile . '.tmp';
if ($json === false || @file_put_contents($tmp, $json, LOCK_EX) === false) {
    http_response_code(500);
    $result['ok'] = false;
    $result['error'] = 'Не удалось записать каталог.';
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}
if (!@rename($tmp, $dataFile)) {
    @file_put_contents($dataFile, $json, LOCK_EX);
    @unlink($tmp);
}
@chmod($dataFile, 0644);

// Витринную копию пересобираем сразу: иначе сайт останется на старых ценах,
// пока кто-нибудь не откроет админку и не нажмёт «Применить».
require_once __DIR__ . '/catalog-split-lib.php';
$split = asmbot_write_split_catalog($next, __DIR__);
$result['liteBytes'] = $split ? $split['lite'] : null;

$result['backup'] = $backup;
asmbot_guard_log($dataFile, [
    'event' => 'auto-refresh',
    'updated' => $totalUpdated,
    'new' => $totalNew,
    'backup' => $backup,
]);
@file_put_contents(
    $logFile,
    gmdate('c') . " обновлено {$totalUpdated}, новых {$totalNew}\n",
    FILE_APPEND | LOCK_EX
);

echo json_encode($result, JSON_UNESCAPED_UNICODE);
