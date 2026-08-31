<?php
/**
 * Защита структуры каталога.
 *
 * Каталог настраивается вручную и должен оставаться таким, каким его оставил
 * сотрудник. Поэтому сервер сравнивает приходящее дерево с текущим и не даёт
 * молча удалить или расформировать разделы: удаление проходит только после
 * явного подтверждения из админки, где показан точный список потерь.
 *
 * Добавление разделов, переименование и смена порядка проверку не проходят —
 * они ничего не теряют.
 */

if (!function_exists('asmbot_guard_key')) {
    /** Нормализует имя раздела для сравнения. */
    function asmbot_guard_key($name)
    {
        $value = str_replace("\xC2\xA0", ' ', (string) $name);
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = trim($value);
        // Так же, как normalizeCompareKey на фронтенде, иначе ключи
        // переименований не совпадут с именами разделов.
        $value = preg_replace('/\s*0+$/u', '', $value);
        $value = mb_strtolower($value, 'UTF-8');
        return str_replace('ё', 'е', $value);
    }
}

if (!function_exists('asmbot_guard_rename_targets')) {
    /**
     * Карта переименований из админки: старый ключ → новое имя.
     * Переименование — это не потеря, хотя со стороны выглядит как
     * исчезновение одного раздела и появление другого.
     */
    function asmbot_guard_rename_targets($catalog)
    {
        $map = [];
        $source = $catalog['subcategoryRenameMap'] ?? null;
        // После нормализации в catalog-publish.php это stdClass, а не массив.
        if (is_object($source)) {
            $source = (array) $source;
        }
        if (!is_array($source)) {
            return $map;
        }
        foreach ($source as $categoryId => $entries) {
            if (is_object($entries)) {
                $entries = (array) $entries;
            }
            if (!is_array($entries)) {
                continue;
            }
            foreach ($entries as $fromKey => $toName) {
                if (!is_string($fromKey) || $fromKey === '') {
                    continue;
                }
                $map[$categoryId . "\x00" . asmbot_guard_key($fromKey)] = asmbot_guard_key($toName);
            }
        }
        return $map;
    }
}

if (!function_exists('asmbot_guard_index_tree')) {
    /**
     * Разворачивает дерево разделов в плоскую карта «ключ → сведения».
     * Ключ включает id категории, чтобы одинаковые имена в разных категориях
     * не путались между собой.
     */
    function asmbot_guard_index_tree($categories)
    {
        $index = [];
        if (!is_array($categories)) {
            return $index;
        }

        $walk = function ($nodes, $categoryId, $parentName) use (&$walk, &$index) {
            if (!is_array($nodes)) {
                return;
            }
            foreach ($nodes as $node) {
                if (!is_array($node)) {
                    continue;
                }
                $name = isset($node['name']) ? (string) $node['name'] : '';
                if (trim($name) === '') {
                    continue;
                }
                $children = isset($node['children']) && is_array($node['children']) ? $node['children'] : null;
                $index[$categoryId . "\x00" . asmbot_guard_key($name)] = [
                    'categoryId' => $categoryId,
                    'name' => $name,
                    'parent' => $parentName,
                    'childCount' => $children === null ? 0 : count($children),
                ];
                if ($children !== null) {
                    $walk($children, $categoryId, $name);
                }
            }
        };

        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }
            $categoryId = isset($category['id']) ? (string) $category['id'] : '';
            $subs = isset($category['subcategories']) && is_array($category['subcategories'])
                ? $category['subcategories']
                : [];
            $walk($subs, $categoryId, '');
        }

        return $index;
    }
}

if (!function_exists('asmbot_guard_index_products')) {
    /** Карта «id товара → категория + раздел» для поиска массовых переносов. */
    function asmbot_guard_index_products($products)
    {
        $index = [];
        if (!is_array($products)) {
            return $index;
        }
        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }
            $id = isset($product['id']) ? (string) $product['id'] : '';
            if ($id === '') {
                continue;
            }
            // В разных ветках кода категория лежит то в categoryId, то в category.
            $categoryId = isset($product['categoryId']) ? (string) $product['categoryId'] : '';
            if ($categoryId === '' && isset($product['category'])) {
                $categoryId = (string) $product['category'];
            }
            $index[$id] = [
                'categoryId' => $categoryId,
                'subcategory' => isset($product['subcategory']) ? (string) $product['subcategory'] : '',
            ];
        }
        return $index;
    }
}

