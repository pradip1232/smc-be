

    // include 'accessToken.php';

    // $curl = curl_init();
    // $morderid = "ORD" . time();

    // curl_setopt_array($curl, array(
    // CURLOPT_URL =>  'https://api-preprod.phonepe.com/apis/pg-sandbox/checkout/v2/pay',
    // CURLOPT_RETURNTRANSFER => true,
    // CURLOPT_ENCODING => '',
    // CURLOPT_MAXREDIRS => 10,
    // CURLOPT_TIMEOUT => 0,
    // CURLOPT_FOLLOWLOCATION => true,
    // CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    // CURLOPT_CUSTOMREQUEST => 'POST',
    // CURLOPT_POSTFIELDS =>'{
    //     "merchantOrderId": "'.$morderid.'",
    //     "amount": 1000,
    //     "expireAfter": 1200,
    //     "metaInfo": {
    //         "udf1": "test1",
    //         "udf2": "new param2",
    //         "udf3": "test3",
    //         "udf4": "dummy value 4",
    //         "udf5": "addition infor ref1"
    //     },
    //     "paymentFlow": {
    //         "type": "PG_CHECKOUT",
    //         "message": "Payment message used for collect requests",
    //         "merchantUrls": {
    //             "redirectUrl": "localhsot/smc/pay/response.php",
    //             "webhookUrl": "https://webhook.site/your-webhook-url"

    //         }
    //     } 
    // }',
    // CURLOPT_HTTPHEADER => array(
    //     'Content-Type: application/json',
    //     'Authorization: O-Bearer '.$accessToken
    // ),
    // ));

    // $response = curl_exec($curl);

    // print_r($response);

    // curl_close($curl);

    // $getPaymentInfo=json_decode($response,true);

    //     if(isset($getPaymentInfo['redirectUrl']) && $getPaymentInfo['redirectUrl'] !=''){
    //         $orderid=$getPaymentInfo['orderId'];
    //         $redirectTokenurl=$getPaymentInfo['redirectUrl'];
        
    //     }



// This is a simple index file to demonstrate the setup of the PhonePe PHP SDK for U

// include 'accessToken.php';

// $curl = curl_init();

// $morderid = "SMC" . time();

// $payload = [
//     "merchantOrderId" => $morderid,
//     "amount" => 1000,
//     "expireAfter" => 1200,
//     "metaInfo" => [
//         "udf1" => "test1",
//         "udf2" => "new param2",
//         "udf3" => "test3",
//         "udf4" => "dummy value 4",
//         "udf5" => "addition infor ref1"
//     ],
//     "paymentFlow" => [
//         "type" => "PG_CHECKOUT",
//         "message" => "Payment message used for collect requests",
//         "merchantUrls" => [
//             "redirectUrl" => "http://localhost/smc/pay/paymentStatus.php",   // Fixed typo + full URL
//             "webhookUrl" => "https://webhook.site/your-webhook-url"
//         ]
//     ]
// ];

// curl_setopt_array($curl, array(
//     CURLOPT_URL => 'https://api-preprod.phonepe.com/apis/pg-sandbox/checkout/v2/pay',
//     CURLOPT_RETURNTRANSFER => true,
//     CURLOPT_ENCODING => '',
//     CURLOPT_MAXREDIRS => 10,
//     CURLOPT_TIMEOUT => 30,
//     CURLOPT_FOLLOWLOCATION => true,
//     CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//     CURLOPT_CUSTOMREQUEST => 'POST',
//     CURLOPT_POSTFIELDS => json_encode($payload),        // Properly JSON encoded
//     CURLOPT_HTTPHEADER => array(
//         'Content-Type: application/json',
//         'Authorization: O-Bearer ' . $accessToken       // Fixed spacing
//     ),
// ));

// $response = curl_exec($curl);
// $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);   // Important for debugging

// if (curl_errno($curl)) {
//     echo 'cURL Error: ' . curl_error($curl);
//     curl_close($curl);
//     exit;
// }

// curl_close($curl);

// echo "<h3>HTTP Status: $httpCode</h3>";
// echo "<pre>";
// print_r($response);
// echo "</pre>";

// $getPaymentInfo = json_decode($response, true);

// if (isset($getPaymentInfo['redirectUrl']) && !empty($getPaymentInfo['redirectUrl'])) {
//     $orderid = $getPaymentInfo['orderId'] ?? '';
//     $redirectTokenurl = $getPaymentInfo['redirectUrl'];
    
//     // Actually redirect the user to PhonePe payment page
//     header("Location: " . $redirectTokenurl);
//     exit();
// } else {
//     echo "<h3>Payment Initiation Failed</h3>";
//     echo "Response: <pre>";
//     print_r($getPaymentInfo);
//     echo "</pre>";
    
//     if (isset($getPaymentInfo['error'])) {
//         echo "Error: " . $getPaymentInfo['error'];
//     }
// php// }

include 'accessToken.php';

$curl = curl_init();
$morderid = "ORD" . time();

$payload = [
    "merchantOrderId" => $morderid,
    "amount" => 1000,                    // ₹10.00
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
            "redirectUrl" => "http://localhost/smc/pay/paymentStatus.php",   // Must be full URL
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
    // Save order ID in session for later use in response.php
    session_start();
    $_SESSION['merchantOrderId'] = $morderid;
    
    // header("Location: " . $data['redirectUrl']);
    echo "<h3>Redirecting to PhonePe Payment Page...</h3>";
    //    echo "<pre>";
    // print_r($data);
    // echo "</pre>";
    exit();
} else {
    echo "<h3>Failed to Initiate Payment</h3>";
    echo "HTTP Code: " . $httpCode . "<br>";
    echo "<pre>";
    print_r($data);
    echo "</pre>";
}
?>