<?php
// pay.php
include 'accessToken.php';

$curl = curl_init();
$morderid = "ORD" . time();

function MakePayementSMC(   $payload){
  
    echo "Inside MakePayementSMC function <br>";
    // &payload
    // echo "<pre>"; 
    // print_r($payload);
    // echo "</pre>";



// Array
// (
//     [merchantOrderId] => SMC-ODR-00021
//     [amount] => 27061640
//     [currency] => INR
//     [customerId] => 6546546513
//     [customerPhone] => 6546546513
//     [customerEmail] => 
//     [merchantTxnId] => SMC-ODR-00021
// )

    // echo "merchantOrderId: " . $payload["merchantOrderId"]  . "<br>";

    // echo "amount: " . $payload["amount"]  . "<br>";
    // echo "currency: " . $payload["currency"]  . "<br>";q
    // echo "customerId: " . $payload["customerId"]  . "<br>";
    // echo "customerPhone: " . $payload["customerPhone"]  . "<br

    // echo "customerEmail: " . $payload["customerEmail"]  . "<br>";
    // echo "merchantTxnId: " . $payload["merchantTxnId"]  . "<br>";
    $moorderid = $payload["merchantOrderId"];



$payload = [
    "merchantOrderId" => $morderid,
    "amount" => 1000,
    "expireAfter" => 1200,
    "metaInfo" => [
        "udf1" => "test1",
        "udf2" => "new param2",
        "udf3" => "test3",
        "udf4" => "dummy value 4",
        "udf5" => "addition infor ref1"
    ],
    "paymentFlow" => [
        "type" => "PG_CHECKOUT",
        "message" => "Payment message used for collect requests",
        "merchantUrls" => [
            "redirectUrl" => "http://localhost/smc/pay/paymentStatus.php",  
            "webhookUrl" => "https://webhook.site/your-webhook-url"
        ]
    ]
];

curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://api-preprod.phonepe.com/apis/pg-sandbox/checkout/v2/pay',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($payload),
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

if (isset($data['redirectUrl']) && !empty($data['redirectUrl'])) {
    
    session_start();
    $_SESSION['merchantOrderId'] = $morderid;   // Save for status check
    

    //  echo "<pre>";
    // print_r($data);
    // echo "</pre>";
    // === ACTUAL REDIRECT TO PHONEPE ===
    header("Location: " . $data['redirectUrl']);
    exit();
    
} else {
    // Error case
    echo "<h3>Failed to Initiate Payment</h3>";
    echo "HTTP Code: " . $httpCode . "<br>";
    echo "<pre>";
    print_r($data);
    echo "</pre>";
}

}
?>