if (!function_exists('asmbot_guard_diff')) {
    /**
     * Считает потери между текущим и приходящим каталогом.
     * Возвращает списки исчезнувших разделов, расформированных групп,
     * пропавших и переброшенных товаров.
     */
    function asmbot_guard_diff($current, $incoming)
    {
        $currentTree = asmbot_guard_index_tree($current['categories'] ?? []);
        $incomingTree = asmbot_guard_index_tree($incoming['categories'] ?? []);

        $removedSections = [];
        $renamedSections = [];
        $emptiedGroups = [];
        $unnested = [];

        $renames = asmbot_guard_rename_targets($incoming);

        // Запасная карта без привязки к категории.
        $renamesByName = [];
        foreach ($renames as $composite => $newKey) {
            $parts = explode("\x00", $composite, 2);
            if (isset($parts[1]) && $parts[1] !== '') {
                $renamesByName[$parts[1]] = $newKey;
            }
        }

        foreach ($currentTree as $key => $before) {
            if (!isset($incomingTree[$key])) {
                // Раздел переименовали: старого имени нет, но новое на месте.
                $newKey = $renames[$key] ?? '';
                if ($newKey !== '' && isset($incomingTree[$before['categoryId'] . "\x00" . $newKey])) {
                    $renamedSections[] = [
                        'category' => $before['categoryId'],
                        'from' => $before['name'],
                        'to' => $incomingTree[$before['categoryId'] . "\x00" . $newKey]['name'],
                    ];
                    continue;
                }

                $removedSections[] = [
                    'category' => $before['categoryId'],
                    'name' => $before['name'],
                    'parent' => $before['parent'],
                ];
                continue;
            }

            $after = $incomingTree[$key];

            // Группа была наполнена, а приходит пустой — содержимое теряется.
            if ($before['childCount'] > 0 && $after['childCount'] === 0) {
                $emptiedGroups[] = [
                    'category' => $before['categoryId'],
                    'name' => $before['name'],
                    'lost' => $before['childCount'],
                ];
            }

            // Раздел лежал внутри группы, а приходит в корне категории.
            if ($before['parent'] !== '' && $after['parent'] === '') {
                $unnested[] = [
                    'category' => $before['categoryId'],
                    'name' => $before['name'],
                    'parent' => $before['parent'],
                ];
            }
        }

        $currentProducts = asmbot_guard_index_products($current['products'] ?? []);
        $incomingProducts = asmbot_guard_index_products($incoming['products'] ?? []);

        $removedProducts = 0;
        $movedProducts = 0;
        $moveSamples = [];

        foreach ($currentProducts as $id => $before) {
            if (!isset($incomingProducts[$id])) {
                $removedProducts++;
                continue;
            }
            $after = $incomingProducts[$id];
            $sameCategory = $before['categoryId'] === $after['categoryId'];
            $beforeKey = asmbot_guard_key($before['subcategory']);
            $afterKey = asmbot_guard_key($after['subcategory']);
            // Переименование раздела переписывает подкатегорию у товаров —
            // это не перенос, товар остался на своём месте.
            // Категория у товара может быть не заполнена — тогда ищем
            // переименование по одному только имени раздела.
            $renamedTo = $renames[$before['categoryId'] . "\x00" . $beforeKey]
                ?? ($renamesByName[$beforeKey] ?? '');
            $sameSection = $beforeKey === $afterKey || ($renamedTo !== '' && $renamedTo === $afterKey);
            if (!$sameCategory || !$sameSection) {
                $movedProducts++;
                if (count($moveSamples) < 10) {
                    $moveSamples[] = [
                        'id' => $id,
                        'from' => trim($before['categoryId'] . ' / ' . $before['subcategory']),
                        'to' => trim($after['categoryId'] . ' / ' . $after['subcategory']),
                    ];
                }
            }
        }

        return [
            'removedSections' => $removedSections,
            'renamedSections' => $renamedSections,
            'emptiedGroups' => $emptiedGroups,
            'unnested' => $unnested,
            'removedProducts' => $removedProducts,
            'movedProducts' => $movedProducts,
            'moveSamples' => $moveSamples,
        ];
    }
}

if (!function_exists('asmbot_guard_is_destructive')) {
    /**
     * Требуется ли подтверждение. Единичные переносы товаров — обычная
     * работа сотрудника, поэтому для них есть порог; потеря разделов
     * подтверждается всегда.
     */
    function asmbot_guard_is_destructive($diff, $movedThreshold = 50)
    {
        return count($diff['removedSections']) > 0
            || count($diff['emptiedGroups']) > 0
            || count($diff['unnested']) > 0
            || $diff['removedProducts'] > 0
            || $diff['movedProducts'] > $movedThreshold;
    }
}

