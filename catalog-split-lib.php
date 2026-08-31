<?php

/**
 * Разделение каталога на витринную и подробную части.
 *
 * Полный каталог весит около 30 МБ, и половину этого веса дают описания,
 * комплектации и документы — то, что нужно ровно на одной странице, странице
 * товара. Списки и фильтры этих полей не касаются, поэтому посетителю они
 * приезжали впустую при каждом первом заходе.
 *
 * Витрина берёт catalog-lite.json, а страница товара догружает подробности
 * одним небольшим файлом из catalog-details/. Полный runtime-catalog.json
 * остаётся как есть: это рабочие данные админки и обновления из выгрузок.
 */

/** Полей, которые нужны только на карточке товара. */
if (!defined('ASMBOT_DETAIL_FIELDS')) {
    // specs и images — самые тяжёлые поля каталога: характеристики занимали
    // 6 МБ из 13, галереи ещё 1,7. Витрине они не нужны — только карточке
    // товара, а она грузит подробности отдельным файлом.
    define('ASMBOT_DETAIL_FIELDS', 'description|including|documents|docPages|specs|images');
}

/** На сколько файлов разложены подробности. */
if (!defined('ASMBOT_DETAIL_BUCKETS')) {
    define('ASMBOT_DETAIL_BUCKETS', 64);
}

if (!function_exists('asmbot_detail_bucket')) {
    /**
     * Номер файла для товара.
     *
     * Считается по сумме байтов идентификатора — так же, как в браузере, иначе
     * страница пойдёт не за тем файлом. Функция должна остаться идентичной
     * `detailBucket` в src/lib/productDetails.js.
     */
    function asmbot_detail_bucket($id)
    {
        $sum = 0;
        $text = (string) $id;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $sum += ord($text[$i]);
        }
        return $sum % ASMBOT_DETAIL_BUCKETS;
    }
}

if (!function_exists('asmbot_product_excerpt')) {
    /**
     * Короткая выжимка описания для карточек в списках.
     *
     * Само описание в витринный файл не попадает, но карточка показывает
     * первую фразу — без неё списки станут беднее, чем были.
     */
    function asmbot_product_excerpt($description)
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $description));
        if ($text === '') {
            return '';
        }

        $parts = preg_split('/(?<=[.!?])\s/u', $text, 2);
        $first = isset($parts[0]) ? trim($parts[0]) : $text;

        if (function_exists('mb_strlen') && mb_strlen($first, 'UTF-8') > 160) {
            return rtrim(mb_substr($first, 0, 160, 'UTF-8')) . '…';
        }
        return $first;
    }
}

if (!function_exists('asmbot_spec_title_key')) {
    /** Тот же ключ заголовка характеристики, что и normalizeSpecTitleKey в JS. */
    function asmbot_spec_title_key($value)
    {
        $text = mb_strtolower(trim((string) $value), 'UTF-8');
        $text = str_replace('ё', 'е', $text);
        return preg_replace('/\s+/u', ' ', $text);
    }
}

