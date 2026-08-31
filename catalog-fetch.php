<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function asmbot_catalog_fetch_json($payload, $status = 200) {
    http_response_code($status);
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $encoded = json_encode($payload, $flags);
    if ($encoded === false) {
        http_response_code(500);
        echo '{"error":"Не удалось сформировать ответ сервера (кодировка каталога)."}';
        exit;
    }
    echo $encoded;
    exit;
}

function asmbot_catalog_fetch_download($url) {
    $error = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_USERAGENT => 'AlphaSmart-CatalogImporter/1.0',
            CURLOPT_HTTPHEADER => [
                'Accept: application/xml,text/xml,application/json,*/*',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($body === false) {
            $error = curl_error($ch);
        }
        curl_close($ch);

        if ($body !== false && $status >= 200 && $status < 400) {
            return [$body, ''];
        }
        if ($body !== false && $status > 0) {
            return [false, 'Поставщик вернул HTTP ' . $status . ' при скачивании каталога.'];
        }
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 90,
            'follow_location' => 1,
            'header' => "User-Agent: AlphaSmart-CatalogImporter/1.0\r\nAccept: application/xml,text/xml,application/json,*/*\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        return [false, $error !== '' ? $error : 'Не удалось скачать каталог с указанного URL.'];
    }

    return [$body, ''];
}

function asmbot_catalog_fetch_to_utf8($body) {
    $declaredEncoding = 'UTF-8';
    if (preg_match('/encoding\s*=\s*["\']([^"\']+)["\']/i', substr($body, 0, 512), $matches)) {
        $declaredEncoding = strtoupper(trim($matches[1]));
    }

    if (!function_exists('mb_convert_encoding')) {
        return $body;
    }

    $looksBrokenUtf8 = function_exists('mb_check_encoding') && !mb_check_encoding($body, 'UTF-8');
    // Many Russian feeds declare utf-8 but ship Windows-1251 bytes.
    $cyrillicUtf8 = preg_match('/[\xD0-\xD1][\x80-\xBF]/', $body) === 1;
    $highBytes = preg_match('/[\x80-\xFF]/', $body) === 1;

    if ($declaredEncoding !== 'UTF-8') {
        $converted = @mb_convert_encoding($body, 'UTF-8', $declaredEncoding);
        if ($converted !== false) {
            $body = $converted;
        }
    } elseif ($looksBrokenUtf8 || ($highBytes && !$cyrillicUtf8)) {
        $converted = @mb_convert_encoding($body, 'UTF-8', 'Windows-1251');
        if ($converted !== false) {
            $body = $converted;
        }
    }

    $body = preg_replace('/encoding\s*=\s*["\'][^"\']+["\']/i', 'encoding="UTF-8"', $body, 1);
    // Browser DOMParser chokes on external DTD references.
    $body = preg_replace('/<!DOCTYPE[^>]*>/i', '', $body, 1);

    return $body;
}

$url = isset($_GET['url']) ? trim($_GET['url']) : '';

if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
    asmbot_catalog_fetch_json(['error' => 'Укажите корректную ссылку на каталог.'], 400);
}

$parts = parse_url($url);
$scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';

if ($scheme !== 'http' && $scheme !== 'https') {
    asmbot_catalog_fetch_json(['error' => 'Поддерживаются только HTTP и HTTPS ссылки.'], 400);
}

list($body, $downloadError) = asmbot_catalog_fetch_download($url);

if ($body === false) {
    asmbot_catalog_fetch_json([
        'error' => $downloadError !== ''
            ? $downloadError
            : 'Не удалось скачать каталог с указанного URL.',
    ], 502);
}

if (strlen($body) > 50 * 1024 * 1024) {
    asmbot_catalog_fetch_json(['error' => 'Файл каталога слишком большой (больше 50 МБ).'], 413);
}

$body = asmbot_catalog_fetch_to_utf8($body);

$path = isset($parts['path']) ? $parts['path'] : '';
$fileName = $path ? basename($path) : 'catalog.xml';

if ($fileName === '' || $fileName === '/' || $fileName === '.') {
    $fileName = 'catalog.xml';
}

asmbot_catalog_fetch_json([
    'text' => $body,
    'fileName' => $fileName,
]);
