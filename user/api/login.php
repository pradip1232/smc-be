<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => false, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$email = trim((string)($input['email'] ?? ''));
$password = (string)($input['password'] ?? '');

if ($email === '' || $password === '') {
    fail('Missing email or password', 400);
}

try {
    // Current schema: users(id, first_name, last_name, email, phone_number, password, status, created_at, updated_at)
    $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone_number, password, status, created_at, updated_at FROM users WHERE email = ? LIMIT 1');

    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$row) {
        fail('Invalid credentials', 401);
    }

    $stored = $row['password'];

    $isValid = false;
    if (is_string($stored) && strlen($stored) > 0 && preg_match('/^\$2y\$/', $stored)) {
        $isValid = password_verify($password, $stored);
    } else {
        // Dev/dev-legacy compatibility: support plain text passwords
        $isValid = hash_equals((string)$stored, (string)$password);

        // bcrypt without $2y$ prefix can still validate
        if (!$isValid && is_string($stored) && str_starts_with($stored, '$2')) {
            $isValid = password_verify($password, $stored);
        }
    }

    if (!$isValid) {
        fail('Invalid credentials', 401);
    }

    unset($row['password']);

    echo json_encode([
        'status' => true,
        'message' => 'Login success',
        'data' => $row
    ]);
} catch (PDOException $e) {
    // Return more detail during debugging (remove in production)
    fail('Database error: ' . $e->getMessage(), 500);
}


