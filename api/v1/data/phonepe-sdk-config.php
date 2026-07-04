<?php
// PhonePe SDK configuration (STANDARD CHECKOUT / v2)
// Update placeholders with your actual PhonePe credentials.

// Composer package uses these values:
//  - clientId
//  - clientVersion
//  - clientSecret

// IMPORTANT:
// - Use Env::PRODUCTION for live.
// - Use Env::SANDBOX for testing.

return [
    'clientId' => 'YOUR_CLIENT_ID',
    'clientVersion' => 'YOUR_CLIENT_VERSION',
    'clientSecret' => 'YOUR_CLIENT_SECRET',

    // Choose environment
    'env' => 'SANDBOX', // change to 'PRODUCTION' when live

    // Redirect URL for PhonePe checkout
    'redirectUrl' => 'localhost:8000/phonepe-return.php',

    // Optional (depending on your flow). If you need callback handling,
    // PhonePe SDK typically supports callbackUrl in some flows.
    // This repo already has phonepe-callback.php, but the SDK sample
    // uses only redirectUrl.
    'callbackUrl' => 'https://your-domain.com/api/v1/data/phonepe-callback.php',
];

