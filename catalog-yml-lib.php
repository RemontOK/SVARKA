<?php
/**
 * Build Yandex Market YML from published runtime-catalog.json payload.
 */

if (!defined('ASMBOT_SITE_URL')) {
    define('ASMBOT_SITE_URL', 'https://asmbot.ru');
}

/**
 * Strip characters illegal in XML 1.0 (keeps tab/LF/CR and normal Unicode).
 */
function asmbot_yml_sanitize($value)
{
    $text = (string) $value;
    if ($text === '') {
        return '';
    }
    // Invalid: C0 controls except TAB/LF/CR; also unpaired surrogates / nonchars.
    $cleaned = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text);
    if (!is_string($cleaned)) {
        return '';
    }
    return trim(preg_replace('/[ \t]+/u', ' ', $cleaned) ?? $cleaned);
}

function asmbot_yml_escape($value)
{
    return htmlspecialchars(asmbot_yml_sanitize($value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function asmbot_yml_cdata($value)
{
    $text = asmbot_yml_sanitize($value);
    if ($text === '') {
        return '';
    }
    $safe = str_replace(']]>', ']]]]><![CDATA[>', $text);
    return '<![CDATA[' . $safe . ']]>';
}

/**
 * Идентификатор оффера: только латиница, цифры и разделители.
 *
 * Раньше кириллица просто заменялась подчёркиванием, и у позиций Вектора,
 * где весь идентификатор русский, получались одинаковые строки из
 * подчёркиваний — разные товары приходили с одним id, и площадки такой файл
 * не принимают. Теперь кириллица переводится в латиницу, а совпадения
 * разводятся суффиксом.
 */
function asmbot_yml_offer_id($productId, $offerCount, array &$used)
{
    static $translit = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e',
        'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k',
        'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
        'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    $text = mb_strtolower((string) $productId, 'UTF-8');
    $text = strtr($text, $translit);
    $id = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $text);
    $id = trim((string) $id, '_');

    if ($id === '') {
        $id = 'p' . ($offerCount + 1);
    }

    if (isset($used[$id])) {
        $used[$id] += 1;
        $id .= '-' . $used[$id];
    } else {
        $used[$id] = 1;
    }

    return $id;
}

function asmbot_yml_offer_name(array $product)
{
    $name = asmbot_yml_sanitize(isset($product['name']) ? $product['name'] : '');
    // After stripping broken encoding, leftover "405 ( )" etc. is useless — use description.
    $letters = preg_replace('/[^\p{L}]+/u', '', $name);
    if ($name === '' || mb_strlen((string) $letters, 'UTF-8') < 3) {
        $description = asmbot_yml_sanitize(isset($product['description']) ? $product['description'] : '');
        if ($description !== '') {
            $cut = preg_split('/(?<=[.!?])\s+/u', $description, 2);
            $name = is_array($cut) && isset($cut[0]) ? trim($cut[0]) : $description;
            if (mb_strlen($name, 'UTF-8') > 120) {
                $name = rtrim(mb_substr($name, 0, 117, 'UTF-8')) . '...';
            }
        }
    }
    return $name;
}

function asmbot_yml_absolute_url($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }
    if (strpos($url, '//') === 0) {
        return 'https:' . $url;
    }
    return rtrim(ASMBOT_SITE_URL, '/') . '/' . ltrim($url, '/');
}

function asmbot_yml_is_available($availability)
{
    $value = mb_strtolower(trim((string) $availability), 'UTF-8');
    if ($value === '') {
        return true;
    }
    if (preg_match('/нет|под\s*заказ|ожида|снят|недоступ/u', $value)) {
        return false;
    }
    return true;
}

/**
 * @return array{xml:string,offers:int,categories:int}
 */
function asmbot_build_yml_catalog(array $catalog)
{
    $hiddenCategoryIds = [];
    $hiddenCategoryList = isset($catalog['hiddenCategoryIds']) && is_array($catalog['hiddenCategoryIds'])
        ? $catalog['hiddenCategoryIds']
        : [];
    foreach ($hiddenCategoryList as $id) {
        $hiddenCategoryIds[(string) $id] = true;
    }

    $hiddenProductIds = [];
    $hiddenProductList = isset($catalog['hiddenProductIds']) && is_array($catalog['hiddenProductIds'])
        ? $catalog['hiddenProductIds']
        : [];
    foreach ($hiddenProductList as $id) {
        $hiddenProductIds[(string) $id] = true;
    }

    $hiddenSubs = [];
    if (isset($catalog['hiddenSubcategories']) && (is_array($catalog['hiddenSubcategories']) || is_object($catalog['hiddenSubcategories']))) {
        foreach ($catalog['hiddenSubcategories'] as $categoryId => $names) {
            if (!is_array($names)) {
                continue;
            }
            foreach ($names as $name) {
                $hiddenSubs[(string) $categoryId . "\0" . (string) $name] = true;
            }
        }
    }

    $categoriesXml = [];
    $categoryIdByKey = [];
    $nextId = 1;

    $walkSubs = function ($nodes, $categoryKey, $parentYmlId) use (
        &$walkSubs,
        &$categoriesXml,
        &$categoryIdByKey,
        &$nextId,
        $hiddenSubs
    ) {
        if (!is_array($nodes)) {
            return;
        }
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            $name = isset($node['name']) ? trim((string) $node['name']) : '';
            if ($name === '') {
                continue;
            }
            if (isset($hiddenSubs[$categoryKey . "\0" . $name])) {
                continue;
            }

            $ymlId = $nextId++;
            $key = $categoryKey . '::' . mb_strtolower($name, 'UTF-8');
            $categoryIdByKey[$key] = $ymlId;

            $line = '<category id="' . $ymlId . '"';
            if ($parentYmlId) {
                $line .= ' parentId="' . $parentYmlId . '"';
            }
            $line .= '>' . asmbot_yml_escape($name) . '</category>';
            $categoriesXml[] = $line;

            if (!empty($node['children']) && is_array($node['children'])) {
                $walkSubs($node['children'], $categoryKey, $ymlId);
            }
        }
    };

    $sourceCategories = isset($catalog['categories']) && is_array($catalog['categories'])
        ? $catalog['categories']
        : [];

    foreach ($sourceCategories as $category) {
        if (!is_array($category)) {
            continue;
        }
        $catId = isset($category['id']) ? (string) $category['id'] : '';
        if ($catId === '' || isset($hiddenCategoryIds[$catId])) {
            continue;
        }
        $title = isset($category['title']) ? trim((string) $category['title']) : $catId;
        $ymlId = $nextId++;
        $categoryIdByKey[$catId] = $ymlId;
        $categoriesXml[] = '<category id="' . $ymlId . '">' . asmbot_yml_escape($title) . '</category>';

        if (!empty($category['subcategories']) && is_array($category['subcategories'])) {
            $walkSubs($category['subcategories'], $catId, $ymlId);
        }
    }

    $offersXml = [];
    $offerCount = 0;
    $usedOfferIds = [];
    $products = isset($catalog['products']) && is_array($catalog['products'])
        ? $catalog['products']
        : [];

    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }
        $productId = isset($product['id']) ? (string) $product['id'] : '';
        if ($productId === '' || isset($hiddenProductIds[$productId])) {
            continue;
        }

        $categoryKey = isset($product['category']) ? (string) $product['category'] : '';
        if ($categoryKey === '' || isset($hiddenCategoryIds[$categoryKey]) || !isset($categoryIdByKey[$categoryKey])) {
            continue;
        }

        $subName = isset($product['subcategory']) ? trim((string) $product['subcategory']) : '';
        if ($subName !== '' && isset($hiddenSubs[$categoryKey . "\0" . $subName])) {
            continue;
        }

        $ymlCategoryId = $categoryIdByKey[$categoryKey];
        if ($subName !== '') {
            $subKey = $categoryKey . '::' . mb_strtolower($subName, 'UTF-8');
            if (isset($categoryIdByKey[$subKey])) {
                $ymlCategoryId = $categoryIdByKey[$subKey];
            }
        }

        $name = asmbot_yml_offer_name($product);
        if ($name === '') {
            continue;
        }

        $price = isset($product['price']) ? (float) $product['price'] : 0;
        if ($price < 0) {
            $price = 0;
        }

        $available = asmbot_yml_is_available(isset($product['availability']) ? $product['availability'] : '');
        $url = rtrim(ASMBOT_SITE_URL, '/') . '/product/' . rawurlencode($productId);

        $pictures = [];
        if (!empty($product['images']) && is_array($product['images'])) {
            foreach ($product['images'] as $img) {
                $abs = asmbot_yml_absolute_url($img);
                if ($abs !== '') {
                    $pictures[] = $abs;
                }
            }
        }
        if (!$pictures && !empty($product['image'])) {
            $abs = asmbot_yml_absolute_url($product['image']);
            if ($abs !== '') {
                $pictures[] = $abs;
            }
        }
        $pictures = array_values(array_unique($pictures));

        $description = '';
        if (!empty($product['description'])) {
            $description = asmbot_yml_sanitize($product['description']);
        }
        if ($description === '' && !empty($product['including'])) {
            $description = asmbot_yml_sanitize($product['including']);
        }

        $vendor = asmbot_yml_sanitize(isset($product['brand']) ? $product['brand'] : '');
        $offerId = asmbot_yml_offer_id($productId, $offerCount, $usedOfferIds);

        $lines = [];
        $lines[] = '<offer id="' . asmbot_yml_escape($offerId) . '" available="' . ($available ? 'true' : 'false') . '">';
        $lines[] = '  <url>' . asmbot_yml_escape($url) . '</url>';
        $lines[] = '  <price>' . asmbot_yml_escape(number_format($price, 2, '.', '')) . '</price>';
        $lines[] = '  <currencyId>RUB</currencyId>';
        $lines[] = '  <categoryId>' . $ymlCategoryId . '</categoryId>';
        foreach ($pictures as $picture) {
            $lines[] = '  <picture>' . asmbot_yml_escape($picture) . '</picture>';
        }
        $lines[] = '  <name>' . asmbot_yml_escape($name) . '</name>';
        if ($vendor !== '') {
            $lines[] = '  <vendor>' . asmbot_yml_escape($vendor) . '</vendor>';
        }
        if ($description !== '') {
            $lines[] = '  <description>' . asmbot_yml_cdata($description) . '</description>';
        }
        if (!empty($product['type'])) {
            $lines[] = '  <param name="Тип">' . asmbot_yml_escape(trim((string) $product['type'])) . '</param>';
        }
        if (!empty($product['specs']) && is_array($product['specs'])) {
            foreach ($product['specs'] as $spec) {
                if (!is_array($spec)) {
                    continue;
                }
                $title = trim((string) (isset($spec['title']) ? $spec['title'] : ''));
                if ($title === '' || preg_match('/^тип$/iu', $title)) {
                    continue;
                }
                $units = trim((string) (isset($spec['units']) ? $spec['units'] : ''));
                $values = [];
                if (!empty($spec['value']) && is_array($spec['value'])) {
                    foreach ($spec['value'] as $value) {
                        $text = trim((string) $value);
                        if ($text !== '') {
                            $values[] = $text;
                        }
                    }
                }
                $text = implode(', ', $values);
                if ($text === '') {
                    continue;
                }
                $unitAttr = $units !== ''
                    ? ' unit="' . asmbot_yml_escape(asmbot_yml_sanitize($units)) . '"'
                    : '';
                $lines[] = '  <param name="' . asmbot_yml_escape(asmbot_yml_sanitize($title)) . '"' . $unitAttr . '>'
                    . asmbot_yml_escape(asmbot_yml_sanitize($text))
                    . '</param>';
            }
        }
        if (!empty($product['sourceFeed']['sku'])) {
            $lines[] = '  <vendorCode>' . asmbot_yml_escape((string) $product['sourceFeed']['sku']) . '</vendorCode>';
        }
        // Остаток: площадки настраивают склад именно по нему. Пишем только
        // когда число реально известно — выдуманный ноль хуже отсутствия.
        if (isset($product['stock']) && is_numeric($product['stock']) && (int) $product['stock'] > 0) {
            $lines[] = '  <count>' . (int) $product['stock'] . '</count>';
        }
        if (!empty($product['videos']) && is_array($product['videos'])) {
            $videoUrls = [];
            foreach ($product['videos'] as $video) {
                $videoUrl = '';
                if (is_string($video)) {
                    $videoUrl = trim($video);
                } elseif (is_array($video) && !empty($video['url'])) {
                    $videoUrl = trim((string) $video['url']);
                }
                if ($videoUrl !== '' && !in_array($videoUrl, $videoUrls, true)) {
                    $videoUrls[] = $videoUrl;
                }
            }
            if ($videoUrls) {
                $lines[] = '  <videos>';
                foreach ($videoUrls as $index => $videoUrl) {
                    $lines[] = '    <video id="' . ($index + 1) . '">' . asmbot_yml_escape($videoUrl) . '</video>';
                }
                $lines[] = '  </videos>';
            }
        }
        $lines[] = '</offer>';

        $offersXml[] = implode("\n", $lines);
        $offerCount++;
    }

    $date = gmdate('Y-m-d H:i');
    if (!empty($catalog['updatedAt'])) {
        $ts = strtotime((string) $catalog['updatedAt']);
        if ($ts) {
            $date = gmdate('Y-m-d H:i', $ts);
        }
    }

    $xml = [];
    $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml[] = '<!DOCTYPE yml_catalog SYSTEM "shops.dtd">';
    $xml[] = '<yml_catalog date="' . asmbot_yml_escape($date) . '">';
    $xml[] = '<shop>';
    $xml[] = '  <name>АльфаСмарт</name>';
    $xml[] = '  <company>АльфаСмарт</company>';
    $xml[] = '  <url>' . asmbot_yml_escape(ASMBOT_SITE_URL) . '</url>';
    $xml[] = '  <currencies>';
    $xml[] = '    <currency id="RUB" rate="1"/>';
    $xml[] = '  </currencies>';
    $xml[] = '  <categories>';
    foreach ($categoriesXml as $line) {
        $xml[] = '    ' . $line;
    }
    $xml[] = '  </categories>';
    $xml[] = '  <offers>';
    foreach ($offersXml as $offer) {
        foreach (explode("\n", $offer) as $line) {
            $xml[] = '    ' . $line;
        }
    }
    $xml[] = '  </offers>';
    $xml[] = '</shop>';
    $xml[] = '</yml_catalog>';

    return [
        'xml' => implode("\n", $xml) . "\n",
        'offers' => $offerCount,
        'categories' => count($categoriesXml),
    ];
}

function asmbot_write_yml_file($path, array $catalog)
{
    $built = asmbot_build_yml_catalog($catalog);
    $tmp = $path . '.tmp';
    if (@file_put_contents($tmp, $built['xml'], LOCK_EX) === false) {
        return false;
    }
    if (!@rename($tmp, $path)) {
        if (@file_put_contents($path, $built['xml'], LOCK_EX) === false) {
            @unlink($tmp);
            return false;
        }
        @unlink($tmp);
    }
    @chmod($path, 0644);
    return $built;
}
