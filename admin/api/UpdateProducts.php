<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => false, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

// === CHANGED: Read multipart/form-data properly ===
$input = $_POST;   // This contains all your text fields

// Optional: merge any JSON string if frontend sends one
if (!empty($_POST['data']) && is_string($_POST['data'])) {
    $jsonData = json_decode($_POST['data'], true);
    if (is_array($jsonData)) {
        $input = array_merge($input, $jsonData);
    }
}

$id = isset($input['id']) ? (int)$input['id'] : null;
$productId = $input['product_id'] ?? null;

if (!$id && !$productId) {
    fail('Missing id or product_id for update');
}

// Allow more fields (you were missing many)
$allowedUp = [
    'product_name','generic_name','brand','category_id','color','color_hex',
    'material','pattern','character_name','gender','class_type','backpack_style',
    'capacity','net_quantity','recommended_age','size','country_of_origin',
    'net_weight','price','selling_price','mrp','stock','short_description',
    'full_description','status','is_published','is_visible_on_website',
    'is_on_offer','is_discounted','discount_type','offer_title',
    'offer_description','offer_start_date','offer_end_date','offer_active',
    'homepage_banner_enabled','hero_banner_title','hero_banner_subtitle',
    'hero_banner_cta','hero_banner_url','is_live','is_new_arrival',
    'show_in_card_slider','actual_cost_price'
];

$sets = [];
$params = [];

foreach ($allowedUp as $f) {
    if (array_key_exists($f, $input)) {
        $sets[] = "$f = ?";
        $params[] = $input[$f];
    }
}

if (empty($sets)) {
    fail('No update fields provided');
}

if ($id) {
    $params[] = $id;
    $sql = 'UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = ?';
} else {
    $params[] = $productId;
    $sql = 'UPDATE products SET ' . implode(', ', $sets) . ' WHERE product_id = ?';
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

if ($stmt->rowCount() === 0) {
    fail('Product not found or no changes made', 404);
}

// Get the actual product id for images
$pid = $id;
if (!$pid && $productId) {
    $g = $pdo->prepare('SELECT id FROM products WHERE product_id = ? LIMIT 1');
    $g->execute([$productId]);
    $pid = $g->fetchColumn();
    if (!$pid) fail('Product not found after update', 404);
}

// === Handle existing images + new uploads ===
$uploadDir = __DIR__ . '/../../uploads/products';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Handle new image uploads if any
if (!empty($_FILES['images'])) {
    // ... your existing image upload code ...
}

// (Optional) Handle color-specific images, primary image index, etc.
if (isset($input['primary_image_index'])) {
    // Update primary image logic if needed
}

echo json_encode(['status' => true, 'message' => 'Product updated successfully']);