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

// Get filters
$status     = $_GET['status'] ?? null;
$startDate  = $_GET['startdate'] ?? null;
$endDate    = $_GET['enddate'] ?? null;
$search     = $_GET['search'] ?? null;
$limit      = max(1, (int)($_GET['limit'] ?? 50));
$page       = max(1, (int)($_GET['page'] ?? 1));
$offset     = ($page - 1) * $limit;

try {
    $whereConditions = [];
    $params = [];

    // Status filter
    if ($status) {
        $whereConditions[] = "(status = ? OR order_status = ?)";
        $params[] = $status;
        $params[] = $status;
    }

    // Date range
    if ($startDate) {
        $whereConditions[] = "created_at >= ?";
        $params[] = $startDate . ' 00:00:00';
    }
    if ($endDate) {
        $whereConditions[] = "created_at <= ?";
        $params[] = $endDate . ' 23:59:59';
    }

    // Search
    if ($search) {
        $whereConditions[] = "(order_id LIKE ? OR customer_name LIKE ? OR customer_email LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $whereClause = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";

    // Count total records
    $countSql = "SELECT COUNT(*) FROM orders" . $whereClause;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    // Main Query
    $sql = "SELECT 
                id, order_id, user_id, customer_name, customer_email, customer_phone,
                shipping_address, city, state, country, pincode,
                payment_method, total_amount, subtotal, tax, shipping_cost,
                status, order_status, payment_status, created_at, updated_at
            FROM orders" 
            . $whereClause 
            . " ORDER BY created_at DESC 
               LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    
    // Bind all parameters + limit & offset
    $paramIndex = 1;
    foreach ($params as $value) {
        $stmt->bindValue($paramIndex++, $value);
    }
    $stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);

    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => true,
        'data' => [
            // 'pagination' => [
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
                'current_page' => $page,
                'limit' => $limit,
                // ]
                'orders' => $orders,
        ]
    ]);

} catch (PDOException $e) {
    fail('Database error: ' . $e->getMessage(), 500);
}