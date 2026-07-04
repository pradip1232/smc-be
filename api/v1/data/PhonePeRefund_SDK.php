<?php
// PhonePeRefund_SDK.php - issues refund using official Composer SDK.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = $_POST;

$originalMerchantOrderId = $body['original_merchant_order_id'] ?? $body['originalMerchantOrderId'] ?? null;
$merchantRefundId = $body['merchant_refund_id'] ?? $body['merchantRefundId'] ?? null;
$amount = $body['amount'] ?? null; // expected in paisa OR rupees? we'll treat as rupees if decimal.

if (!$originalMerchantOrderId || !$amount) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'original_merchant_order_id and amount are required']);
    exit;
}

if (!$merchantRefundId) {
    $merchantRefundId = 'REFUND_' . time();
}

// Convert amount to paisa
// If amount is numeric with decimals, treat as rupees. If it's integer, assume paisa.
if (is_numeric($amount) && (string)$amount !== (string)(int)$amount) {
    $amountPaisa = (int)round((float)$amount * 100);
} else {
    $amountPaisa = (int)$amount;
}

try {
    $client = require __DIR__ . '/phonepe-sdk-init.php';

    $refundRequest = \PhonePe\payments\v2\models\request\builders\StandardCheckoutRefundRequestBuilder::builder()
        ->merchantRefundId((string)$merchantRefundId)
        ->originalMerchantOrderId((string)$originalMerchantOrderId)
        ->amount($amountPaisa)
        ->build();

    $refundResponse = $client->refund($refundRequest);

    echo json_encode([
        'status' => true,
        'refund_id' => $refundResponse->getRefundId(),
        'amount' => $refundResponse->getAmount(),
        'state' => $refundResponse->getState(),
    ]);
    exit;

} catch (\PhonePe\common\exceptions\PhonePeException $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Error initiating refund: ' . $e->getMessage()]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Error initiating refund: ' . $e->getMessage()]);
    exit;
}

