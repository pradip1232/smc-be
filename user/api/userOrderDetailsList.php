<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => false, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$userId = null;
$tokenUserId = $_SERVER['HTTP_X_USER_ID'] ?? null;
if ($tokenUserId !== null && $tokenUserId !== '') {
    $userId = (int)$tokenUserId;
}

if (!$userId) {
    fail('Missing user_id (send X-USER-ID header)', 401);
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 10;
$offset = ($page - 1) * $limit;

try {
    // total record for pagination
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
    $countStmt->execute([$userId]);
    $totalOrders = (int)$countStmt->fetchColumn();

    // Order list
    $ordersStmt = $pdo->prepare(
        'SELECT id, order_id, user_id, total_amount, payment_method, payment_status, order_status, customer_name, phone, shipping_address, city, state, country, pincode, created_at, updated_at\n'
        . 'FROM orders\n'
        . 'WHERE user_id = ?\n'
        . 'ORDER BY created_at DESC\n'
        . 'LIMIT ? OFFSET ?'
    );
    $ordersStmt->bindValue(1, $userId, PDO::PARAM_INT);
    $ordersStmt->bindValue(2, $limit, PDO::PARAM_INT);
    $ordersStmt->bindValue(3, $offset, PDO::PARAM_INT);
    $ordersStmt->execute();
    $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch order items + products for those orders
    $orderIds = array_map(static fn($o) => (int)$o['id'], $orders);
    $itemsByOrderId = [];

    if (!empty($orderIds)) {
        $ph = implode(',', array_fill(0, count($orderIds), '?'));
        $itemsStmt = $pdo->prepare(
            'SELECT oi.order_id, oi.product_id, oi.quantity, oi.price, oi.total_price,\n'
            . 'p.product_name, p.product_id, p.price AS current_product_price\n'
            . 'FROM order_items oi\n'
            . 'LEFT JOIN products p ON p.id = oi.product_id\n'
            . 'WHERE oi.order_id IN (' . $ph . ')'
        );
        $itemsStmt->execute($orderIds);

        while ($it = $itemsStmt->fetch(PDO::FETCH_ASSOC)) {
            $oid = (int)$it['order_id'];
            if (!isset($itemsByOrderId[$oid])) $itemsByOrderId[$oid] = [];

            $itemsByOrderId[$oid][] = [
                'product' => [
                    'id' => $it['product_id'] !== null ? (int)$it['product_id'] : null,
                    'product_id' => $it['product_id'] !== null ? ($it['product_id'] ?? null) : null,
                    'product_name' => $it['product_name'] ?? null
                ],
                'quantity' => isset($it['quantity']) ? (int)$it['quantity'] : 0,
                'price' => $it['price'] ?? null,
                'total_price' => $it['total_price'] ?? null
            ];
        }
    }

    // Attach items to each order
    foreach ($orders as &$o) {
        $oid = (int)$o['id'];
        $o['items'] = $itemsByOrderId[$oid] ?? [];
    }

    $totalPages = $limit > 0 ? (int)ceil($totalOrders / $limit) : 0;

    echo json_encode([
        'status' => true,
        'message' => 'Order details fetched successfully',
        'data' => [
            'orders' => $orders,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'totalCount' => $totalOrders,
                'totalPages' => $totalPages
            ]
        ]
    ]);
} catch (PDOException $e) {
    fail('Database error: ' . $e->getMessage(), 500);
}


