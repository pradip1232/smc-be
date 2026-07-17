<?php
// admin/api/CreateMasterProduct.php
require_once __DIR__ . '/db.php';

function fail(string $msg, int $code = 400, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(['status' => false, 'message' => $msg, ...$extra]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$jsonBody = json_decode(file_get_contents('php://input'), true) ?? [];
$source   = array_merge($jsonBody, $_POST);

$masterColumns = [
    'product_id', 'product_name', 'generic_name', 'brand', 'category_id',
    'material', 'pattern', 'character_name', 'gender', 'class_type',
    'backpack_style', 'capacity', 'net_quantity', 'recommended_age',
    'country_of_origin', 'net_weight', 'gst',
    'features', 'description', 'short_description', 'full_description',
    'homepage_banner_enabled', 'hero_banner_title', 'hero_banner_subtitle',
    'hero_banner_cta', 'hero_banner_url',
    'is_on_offer', 'is_discounted', 'discount_type',
    'offer_title', 'offer_description', 'offer_start_date', 'offer_end_date', 'offer_active',
    'is_live', 'is_new_arrival', 'show_in_card_slider',
    'is_published', 'is_visible_on_website', 'status'
];

$aliases = [
    'productName' => 'product_name', 'genericName' => 'generic_name',
    'character' => 'character_name', 'productClass' => 'class_type',
    'class' => 'class_type', 'backpackStyle' => 'backpack_style',
    'bagCapacity' => 'capacity', 'netQuantity' => 'net_quantity',
    'recommendedAge' => 'recommended_age', 'countryOfOrigin' => 'country_of_origin',
    'netWeight' => 'net_weight', 'category' => 'category_id'
];

$data = [];
foreach ($source as $key => $value) {
    $mapped = $aliases[$key] ?? $key;
    if (in_array($mapped, $masterColumns)) {
        $data[$mapped] = $value;
    }
}

// Sanitization
$boolFields = ['is_on_offer','is_discounted','offer_active','homepage_banner_enabled',
               'is_live','is_new_arrival','show_in_card_slider','is_published','is_visible_on_website'];

foreach ($boolFields as $f) {
    if (isset($data[$f])) $data[$f] = (int)(bool)$data[$f];
}

if (isset($data['features']) && is_array($data['features'])) {
    $data['features'] = json_encode($data['features']);
}

if (empty($data['product_name'])) fail('product_name is required');

if (empty($data['product_id'])) {
    $stmt = $pdo->query("SELECT product_id FROM products WHERE product_id LIKE 'SMC-%' ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    $next = $last ? ((int)substr($last, 4)) + 1 : 1;
    $data['product_id'] = 'SMC-' . str_pad($next, 5, '0', STR_PAD_LEFT);
}

$data['status'] = $data['status'] ?? ($data['is_published'] ?? 0 ? 'published' : 'draft');

$cols = array_keys($data);
$sql = "INSERT INTO products (" . implode(", ", $cols) . ") VALUES (" . str_repeat("?,", count($cols)-1) . "?)";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_values($data));

echo json_encode([
    'status' => true,
    'message' => 'Master product created',
    'product_id' => $data['product_id']
]);