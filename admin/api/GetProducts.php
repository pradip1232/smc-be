<?php
// admin/api/GetProducts.php
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

// Parameters
$id         = isset($_GET['id']) ? (int)$_GET['id'] : null;
$productId  = $_GET['product_id'] ?? null;
$page       = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage    = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
$offset     = ($page - 1) * $perPage;

// Base Query
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

// Total Count (for list only)
$totalCount = null;
if (!$id && !$productId) {
    $countSql = "SELECT COUNT(*) AS total_count " . $baseSql . $whereSql;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetchColumn();
}

// Fetch Products
if ($id || $productId) {
    $sql = "SELECT p.*, c.name AS category_name " . $baseSql . $whereSql . " LIMIT 1";
} else {
    $sql = "SELECT p.*, c.name AS category_name " . $baseSql . $whereSql . 
           " ORDER BY p.created_at DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($products)) {
    if ($id || $productId) fail('Product not found', 404);
    else {
        echo json_encode([
            'status' => true,
            'products' => [],
            'totalRecords' => 0,
            'page' => $page,
            'perPage' => $perPage
        ]);
        exit;
    }
}

// ====================== Attach Variants + Images ======================
$productIds = array_column($products, 'product_id');

if (!empty($productIds)) {
    $ph = implode(',', array_fill(0, count($productIds), '?'));

    // Fetch All Variants
    $varSql = "SELECT * FROM product_variants WHERE product_id IN ($ph) ORDER BY color_name, size";
    $varStmt = $pdo->prepare($varSql);
    $varStmt->execute($productIds);
    $allVariants = $varStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Images for Variants
    $variantIds = array_column($allVariants, 'variant_id');
    $variantImages = [];

    if (!empty($variantIds)) {
        $imgPh = implode(',', array_fill(0, count($variantIds), '?'));
        $imgSql = "SELECT variant_id, image_url, is_main FROM product_images WHERE variant_id IN ($imgPh)";
        $imgStmt = $pdo->prepare($imgSql);
        $imgStmt->execute($variantIds);

        while ($img = $imgStmt->fetch(PDO::FETCH_ASSOC)) {
            $variantImages[$img['variant_id']][] = [
                'image_url' => $img['image_url'],
                'is_main'   => (bool)$img['is_main']
            ];
        }
    }

    // Group variants by product_id
    $variantsByProduct = [];
    foreach ($allVariants as $v) {
        $v['images'] = $variantImages[$v['variant_id']] ?? [];
        $variantsByProduct[$v['product_id']][] = $v;
    }

    // Attach to products
    foreach ($products as &$product) {
        $product['variants'] = $variantsByProduct[$product['product_id']] ?? [];
    }
}

// Response
if ($id || $productId) {
    echo json_encode([
        'status' => true,
        'product' => $products[0]
    ]);
} else {
    echo json_encode([
        'status'       => true,
        'products'     => $products,
        'totalRecords' => $totalCount,
        'page'         => $page,
        'perPage'      => $perPage,
        'totalPages'   => $perPage > 0 ? (int)ceil($totalCount / $perPage) : 0
    ]);
}