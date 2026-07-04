
<!-- 
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

<?php unset($_SESSION['merchantOrderId']); ?> -->