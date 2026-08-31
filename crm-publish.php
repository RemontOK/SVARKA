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
$dataFile = __DIR__ . '/runtime-crm.json';

$empty = [
    'clients' => [],
    'deals' => [],
    'documents' => [],
    'updatedAt' => null,
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!is_file($dataFile)) {
        echo json_encode($empty, JSON_UNESCAPED_UNICODE);
        exit;
    }
    $raw = @file_get_contents($dataFile);
    if ($raw === false || $raw === '') {
        echo json_encode($empty, JSON_UNESCAPED_UNICODE);
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

if (strlen($rawBody) > 12 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'Слишком большой объём CRM (больше 12 МБ).'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Некорректный JSON.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = isset($payload['action']) ? (string) $payload['action'] : 'save';

if ($action === 'clear') {
    $bundle = $empty;
    $bundle['updatedAt'] = gmdate('c');
} else {
    $source = isset($payload['crm']) && is_array($payload['crm']) ? $payload['crm'] : $payload;
    $bundle = [
        'clients' => isset($source['clients']) && is_array($source['clients']) ? $source['clients'] : [],
        'deals' => isset($source['deals']) && is_array($source['deals']) ? $source['deals'] : [],
        'documents' => isset($source['documents']) && is_array($source['documents']) ? $source['documents'] : [],
        'updatedAt' => gmdate('c'),
    ];
}

$json = json_encode($bundle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Не удалось закодировать CRM.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$tmpFile = $dataFile . '.tmp';
if (@file_put_contents($tmpFile, $json, LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Не удалось записать временный файл CRM.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!@rename($tmpFile, $dataFile)) {
    if (@file_put_contents($dataFile, $json, LOCK_EX) === false) {
        @unlink($tmpFile);
        http_response_code(500);
        echo json_encode(['error' => 'Не удалось сохранить файл CRM.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    @unlink($tmpFile);
}

echo json_encode([
    'ok' => true,
    'updatedAt' => $bundle['updatedAt'],
    'clients' => count($bundle['clients']),
    'deals' => count($bundle['deals']),
    'documents' => count($bundle['documents']),
], JSON_UNESCAPED_UNICODE);
