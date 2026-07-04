<?php
// PhonePe configuration - update these values for your merchant
define('PHONEPE_BASE', 'https://pg-sandbox.phonepe.com/v3/pg');
define('PHONEPE_MERCHANT_ID', 'YOUR_MERCHANT_ID');
define('PHONEPE_MERCHANT_KEY', 'YOUR_SECRET_KEY');
define('PHONEPE_REDIRECT_URL', 'https://your-domain.com/phonepe-return.php');
define('PHONEPE_CALLBACK_URL', 'https://your-domain.com/api/v1/data/phonepe-callback.php');

function generatePhonePeSignature(string $payloadJson): string
{
    return hash_hmac('sha256', $payloadJson, PHONEPE_MERCHANT_KEY);
}

function createPhonePePaymentUrl(array $payload): array
{
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $signature = generatePhonePeSignature($json);

    $ch = curl_init(PHONEPE_BASE . '/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-VERIFY: ' . $signature,
        'X-MERCHANT-ID: ' . PHONEPE_MERCHANT_ID,
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception('PhonePe request failed: ' . $err);
    }
    curl_close($ch);

    $result = json_decode($response, true);
    return $result ?: ['success' => false, 'raw' => $response];
}

?>