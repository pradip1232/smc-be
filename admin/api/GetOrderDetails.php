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

$offset = ($page - 1) * $limit;

try {
    $whereConditions = [];
    $params = [];

    // Build common WHERE conditions
    if ($status) {
        $whereConditions[] = "(status = ? OR order_status = ?)";
        $params[] = $status;
        $params[] = $status;
    }
    if ($startDate) {
        $whereConditions[] = "created_at >= ?";
        $params[] = $startDate . ' 00:00:00';
    }
    if ($endDate) {
        $whereConditions[] = "created_at <= ?";
        $params[] = $endDate . ' 23:59:59';
    }
    if ($search) {
        $whereConditions[] = "(order_id LIKE ? OR customer_name LIKE ? OR customer_email LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    $whereClause = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";

    // ====================== SUMMARY COUNTS ======================
    $countSql = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status IN ('pending','new','to_accept') OR order_status IN ('pending','new','to_accept') THEN 1 ELSE 0 END) as to_accept,
                    SUM(CASE WHEN status IN ('processing','to_pack','packed') OR order_status IN ('processing','to_pack','packed') THEN 1 ELSE 0 END) as to_pack,
                    SUM(CASE WHEN status IN ('transit','shipped','in_transit','out_for_delivery') OR order_status IN ('transit','shipped') THEN 1 ELSE 0 END) as in_transit,
                    SUM(CASE WHEN status IN ('completed','delivered','done') OR order_status IN ('completed','delivered') THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status IN ('upcoming','scheduled') OR order_status IN ('upcoming','scheduled') THEN 1 ELSE 0 END) as upcoming
                 FROM orders" . $whereClause;

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $summary = $countStmt->fetch(PDO::FETCH_ASSOC);

    // ====================== PAGINATION COUNT ======================
    $totalRecords = (int)$summary['total_orders'];
    $totalPages = ceil($totalRecords / $limit);

    // ====================== MAIN ORDERS QUERY ======================
    $sql = "SELECT 
                id, order_id, user_id, merchant_id, customer_name, customer_email, customer_phone,
                shipping_address, city, state, country, pincode,
                payment_method, total_amount, subtotal, tax, shipping_cost,
                status, order_status, payment_status, created_at, updated_at
            FROM orders" 
            . $whereClause 
            . " ORDER BY created_at DESC 
               LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    
    $paramIndex = 1;
    foreach ($params as $value) {
        $stmt->bindValue($paramIndex++, $value);
    }
    $stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);

    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ====================== FINAL RESPONSE ======================
    echo json_encode([
        'status' => true,
        'data' => [
            'total_records' => $totalRecords,
            'total_pages'   => $totalPages,
            'current_page'  => $page,
            'limit'         => $limit,
            
            // Summary Counts Added Here
            'total_orders'  => (int)$summary['total_orders'],
            'to_accept'     => (int)$summary['to_accept'],
            'to_pack'       => (int)$summary['to_pack'],
            'in_transit'    => (int)$summary['in_transit'],
            'completed'     => (int)$summary['completed'],
            'upcoming'      => (int)$summary['upcoming'],
            
            'orders'        => $orders
        ]
    ]);

} catch (PDOException $e) {
    fail('Database error: ' . $e->getMessage(), 500);
}