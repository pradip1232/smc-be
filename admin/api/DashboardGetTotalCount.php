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

// Helpers for time windows
$now = new DateTime('now');
$todayStart = (clone $now)->setTime(0, 0, 0)->format('Y-m-d H:i:s');
$sevenDaysAgo = (clone $now)->modify('-7 days')->format('Y-m-d H:i:s');
$oneMonthAgo = (clone $now)->modify('-1 month')->format('Y-m-d H:i:s');

try {
    // Products
    $totalProducts = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $publishedProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='published'")->fetchColumn();
    $draftProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='draft'")->fetchColumn();

    // Categories
    $totalCategories = (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();

    // Users
    $totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    // "actively" (based on last_login in admins schema)
    // Since we only have users table in schema with created_at, we approximate:
    // - active users = created within last 7 days
    $activeUsersLast7Days = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= '{$sevenDaysAgo}'")->fetchColumn();

    $newUsersLast7Days = $activeUsersLast7Days;
    $newUsersLast1Month = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= '{$oneMonthAgo}'")->fetchColumn();
    $todayNewUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= '{$todayStart}'")->fetchColumn();

    echo json_encode([
        'status' => true,
        'data' => [
            'totalProductCount' => $totalProducts,
            'totalPublishedProducts' => $publishedProducts,
            'totalDraftProducts' => $draftProducts,
            'totalProducts' => $totalProducts,
            'totalCategories' => $totalCategories,

            'totalUserActiveLast7Days' => $activeUsersLast7Days,
            'totalNewUsersLast7Days' => $newUsersLast7Days,
            'totalNewUsersLast1Month' => $newUsersLast1Month,
            'todayTotalNewUsers' => $todayNewUsers,
            'totalUserCount' => $totalUsers
        ],
        'meta' => [
            'todayStart' => $todayStart,
            'sevenDaysAgo' => $sevenDaysAgo,
            'oneMonthAgo' => $oneMonthAgo
        ]
    ]);
} catch (PDOException $e) {
    fail('Database error: ' . $e->getMessage(), 500);
}

