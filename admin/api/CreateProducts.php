<?php
// admin/api/CreateProducts.php
// Accepts multipart/form-data POST with product fields + images[N] files.
require_once __DIR__ . '/db.php';   // loads api_headers.php + db_con.php → $pdo

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function fail(string $msg, int $code = 400, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(['status' => false, 'message' => $msg, ...$extra]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Method guard
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

/*
|--------------------------------------------------------------------------
| Merge JSON body + multipart POST into one $source array
| (multipart fields take priority so the frontend always works)
|--------------------------------------------------------------------------
*/
$jsonBody = json_decode(file_get_contents('php://input'), true) ?? [];
$source   = array_merge($jsonBody, $_POST);   // $_POST wins on key collision

/*
|--------------------------------------------------------------------------
| Field aliases  (camelCase → snake_case friendly names)
|--------------------------------------------------------------------------
*/
$aliases = [
    'productName'     => 'product_name',
    'genericName'     => 'generic_name',
    'character'       => 'character_name',
    'productClass'    => 'class_type',
    'class'           => 'class_type',
    'backpackStyle'   => 'backpack_style',
    'bagCapacity'     => 'capacity',
    'netQuantity'     => 'net_quantity',
    'recommendedAge'  => 'recommended_age',
    'countryOfOrigin' => 'country_of_origin',
    'netWeight'       => 'net_weight',
    'weight'          => 'net_weight',
    'category'        => 'category_id',
];

/*
|--------------------------------------------------------------------------
| All columns that map 1-to-1 into the `products` table
|--------------------------------------------------------------------------
*/
$productColumns = [
    'product_id', 'product_name', 'generic_name', 'brand', 'category_id',
    'color', 'color_hex', 'selected_colors',
    'material', 'pattern', 'character_name', 'gender', 'class_type',
    'backpack_style', 'capacity', 'net_quantity', 'recommended_age', 'size',
    'country_of_origin', 'net_weight',
    // Pricing
    'price', 'mrp', 'selling_price', 'actual_cost_price', 'discount_price',
    // Stock
    'stock',
    // Rich content
    'features', 'short_description', 'full_description', 'description',
    // Offer fields
    'is_on_offer', 'is_discounted', 'discount_type',
    'offer_title', 'offer_description', 'offer_start_date', 'offer_end_date', 'offer_active',
    // Banner
    'homepage_banner_enabled', 'hero_banner_title', 'hero_banner_subtitle',
    'hero_banner_cta', 'hero_banner_url',
    // Visibility flags
    'is_live', 'is_new_arrival', 'show_in_card_slider',
    'is_published', 'is_visible_on_website',
    // Status
    'status',
];

/*
|--------------------------------------------------------------------------
| Normalize: apply aliases then pick only known columns
|--------------------------------------------------------------------------
*/
$data = [];
foreach ($source as $key => $value) {
    $mapped = $aliases[$key] ?? $key;           // resolve alias
    if (in_array($mapped, $productColumns, true)) {
        $data[$mapped] = $value;
    }
}

/*
|--------------------------------------------------------------------------
| Coerce / sanitize special fields
|--------------------------------------------------------------------------
*/

// features — accept JSON string or PHP array, store as JSON string
if (isset($data['features'])) {
    if (is_string($data['features'])) {
        $decoded = json_decode($data['features'], true);
        $data['features'] = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
            ? json_encode($decoded)
            : $data['features'];
    } elseif (is_array($data['features'])) {
        $data['features'] = json_encode($data['features']);
    }
}

// selected_colors — same treatment
if (isset($data['selected_colors'])) {
    if (is_string($data['selected_colors'])) {
        $decoded = json_decode($data['selected_colors'], true);
        $data['selected_colors'] = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
            ? json_encode($decoded)
            : $data['selected_colors'];
    } elseif (is_array($data['selected_colors'])) {
        $data['selected_colors'] = json_encode($data['selected_colors']);
    }
}

// Boolean / tinyint fields — cast to 0 or 1
$boolFields = [
    'is_on_offer', 'is_discounted', 'offer_active',
    'homepage_banner_enabled', 'is_live', 'is_new_arrival',
    'show_in_card_slider', 'is_published', 'is_visible_on_website',
];
foreach ($boolFields as $bf) {
    if (array_key_exists($bf, $data)) {
        $data[$bf] = (int)(bool)$data[$bf];
    }
}

// Decimal fields — cast to float
$decimalFields = ['price', 'mrp', 'selling_price', 'actual_cost_price', 'discount_price'];
foreach ($decimalFields as $df) {
    if (array_key_exists($df, $data)) {
        $data[$df] = (float)$data[$df];
    }
}

// stock — cast to int
if (array_key_exists('stock', $data)) {
    $data['stock'] = (int)$data['stock'];
}

// Empty date fields → null (avoid DB errors on empty string DATE columns)
foreach (['offer_start_date', 'offer_end_date'] as $dateField) {
    if (isset($data[$dateField]) && trim($data[$dateField]) === '') {
        $data[$dateField] = null;
    }
}

// status default
if (empty($data['status'])) {
    $data['status'] = isset($data['is_published']) && $data['is_published']
        ? 'published'
        : 'draft';
}

/*
|--------------------------------------------------------------------------
| Required fields
|--------------------------------------------------------------------------
*/
if (empty($data['product_name'])) {
    fail('product_name is required');
}
if (!isset($data['price']) || $data['price'] === '') {
    fail('price is required');
}

/*
|--------------------------------------------------------------------------
| Auto-generate product_id if not provided
|--------------------------------------------------------------------------
*/
if (empty($data['product_id'])) {
    $stmt = $pdo->query("
        SELECT product_id FROM products
        WHERE product_id LIKE 'SMC-PROD-%'
        ORDER BY id DESC
        LIMIT 1
    ");
    $last  = $stmt->fetchColumn();
    $next  = $last ? (int)str_replace('SMC-PROD-', '', $last) + 1 : 1;
    $data['product_id'] = 'SMC-PROD-' . str_pad($next, 4, '0', STR_PAD_LEFT);
}

/*
|--------------------------------------------------------------------------
| Validate category exists
|--------------------------------------------------------------------------
*/
if (!empty($data['category_id'])) {
    $catStmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? LIMIT 1");
    $catStmt->execute([$data['category_id']]);
    if (!$catStmt->fetchColumn()) {
        fail('category_id does not exist');
    }
}

/*
|--------------------------------------------------------------------------
| Insert product
|--------------------------------------------------------------------------
*/
$cols   = array_keys($data);
$ph     = array_fill(0, count($cols), '?');
$sql    = 'INSERT INTO products (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')';
$stmt   = $pdo->prepare($sql);
$stmt->execute(array_values($data));
$newId  = (int)$pdo->lastInsertId();

/*
|--------------------------------------------------------------------------
| Handle uploaded images  (images[0], images[1], …)
|--------------------------------------------------------------------------
*/
$uploadDir   = __DIR__ . '/../../uploads/products';
$uploadDebug = [];

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!empty($_FILES['images'])) {
    $primaryIndex = isset($source['primary_image_index']) && is_numeric($source['primary_image_index'])
        ? (int)$source['primary_image_index']
        : 0;

    $piStmt = $pdo->prepare("
        INSERT INTO product_images (product_id, image_url, is_main, color_label, color_hex)
        VALUES (?, ?, ?, ?, ?)
    ");

    $files = $_FILES['images'];

    // Normalise to always-array format
    $fileList = [];
    if (is_array($files['name'])) {
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            $fileList[] = [
                'name'     => $files['name'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'index'    => $i,
            ];
        }
    } else {
        $fileList[] = [
            'name'     => $files['name'],
            'tmp_name' => $files['tmp_name'],
            'error'    => $files['error'],
            'index'    => 0,
        ];
    }

    foreach ($fileList as $f) {
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $uploadDebug[] = ['index' => $f['index'], 'error' => 'upload_err_' . $f['error']];
            continue;
        }

        $i        = $f['index'];
        $origBase = basename($f['name']);
        $safeOrig = preg_replace('/[^a-zA-Z0-9._-]/', '_', $origBase);
        $filename = time() . '_' . bin2hex(random_bytes(6)) . '_' . $safeOrig;
        $dest     = $uploadDir . '/' . $filename;
        $dbPath   =  $filename;

        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            $uploadDebug[] = ['index' => $i, 'original' => $origBase, 'error' => 'move_failed'];
            continue;
        }

        $colorLabel = $source["image_color_label[$i]"] ?? null;
        $colorHex   = $source["image_color_hex[$i]"]   ?? null;
        $isMain     = ($primaryIndex === $i) ? 1 : 0;

        $piStmt->execute([$newId, $dbPath, $isMain, $colorLabel, $colorHex]);

        $uploadDebug[] = [
            'index'       => $i,
            'original'    => $origBase,
            'saved_as'    => $filename,
            'db_path'     => $dbPath,
            'is_main'     => (bool)$isMain,
            'color_label' => $colorLabel,
            'color_hex'   => $colorHex,
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Success response
|--------------------------------------------------------------------------
*/
echo json_encode([
    'status'         => true,
    'message'        => 'Product created successfully',
    'id'             => $newId,
    'product_id'     => $data['product_id'],
    'product_status' => $data['status'],
    'images_saved'   => count($uploadDebug),
    'upload_debug'   => $uploadDebug,
]);
