<?php
// Shared CORS and JSON response headers for API endpoints.
$allowedOrigins = [
    'http://localhost:5173',
    'http://localhost',
    'http://127.0.0.1:5173',
    'http://127.0.0.1',
    'https://shreemahaveercollections.com',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-ADMIN-TOKEN');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (!$origin || !in_array($origin, $allowedOrigins, true)) {
        http_response_code(403);
        exit;
    }
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');
