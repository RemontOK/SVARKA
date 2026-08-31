<?php
/**
 * Live YML feed of the published catalog.
 * URL: https://asmbot.ru/catalog-yml.php
 *
 * Фид забирают роботы маркетплейсов, поэтому он отдаётся из готового файла
 * и пересобирается только когда каталог реально изменился. Пересборка на
 * каждый запрос — это 10 МБ и почти секунда работы, чего роботы не любят.
 */
require_once __DIR__ . '/catalog-yml-lib.php';

$dataFile = __DIR__ . '/runtime-catalog.json';
$staticFile = __DIR__ . '/catalog-feed.yml';

header('Content-Type: application/xml; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=300');

if (!is_file($dataFile)) {
    http_response_code(404);
    echo '<?xml version="1.0" encoding="UTF-8"?><error>Catalog not found</error>';
    exit;
}

$catalogTime = (int) @filemtime($dataFile);
$cacheTime = is_file($staticFile) ? (int) @filemtime($staticFile) : 0;
$xml = null;

// Готовый файл свежее каталога — значит пересобирать нечего.
if ($cacheTime > 0 && $cacheTime >= $catalogTime) {
    $xml = @file_get_contents($staticFile);
    if ($xml === false || $xml === '') {
        $xml = null;
    }
}

if ($xml === null) {
    $raw = @file_get_contents($dataFile);
    if ($raw === false || $raw === '') {
        http_response_code(500);
        echo '<?xml version="1.0" encoding="UTF-8"?><error>Catalog empty</error>';
        exit;
    }

    $catalog = json_decode($raw, true);
    if (!is_array($catalog)) {
        http_response_code(500);
        echo '<?xml version="1.0" encoding="UTF-8"?><error>Invalid catalog JSON</error>';
        exit;
    }

    $built = asmbot_build_yml_catalog($catalog);
    $xml = $built['xml'];

    // Снимок для роботов, которые ходят на /catalog.yml.
    $tmp = $staticFile . '.tmp';
    if (@file_put_contents($tmp, $xml, LOCK_EX) !== false && @rename($tmp, $staticFile)) {
        @chmod($staticFile, 0644);
    } else {
        @unlink($tmp);
        @file_put_contents($staticFile, $xml, LOCK_EX);
    }
    $cacheTime = time();
}

// Кто и когда забирал выгрузку. Нужно, чтобы отличать «площадка пришла и
// файл ей не понравился» от «площадка к нам вообще не обращалась».
$logLine = sprintf(
    "%s\t%s\t%s\t%s\n",
    gmdate('c'),
    isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '-',
    isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '-',
    isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 200) : '-'
);
@file_put_contents(__DIR__ . '/runtime-feed-access.log', $logLine, FILE_APPEND | LOCK_EX);

$download = isset($_GET['download']) && $_GET['download'] !== '0' && $_GET['download'] !== '';
if ($download) {
    header('Content-Disposition: attachment; filename="alfasmart-catalog.yml"');
}

// Условный запрос: робот, у которого фид не изменился, качает 0 байт.
$etag = '"' . md5($xml) . '"';
$lastModified = gmdate('D, d M Y H:i:s', $cacheTime > 0 ? $cacheTime : time()) . ' GMT';
header('ETag: ' . $etag);
header('Last-Modified: ' . $lastModified);

$ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) : '';
$ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
    ? strtotime((string) $_SERVER['HTTP_IF_MODIFIED_SINCE'])
    : 0;

if (!$download && ($ifNoneMatch === $etag || ($ifModifiedSince > 0 && $ifModifiedSince >= $cacheTime))) {
    http_response_code(304);
    exit;
}

$accept = isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? (string) $_SERVER['HTTP_ACCEPT_ENCODING'] : '';
if (function_exists('gzencode') && stripos($accept, 'gzip') !== false) {
    $packed = gzencode($xml, 6);
    if ($packed !== false) {
        header('Content-Encoding: gzip');
        header('Vary: Accept-Encoding');
        header('Content-Length: ' . strlen($packed));
        echo $packed;
        exit;
    }
}

header('Content-Length: ' . strlen($xml));
echo $xml;