if (!function_exists('asmbot_guard_describe')) {
    /** Человеческое описание потерь — уходит в админку и в журнал. */
    function asmbot_guard_describe($diff)
    {
        $lines = [];

        foreach ($diff['removedSections'] as $item) {
            $where = $item['parent'] !== '' ? ' (из «' . $item['parent'] . '»)' : '';
            $lines[] = 'удаляется раздел «' . $item['name'] . '»' . $where;
        }
        foreach ($diff['emptiedGroups'] as $item) {
            $lines[] = 'группа «' . $item['name'] . '» теряет вложенные разделы: ' . $item['lost'];
        }
        foreach ($diff['unnested'] as $item) {
            $lines[] = 'раздел «' . $item['name'] . '» вынимается из группы «' . $item['parent'] . '»';
        }
        if ($diff['removedProducts'] > 0) {
            $lines[] = 'пропадает товаров: ' . $diff['removedProducts'];
        }
        if ($diff['movedProducts'] > 0) {
            $lines[] = 'переносится товаров между разделами: ' . $diff['movedProducts'];
        }

        return $lines;
    }
}

if (!function_exists('asmbot_guard_backup_dir')) {
    /** Папка с копиями, закрытая от доступа снаружи. */
    function asmbot_guard_backup_dir($dataFile)
    {
        $dir = dirname($dataFile) . '/catalog-backups';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }

        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
        }

        return $dir;
    }
}

if (!function_exists('asmbot_guard_prune_backups')) {
    /**
     * Многоуровневое хранение: за последние двое суток держим все копии,
     * дальше — по одной на день за месяц. Иначе один активный день
     * вытеснил бы вчерашнее хорошее состояние, ради которого всё и затевалось.
     */
    function asmbot_guard_prune_backups($dir, $recentWindow = 172800, $dailyDays = 30, $hardLimit = 400)
    {
        $files = glob($dir . '/catalog-*.json');
        if (!is_array($files) || count($files) === 0) {
            return;
        }

        rsort($files); // имена начинаются с даты, поэтому свежие идут первыми
        $now = time();
        $keptDays = [];
        $kept = 0;

        foreach ($files as $index => $file) {
            $age = $now - (int) @filemtime($file);

            // Самую свежую копию не удаляем никогда.
            if ($index === 0) {
                $kept++;
                continue;
            }

            if ($age <= $recentWindow && $kept < $hardLimit) {
                $kept++;
                continue;
            }

            if ($age <= $dailyDays * 86400) {
                $day = substr(basename($file), strlen('catalog-'), 8);
                if (!isset($keptDays[$day]) && $kept < $hardLimit) {
                    $keptDays[$day] = true;
                    $kept++;
                    continue;
                }
            }

            @unlink($file);
        }
    }
}

if (!function_exists('asmbot_guard_backup')) {
    /** Снимок каталога перед записью. */
    function asmbot_guard_backup($dataFile)
    {
        if (!is_file($dataFile)) {
            return null;
        }

        $dir = asmbot_guard_backup_dir($dataFile);
        if ($dir === null) {
            return null;
        }

        $target = $dir . '/catalog-' . gmdate('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.json';
        if (!@copy($dataFile, $target)) {
            return null;
        }

        asmbot_guard_prune_backups($dir);

        return basename($target);
    }
}

if (!function_exists('asmbot_guard_backup_if_stale')) {
    /**
     * Снимок «по времени»: если свежей копии давно не было, делаем её,
     * даже когда каталог никто не публиковал. Так между редкими правками
     * всё равно остаются точки отката.
     */
    function asmbot_guard_backup_if_stale($dataFile, $maxAge = 21600)
    {
        if (!is_file($dataFile)) {
            return null;
        }

        $dir = asmbot_guard_backup_dir($dataFile);
        if ($dir === null) {
            return null;
        }

        $files = glob($dir . '/catalog-*.json');
        if (is_array($files) && count($files) > 0) {
            $newest = 0;
            foreach ($files as $file) {
                $newest = max($newest, (int) @filemtime($file));
            }
            if ($newest > 0 && (time() - $newest) < $maxAge) {
                return null;
            }
        }

        return asmbot_guard_backup($dataFile);
    }
}

if (!function_exists('asmbot_guard_log')) {
    /** Журнал изменений каталога — кто и что менял. */
    function asmbot_guard_log($dataFile, $entry)
    {
        $dir = dirname($dataFile) . '/catalog-backups';
        if (!is_dir($dir)) {
            return;
        }
        $entry['at'] = gmdate('c');
        $entry['ip'] = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        @file_put_contents(
            $dir . '/history.log',
            json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}
