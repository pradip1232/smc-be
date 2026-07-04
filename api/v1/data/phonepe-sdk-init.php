<?php
// Creates PhonePe SDK client instance.

require_once 'vendor/autoload.php';

use PhonePe\payments\v2\standardCheckout\StandardCheckoutClient;
use PhonePe\Env;

$config = require __DIR__ . '/phonepe-sdk-config.php';

$clientId = (string)($config['clientId'] ?? '');
$clientVersion = (string)($config['clientVersion'] ?? '');
$clientSecret = (string)($config['clientSecret'] ?? '');

$envRaw = strtoupper((string)($config['env'] ?? 'SANDBOX'));
$env = ($envRaw === 'PRODUCTION') ? Env::PRODUCTION : Env::SANDBOX;

if ($clientId === '' || $clientVersion === '' || $clientSecret === '') {
    throw new RuntimeException('PhonePe SDK config missing: clientId/clientVersion/clientSecret');
}

$client = StandardCheckoutClient::getInstance(
    $clientId,
    $clientVersion,
    $clientSecret,
    $env
);

return $client;

