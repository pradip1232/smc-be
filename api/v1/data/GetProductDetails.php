<?php
// React-friendly CORS support for browser calls
$allowedOrigin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: false');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once '../../../db/db_con.php';

if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'DB connection error']);
    exit;
}

$product_id = isset($_GET['product_id']) ? trim($_GET['product_id']) : null;
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

if (!$product_id && !$id) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Missing product_id or id parameter']);
    exit;
}

$sql = "SELECT 
    p.id,
    p.product_id,
    p.product_name,
    p.generic_name,
    p.brand,
    p.category_id,
    p.short_description,
    p.description,
    p.price,
    p.discount_price,
    p.mrp,
    p.stock,
    p.status,
    p.is_new_arrival,
    p.show_in_card_slider,
    p.size,
    p.country_of_origin,
    p.material,
    p.pattern,
    p.gender,
    p.capacity AS bag_capacity,
    p.net_weight,
    p.recommended_age,
    p.backpack_style,
    p.color,
    p.created_at,
    c.category_id AS category_slug,
    c.name AS category_name
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
WHERE ";
$params = [];

if ($product_id) {
    $sql .= "p.product_id = :product_id";
    $params[':product_id'] = $product_id;
} else {
    $sql .= "p.id = :id";
    $params[':id'] = $id;
}

$sql .= " AND (p.status = 'published' OR p.status = 'live' OR p.is_live = 1) LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    http_response_code(404);
    echo json_encode(['status' => false, 'message' => 'Product not found']);
    exit;
}

$image_stmt = $pdo->prepare("SELECT image_url, is_main FROM product_images WHERE product_id = ? ORDER BY is_main DESC, id ASC");
$image_stmt->execute([$product['id']]);
$images = [];
$primary_image = null;
while ($row = $image_stmt->fetch(PDO::FETCH_ASSOC)) {
    $url = $row['image_url'];
    if (!preg_match('#^https?://#', $url) && strpos($url, '/') !== 0) {
        $url = '/uploads/products/' . ltrim($url, '/');
    }
    if ($primary_image === null && !empty($row['is_main'])) {
        $primary_image = $url;
    }
    $images[] = $url;
}
if ($primary_image === null && count($images) > 0) {
    $primary_image = $images[0];
}

function parse_colors($raw) {
    if ($raw === null || $raw === '') {
        return [];
    }
    if (is_array($raw)) {
        return $raw;
    }
    $raw = str_replace(['|', ';'], ',', $raw);
    return array_values(array_filter(array_map('trim', explode(',', $raw)), fn($v) => $v !== ''));
}

$discount_percent = null;
if (
    isset($product['price'], $product['discount_price']) &&
    is_numeric($product['price']) &&
    is_numeric($product['discount_price']) &&
    $product['price'] > 0 &&
    $product['discount_price'] > 0
) {
    $discount_percent = round(100 * ($product['price'] - $product['discount_price']) / $product['price']);
}

$badge = null;
if (!empty($product['is_new_arrival'])) {
    $badge = 'New';
}
if (!empty($product['show_in_card_slider'])) {
    $badge = $badge ? $badge . ',Limited' : 'Limited';
}

$response = [
    'status' => true,
    'message' => 'Product details fetched successfully',
    'product' => [
        'id' => (int)$product['id'],
        'product_id' => $product['product_id'],
        'product_name' => $product['product_name'],
        'generic_name' => $product['generic_name'] ?? null,
        'brand' => $product['brand'],
        'category_id' => $product['category_id'] ? (string)$product['category_id'] : null,
        'category_slug' => $product['category_slug'] ?? null,
        'category_name' => $product['category_name'] ?? null,
        'short_description' => $product['short_description'] ?? null,
        'description' => $product['description'] ?? null,
        'price' => isset($product['price']) ? (float)$product['price'] : null,
        'selling_price' => isset($product['discount_price']) && $product['discount_price'] ? (float)$product['discount_price'] : (isset($product['price']) ? (float)$product['price'] : null),
        'mrp' => isset($product['mrp']) && $product['mrp'] ? (float)$product['mrp'] : (isset($product['price']) ? (float)$product['price'] : null),
        'discount_percent' => $discount_percent,
        'stock' => isset($product['stock']) ? (int)$product['stock'] : 0,
        'is_live' => isset($product['status']) && ($product['status'] === 'published' || $product['status'] === 'live' || $product['is_live'] == 1),
        'is_new_arrival' => !empty($product['is_new_arrival']),
        'show_in_card_slider' => !empty($product['show_in_card_slider']),
        'size' => $product['size'] ?? null,
        'country_of_origin' => $product['country_of_origin'] ?? null,
        'material' => $product['material'] ?? null,
        'pattern' => $product['pattern'] ?? null,
        'gender' => $product['gender'] ?? null,
        'bag_capacity' => $product['bag_capacity'] ?? null,
        'net_weight' => $product['net_weight'] ?? null,
        'recommended_age' => $product['recommended_age'] ?? null,
        'backpack_style' => $product['backpack_style'] ?? null,
        'colors' => parse_colors($product['color'] ?? ''),
        'images' => $images,
        'primary_image' => $primary_image,
        'badge' => $badge,
        'created_at' => $product['created_at'] ?? null,
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
