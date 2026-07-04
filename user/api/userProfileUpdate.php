<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => false, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    fail('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$userId = null;
$tokenUserId = $_SERVER['HTTP_X_USER_ID'] ?? null;
if ($tokenUserId !== null && $tokenUserId !== '') {
    $userId = (int)$tokenUserId;
}

if (!$userId) {
    fail('Missing user_id (send X-USER-ID header)', 401);
}

$allowed = [
    'first_name',
    'last_name',
    'email',
    'phone_number',
    'city',
    'state',
    'country',
    'landmark_address',
    'status'
];

$sets = [];
$params = [];
foreach ($allowed as $field) {
    if (array_key_exists($field, $input)) {
        $sets[] = "$field = ?";
        $params[] = $input[$field];
    }
}

if (empty($sets)) {
    fail('No fields to update', 400);
}

try {
    $params[] = $userId;
    $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        fail('User not found or no changes made', 404);
    }

    $get = $pdo->prepare('SELECT id, first_name, last_name, email, phone_number, city, state, country, landmark_address, status, created_at, updated_at FROM users WHERE id = ? LIMIT 1');
    $get->execute([$userId]);
    $user = $get->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => true,
        'message' => 'Profile updated successfully',
        'data' => $user
    ]);
} catch (PDOException $e) {
    // e.g. duplicate email/phone
    if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
        fail('Email or phone_number already exists', 409);
    }
    fail('Database error: ' . $e->getMessage(), 500);
}

