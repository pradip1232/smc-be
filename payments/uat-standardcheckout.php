<?php
// UAT (Sandbox) StandardCheckoutClient bootstrap
// Install: composer require phonepe/phonepe-php (or as per your existing vendor)

require_once __DIR__ . '/../vendor/autoload.php';

use PhonePe\payments\v2\standardCheckout\StandardCheckoutClient;
use PhonePe\Env;

// Replace with your credentials (Sandbox/UAT)
$clientId = 'YOUR_CLIENT_ID';
$clientVersion = 'YOUR_CLIENT_VERSION';
$clientSecret = 'YOUR_CLIENT_SECRET';

// UAT / Sandbox
$env = Env::SANDBOX;

$client = StandardCheckoutClient::getInstance(
    (string) $clientId,
    (string) $clientVersion,
    (string) $clientSecret,
    $env
);

// At this point $client is ready.
// Next step: use SDK methods from your CreateOrderOnline_SDK.php flow.
// For quick verification, output basic confirmation.

header('Content-Type: text/plain; charset=utf-8');
echo 'PhonePe StandardCheckoutClient initialized for UAT (SANDBOX).';

