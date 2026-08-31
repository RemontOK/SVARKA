<?php
/**
 * Branded price list download (Excel-compatible HTML).
 * URL: /price-list.php
 * Optional: ?format=csv
 */
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate');

$dataFile = __DIR__ . '/runtime-catalog.json';
if (!is_file($dataFile)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Каталог не найден.';
    exit;
}

$raw = @file_get_contents($dataFile);
$catalog = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($catalog) || !isset($catalog['products']) || !is_array($catalog['products'])) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Не удалось прочитать каталог.';
    exit;
}

$format = isset($_GET['format']) ? strtolower(trim((string) $_GET['format'])) : 'xls';
if ($format !== 'csv' && $format !== 'xls') {
    $format = 'xls';
}

$stamp = date('Y-m-d');
$filenameBase = 'AlfaSmart-price-' . $stamp;

$categoryTitles = [];
foreach (isset($catalog['categories']) && is_array($catalog['categories']) ? $catalog['categories'] : [] as $category) {
    $id = isset($category['id']) ? (string) $category['id'] : '';
    if ($id === '') {
        continue;
    }
    $title = trim((string) ($category['title'] ?? $category['name'] ?? $id));
    $categoryTitles[$id] = $title !== '' ? $title : $id;
}

$rows = [];
foreach ($catalog['products'] as $product) {
    if (!is_array($product)) {
        continue;
    }
    $name = trim((string) ($product['name'] ?? ''));
    if ($name === '') {
        continue;
    }

    $categoryId = (string) ($product['category'] ?? '');
    $categoryTitle = $categoryTitles[$categoryId] ?? ($categoryId !== '' ? $categoryId : 'Без категории');
    $subcategory = trim((string) ($product['subcategory'] ?? ''));
    $brand = trim((string) ($product['brand'] ?? ''));
    $availability = trim((string) ($product['availability'] ?? ''));

    $sku = '';
    if (!empty($product['sourceFeed']) && is_array($product['sourceFeed'])) {
        $sku = trim((string) ($product['sourceFeed']['sku'] ?? $product['sourceFeed']['offerId'] ?? ''));
    }
    if ($sku === '' && !empty($product['sku'])) {
        $sku = trim((string) $product['sku']);
    }
    if ($sku === '' && !empty($product['id'])) {
        $sku = preg_replace('/^yml-feed-svarog-/u', '', (string) $product['id']);
        $sku = preg_replace('/^yml-/u', '', (string) $sku);
    }

    $priceRaw = $product['price'] ?? null;
    $priceNum = is_numeric($priceRaw) ? (float) $priceRaw : null;
    $hasPrice = $priceNum !== null && $priceNum > 0;

    $rows[] = [
        'category' => $categoryTitle,
        'subcategory' => $subcategory !== '' ? $subcategory : '—',
        'sku' => $sku !== '' ? $sku : '—',
        'name' => $name,
        'brand' => $brand !== '' ? $brand : '—',
        'price' => $hasPrice ? $priceNum : null,
        'priceText' => $hasPrice ? number_format($priceNum, 0, ',', ' ') : 'по запросу',
        'availability' => $availability !== '' ? $availability : '—',
        'sortKey' => mb_strtolower($categoryTitle . "\0" . $subcategory . "\0" . $name, 'UTF-8'),
    ];
}

usort($rows, static function ($a, $b) {
    return strcmp($a['sortKey'], $b['sortKey']);
});

$updatedAt = isset($catalog['updatedAt']) ? (string) $catalog['updatedAt'] : '';
$updatedLabel = $stamp;
if ($updatedAt !== '') {
    $ts = strtotime($updatedAt);
    if ($ts !== false) {
        $updatedLabel = date('d.m.Y H:i', $ts);
    }
}

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        '№',
        'Артикул',
        'Наименование',
        'Бренд',
        'Категория',
        'Подкатегория',
        'Цена, ₽',
        'Наличие',
    ], ';');
    $n = 1;
    foreach ($rows as $row) {
        fputcsv($out, [
            $n,
            $row['sku'],
            $row['name'],
            $row['brand'],
            $row['category'],
            $row['subcategory'],
            $row['price'] !== null ? (string) (int) $row['price'] : 'по запросу',
            $row['availability'],
        ], ';');
        $n += 1;
    }
    fclose($out);
    exit;
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filenameBase . '.xls"');

