<?php
/**
 * Клиенты и заказы для админки.
 *
 *   GET  ?action=list                     — клиенты со сводкой по заказам
 *   POST ?action=discount {clientId, discountPct}
 *   GET  ?action=orders                   — все заказы (последние 300)
 *   POST ?action=order-status {orderId, status}
 *
 * Доступ только по админскому паролю.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/admin-auth.php';
require_once __DIR__ . '/db.php';

asmbot_require_admin();

const ASMBOT_ORDER_STATUSES = ['new', 'confirmed', 'invoiced', 'paid', 'shipped', 'done', 'cancelled'];

function asmbot_clients_fail($code, $message)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = asmbot_db();
} catch (RuntimeException $e) {
    asmbot_clients_fail(500, 'База данных недоступна.');
}

$action = isset($_GET['action']) ? trim((string) $_GET['action']) : 'list';
$method = $_SERVER['REQUEST_METHOD'];

$input = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $decoded = $raw ? json_decode($raw, true) : null;
    $input = is_array($decoded) ? $decoded : [];
}

// ------------------------------------------------------------- list
if ($action === 'list') {
    $rows = $pdo->query(
        "SELECT c.id, c.email, c.name, c.phone, c.company, c.inn, c.discount_pct,
                c.email_verified_at, c.created_at,
                COUNT(o.id) AS orders_count,
                COALESCE(SUM(CASE WHEN o.status <> 'cancelled' THEN o.total ELSE 0 END), 0) AS orders_total
         FROM clients c
         LEFT JOIN orders o ON o.client_id = c.id
         GROUP BY c.id
         ORDER BY c.created_at DESC
         LIMIT 500"
    )->fetchAll();

    $clients = array_map(static function ($row) {
        return [
            'id' => (int) $row['id'],
            'email' => $row['email'],
            'name' => $row['name'],
            'phone' => $row['phone'],
            'company' => $row['company'],
            'inn' => $row['inn'],
            'discountPct' => (float) $row['discount_pct'],
            'emailVerified' => !empty($row['email_verified_at']),
            'createdAt' => $row['created_at'],
            'ordersCount' => (int) $row['orders_count'],
            'ordersTotal' => (float) $row['orders_total'],
        ];
    }, $rows);

    echo json_encode(['ok' => true, 'clients' => $clients], JSON_UNESCAPED_UNICODE);
    exit;
}

// --------------------------------------------------------- discount
if ($action === 'discount') {
    if ($method !== 'POST') {
        asmbot_clients_fail(405, 'Метод не поддерживается.');
    }

    $clientId = isset($input['clientId']) ? (int) $input['clientId'] : 0;
    $discount = isset($input['discountPct']) ? (float) $input['discountPct'] : -1;

    if ($clientId <= 0) {
        asmbot_clients_fail(400, 'Не указан клиент.');
    }
    if ($discount < 0 || $discount > 90) {
        asmbot_clients_fail(400, 'Скидка должна быть от 0 до 90%.');
    }

    $stmt = $pdo->prepare('UPDATE clients SET discount_pct = ? WHERE id = ?');
    $stmt->execute([round($discount, 2), $clientId]);

    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare('SELECT id FROM clients WHERE id = ?');
        $check->execute([$clientId]);
        if (!$check->fetch()) {
            asmbot_clients_fail(404, 'Клиент не найден.');
        }
    }

    echo json_encode(['ok' => true, 'clientId' => $clientId, 'discountPct' => round($discount, 2)], JSON_UNESCAPED_UNICODE);
    exit;
}

// ----------------------------------------------------------- orders
if ($action === 'orders') {
    $rows = $pdo->query(
        'SELECT o.*, c.email AS client_email, c.name AS client_name, c.company AS client_company
         FROM orders o
         LEFT JOIN clients c ON c.id = o.client_id
         ORDER BY o.created_at DESC
         LIMIT 300'
    )->fetchAll();

    $orders = [];
    if ($rows) {
        $ids = array_column($rows, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $itemStmt = $pdo->prepare(
            "SELECT order_id, product_id, name, qty, price FROM order_items WHERE order_id IN ($in)"
        );
        $itemStmt->execute($ids);

        $byOrder = [];
        foreach ($itemStmt->fetchAll() as $item) {
            $byOrder[$item['order_id']][] = [
                'productId' => $item['product_id'],
                'name' => $item['name'],
                'qty' => (int) $item['qty'],
                'price' => (float) $item['price'],
            ];
        }

        foreach ($rows as $row) {
            $orders[] = [
                'id' => (int) $row['id'],
                'number' => $row['number'],
                'status' => $row['status'],
                'total' => (float) $row['total'],
                'discountPct' => (float) $row['discount_pct'],
                'comment' => $row['comment'],
                'createdAt' => $row['created_at'],
                'client' => [
                    'id' => $row['client_id'] !== null ? (int) $row['client_id'] : null,
                    'email' => $row['client_email'] ?: $row['contact_email'],
                    'name' => $row['client_name'] ?: $row['contact_name'],
                    'company' => $row['client_company'],
                    'phone' => $row['contact_phone'],
                ],
                'items' => isset($byOrder[$row['id']]) ? $byOrder[$row['id']] : [],
            ];
        }
    }

    echo json_encode(['ok' => true, 'orders' => $orders], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------- order-status
if ($action === 'order-status') {
    if ($method !== 'POST') {
        asmbot_clients_fail(405, 'Метод не поддерживается.');
    }

    $orderId = isset($input['orderId']) ? (int) $input['orderId'] : 0;
    $status = isset($input['status']) ? trim((string) $input['status']) : '';

    if ($orderId <= 0) {
        asmbot_clients_fail(400, 'Не указан заказ.');
    }
    if (!in_array($status, ASMBOT_ORDER_STATUSES, true)) {
        asmbot_clients_fail(400, 'Недопустимый статус.');
    }

    $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $stmt->execute([$status, $orderId]);

    echo json_encode(['ok' => true, 'orderId' => $orderId, 'status' => $status], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------- order-delete
if ($action === 'order-delete') {
    if ($method !== 'POST') {
        asmbot_clients_fail(405, 'Метод не поддерживается.');
    }

    $orderId = isset($input['orderId']) ? (int) $input['orderId'] : 0;
    if ($orderId <= 0) {
        asmbot_clients_fail(400, 'Не указан заказ.');
    }

    // Позиции уходят каскадом по внешнему ключу.
    $stmt = $pdo->prepare('DELETE FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);

    if ($stmt->rowCount() === 0) {
        asmbot_clients_fail(404, 'Заказ не найден.');
    }

    echo json_encode(['ok' => true, 'orderId' => $orderId], JSON_UNESCAPED_UNICODE);
    exit;
}

// --------------------------------------------------- client-delete
if ($action === 'client-delete') {
    if ($method !== 'POST') {
        asmbot_clients_fail(405, 'Метод не поддерживается.');
    }

    $clientId = isset($input['clientId']) ? (int) $input['clientId'] : 0;
    if ($clientId <= 0) {
        asmbot_clients_fail(400, 'Не указан клиент.');
    }

    // Заказы остаются для отчётности: связь мягкая (ON DELETE SET NULL).
    $stmt = $pdo->prepare('DELETE FROM clients WHERE id = ?');
    $stmt->execute([$clientId]);

    if ($stmt->rowCount() === 0) {
        asmbot_clients_fail(404, 'Клиент не найден.');
    }

    echo json_encode(['ok' => true, 'clientId' => $clientId], JSON_UNESCAPED_UNICODE);
    exit;
}

asmbot_clients_fail(400, 'Неизвестное действие.');
