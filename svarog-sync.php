<?php
/**
 * Svarog catalog sync.
 * - action=prices  — update rr_price / stock for existing feed-svarog products (daily)
 * - action=full    — add/refresh product cards from API in batches
 *
 * Structure lock: never moves products between categories/subcategories and never
 * rewrites admin category trees (except appending leaves under category `svarog`
 * for brand-new products).
 *
 * Cron examples:
 *   /usr/bin/curl -s "https://SITE/svarog-sync.php?action=prices" >/dev/null
 *   /usr/bin/curl -s "https://SITE/svarog-sync.php?action=full&batch=40" >/dev/null
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$FEED_ID = 'feed-svarog';
$CATALOG_FILE = __DIR__ . '/runtime-catalog.json';
$STATE_FILE = __DIR__ . '/svarog-sync-state.json';
$MIN_PRICE_INTERVAL_SEC = 6 * 60 * 60; // 6 hours

$action = isset($_GET['action']) ? trim((string) $_GET['action']) : 'prices';
$batch = isset($_GET['batch']) ? (int) $_GET['batch'] : 40;
if ($batch < 5) {
    $batch = 5;
}
if ($batch > 80) {
    $batch = 80;
}
$force = isset($_GET['force']) && $_GET['force'] === '1';

function svarog_http_get($url)
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 45,
            'ignore_errors' => true,
            'header' => "User-Agent: AlphaSmart-SvarogSync/1.0\r\nAccept: application/json\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $status = 502;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $line, $m)) {
                $status = (int) $m[1];
                break;
            }
        }
    }

    return [$status, $body];
}

function svarog_slugify($value)
{
    $text = mb_strtolower(trim((string) $value), 'UTF-8');
    $text = preg_replace('/[^a-z0-9а-яё]+/u', '-', $text);
    $text = trim($text, '-');
    $text = str_replace('ё', 'e', $text);
    return $text !== '' ? $text : 'item';
}

function svarog_strip_html($html)
{
    $text = (string) $html;
    $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
    $text = preg_replace('/<\/p>/i', "\n", $text);
    $text = preg_replace('/<\/li>/i', "\n", $text);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/[ \t]+/", ' ', $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
}

function svarog_guess_category($categoryName)
{
    return 'svarog';
}

function svarog_product_id($num)
{
    return 'yml-feed-svarog-' . svarog_slugify($num);
}

function svarog_load_json($file, $fallback)
{
    if (!is_file($file)) {
        return $fallback;
    }
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') {
        return $fallback;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $fallback;
}

function svarog_save_json($file, $data)
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    if (basename($file) === 'runtime-catalog.json') {
        require_once __DIR__ . '/catalog-guard.php';

        // Синхронизация обновляет цены и содержимое, но не имеет права
        // трогать раскладку. Подтвердить тут некому, поэтому при любой
        // потере разделов или товаров запись просто отменяется.
        $currentRaw = is_file($file) ? @file_get_contents($file) : '';
        $current = is_string($currentRaw) && $currentRaw !== '' ? json_decode($currentRaw, true) : null;
        if (is_array($current)) {
            $diff = asmbot_guard_diff($current, $data);
            if (asmbot_guard_is_destructive($diff, PHP_INT_MAX)) {
                asmbot_guard_log($file, [
                    'event' => 'sync-blocked',
                    'action' => 'svarog-sync',
                    'changes' => asmbot_guard_describe($diff),
                ]);
                return false;
            }
        }

        // Копия до записи — чтобы можно было вернуться к состоянию,
        // которое настроил сотрудник.
        $backup = asmbot_guard_backup($file);
        if ($backup !== null) {
            asmbot_guard_log($file, ['event' => 'backup', 'action' => 'svarog-sync', 'file' => $backup]);
        }
    }

    $tmp = $file . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }
    if (!@rename($tmp, $file)) {
        if (@file_put_contents($file, $json, LOCK_EX) === false) {
            @unlink($tmp);
            return false;
        }
        @unlink($tmp);
    }
    @chmod($file, 0644);
    return true;
}

function svarog_ensure_subcategory(&$catalog, $categoryId, $subcategoryName)
{
    if ($categoryId === '' || $subcategoryName === '') {
        return;
    }

    if (!isset($catalog['categories']) || !is_array($catalog['categories'])) {
        $catalog['categories'] = [];
    }

    $categoryIndex = null;
    foreach ($catalog['categories'] as $index => $category) {
        if (($category['id'] ?? '') === $categoryId) {
            $categoryIndex = $index;
            break;
        }
    }

    if ($categoryIndex === null) {
        $title = $categoryId === 'svarog' ? 'Сварог' : $categoryId;
        $catalog['categories'][] = [
            'id' => $categoryId,
            'title' => $title,
            'description' => $categoryId === 'svarog' ? 'Каталог продукции Сварог' : '',
            'accent' => '#f5b400',
            'image' => '',
            'icon' => $categoryId === 'svarog' ? '◆' : '•',
            'subcategories' => [['name' => $subcategoryName]],
        ];
        return;
    }

    $subs = isset($catalog['categories'][$categoryIndex]['subcategories'])
        && is_array($catalog['categories'][$categoryIndex]['subcategories'])
        ? $catalog['categories'][$categoryIndex]['subcategories']
        : [];

    $needle = mb_strtolower($subcategoryName, 'UTF-8');
    foreach ($subs as $sub) {
        $name = is_array($sub) ? (string) ($sub['name'] ?? '') : (string) $sub;
        if (mb_strtolower(trim($name), 'UTF-8') === $needle) {
            return;
        }
        if (is_array($sub) && !empty($sub['children']) && is_array($sub['children'])) {
            foreach ($sub['children'] as $child) {
                $childName = is_array($child) ? (string) ($child['name'] ?? '') : (string) $child;
                if (mb_strtolower(trim($childName), 'UTF-8') === $needle) {
                    return;
                }
            }
        }
    }

    $subs[] = ['name' => $subcategoryName];
    $catalog['categories'][$categoryIndex]['subcategories'] = $subs;
}

function svarog_map_detail_to_product($detail, $row, $feedId)
{
    $num = (string) ($detail['num'] ?? $row['num'] ?? '');
    $title = trim((string) ($detail['title'] ?? ''));
    if ($num === '' || $title === '') {
        return null;
    }

    $categoryName = trim((string) ($detail['category'] ?? 'Без категории'));
    if ($categoryName === '') {
        $categoryName = 'Без категории';
    }
    $siteCategory = svarog_guess_category($categoryName);
    $price = isset($detail['rr_price']) ? (int) $detail['rr_price'] : (int) ($row['rr_price'] ?? 0);
    $inStock = array_key_exists('in_stock', $detail)
        ? (bool) $detail['in_stock']
        : (bool) ($row['in_stock'] ?? false);

    $images = [];
    if (!empty($detail['images']) && is_array($detail['images'])) {
        foreach ($detail['images'] as $image) {
            if (!empty($image['url'])) {
                $images[] = $image['url'];
            }
        }
    }

    $description = svarog_strip_html($detail['description'] ?? '');
    $including = svarog_strip_html($detail['including'] ?? '');

    $dutyCycle = '';
    $inputVoltage = '';
    $specs = [];
    if (!empty($detail['spec_fields']) && is_array($detail['spec_fields'])) {
        foreach ($detail['spec_fields'] as $field) {
            $fieldTitle = trim((string) ($field['title'] ?? ''));
            $values = isset($field['value']) && is_array($field['value']) ? $field['value'] : [];
            $units = trim((string) ($field['units'] ?? ''));
            $cleanValues = [];
            foreach ($values as $value) {
                $text = trim((string) $value);
                if ($text !== '') {
                    $cleanValues[] = $text;
                }
            }
            if ($fieldTitle === '' || !$cleanValues) {
                continue;
            }
            $specs[] = [
                'id' => isset($field['id']) ? $field['id'] : null,
                'title' => $fieldTitle,
                'units' => $units,
                'value' => $cleanValues,
            ];
            $full = $units !== '' ? implode(', ', $cleanValues) . ' ' . $units : implode(', ', $cleanValues);
            if ($dutyCycle === '' && preg_match('/ток|производительн/ui', $fieldTitle)) {
                $dutyCycle = mb_substr($full, 0, 40, 'UTF-8');
            }
            if ($inputVoltage === '' && preg_match('/напряжен|питание/ui', $fieldTitle)) {
                $inputVoltage = mb_substr($full, 0, 40, 'UTF-8');
            }
        }
    }

    $documents = [];
    if (!empty($detail['documents']) && is_array($detail['documents'])) {
        foreach ($detail['documents'] as $doc) {
            $url = trim((string) ($doc['url'] ?? ''));
            $docTitle = trim((string) ($doc['title'] ?? ''));
            if ($url === '' || $docTitle === '') {
                continue;
            }
            $documents[] = [
                'title' => $docTitle,
                'url' => $url,
                'category' => trim((string) ($doc['category'] ?? '')),
            ];
        }
    }

    $labels = [];
    if (!empty($detail['labels']) && is_array($detail['labels'])) {
        foreach ($detail['labels'] as $label) {
            $text = trim((string) $label);
            if ($text !== '') {
                $labels[] = $text;
            }
        }
    }

    $type = '';
    $blob = mb_strtolower($title . ' ' . $categoryName, 'UTF-8');
    if (preg_match('/tig|аргон/u', $blob)) {
        $type = 'TIG';
    } elseif (preg_match('/mig|mag|полуавтомат/u', $blob)) {
        $type = 'MIG/MAG';
    } elseif (preg_match('/mma|инвертор/u', $blob)) {
        $type = 'MMA';
    } elseif (preg_match('/плазм|резк/u', $blob)) {
        $type = 'Плазменная резка';
    } else {
        $type = 'Аксессуар';
    }

    return [
        'id' => svarog_product_id($num),
        'name' => $title,
        'description' => $description,
        'including' => $including,
        'brand' => 'Сварог',
        'type' => $type,
        'dutyCycle' => $dutyCycle,
        'inputVoltage' => $inputVoltage,
        'availability' => $inStock ? 'В наличии' : 'Нет в наличии',
        'price' => $price > 0 ? $price : null,
        'image' => $images[0] ?? '',
        'images' => $images,
        'category' => $siteCategory,
        'subcategory' => $categoryName,
        'isCustom' => true,
        'specs' => $specs,
        'documents' => $documents,
        'labels' => $labels,
        'warranty' => array_key_exists('warranty', $detail) ? $detail['warranty'] : null,
        'sourceFeed' => [
            'feedId' => $feedId,
            'format' => 'svarog-api',
            'offerId' => $num,
            'sku' => $num,
            'url' => (string) ($detail['url'] ?? ''),
            'shopName' => 'Сварог',
            'shopUrl' => 'https://svarog-rf.ru',
            'categoryId' => svarog_slugify($categoryName),
            'categoryPath' => $categoryName,
            'rr_price' => $price > 0 ? $price : null,
            'ma_price' => isset($detail['ma_price']) ? (int) $detail['ma_price'] : (isset($row['ma_price']) ? (int) $row['ma_price'] : null),
            'importedAt' => gmdate('c'),
            'syncedAt' => gmdate('c'),
        ],
    ];
}

$emptyCatalog = [
    'categories' => [],
    'products' => [],
    'hiddenCategoryIds' => [],
    'hiddenSubcategories' => new stdClass(),
    'catalogFeeds' => [],
    'updatedAt' => null,
];

$catalog = svarog_load_json($CATALOG_FILE, $emptyCatalog);
if (!isset($catalog['products']) || !is_array($catalog['products'])) {
    $catalog['products'] = [];
}
if (!isset($catalog['catalogFeeds']) || !is_array($catalog['catalogFeeds'])) {
    $catalog['catalogFeeds'] = [];
}

$state = svarog_load_json($STATE_FILE, [
    'lastPriceSyncAt' => null,
    'lastFullSyncAt' => null,
    'queue' => [],
    'queueOffset' => 0,
]);

if ($action === 'prices') {
    $last = isset($state['lastPriceSyncAt']) ? strtotime($state['lastPriceSyncAt']) : 0;
    if (!$force && $last && (time() - $last) < $MIN_PRICE_INTERVAL_SEC) {
        echo json_encode([
            'ok' => true,
            'skipped' => true,
            'reason' => 'recently_synced',
            'lastPriceSyncAt' => $state['lastPriceSyncAt'],
            'updated' => 0,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    list($status, $body) = svarog_http_get('https://svarog-rf.ru/api/pricelist');
    if ($status < 200 || $status >= 300 || $body === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Не удалось получить прайс Сварог.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pricelist = json_decode($body, true);
    if (!is_array($pricelist)) {
        http_response_code(502);
        echo json_encode(['error' => 'Прайс Сварог имеет неверный формат.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $byNum = [];
    foreach ($pricelist as $row) {
        if (!empty($row['num'])) {
            $byNum[(string) $row['num']] = $row;
        }
    }

    $updated = 0;
    $missing = 0;
    foreach ($catalog['products'] as &$product) {
        $feedId = $product['sourceFeed']['feedId'] ?? '';
        $offerId = $product['sourceFeed']['offerId'] ?? ($product['sourceFeed']['sku'] ?? '');
        if ($feedId !== $FEED_ID || $offerId === '') {
            continue;
        }
        if (!isset($byNum[$offerId])) {
            $missing += 1;
            continue;
        }
        $row = $byNum[$offerId];
        $nextPrice = isset($row['rr_price']) ? (int) $row['rr_price'] : 0;
        $nextAvailability = !empty($row['in_stock']) ? 'В наличии' : 'Нет в наличии';
        $currentPrice = isset($product['price']) ? (int) $product['price'] : 0;
        $changed = false;
        if (($nextPrice > 0 ? $nextPrice : null) !== ($currentPrice > 0 ? $currentPrice : null)) {
            $product['price'] = $nextPrice > 0 ? $nextPrice : null;
            $changed = true;
        }
        if (($product['availability'] ?? '') !== $nextAvailability) {
            $product['availability'] = $nextAvailability;
            $changed = true;
        }
        if ($changed) {
            $updated += 1;
            if (!isset($product['sourceFeed']) || !is_array($product['sourceFeed'])) {
                $product['sourceFeed'] = [];
            }
            $product['sourceFeed']['rr_price'] = $nextPrice > 0 ? $nextPrice : null;
            $product['sourceFeed']['ma_price'] = isset($row['ma_price']) ? (int) $row['ma_price'] : null;
            $product['sourceFeed']['syncedAt'] = gmdate('c');
        }
    }
    unset($product);

    // NEVER force feed-svarog products back into category `svarog` here.
    // Admin may place consumables into components / other sections; price sync
    // must only update price + availability.

    $state['lastPriceSyncAt'] = gmdate('c');
    // Do NOT bump catalog updatedAt — that discards admin drafts / breaks Apply confirm.
    $catalog['pricesUpdatedAt'] = gmdate('c');

    if ($updated > 0) {
        if (!svarog_save_json($CATALOG_FILE, $catalog)) {
            http_response_code(500);
            echo json_encode(['error' => 'Не удалось сохранить каталог.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    svarog_save_json($STATE_FILE, $state);

    echo json_encode([
        'ok' => true,
        'action' => 'prices',
        'updated' => $updated,
        'healedCategory' => 0,
        'missing' => $missing,
        'pricelist' => count($pricelist),
        'products' => count($catalog['products']),
        'updatedAt' => $catalog['updatedAt'] ?? null,
        'pricesUpdatedAt' => $catalog['pricesUpdatedAt'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'full') {
    // Build / refresh queue from pricelist
    $needQueue = empty($state['queue']) || !empty($_GET['reset']);
    if ($needQueue) {
        list($status, $body) = svarog_http_get('https://svarog-rf.ru/api/pricelist');
        if ($status < 200 || $status >= 300 || $body === false) {
            http_response_code(502);
            echo json_encode(['error' => 'Не удалось получить прайс Сварог.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $pricelist = json_decode($body, true);
        if (!is_array($pricelist)) {
            http_response_code(502);
            echo json_encode(['error' => 'Прайс Сварог имеет неверный формат.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $state['queue'] = [];
        foreach ($pricelist as $row) {
            if (!empty($row['num'])) {
                $state['queue'][] = $row;
            }
        }
        $state['queueOffset'] = 0;
    }

    $queue = $state['queue'];
    $offset = (int) ($state['queueOffset'] ?? 0);
    $slice = array_slice($queue, $offset, $batch);
    $added = 0;
    $refreshed = 0;
    $skipped = 0;

    $byId = [];
    foreach ($catalog['products'] as $index => $product) {
        if (!empty($product['id'])) {
            $byId[$product['id']] = $index;
        }
    }

    foreach ($slice as $row) {
        $num = (string) $row['num'];
        list($status, $body) = svarog_http_get('https://svarog-rf.ru/api/products/' . rawurlencode($num));
        if ($status === 404 || $body === false) {
            $skipped += 1;
            continue;
        }
        if ($status < 200 || $status >= 300) {
            $skipped += 1;
            continue;
        }
        $detail = json_decode($body, true);
        if (!is_array($detail) || empty($detail['title']) || !empty($detail['outdated'])) {
            $skipped += 1;
            continue;
        }

        $mapped = svarog_map_detail_to_product($detail, $row, $FEED_ID);
        if (!$mapped) {
            $skipped += 1;
            continue;
        }

        // Existing products: never move placements / structure. Refresh content + prices only.
        if (isset($byId[$mapped['id']])) {
            $prev = $catalog['products'][$byId[$mapped['id']]];
            $prevCategory = trim((string) ($prev['category'] ?? ''));
            $prevSub = trim((string) ($prev['subcategory'] ?? ''));

            $mapped['category'] = $prevCategory !== '' ? $prevCategory : 'svarog';
            $mapped['subcategory'] = $prevSub !== '' ? $prevSub : (string) ($mapped['subcategory'] ?? '');
            $mapped['placementLocked'] = true;
            if (!empty($prev['videos'])) {
                $mapped['videos'] = $prev['videos'];
            }
            if (!empty($prev['isCustom'])) {
                $mapped['isCustom'] = true;
            }

            // Do not mutate category tree for already-placed products.
            $catalog['products'][$byId[$mapped['id']]] = $mapped;
            $refreshed += 1;
            continue;
        }

        // Новинки не выкладываем на сайт сами: они ждут, пока сотрудник
        // разложит их по разделам. Подсказку поставщика сохраняем рядом.
        if (!isset($catalog['pendingProducts']) || !is_array($catalog['pendingProducts'])) {
            $catalog['pendingProducts'] = [];
        }
        $alreadyPending = false;
        foreach ($catalog['pendingProducts'] as $waiting) {
            if ((string) ($waiting['id'] ?? '') === (string) $mapped['id']) {
                $alreadyPending = true;
                break;
            }
        }
        if ($alreadyPending) {
            continue;
        }

        $suggested = trim((string) ($mapped['subcategory'] ?? ''));
        $mapped['category'] = '';
        $mapped['subcategory'] = '';
        $mapped['suggestedCategory'] = 'svarog';
        $mapped['suggestedSubcategory'] = $suggested !== '' ? $suggested : 'Сварог';
        $mapped['pendingFeedId'] = $FEED_ID;
        $mapped['pendingSince'] = gmdate('c');
        $catalog['pendingProducts'][] = $mapped;
        $added += 1;
    }

    $state['queueOffset'] = $offset + count($slice);
    $remaining = max(0, count($queue) - $state['queueOffset']);
    $done = $remaining === 0;

    if ($done) {
        $state['lastFullSyncAt'] = gmdate('c');
        $state['queue'] = [];
        $state['queueOffset'] = 0;

        // Upsert feed record
        $feedFound = false;
        foreach ($catalog['catalogFeeds'] as &$feed) {
            if (($feed['id'] ?? '') === $FEED_ID) {
                $feed['importedAt'] = gmdate('c');
                $feed['importedCount'] = 0;
                foreach ($catalog['products'] as $product) {
                    if (($product['sourceFeed']['feedId'] ?? '') === $FEED_ID) {
                        $feed['importedCount'] += 1;
                    }
                }
                $feed['format'] = 'svarog-api';
                $feed['shopName'] = 'Сварог';
                $feed['shopUrl'] = 'https://svarog-rf.ru';
                $feedFound = true;
                break;
            }
        }
        unset($feed);
        if (!$feedFound) {
            $count = 0;
            foreach ($catalog['products'] as $product) {
                if (($product['sourceFeed']['feedId'] ?? '') === $FEED_ID) {
                    $count += 1;
                }
            }
            array_unshift($catalog['catalogFeeds'], [
                'id' => $FEED_ID,
                'format' => 'svarog-api',
                'fileName' => 'svarog-api',
                'shopName' => 'Сварог',
                'shopUrl' => 'https://svarog-rf.ru',
                'importedAt' => gmdate('c'),
                'stats' => ['offers' => $count, 'categories' => 0, 'withPrice' => $count],
                'categoryMappings' => new stdClass(),
                'importedCount' => $count,
            ]);
        }
    }

    // Do NOT bump catalog updatedAt — admin Apply/drafts use it as the publish epoch.
    $catalog['svarogSyncedAt'] = gmdate('c');

    if (!svarog_save_json($CATALOG_FILE, $catalog) || !svarog_save_json($STATE_FILE, $state)) {
        http_response_code(500);
        echo json_encode(['error' => 'Не удалось сохранить каталог / состояние синка.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'action' => 'full',
        'added' => $added,
        'refreshed' => $refreshed,
        'skipped' => $skipped,
        'processed' => count($slice),
        'offset' => $state['queueOffset'],
        'remaining' => $remaining,
        'done' => $done,
        'products' => count($catalog['products']),
        'updatedAt' => $catalog['updatedAt'] ?? null,
        'svarogSyncedAt' => $catalog['svarogSyncedAt'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'status') {
    $svarogCount = 0;
    foreach ($catalog['products'] as $product) {
        if (($product['sourceFeed']['feedId'] ?? '') === $FEED_ID) {
            $svarogCount += 1;
        }
    }
    echo json_encode([
        'ok' => true,
        'products' => count($catalog['products']),
        'svarogProducts' => $svarogCount,
        'lastPriceSyncAt' => $state['lastPriceSyncAt'] ?? null,
        'lastFullSyncAt' => $state['lastFullSyncAt'] ?? null,
        'queueRemaining' => max(0, count($state['queue'] ?? []) - (int) ($state['queueOffset'] ?? 0)),
        'updatedAt' => $catalog['updatedAt'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Укажите action=prices|full|status.'], JSON_UNESCAPED_UNICODE);
