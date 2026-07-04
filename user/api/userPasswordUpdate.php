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

$currentPassword = (string)($input['current_password'] ?? '');
$newPassword = (string)($input['new_password'] ?? '');

if ($currentPassword === '' || $newPassword === '') {
    fail('Missing current_password or new_password', 400);
}

try {
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) fail('User not found', 404);

    $stored = $row['password'];
    $isValid = false;

    if (is_string($stored) && strlen($stored) > 0 && preg_match('/^\$2y\$/', $stored)) {
        $isValid = password_verify($currentPassword, $stored);
    } else {
        $isValid = hash_equals((string)$stored, (string)$currentPassword);
        if (!$isValid && is_string($stored) && str_starts_with($stored, '$2')) {
            $isValid = password_verify($currentPassword, $stored);
        }
    }

    if (!$isValid) fail('Current password is incorrect', 401);

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    $upd = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    $upd->execute([$hash, $userId]);

    echo json_encode(['status' => true, 'message' => 'Password updated successfully']);
} catch (PDOException $e) {
    fail('Database error: ' . $e->getMessage(), 500);
}

