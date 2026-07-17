<?php
// ================================================
// GetShipmentDetails.php - Shipment List API
// ================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ====================== ROBUST DB CONNECTION ======================
$possible_paths = [
    __DIR__ . '/../../db/db_con.php',
    __DIR__ . '/../../../db/db_con.php',
    __DIR__ . '/../../../../db/db_con.php',
    $_SERVER['DOCUMENT_ROOT'] . '/smc/db/db_con.php',
    $_SERVER['DOCUMENT_ROOT'] . '/db/db_con.php',
    __DIR__ . '/../../../../../db/db_con.php'
];

$db_con_path = null;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $db_con_path = $path;
        break;
    }
}

if ($db_con_path === null) {
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'Database connection file not found',
        'debug' => ['document_root' => $_SERVER['DOCUMENT_ROOT']]
    ]);
    exit;
}

require_once $db_con_path;
// ================================================================

$page   = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit  = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$page  = max(1, $page);
$limit = min(50, max(1, $limit));
$offset = ($page - 1) * $limit;

try {
    $where = "1=1";
    $params = [];

    if (!empty($search)) {
        $where .= " AND (
                    s.tracking_id LIKE ? 
                    OR o.order_id LIKE ? 
                    OR o.customer_name LIKE ?
                )";
        $searchParam = "%$search%";
        $params = [$searchParam, $searchParam, $searchParam];
    }

    // Count total
    $count_sql = "SELECT COUNT(*) as total 
                  FROM shipments s 
                  LEFT JOIN orders o ON s.order_id = o.id 
                  WHERE $where";

    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $limit);

    // Main Query
    $sql = "SELECT 
                s.*,
                o.order_id,
                o.customer_name,
                o.customer_phone,
                o.total_amount,
                o.payment_status,
                o.order_status
            FROM shipments s
            LEFT JOIN orders o ON s.order_id = o.id
            WHERE $where 
            ORDER BY s.created_at DESC 
            LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    
    // Bind parameters properly
    $paramIndex = 1;
    foreach ($params as $value) {
        $stmt->bindValue($paramIndex++, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);

    $stmt->execute();

    $shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => true,
        'message' => 'Shipments fetched successfully',
        'search' => !empty($search) ? $search : null,
        
        'current_page' => $page,
        'per_page' => $limit,
        'total_records' => (int)$total_records,
        'total_pages' => $total_pages,
        'has_next' => $page < $total_pages,
        'has_prev' => $page > 1,
        'data' => $shipments,
      
    ], JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
}
?>