if (!function_exists('asmbot_write_split_catalog')) {
    /**
     * Пишет витринный каталог и файлы подробностей.
     *
     * @return array{lite:int,details:int,products:int}|null
     */
    function asmbot_write_split_catalog(array $catalog, $dir)
    {
        $products = isset($catalog['products']) && is_array($catalog['products'])
            ? $catalog['products']
            : [];

        $detailFields = explode('|', ASMBOT_DETAIL_FIELDS);

        // Часть характеристик витрине всё же нужна: по ним построены фильтры
        // разделов и по ним же ищут по артикулу. Собираем список заголовков,
        // которые администратор включил в фильтры, — остальное уезжает в
        // подробности.
        $filterSpecTitles = [];
        if (isset($catalog['categoryFilters']) && is_array($catalog['categoryFilters'])) {
            foreach ($catalog['categoryFilters'] as $entries) {
                if (!is_array($entries)) {
                    continue;
                }
                foreach ($entries as $entry) {
                    if (!is_array($entry) || !isset($entry['kind']) || $entry['kind'] !== 'spec') {
                        continue;
                    }
                    $key = isset($entry['key']) ? asmbot_spec_title_key($entry['key']) : '';
                    if ($key !== '') {
                        $filterSpecTitles[$key] = true;
                    }
                }
            }
        }

        $buckets = [];
        $lite = $catalog;
        $liteProducts = [];

        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }

            $id = isset($product['id']) ? (string) $product['id'] : '';
            $details = [];
            foreach ($detailFields as $field) {
                if (isset($product[$field]) && $product[$field] !== '' && $product[$field] !== []) {
                    $details[$field] = $product[$field];
                }
                unset($product[$field]);
            }

            if ($id !== '' && $details) {
                $buckets[asmbot_detail_bucket($id)][$id] = $details;
            }

            // Возвращаем в витрину только те характеристики, что реально
            // работают на списке: значения фильтров и артикульные коды.
            if (isset($details['specs']) && is_array($details['specs'])) {
                $keep = [];
                foreach ($details['specs'] as $spec) {
                    if (!is_array($spec) || !isset($spec['title'])) {
                        continue;
                    }
                    $title = (string) $spec['title'];
                    $key = asmbot_spec_title_key($title);
                    $isFilter = $key !== '' && isset($filterSpecTitles[$key]);
                    $isCode = (bool) preg_match(
                        '/(артикул|vendor\s*code|sku|код\s*товар|код\s*номенклатур)/ui',
                        $title
                    );
                    if ($isFilter || $isCode) {
                        $keep[] = $spec;
                    }
                }
                if ($keep) {
                    $product['specs'] = $keep;
                }
            }

            // Одна запасная фотография на случай, когда основная у поставщика
            // отдаёт 404: карточка списка перебирает адреса по очереди.
            if (isset($details['images']) && is_array($details['images'])) {
                foreach ($details['images'] as $candidate) {
                    $url = is_string($candidate) ? trim($candidate) : '';
                    if ($url === '' || (isset($product['image']) && $url === $product['image'])) {
                        continue;
                    }
                    $product['altImage'] = $url;
                    break;
                }
            }

            if (isset($details['description'])) {
                $excerpt = asmbot_product_excerpt($details['description']);
                if ($excerpt !== '') {
                    $product['excerpt'] = $excerpt;
                }
            }

            // Из служебных данных выгрузки витрине нужен только артикул:
            // его показывает карточка товара в списке.
            if (isset($product['sourceFeed']) && is_array($product['sourceFeed'])) {
                $sku = '';
                if (isset($product['sourceFeed']['sku']) && $product['sourceFeed']['sku'] !== '') {
                    $sku = (string) $product['sourceFeed']['sku'];
                } elseif (isset($product['sourceFeed']['offerId'])) {
                    $sku = (string) $product['sourceFeed']['offerId'];
                }
                $product['sourceFeed'] = $sku !== '' ? ['sku' => $sku] : new stdClass();
            }

            $liteProducts[] = $product;
        }

        $lite['products'] = $liteProducts;
        // Очередь разбора — данные админки, витрине она не нужна.
        $lite['pendingProducts'] = [];
        // Метка урезанной копии. Публикация такой обратно стёрла бы описания
        // и документы у всех товаров, поэтому админка её отвергает.
        $lite['lite'] = true;

        $liteJson = json_encode($lite, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($liteJson === false) {
            return null;
        }

        $litePath = $dir . '/catalog-lite.json';
        if (@file_put_contents($litePath . '.tmp', $liteJson, LOCK_EX) === false) {
            return null;
        }
        if (!@rename($litePath . '.tmp', $litePath)) {
            @file_put_contents($litePath, $liteJson, LOCK_EX);
            @unlink($litePath . '.tmp');
        }
        @chmod($litePath, 0644);

        $detailsDir = $dir . '/catalog-details';
        if (!is_dir($detailsDir)) {
            @mkdir($detailsDir, 0755, true);
        }

        $written = 0;
        for ($i = 0; $i < ASMBOT_DETAIL_BUCKETS; $i++) {
            $payload = isset($buckets[$i]) ? $buckets[$i] : new stdClass();
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                continue;
            }
            $path = $detailsDir . '/' . $i . '.json';
            if (@file_put_contents($path, $json, LOCK_EX) !== false) {
                @chmod($path, 0644);
                $written++;
            }
        }

        return [
            'lite' => strlen($liteJson),
            'details' => $written,
            'products' => count($liteProducts),
        ];
    }
}
