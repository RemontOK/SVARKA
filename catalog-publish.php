<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Admin-Password');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/admin-auth.php';

// Root of site — not /data/ (often blocked on shared hosting)
$dataFile = __DIR__ . '/runtime-catalog.json';

$emptyCatalog = [
    'categories' => [],
    'products' => [],
    'hiddenCategoryIds' => [],
    'hiddenProductIds' => [],
    'hiddenSubcategories' => new stdClass(),
    'retiredSubcategoryNames' => new stdClass(),
    'subcategoryRenameMap' => new stdClass(),
    'catalogFeeds' => [],
    'pendingProducts' => [],
    'categoryOrder' => [],
    'categoryFilters' => new stdClass(),
    'updatedAt' => null,
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!is_file($dataFile)) {
        echo json_encode($emptyCatalog, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Админка открылась — заодно проверяем, давно ли снимали копию.
    // Дёшево и даёт точки отката даже без публикаций.
    require_once __DIR__ . '/catalog-guard.php';
    asmbot_guard_backup_if_stale($dataFile);

    $raw = @file_get_contents($dataFile);
    if ($raw === false || $raw === '') {
        echo json_encode($emptyCatalog, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $accept = isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? (string) $_SERVER['HTTP_ACCEPT_ENCODING'] : '';
    if (function_exists('gzencode') && stripos($accept, 'gzip') !== false) {
        header('Content-Encoding: gzip');
        header('Vary: Accept-Encoding');
        echo gzencode($raw, 6);
        exit;
    }

    echo $raw;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не поддерживается.'], JSON_UNESCAPED_UNICODE);
    exit;
}

asmbot_require_admin();

$rawBody = file_get_contents('php://input');
if ($rawBody === false || $rawBody === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Пустое тело запроса.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($rawBody) > 40 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'Слишком большой каталог (больше 40 МБ).'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Некорректный JSON.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = isset($payload['action']) ? (string) $payload['action'] : 'save';

// Site catalog may only change after explicit admin confirmation («Применить»).
$confirmedByAdmin = !empty($payload['confirmedByAdmin']);
if (!$confirmedByAdmin) {
    http_response_code(403);
    echo json_encode([
        'error' => 'Публикация без подтверждения администратора запрещена. Нажмите «Применить» в админке.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Reject stale overwrites when admin edited from an older snapshot.
if (isset($payload['baseUpdatedAt']) && is_string($payload['baseUpdatedAt']) && $payload['baseUpdatedAt'] !== '') {
    if (is_file($dataFile)) {
        $currentRaw = @file_get_contents($dataFile);
        $current = is_string($currentRaw) ? json_decode($currentRaw, true) : null;
        $currentUpdatedAt = is_array($current) && isset($current['updatedAt'])
            ? (string) $current['updatedAt']
            : '';
        if ($currentUpdatedAt !== '' && $currentUpdatedAt !== (string) $payload['baseUpdatedAt']) {
            http_response_code(409);
            echo json_encode([
                'error' => 'Каталог на сайте уже изменился. Перезагрузите админку и примените черновик снова.',
                'serverUpdatedAt' => $currentUpdatedAt,
                'baseUpdatedAt' => (string) $payload['baseUpdatedAt'],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

require_once __DIR__ . '/catalog-guard.php';

// Каталог настроен вручную — сервер не даёт потерять разделы по ошибке.
// Пропускаем изменение только после явного подтверждения из админки,
// где сотруднику показан точный список потерь.
$allowStructureChange = !empty($payload['allowStructureChange']);

if ($action === 'clear') {
    $catalog = $emptyCatalog;
    $catalog['updatedAt'] = gmdate('c');
    $catalog['hiddenSubcategories'] = new stdClass();
    $catalog['retiredSubcategoryNames'] = new stdClass();
    $catalog['subcategoryRenameMap'] = new stdClass();
} else {
    $source = isset($payload['catalog']) && is_array($payload['catalog']) ? $payload['catalog'] : $payload;

    $hiddenSubs = new stdClass();
    if (isset($source['hiddenSubcategories']) && is_array($source['hiddenSubcategories'])) {
        foreach ($source['hiddenSubcategories'] as $categoryId => $names) {
            $hiddenSubs->{$categoryId} = is_array($names) ? array_values($names) : [];
        }
    }

    $retiredSubs = new stdClass();
    if (isset($source['retiredSubcategoryNames']) && is_array($source['retiredSubcategoryNames'])) {
        foreach ($source['retiredSubcategoryNames'] as $categoryId => $names) {
            $retiredSubs->{$categoryId} = is_array($names) ? array_values($names) : [];
        }
    }

    $renameMap = new stdClass();
    if (isset($source['subcategoryRenameMap']) && is_array($source['subcategoryRenameMap'])) {
        foreach ($source['subcategoryRenameMap'] as $categoryId => $entries) {
            if (!is_array($entries)) continue;
            $bucket = new stdClass();
            foreach ($entries as $fromKey => $toName) {
                if (!is_string($fromKey) || $fromKey === '') continue;
                if (!is_string($toName) && !is_numeric($toName)) continue;
                $bucket->{$fromKey} = (string) $toName;
            }
            $renameMap->{$categoryId} = $bucket;
        }
    }

    $categoryFilters = new stdClass();
    if (isset($source['categoryFilters']) && is_array($source['categoryFilters'])) {
        foreach ($source['categoryFilters'] as $scopeKey => $filters) {
            if (!is_string($scopeKey) || $scopeKey === '' || !is_array($filters)) continue;
            $list = [];
            foreach ($filters as $filter) {
                if (!is_array($filter)) continue;
                $list[] = $filter;
            }
            if (count($list) > 0) {
                $categoryFilters->{$scopeKey} = array_values($list);
            }
        }
    }

    $catalog = [
        'categories' => isset($source['categories']) && is_array($source['categories']) ? $source['categories'] : [],
        'products' => isset($source['products']) && is_array($source['products']) ? $source['products'] : [],
        'hiddenCategoryIds' => isset($source['hiddenCategoryIds']) && is_array($source['hiddenCategoryIds'])
            ? array_values($source['hiddenCategoryIds'])
            : [],
        'hiddenProductIds' => isset($source['hiddenProductIds']) && is_array($source['hiddenProductIds'])
            ? array_values(array_map('strval', $source['hiddenProductIds']))
            : [],
        'hiddenSubcategories' => $hiddenSubs,
        'retiredSubcategoryNames' => $retiredSubs,
        'subcategoryRenameMap' => $renameMap,
        'catalogFeeds' => isset($source['catalogFeeds']) && is_array($source['catalogFeeds'])
            ? $source['catalogFeeds']
            : [],
        // Новые товары из фидов: ждут распределения, на сайте не показываются.
        'pendingProducts' => isset($source['pendingProducts']) && is_array($source['pendingProducts'])
            ? array_values($source['pendingProducts'])
            : [],
        'categoryOrder' => isset($source['categoryOrder']) && is_array($source['categoryOrder'])
            ? array_values(array_map('strval', $source['categoryOrder']))
            : [],
        'categoryFilters' => $categoryFilters,
        'updatedAt' => gmdate('c'),
    ];
}

// Сверяем с тем, что сейчас на сайте, и не пускаем незаявленные потери.
$currentCatalog = null;
if (is_file($dataFile)) {
    $currentRaw = @file_get_contents($dataFile);
    $decoded = is_string($currentRaw) && $currentRaw !== '' ? json_decode($currentRaw, true) : null;
    if (is_array($decoded)) {
        $currentCatalog = $decoded;
    }
}

if ($currentCatalog !== null) {
    $diff = asmbot_guard_diff($currentCatalog, $catalog);
    $destructive = $action === 'clear' || asmbot_guard_is_destructive($diff);

    if ($destructive && !$allowStructureChange) {
        asmbot_guard_log($dataFile, [
            'event' => 'blocked',
            'action' => $action,
            'changes' => asmbot_guard_describe($diff),
        ]);
        http_response_code(409);
        echo json_encode([
            'error' => 'Публикация остановлена: она удаляет части каталога.',
            'structureGuard' => [
                'action' => $action,
                'summary' => asmbot_guard_describe($diff),
                'removedSections' => $diff['removedSections'],
                'emptiedGroups' => $diff['emptiedGroups'],
                'unnested' => $diff['unnested'],
                'removedProducts' => $diff['removedProducts'],
                'movedProducts' => $diff['movedProducts'],
                'moveSamples' => $diff['moveSamples'],
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($destructive) {
        asmbot_guard_log($dataFile, [
            'event' => 'confirmed',
            'action' => $action,
            'changes' => asmbot_guard_describe($diff),
        ]);
    }
}

// Снимок предыдущего состояния — откат возможен всегда.
$backupName = asmbot_guard_backup($dataFile);

$json = json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Не удалось закодировать каталог.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tmpFile = $dataFile . '.tmp';
if (@file_put_contents($tmpFile, $json, LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Не удалось записать временный файл каталога.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!@rename($tmpFile, $dataFile)) {
    // Some hosts block rename across FS — fallback to copy
    if (@file_put_contents($dataFile, $json, LOCK_EX) === false) {
        @unlink($tmpFile);
        http_response_code(500);
        echo json_encode(['error' => 'Не удалось сохранить runtime-catalog.json. Проверьте права на запись.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    @unlink($tmpFile);
}

@chmod($dataFile, 0644);

require_once __DIR__ . '/catalog-yml-lib.php';
$ymlBuilt = asmbot_write_yml_file(__DIR__ . '/catalog.yml', $catalog);

// Витринная копия каталога и подробности по товарам — их читает сайт.
require_once __DIR__ . '/catalog-split-lib.php';
$split = asmbot_write_split_catalog($catalog, __DIR__);

echo json_encode([
    'ok' => true,
    'products' => count($catalog['products']),
    'categories' => count($catalog['categories']),
    'hiddenCategories' => count($catalog['hiddenCategoryIds']),
    'hiddenProducts' => count($catalog['hiddenProductIds']),
    'ymlOffers' => $ymlBuilt ? $ymlBuilt['offers'] : null,
    'ymlCategories' => $ymlBuilt ? $ymlBuilt['categories'] : null,
    'updatedAt' => $catalog['updatedAt'],
    'liteBytes' => $split ? $split['lite'] : null,
    'detailFiles' => $split ? $split['details'] : null,
    'backup' => $backupName,
], JSON_UNESCAPED_UNICODE);
