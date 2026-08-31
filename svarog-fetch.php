<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$action = isset($_GET['action']) ? trim((string) $_GET['action']) : '';

if ($action !== 'pricelist' && $action !== 'product') {
    http_response_code(400);
    echo json_encode(
        ['error' => 'Укажите action=pricelist или action=product.'],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

if ($action === 'pricelist') {
    $url = 'https://svarog-rf.ru/api/pricelist';
} else {
    $num = isset($_GET['num']) ? trim((string) $_GET['num']) : '';
    if ($num === '' || !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $num)) {
        http_response_code(400);
        echo json_encode(['error' => 'Укажите корректный артикул num.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $url = 'https://svarog-rf.ru/api/products/' . rawurlencode($num);
}

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 60,
        'ignore_errors' => true,
        'header' => "User-Agent: AlphaSmart-SvarogImporter/1.0\r\nAccept: application/json\r\n",
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
    echo json_encode(['error' => 'Не удалось связаться с API Сварог.'], JSON_UNESCAPED_UNICODE);
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
        ['error' => 'API Сварог вернуло ошибку.', 'status' => $status],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

if (strlen($body) > 20 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'Ответ API слишком большой.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$decoded = json_decode($body, true);
if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(502);
    echo json_encode(['error' => 'API Сварог вернуло не JSON.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($decoded, JSON_UNESCAPED_UNICODE);
