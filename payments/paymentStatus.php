<?php
// paymentStatus.php
session_start();
include 'accessToken.php';

$merchantOrderId = $_SESSION['merchantOrderId'] ?? $_GET['merchantOrderId'] ?? null;

if (!$merchantOrderId) {
    die("<h3 style='color:red;'>Order ID not found.</h3>");
}

// === CORRECT STATUS ENDPOINT ===
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
    die('cURL Error: ' . curl_error($curl));
}
curl_close($curl);

$data = json_decode($response, true);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment Status</title>
    <style>
        body { font-family: Arial; text-align: center; padding: 50px; }
        .success { color: green; font-size: 26px; }
        .failed  { color: red; font-size: 26px; }
        .pending { color: orange; font-size: 24px; }
    </style>
</head>
<body>

<h2>Payment Status</h2>

<?php if (isset($data['state'])): ?>
    
    <strong>Merchant Order ID:</strong> <?= htmlspecialchars($merchantOrderId) ?><br><br>
    <strong>PhonePe Order ID:</strong> <?= htmlspecialchars($data['orderId'] ?? 'N/A') ?><br>
    <strong>State:</strong> <?= htmlspecialchars($data['state']) ?><br>
    <strong>Amount:</strong> <?= isset($data['amount']) ? $data['amount']/100 : 0 ?> INR<br><br>

    <?php if ($data['state'] === 'COMPLETED'): ?>
        <h3 class="success">✅ Payment Successful!</h3>
        
    <?php elseif ($data['state'] === 'FAILED'): ?>
        <h3 class="failed">❌ Payment Failed</h3>
        Error: <?= htmlspecialchars($data['errorCode'] ?? '') ?> 
        
    <?php elseif ($data['state'] === 'PENDING'): ?>
        <h3 class="pending">⏳ Payment is Pending</h3>
        
    <?php else: ?>
        <h3>Unknown Status</h3>
    <?php endif; ?>

    <br><hr><br>
    <details>
        <summary>Full Response (Debug)</summary>
        <pre><?= htmlspecialchars(print_r($data, true)) ?></pre>
    </details>

<?php else: ?>
    <h3 style="color:red;">Failed to fetch status</h3>
    <strong>HTTP Code:</strong> <?= $httpCode ?><br>
    <pre><?= htmlspecialchars($response) ?></pre>
<?php endif; ?>

</body>
</html>

<?php unset($_SESSION['merchantOrderId']); ?>