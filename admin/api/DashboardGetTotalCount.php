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

// Get date range parameters
$startDate = $_GET['startdate'] ?? null;
$endDate   = $_GET['enddate']   ?? null;

function isValidDate($date) {
    return $date && DateTime::createFromFormat('Y-m-d', $date) !== false;
}

if (($startDate && !isValidDate($startDate)) || ($endDate && !isValidDate($endDate))) {
    fail('Invalid date format. Use YYYY-MM-DD', 400);
}

$now = new DateTime('now');
$todayStart = (clone $now)->setTime(0, 0, 0)->format('Y-m-d H:i:s');
$sevenDaysAgo = (clone $now)->modify('-7 days')->format('Y-m-d H:i:s');
$oneMonthAgo  = (clone $now)->modify('-1 month')->format('Y-m-d H:i:s');

try {
    // ==================== PRODUCTS ====================
    $totalProducts = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $publishedProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='published'")->fetchColumn();
    $draftProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='draft'")->fetchColumn();
    $totalCategories = (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();

    // ==================== USERS ====================
    $totalRegisteredUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $activeRegisteredUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= '{$oneMonthAgo}'")->fetchColumn();
    $totalGuestUsers = (int)$pdo->query("SELECT COUNT(DISTINCT id) FROM orders WHERE user_id IS NULL")->fetchColumn();

    // New Users
    $newUsersLast7Days = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= '{$sevenDaysAgo}'")->fetchColumn();
    $newUsersLast1Month = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= '{$oneMonthAgo}'")->fetchColumn();
    $todayNewUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= '{$todayStart}'")->fetchColumn();

    // ==================== ORDERS WITH PROPER QUERY BUILDING ====================
    $whereConditions = [];
    $params = [];

    if ($startDate) {
        $whereConditions[] = "created_at >= ?";
        $params[] = $startDate . ' 00:00:00';
    }
    if ($endDate) {
        $whereConditions[] = "created_at <= ?";
        $params[] = $endDate . ' 23:59:59';
    }

    $whereClause = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";

    // Total Orders
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders" . $whereClause);
    $stmt->execute($params);
    $totalOrders = (int)$stmt->fetchColumn();

    // Pending Orders
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders" . $whereClause . 
        (!empty($whereClause) ? " AND " : " WHERE ") . "(status = 'pending' OR order_status = 'pending')");
    $stmt->execute($params);
    $pendingOrders = (int)$stmt->fetchColumn();

    // Shipped Orders
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders" . $whereClause . 
        (!empty($whereClause) ? " AND " : " WHERE ") . "(status = 'shipped' OR order_status = 'shipped')");
    $stmt->execute($params);
    $shippedOrders = (int)$stmt->fetchColumn();

    // Delivered Orders
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders" . $whereClause . 
        (!empty($whereClause) ? " AND " : " WHERE ") . "(status = 'delivered' OR order_status = 'delivered')");
    $stmt->execute($params);
    $deliveredOrders = (int)$stmt->fetchColumn();

    // Cancelled Orders
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders" . $whereClause . 
        (!empty($whereClause) ? " AND " : " WHERE ") . "(status = 'cancelled' OR order_status = 'cancelled')");
    $stmt->execute($params);
    $cancelledOrders = (int)$stmt->fetchColumn();

    // Total Revenue (Delivered only)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders" . $whereClause . 
        (!empty($whereClause) ? " AND " : " WHERE ") . "(status = 'delivered' OR order_status = 'delivered')");
    $stmt->execute($params);
    $totalRevenue = (float)$stmt->fetchColumn();

    echo json_encode([
        'status' => true,
        'data' => [
            'totalProducts' => $totalProducts,
            'totalPublishedProducts' => $publishedProducts,
            'totalDraftProducts' => $draftProducts,
            'totalCategories' => $totalCategories,

            'totalRegisteredUsers' => $totalRegisteredUsers,
            'totalActiveRegisteredUsers' => $activeRegisteredUsers,
            'totalGuestUsers' => $totalGuestUsers,
            'totalUserCount' => $totalRegisteredUsers,

            'totalNewUsersLast7Days' => $newUsersLast7Days,
            'totalNewUsersLast1Month' => $newUsersLast1Month,
            'todayTotalNewUsers' => $todayNewUsers,

            'totalOrders' => $totalOrders,
            'pendingOrders' => $pendingOrders,
            'shippedOrders' => $shippedOrders,
            'deliveredOrders' => $deliveredOrders,
            'cancelledOrders' => $cancelledOrders,
            'totalRevenue' => round($totalRevenue, 2),
        ],
        'meta' => [
            'dateFilterApplied' => !empty($whereClause),
            'startDate' => $startDate,
            'endDate' => $endDate
        ]
    ]);

} catch (PDOException $e) {
    fail('Database error: ' . $e->getMessage(), 500);
}