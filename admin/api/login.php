<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';
if (!$username || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing username or password']);
    exit;
}
$stmt = $pdo->prepare('SELECT id, password FROM admins WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$row = $stmt->fetch();
if ($row && password_verify($password, $row['password'])) {
    echo json_encode(['success' => true, 'admin_id' => $row['id']]);
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
}
