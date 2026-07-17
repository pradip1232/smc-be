<?php
// paymentStatus.php - Smart Version (Detects ONLINE or COD)

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

session_start();
include 'accessToken.php';
include '../db/db_con.php';

$merchantOrderId = $_SESSION['merchantOrderId'] ?? $_GET['merchantOrderId'] ?? $_GET['morderid'] ?? null;

if (!$merchantOrderId) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Order ID not found']);
    exit;
}

// === PHONEPE STATUS CHECK ===
$statusUrl = "https://api-preprod.phonepe.com/apis/pg-sandbox/checkout/v2/order/" . $merchantOrderId . "/status";

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $statusUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: O-Bearer ' . trim($accessToken)
    ],
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if (curl_errno($curl) || $httpCode !== 200) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Failed to connect to PhonePe']);
    exit;
}

$data = json_decode($response, true);

// Determine Status
$phonepeState = strtoupper($data['state'] ?? 'UNKNOWN');

$paymentStatus = match($phonepeState) {
    'COMPLETED' => 'success',
    'FAILED'    => 'failed',
    'PENDING'   => 'pending',
    default     => 'unknown'
};

$message = match($phonepeState) {
    'COMPLETED' => 'Payment Successful',
    'FAILED'    => 'Payment Failed',
    'PENDING'   => 'Payment is Pending',
    default     => 'Unknown Payment Status'
};

// === FIND ORDER & PAYMENT METHOD ===
$stmt = $pdo->prepare("
    SELECT payment_method, order_id FROM orders 
    WHERE order_id = ? OR merchant_id = ? 
    LIMIT 1
");
$stmt->execute([$merchantOrderId, $merchantOrderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

$paymentMethod = $order ? strtoupper($order['payment_method']) : 'ONLINE';
$Order_id = $order ? $order['order_id'] : null;
// === UPDATE BASED ON PAYMENT METHOD ===
if ($paymentMethod === 'COD') {
    // Update only Shipment Table for COD (Shipping Charge)
    $stmt = $pdo->prepare("
        UPDATE shipments 
        SET shipping_charge_status = ?,
            shipment_status = IF(? = 'success', 'shipped', shipment_status),
            updated_at = NOW()
        WHERE order_id = ?
    ");
    $stmt->execute([$paymentStatus, $paymentStatus, $Order_id]);

} else {
    // Update Orders Table for ONLINE Payment
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET payment_status = ?,
            order_status = ?,
            updated_at = NOW()
        WHERE order_id = ? OR merchant_id = ?
    ");
    $stmt->execute([
        $paymentStatus,
        ($paymentStatus === 'success') ? 'confirmed' : 'pending',
        $merchantOrderId,
        $merchantOrderId
    ]);
}

// === FINAL RESPONSE ===
echo json_encode([
    'status'  => ($paymentStatus === 'success'),
    'message' => $message,
    'state'   => $phonepeState,
    'payment_method' => $paymentMethod,
    'data'    => [
        'merchantOrderId' => $merchantOrderId,
        'phonepeOrderId'  => $data['orderId'] ?? null,
        'amount'          => isset($data['amount']) ? $data['amount'] / 100 : 0,
        'payment_status'  => $paymentStatus,
        'raw'             => $data
    ]
]);

unset($_SESSION['merchantOrderId']);
?>