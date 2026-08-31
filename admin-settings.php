<?php
/**
 * Реквизиты компании для счетов и документов.
 *
 *   GET  ?action=get   — текущие значения и список полей
 *   POST ?action=save  — сохранить
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/admin-auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings-lib.php';

asmbot_require_admin();

try {
    $pdo = asmbot_db();
} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'База данных недоступна.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = isset($_GET['action']) ? trim((string) $_GET['action']) : 'get';

if ($action === 'get') {
    $info = asmbot_company_info($pdo);
    echo json_encode([
        'ok' => true,
        'fields' => asmbot_company_fields(),
        'values' => $info,
        'ready' => asmbot_company_is_ready($info),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Некорректный запрос.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Сохраняем только известные поля — чужие ключи в настройки не попадут.
    $allowed = array_keys(asmbot_company_fields());
    $values = [];
    foreach ($allowed as $key) {
        if (array_key_exists($key, $data)) {
            $values[$key] = trim((string) $data[$key]);
        }
    }

    if ($values) {
        asmbot_save_settings($pdo, $values);
    }

    $info = asmbot_company_info($pdo);
    echo json_encode([
        'ok' => true,
        'values' => $info,
        'ready' => asmbot_company_is_ready($info),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Неизвестное действие.'], JSON_UNESCAPED_UNICODE);
