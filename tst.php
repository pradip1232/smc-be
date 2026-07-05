<?php
// paymentStatus.php - Clean JSON Response with status & message



// paymentStatus.php - Clean JSON Response with status & message
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

// === DATABASE CONNECTION ===
include '../db/db.php'; // ← Create this file if it doesn't exist (see below)

$merchantOrderId = $_SESSION['merchantOrderId'] ?? $_GET['merchantOrderId'] ?? $_GET['morderid'] ?? null;
$UserId = $_SESSION['UserId'] ?? $_GET['UserId'] ?? null;

if (!$merchantOrderId) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'Order ID not found'
    ]);
    exit;
}

// === PHONEPE STATUS CHECK ===
$statusUrl = "https://api-preprod.phonepe.com/apis/pg-sandbox/checkout/v2/order/" . $merchantOrderId . "/status";
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => $statusUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'Authorization: O-Bearer ' . trim($accessToken)
    ),
));
$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

if (curl_errno($curl)) {
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'cURL Error: ' . curl_error($curl)
    ]);
    curl_close($curl);
    exit;
}
curl_close($curl);

$data = json_decode($response, true);

// === UPDATE PAYMENT STATE IN DATABASE ===
$paymentState = strtoupper($data['state'] ?? 'UNKNOWN');

if (isset($conn) && ($UserId || $merchantOrderId)) {
    $sql = "UPDATE payments 
            SET paymentstate = ? 
            WHERE (userid = ? OR merchantOrderId = ?) 
            LIMIT 1";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sss", $paymentState, $UserId, $merchantOrderId);
        $stmt->execute();
        $stmt->close();
    }
    // Optional: log if no rows updated
    // if ($conn->affected_rows === 0) { error_log("No payment record updated for order: $merchantOrderId"); }
}

// === FINAL CLEAN RESPONSE ===
if (isset($data['state'])) {
    $state = strtoupper($data['state']);
    $isSuccess = ($state === 'COMPLETED');
    $message = match($state) {
        'COMPLETED' => 'Payment Successful',
        'FAILED' => 'Payment Failed',
        'PENDING' => 'Payment is Pending',
        default => 'Unknown Payment Status'
    };

    echo json_encode([
        'status' => $isSuccess,
        'message' => $message,
        'state' => $state,
        'data' => [
            'merchantOrderId' => $merchantOrderId,
            'phonepeOrderId' => $data['orderId'] ?? null,
            'amount' => isset($data['amount']) ? $data['amount'] / 100 : 0,
            'errorCode' => $data['errorCode'] ?? null,
            'raw' => $data
        ]
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch payment status from PhonePe',
        'data' => null
    ]);
}

unset($_SESSION['merchantOrderId']);
?>









// header('Content-Type: application/json');
// header('Access-Control-Allow-Origin: http://localhost:5173');
// header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
// header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
// header('Access-Control-Allow-Credentials: true');

// if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
//     http_response_code(204);
//     exit;
// }

// session_start();
// include 'accessToken.php';

// $merchantOrderId = $_SESSION['merchantOrderId'] ?? $_GET['merchantOrderId'] ?? $_GET['morderid'] ?? null;
// $UserId = $_SESSION['UserId'] ?? $_GET['UserId'] ?? null;

// if (!$merchantOrderId) {
//     http_response_code(400);
//     echo json_encode([
//         'status' => false,
//         'message' => 'Order ID not found'
//     ]);
//     exit;
// }

// // === PHONEPE STATUS CHECK ===
// $statusUrl = "https://api-preprod.phonepe.com/apis/pg-sandbox/checkout/v2/order/" . $merchantOrderId . "/status";

// $curl = curl_init();
// curl_setopt_array($curl, array(
//     CURLOPT_URL => $statusUrl,
//     CURLOPT_RETURNTRANSFER => true,
//     CURLOPT_TIMEOUT => 30,
//     CURLOPT_HTTPHEADER => array(
//         'Content-Type: application/json',
//         'Authorization: O-Bearer ' . trim($accessToken)
//     ),
// ));

// $response = curl_exec($curl);
// $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

// if (curl_errno($curl)) {
//     http_response_code(500);
//     echo json_encode([
//         'status' => false,
//         'message' => 'cURL Error: ' . curl_error($curl)
//     ]);
//     curl_close($curl);
//     exit;
// }

// curl_close($curl);

// $data = json_decode($response, true);

// // === FINAL CLEAN RESPONSE ===
// if (isset($data['state'])) {
    
//     $state = strtoupper($data['state']);
//     $isSuccess = ($state === 'COMPLETED');

//     $message = match($state) {
//         'COMPLETED' => 'Payment Successful',
//         'FAILED'    => 'Payment Failed',
//         'PENDING'   => 'Payment is Pending',
//         default     => 'Unknown Payment Status'
//     };

//     echo json_encode([
//         'status'  => $isSuccess,
//         'message' => $message,
//         'state'   => $state,
//         'data'    => [
//             'merchantOrderId' => $merchantOrderId,
//             'phonepeOrderId'  => $data['orderId'] ?? null,
//             'amount'          => isset($data['amount']) ? $data['amount'] / 100 : 0,
//             'errorCode'       => $data['errorCode'] ?? null,
//             'raw'             => $data   // Full PhonePe response (for debugging)
//         ]
//     ]);

// } else {
//     http_response_code(400);
//     echo json_encode([
//         'status'  => false,
//         'message' => 'Failed to fetch payment status from PhonePe',
//         'data'    => null
//     ]);
// }

// unset($_SESSION['merchantOrderId']);
// ?>