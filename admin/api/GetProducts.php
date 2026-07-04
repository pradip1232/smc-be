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

// allow admin to list or fetch single by id/product_id
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$productId = $_GET['product_id'] ?? null;

// pagination (only for list requests)
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
$offset = ($page - 1) * $perPage;

$baseSql = "FROM products p LEFT JOIN categories c ON p.category_id = c.id";
$conditions = [];
$params = [];
if ($id) {
    $conditions[] = 'p.id = ?';
    $params[] = $id;
} elseif ($productId) {
    $conditions[] = 'p.product_id = ?';
    $params[] = $productId;
}

$whereSql = !empty($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';

// total records for pagination (list only)
$totalCount = null;
if (!$id && !$productId) {
    $countSql = "SELECT COUNT(*) AS total_count " . $baseSql . $whereSql;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = (int)($countStmt->fetchColumn() ?? 0);
}

if ($id || $productId) {
    $listSql = "SELECT p.*, c.name AS category_name " . $baseSql . $whereSql . " ORDER BY p.created_at DESC";
    $listParams = $params;
} else {
    // MariaDB sometimes complains with LIMIT/OFFSET placeholders, so interpolate safely as integers
    $limitSql = (int)$perPage;
    $offsetSql = (int)$offset;
    $listSql = "SELECT p.*, c.name AS category_name " . $baseSql . $whereSql . " ORDER BY p.created_at DESC LIMIT {$limitSql} OFFSET {$offsetSql}";
    $listParams = $params;
}

$stmt = $pdo->prepare($listSql);
$stmt->execute($listParams);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (($id || $productId) && empty($rows)) fail('Product not found', 404);

// attach images
$ids = array_column($rows, 'id');
if (!empty($ids)) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $imgStmt = $pdo->prepare("SELECT product_id, image_url, is_main FROM product_images WHERE product_id IN ($ph)");
    $imgStmt->execute($ids);
    $imgs = [];
    while ($r = $imgStmt->fetch(PDO::FETCH_ASSOC)) {
        $imgs[$r['product_id']][] = ['image_url' => $r['image_url'], 'is_main' => (bool)$r['is_main']];
    }
    foreach ($rows as &$row) { $row['images'] = $imgs[$row['id']] ?? []; }
}

if ($id || $productId) {
    echo json_encode($rows[0]);
} else {
    $totalPages = $perPage > 0 ? (int)ceil($totalCount / $perPage) : 0;
    echo json_encode([
        'products' => $rows,
        'totalRecords' => $totalCount,
        'totalCount' => $totalCount,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => $totalPages
    ]);
}

