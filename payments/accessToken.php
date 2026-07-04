<?php
$curl = curl_init();



$client_id = 'M223WSLLP5BWD_2606141904';
$client_version = 1;
$client_secret = "YzE2NmVkZTktZTU2Mi00ZmYyLWEwMGItZWQ2MzMyYmI5ZWFh";
$grant_type = 'client_credentials' || null;
// curl_setopt_array($curl, array(
//   CURLOPT_URL =>  'https://api-preprod.phonepe.com/apis/pg-sandbox/v1/oauth/token',
//   CURLOPT_RETURNTRANSFER => true,
//   CURLOPT_ENCODING => '',
//   CURLOPT_MAXREDIRS => 10,
//   CURLOPT_TIMEOUT => 0,
//   CURLOPT_FOLLOWLOCATION => true,
//   CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//   CURLOPT_CUSTOMREQUEST => 'POST',
//   CURLOPT_POSTFIELDS => 'client_id='.$client_id.'&client_version='.$client_version.'&client_secret='.$client_secret,
//   CURLOPT_HTTPHEADER => array(
//     'Content-Type: application/x-www-form-urlencoded'
//   ),
// ));


// $response = curl_exec($curl);

// curl_close($curl);
// $getToken=json_decode($response, true) ;

// //echo $getToken['access_token'];
// if(isset($getToken['access_token']) && $getToken['access_token'] !=''){
//     $accessToken=$getToken['access_token'];
//     $expires_at=$getToken['expires_at'];
// // Save this details in the database to use access token and check expiry

// }else{
//     $accessToken='';
//     $expires_at='';
// }



// echo $client_id . "<br>";
// echo $client_version . "<br>";
// echo $client_secret . "<br>";
// echo $grant_type . "<br>";

// echo "<pre>";
// print_r($getToken);
// echo "</pre>";
// exit;




$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api-preprod.phonepe.com/apis/pg-sandbox/v1/oauth/token',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'client_id'      => $client_id,
        'client_version' => $client_version,
        'client_secret'  => $client_secret,
        'grant_type'     => $grant_type
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded'
    ]
]);

$response = curl_exec($curl);

if (curl_errno($curl)) {
    die('cURL Error: ' . curl_error($curl));
}

curl_close($curl);

$getToken = json_decode($response, true);

echo "<pre>";
print_r($getToken);
echo "</pre>";

if (
    isset($getToken['access_token']) &&
    !empty($getToken['access_token'])
) {
    $accessToken = $getToken['access_token'];
    $expires_at  = $getToken['expires_at'];

    // echo "<br><b>Access Token:</b> " . $accessToken;
    // echo "<br><b>Expires At:</b> " . $expires_at;

    // Save to database if needed

} else {

    echo "<br><b>Token Generation Failed</b>";
}