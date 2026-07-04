<?php
// CreateOrderOnline_SDK.php - initiates PhonePe payment using the official Composer SDK.
// Keeps response structure similar to existing CreateOrderOnline.php.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once 'phonepe-sdk-init.php';

$client = null;
try {
    $client = require __DIR__ . '/phonepe-sdk-init.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
    exit;
}

// Read input
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = $_POST;

$paymentMethod = isset($body['payment_method']) ? strtoupper(trim($body['payment_method'])) : 'ONLINE';
$shipping = $body['shipping'] ?? [];
$items = $body['items'] ?? [];
$user_id = isset($body['user_id']) && is_numeric($body['user_id']) ? (int)$body['user_id'] : null;
$total_amount = isset($body['total_amount']) ? (float)$body['total_amount'] : null;

if ($paymentMethod !== 'ONLINE') {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'payment_method must be ONLINE']);
    exit;
}

if (empty($items) || empty($shipping) || $total_amount === null || $total_amount <= 0) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Items, shipping and total_amount required']);
    exit;
}

// This endpoint only creates PhonePe payment intent and returns redirect URL.
// If you also need DB order insertion like CreateOrderOnline.php, extend it similarly.
$order_ref = isset($body['merchant_order_id']) && is_string($body['merchant_order_id']) && $body['merchant_order_id'] !== ''
    ? $body['merchant_order_id']
    : ('ORD' . time() . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)));

$merchantTxnId = $order_ref;

$redirectUrl = $body['redirectUrl'] ?? null;
$udf1 = $body['udf1'] ?? 'udf1';
$udf2 = $body['udf2'] ?? 'udf2';
$udf3 = $body['udf3'] ?? 'udf3';
$udf4 = $body['udf4'] ?? 'udf4';
$udf5 = $body['udf5'] ?? 'udf5';

// Amount must be in paisa (int)
$amountPaisa = (int)round($total_amount * 100);

$customerName = trim(($shipping['firstName'] ?? '') . ' ' . ($shipping['lastName'] ?? ''));
if ($customerName === '') $customerName = $shipping['name'] ?? 'Guest';
$customerPhone = $shipping['phone'] ?? ($body['customer_phone'] ?? '');
$customerEmail = $shipping['email'] ?? ($body['customer_email'] ?? '');

$payRequestBuilder = \PhonePe\payments\v2\models\request\builders\StandardCheckoutPayRequestBuilder::builder();

$payRequest = $payRequestBuilder
    ->merchantOrderId($order_ref)
    ->amount($amountPaisa)
    // SDK sample uses redirectUrl; our repo config uses PHONEPE_REDIRECT_URL.
    ->redirectUrl($redirectUrl ?? ($body['phonepe_redirect_url'] ?? null) ?? (require __DIR__ . '/phonepe-sdk-config.php')['redirectUrl'])
    ->message($body['message'] ?? 'Order payment')
    ->udf1((string)$udf1)
    ->udf2((string)$udf2)
    ->udf3((string)$udf3)
    ->udf4((string)$udf4)
    ->udf5((string)$udf5)
    ->build();

try {
    $payResponse = $client->pay($payRequest);

    // Handle state
    $state = $payResponse->getState();
    if ($state === 'PENDING') {
        $redirect = $payResponse->getRedirectUrl();
        echo json_encode([
            'status' => true,
            'order_id' => $order_ref,
            'payment_state' => $state,
            'payment_url' => $redirect,
        ]);
        exit;
    }

    echo json_encode([
        'status' => false,
        'order_id' => $order_ref,
        'payment_state' => $state,
        'message' => 'Payment initiation failed',
    ]);
    exit;

} catch (\PhonePe\common\exceptions\PhonePeException $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Error initiating payment: ' . $e->getMessage()]);
    exit;
}

