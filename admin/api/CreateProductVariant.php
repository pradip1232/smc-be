<?php
// smc/admin/api/CreateProductVariant.php
require_once __DIR__ . '/db.php';

$uploadDir = __DIR__ . '/../../../uploads/products/';
$debugLog = [];

$debugLog[] = "=== SCRIPT STARTED ===";
$debugLog[] = "Upload Dir: " . realpath($uploadDir) ?: $uploadDir;

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
    $debugLog[] = "Directory created";
}

// ==================== HELPERS ====================
function generateNextSKU($pdo): string {
    $stmt = $pdo->prepare("SELECT sku FROM product_variants WHERE sku LIKE 'SKU-%' ORDER BY CAST(SUBSTRING(sku, 5) AS UNSIGNED) DESC LIMIT 1");
    $stmt->execute();
    $lastSKU = $stmt->fetchColumn();
    $next = $lastSKU ? (int)substr($lastSKU, 4) + 1 : 1;
    return 'SKU-' . str_pad($next, 5, '0', STR_PAD_LEFT);
}

function getUniqueSKU($pdo, ?string $proposed = null): string {
    if ($proposed) {
        $stmt = $pdo->prepare("SELECT 1 FROM product_variants WHERE sku = ?");
        $stmt->execute([$proposed]);
        if (!$stmt->fetchColumn()) return $proposed;
    }
    return generateNextSKU($pdo);
}

function generateUniqueFilename($dir, $originalName) {
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    $name = pathinfo($originalName, PATHINFO_FILENAME);
    $newName = $name . '.' . strtolower($ext);
    $counter = 1;
    while (file_exists($dir . $newName)) {
        $newName = $name . '_' . $counter . '.' . strtolower($ext);
        $counter++;
    }
    return $newName;
}

function fail($msg, $code = 400) {
    global $debugLog;
    http_response_code($code);
    echo json_encode(['status' => false, 'message' => $msg, 'debug' => $debugLog]);
    exit;
}

// ====================== MAIN ======================
$debugLog[] = "POST: " . print_r($_POST, true);
$debugLog[] = "FILES: " . print_r($_FILES, true);

$productCode = $_POST['product_id'] ?? null;   // e.g. SMC-PROD-0005
$variantsRaw = $_POST['variants'] ?? '[]';
$variantsInput = json_decode($variantsRaw, true) ?? [];

if (!$productCode) fail('product_id is required');

// Get real numeric product_id from products table
$stmt = $pdo->prepare("SELECT id FROM products WHERE product_id = ? LIMIT 1");
$stmt->execute([$productCode]);
$realProductId = $stmt->fetchColumn();

if (!$realProductId) fail('Master product not found (invalid product_id)');

$debugLog[] = "Resolved product_id '$productCode' → numeric ID: $realProductId";

$results = [];

// Primary index
$primaryIndex = 0;
if (isset($_POST['primary_image_index'])) {
    $p = $_POST['primary_image_index'];
    $primaryIndex = is_array($p) && isset($p[0]) ? (int)$p[0] : (int)$p;
}

foreach ($variantsInput as $v) {
    $sku = getUniqueSKU($pdo, $v['sku'] ?? null);

    $variantData = [
        'variant_id'        => $v['variant_id'] ?? 'VAR-' . strtoupper(bin2hex(random_bytes(4))),
        'product_id'        => $productCode,           // Store original code
        'sku'               => $sku,
        'color_name'        => $v['color_name'] ?? null,
        'color_hex'         => $v['color_hex'] ?? null,
        'size'              => $v['size'] ?? null,
        'mrp'               => (float)($v['mrp'] ?? 0),
        'selling_price'     => (float)($v['selling_price'] ?? 0),
        'actual_cost_price' => (float)($v['actual_cost_price'] ?? 0),
        'discount_price'    => (float)($v['discount_price'] ?? 0),
        'stock'             => (int)($v['stock'] ?? 0),
        'status'            => ($v['status'] ?? 'active') === 'active' ? 1 : 0,
    ];

    $cols = array_keys($variantData);
    $sql = "INSERT INTO product_variants (" . implode(", ", $cols) . ") VALUES (" . str_repeat("?,", count($cols)-1) . "?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($variantData));

    $newVariantId = $variantData['variant_id'];

    // ====================== IMAGE HANDLING ======================
    $imagesSaved = 0;

    if (isset($_FILES['images']) && isset($_FILES['images']['name'][0])) {
        $imageFiles = $_FILES['images'];
        $totalImages = count($imageFiles['name'][0] ?? []);

        for ($i = 0; $i < $totalImages; $i++) {
            if (($imageFiles['error'][0][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $originalName = $imageFiles['name'][0][$i];
                $tmpName = $imageFiles['tmp_name'][0][$i];

                $uniqueName = generateUniqueFilename($uploadDir, $originalName);
                $targetPath = $uploadDir . $uniqueName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $isMain = ($i === $primaryIndex) ? 1 : 0;
                    $dbPath = 'uploads/products/' . $uniqueName;

                    $stmt = $pdo->prepare("INSERT INTO product_images 
                        (product_id, variant_id, image_url, is_main) 
                        VALUES (?, ?, ?, ?)");
                    $stmt->execute([$realProductId, $newVariantId, $dbPath, $isMain]);   // Use numeric ID

                    $imagesSaved++;
                    $debugLog[] = "Image saved: $dbPath";
                }
            }
        }
    }

    $results[] = [
        'variant_id'   => $newVariantId,
        'sku'          => $sku,
        'color_name'   => $v['color_name'] ?? null,
        'images_saved' => $imagesSaved
    ];
}

echo json_encode([
    'status'     => true,
    'message'    => 'Variant(s) + images saved successfully',
    'product_id' => $productCode,
    'variants'   => $results,
    'debug'      => $debugLog
]);