<?php
/**
 * Generic JSON API proxy for supplier catalog import (CORS bypass).
 * GET ?url=https://…  [&header_Authorization=Bearer%20…]
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$url = isset($_GET['url']) ? trim((string) $_GET['url']) : '';

if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Укажите корректный URL API.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$parts = parse_url($url);
$scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
$host = isset($parts['host']) ? strtolower($parts['host']) : '';

if ($scheme !== 'http' && $scheme !== 'https') {
    http_response_code(400);
    echo json_encode(['error' => 'Поддерживаются только HTTP и HTTPS.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
    http_response_code(400);
    echo json_encode(['error' => 'Локальные адреса запрещены.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (filter_var($host, FILTER_VALIDATE_IP)) {
    if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        http_response_code(400);
        echo json_encode(['error' => 'Приватные IP запрещены.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$extraHeaders = [];
foreach ($_GET as $key => $value) {
    if (!is_string($key) || strpos($key, 'header_') !== 0) {
        continue;
    }
    $headerName = substr($key, 7);
    if ($headerName === '' || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $headerName)) {
        continue;
    }
    $headerValue = trim((string) $value);
    if ($headerValue === '' || strlen($headerValue) > 2048) {
        continue;
    }
    // Prevent header injection
    $headerName = str_replace(['_', ' '], '-', $headerName);
    if (preg_match('/[\r\n]/', $headerValue)) {
        continue;
    }
    $extraHeaders[] = $headerName . ': ' . $headerValue;
}

$headerBlock =
    "User-Agent: AlphaSmart-SupplierApiImporter/1.0\r\n" .
    "Accept: application/json\r\n";
if ($extraHeaders) {
    $headerBlock .= implode("\r\n", $extraHeaders) . "\r\n";
}

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 60,
        'ignore_errors' => true,
        'header' => $headerBlock,
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
    ],
]);

$body = @file_get_contents($url, false, $context);

$status = 502;
if (isset($http_response_header) && is_array($http_response_header)) {
    foreach ($http_response_header as $headerLine) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $headerLine, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }
}

if ($body === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Не удалось связаться с API поставщика.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($status === 404) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($status < 200 || $status >= 300) {
    http_response_code(502);
    echo json_encode(
        ['error' => 'API поставщика вернуло ошибку.', 'status' => $status],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

if (strlen($body) > 25 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'Ответ API слишком большой.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$decoded = json_decode($body, true);
if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(502);
    echo json_encode(['error' => 'API вернуло не JSON.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($decoded, JSON_UNESCAPED_UNICODE);
