<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => false, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$tokenUserId = $_SERVER['HTTP_X_USER_ID'] ?? null;
$userId = null;
if ($tokenUserId !== null && $tokenUserId !== '') {
    $userId = (int)$tokenUserId;
}

if (!$userId) {
    // No auth system in this repo; expect user_id via header.
    // If you have another auth approach, update here.
    fail('Missing user_id (send X-USER-ID header)', 401);
}

try {
    // We don't have orders/cart tables in current schema.sql.
    // Provide what we can: profile + user status.

    $userStmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone_number, status, created_at, updated_at FROM users WHERE id = ? LIMIT 1');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) fail('User not found', 404);

    // Total user count
    $totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    echo json_encode([
        'status' => true,
        'message' => 'Dashboard totals fetched successfully',
        'data' => [
            'user' => $user,
            'totalUsers' => $totalUsers,
            // placeholders for future tables
            'totalOrders' => 0,
            'totalActiveOrders' => 0,
            'totalPendingOrders' => 0
        ]
    ]);
} catch (PDOException $e) {
    fail('Database error: ' . $e->getMessage(), 500);
}

