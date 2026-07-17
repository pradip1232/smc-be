<?php
// admin/api/GetProductVariants.php
require_once __DIR__ . '/db.php';

function fail(string $msg, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['status' => false, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$productId = $_GET['product_id'] ?? null;
if (!$productId) {
    fail('product_id is required');
}

$stmt = $pdo->prepare('SELECT 1 FROM products WHERE product_id = ?');
$stmt->execute([$productId]);
if (!$stmt->fetchColumn()) {
    fail('Product not found', 404);
}

$stmt = $pdo->prepare('SELECT * FROM product_variants WHERE product_id = ? ORDER BY color_name, size');
$stmt->execute([$productId]);
$variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

$variantIds = array_column($variants, 'variant_id');
$variantImages = [];

if (!empty($variantIds)) {
    $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
    $imgSql = "SELECT variant_id, image_url, is_main FROM product_images WHERE variant_id IN ($placeholders)";
    $imgStmt = $pdo->prepare($imgSql);
    $imgStmt->execute($variantIds);

    while ($img = $imgStmt->fetch(PDO::FETCH_ASSOC)) {
        $variantImages[$img['variant_id']][] = [
            'image_url' => $img['image_url'],
            'is_main'   => (bool) $img['is_main'],
        ];
    }
}

foreach ($variants as &$variant) {
    $variant['images'] = $variantImages[$variant['variant_id']] ?? [];
}
unset($variant);

echo json_encode([
    'status'     => true,
    'product_id' => $productId,
    'variants'   => $variants,
]);
