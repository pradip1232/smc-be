<?php
// PhonePe callback handler
header('Content-Type: application/json');
require_once 'phonepe-config.php';
require_once '../../../db/db_con.php';

$payload = file_get_contents('php://input');
$headers = getallheaders();
$signature = $headers['X-VERIFY'] ?? $headers['x-verify'] ?? '';

if (generatePhonePeSignature($payload) !== $signature) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$data = json_decode($payload, true);
$paymentStatus = $data['paymentStatus'] ?? null;
$merchantOrderId = $data['merchantOrderId'] ?? $data['merchantOrderId'] ?? null;

if (!$merchantOrderId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing order id']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM orders WHERE order_id = :order_id LIMIT 1");
$stmt->execute([':order_id' => $merchantOrderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit;
}

$status = 'failed';
$orderStatus = 'pending';
if ($paymentStatus === 'SUCCESS' || $paymentStatus === 'SUCCESSFUL') {
    $status = 'paid';
    $orderStatus = 'confirmed';
}

$update = $pdo->prepare("UPDATE orders SET payment_status = :payment_status, order_status = :order_status, status = :status, updated_at = NOW() WHERE id = :id");
$update->execute([
    ':payment_status' => $status,
    ':order_status' => $orderStatus,
    ':status' => $orderStatus,
    ':id' => $order['id'],
]);

echo json_encode(['success' => true]);

?>