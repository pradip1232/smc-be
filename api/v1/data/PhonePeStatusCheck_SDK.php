<?php
// PhonePeStatusCheck_SDK.php - checks payment status using official Composer SDK.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$merchantOrderId = null;
$body = json_decode(file_get_contents('php://input'), true);
if (is_array($body)) {
    $merchantOrderId = $body['merchant_order_id'] ?? $body['merchantOrderId'] ?? null;
}
if (!$merchantOrderId) {
    $merchantOrderId = $_POST['merchant_order_id'] ?? $_POST['merchantOrderId'] ?? null;
}

if (!$merchantOrderId) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'merchant_order_id is required']);
    exit;
}

try {
    $client = require __DIR__ . '/phonepe-sdk-init.php';

    // getOrderStatus signature depends on SDK version; based on your snippet:
    // getOrderStatus($merchantOrderId, true)
    $statusCheckResponse = $client->getOrderStatus($merchantOrderId, true);

    $resp = [
        'status' => true,
        'order_id' => $statusCheckResponse->getOrderId(),
        'state' => $statusCheckResponse->getState(),
        'expireAt' => $statusCheckResponse->getExpireAt(),
        'amount' => $statusCheckResponse->getAmount(),
        'metaInfo' => $statusCheckResponse->getMetaInfo(),
    ];

    // errorCode/detailedErrorCode exist only when FAILED
    if (method_exists($statusCheckResponse, 'getErrorCode')) {
        $resp['errorCode'] = $statusCheckResponse->getErrorCode();
    }
    if (method_exists($statusCheckResponse, 'getDetailedErrorCode')) {
        $resp['detailedErrorCode'] = $statusCheckResponse->getDetailedErrorCode();
    }

    if (method_exists($statusCheckResponse, 'getPaymentDetails')) {
        $resp['paymentDetails'] = $statusCheckResponse->getPaymentDetails();
    }

    echo json_encode($resp);
    exit;

} catch (\PhonePe\common\exceptions\PhonePeException $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
    exit;
}