$esc = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo "\xEF\xBB\xBF";
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html4/loose.dtd">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<!--[if gte mso 9]>
<xml>
  <x:ExcelWorkbook>
    <x:ExcelWorksheets>
      <x:ExcelWorksheet>
        <x:Name>Прайс АльфаСмарт</x:Name>
        <x:WorksheetOptions>
          <x:FreezePanes/>
          <x:FrozenNoSplit/>
          <x:SplitHorizontal>6</x:SplitHorizontal>
          <x:TopRowBottomPane>6</x:TopRowBottomPane>
          <x:ActivePane>2</x:ActivePane>
        </x:WorksheetOptions>
      </x:ExcelWorksheet>
    </x:ExcelWorksheets>
  </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
  body { font-family: Calibri, Arial, sans-serif; }
  table { border-collapse: collapse; width: 100%; }
  .brand-bar td {
    background: #11141a;
    color: #f8c400;
    font-size: 22pt;
    font-weight: 700;
    padding: 14px 12px;
    border: none;
  }
  .meta td {
    background: #1a1f28;
    color: #ffffff;
    font-size: 10pt;
    padding: 6px 12px;
    border: none;
  }
  .note td {
    background: #f6f0e6;
    color: #1a1a1a;
    font-size: 9pt;
    padding: 8px 12px;
    border: none;
  }
  .head th {
    background: #f8c400;
    color: #11141a;
    font-size: 10pt;
    font-weight: 700;
    text-align: left;
    padding: 8px 10px;
    border: 1px solid #d4a800;
  }
  .cat td {
    background: #232936;
    color: #f8c400;
    font-size: 11pt;
    font-weight: 700;
    padding: 8px 10px;
    border: 1px solid #11141a;
  }
  .row td {
    font-size: 10pt;
    color: #1a1a1a;
    padding: 5px 10px;
    border: 1px solid #d9d2c4;
    vertical-align: top;
  }
  .row-alt td { background: #fffdf8; }
  .row-odd td { background: #ffffff; }
  .num { text-align: right; mso-number-format:"\#\,\#\#0"; }
  .center { text-align: center; }
  .muted { color: #666666; }
</style>
</head>
<body>
<table>
  <tr class="brand-bar">
    <td colspan="8">АльфаСмарт — прайс-лист сварочного оборудования</td>
  </tr>
  <tr class="meta">
    <td colspan="8">
      Дата: <?php echo $esc($updatedLabel); ?>
      &nbsp;|&nbsp; Позиций: <?php echo count($rows); ?>
      &nbsp;|&nbsp; +7 (982) 698-42-80
      &nbsp;|&nbsp; ekat@asmbot.ru
      &nbsp;|&nbsp; asmbot.ru
    </td>
  </tr>
  <tr class="meta">
    <td colspan="8">
      г. Екатеринбург, ул. Отто Шмидта, стр. 58 &nbsp;|&nbsp; Пн–Пт 9:00–18:00<br>
      Склад: г. Березовский, ул. Западная промышленная зона, 13Б
    </td>
  </tr>
  <tr class="note">
    <td colspan="8">
      Цены указаны в рублях с НДС. Актуальная стоимость и наличие уточняйте у менеджера — прайс формируется автоматически из каталога сайта.
    </td>
  </tr>
  <tr class="head">
    <th style="width:40px">№</th>
    <th style="width:110px">Артикул</th>
    <th style="width:420px">Наименование</th>
    <th style="width:110px">Бренд</th>
    <th style="width:160px">Категория</th>
    <th style="width:180px">Подкатегория</th>
    <th style="width:90px">Цена, ₽</th>
    <th style="width:110px">Наличие</th>
  </tr>
<?php
$n = 0;
$prevCategory = null;
foreach ($rows as $row) {
    if ($prevCategory !== $row['category']) {
        echo '<tr class="cat"><td colspan="8">' . $esc($row['category']) . '</td></tr>';
        $prevCategory = $row['category'];
    }
    $n += 1;
    $zebra = ($n % 2 === 0) ? 'row-alt' : 'row-odd';
    $priceCell = $row['price'] !== null
        ? '<td class="num">' . $esc((string) (int) $row['price']) . '</td>'
        : '<td class="center muted">по запросу</td>';
    echo '<tr class="row ' . $zebra . '">';
    echo '<td class="center">' . $n . '</td>';
    echo '<td>' . $esc($row['sku']) . '</td>';
    echo '<td>' . $esc($row['name']) . '</td>';
    echo '<td>' . $esc($row['brand']) . '</td>';
    echo '<td>' . $esc($row['category']) . '</td>';
    echo '<td>' . $esc($row['subcategory']) . '</td>';
    echo $priceCell;
    echo '<td>' . $esc($row['availability']) . '</td>';
    echo '</tr>';
}
?>
</table>
</body>
</html>
