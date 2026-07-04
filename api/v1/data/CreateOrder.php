<?php
// CORS for React/browser clients
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once '../../../db/db_con.php';

if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'DB connection error']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$payment_method = isset($input['payment_method']) ? strtolower(trim($input['payment_method'])) : null;
$items = isset($input['items']) && is_array($input['items']) ? $input['items'] : [];
$shipping = isset($input['shipping']) && is_array($input['shipping']) ? $input['shipping'] : null;
$customer_name = isset($input['customer_name']) ? trim($input['customer_name']) : null;
$customer_email = isset($input['customer_email']) ? trim($input['customer_email']) : null;
$customer_phone = isset($input['customer_phone']) ? trim($input['customer_phone']) : null;
$shipping_address = isset($input['shipping_address']) ? trim($input['shipping_address']) : null;
$user_id = isset($input['user_id']) && is_numeric($input['user_id']) ? (int)$input['user_id'] : null;

// Fallbacks from shipping object (used by your ONLINE order endpoint)
if ($shipping) {
    if (empty($customer_name)) {
        $customer_name = trim(($shipping['firstName'] ?? '') . ' ' . ($shipping['lastName'] ?? ''));
        if ($customer_name === '') $customer_name = ($shipping['name'] ?? null);
    }
    if (empty($customer_phone)) $customer_phone = $shipping['phone'] ?? null;
    if (empty($customer_email)) $customer_email = $shipping['email'] ?? null;
    if (empty($shipping_address)) $shipping_address = $shipping['address'] ?? null;
}


// Basic validation — for COD ensure customer details and items exist
if (empty($payment_method) || strtolower($payment_method) !== 'cod') {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Only COD orders are supported by this endpoint. Set payment_method to "cod".']);
    exit;
}

if (empty($items) || !is_array($items)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Order items required']);
    exit;
}

// SHIPPING/TOTAL validation parity with your ONLINE order endpoint
// (your frontend/backend is sending `shipping` and expects those fields to be required)
$shipping = $shipping ?? [];
// if (empty($shipping) || !is_array($shipping)) {
//     http_response_code(400);
//     echo json_encode(['status' => false, 'message' => 'Items, shipping and total_amount required']);
//     exit;
// }

if (empty($customer_name) || empty($customer_phone)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Customer name and phone are required']);
    exit;
}


try {
    $pdo->beginTransaction();

    // generate an order reference
    $order_ref = 'ORD-' . time() . '-' . strtoupper(substr(bin2hex(random_bytes(3)),0,6));

    $total_amount = 0.00;

    // prepare statements
    $product_lookup = $pdo->prepare('SELECT id, product_id AS sku, product_name, price, discount_price FROM products WHERE id = :id OR product_id = :sku LIMIT 1');
    $insert_order = $pdo->prepare('INSERT INTO orders (order_id, user_id, customer_name, customer_email, customer_phone, shipping_address, payment_method, total_amount, status) VALUES (:order_id, :user_id, :customer_name, :customer_email, :customer_phone, :shipping_address, :payment_method, :total_amount, :status)');
    $insert_item = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_sku, product_name, quantity, unit_price, total_price) VALUES (:order_id, :product_id, :product_sku, :product_name, :quantity, :unit_price, :total_price)');

    // compute totals and collect item rows
    $item_rows = [];
    foreach ($items as $it) {
        $qty = isset($it['quantity']) && is_numeric($it['quantity']) ? (int)$it['quantity'] : 1;
        $identifier = isset($it['product_id']) ? $it['product_id'] : (isset($it['id']) ? $it['id'] : null);
        if ($identifier === null) {
            throw new Exception('Each item must include product_id (sku) or id');
        }

        $product_lookup->execute([':id' => is_numeric($identifier) ? (int)$identifier : 0, ':sku' => $identifier]);
        $prod = $product_lookup->fetch(PDO::FETCH_ASSOC);
        if (!$prod) {
            throw new Exception('Product not found: ' . $identifier);
        }

        $unit = (isset($prod['discount_price']) && $prod['discount_price'] > 0) ? (float)$prod['discount_price'] : (float)$prod['price'];
        $total = $unit * $qty;
        $total_amount += $total;

        $item_rows[] = [
            'product_id' => $prod['id'],
            'product_sku' => $prod['sku'],
            'product_name' => $prod['product_name'],
            'quantity' => $qty,
            'unit_price' => $unit,
            'total_price' => $total,
        ];
    }

    // insert order with total_amount (status pending)
    $insert_order->execute([
        ':order_id' => $order_ref,
        ':user_id' => $user_id,
        ':customer_name' => $customer_name,
        ':customer_email' => $customer_email,
        ':customer_phone' => $customer_phone,
        ':shipping_address' => $shipping_address,
        ':payment_method' => 'cod',
        ':total_amount' => $total_amount,
        ':status' => 'pending',
    ]);

    $order_db_id = (int)$pdo->lastInsertId();

    // insert items
    foreach ($item_rows as $row) {
        $insert_item->execute([
            ':order_id' => $order_db_id,
            ':product_id' => $row['product_id'],
            ':product_sku' => $row['product_sku'],
            ':product_name' => $row['product_name'],
            ':quantity' => $row['quantity'],
            ':unit_price' => $row['unit_price'],
            ':total_price' => $row['total_price'],
        ]);
    }

    $pdo->commit();

    echo json_encode(['status' => true, 'message' => 'Order placed', 'order_id' => $order_ref, 'order_db_id' => $order_db_id]);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Failed to place order: ' . $e->getMessage()]);
    exit;
}

?>