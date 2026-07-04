<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../../db/db_con.php'; // database connection

if (!isset($pdo) || !$pdo) {
    http_response_code(500);    
    echo json_encode(['status' => false, 'message' => 'DB connection error']);
    exit;
}
    
// ============================ INPUT PARAMETERS ============================
$page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 12;

$category = isset($_GET['category']) ? trim($_GET['category']) : null;
$q        = isset($_GET['q']) ? trim($_GET['q']) : null;
$sort     = isset($_GET['sort']) ? $_GET['sort'] : 'recommended';
$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$price_param = isset($_GET['price']) ? trim($_GET['price']) : (isset($_GET['pricing']) ? trim($_GET['pricing']) : null);
$recommended = isset($_GET['recommended']) ? strtolower(trim($_GET['recommended'])) : null;
$badge    = isset($_GET['badge']) ? strtolower(trim($_GET['badge'])) : null;

// ============================ BUILD QUERY ============================
$where = ["1=1"];
$params = [];

if ($category) {
    // Support mapped slugs to category name or id if needed; here assume name or slug is in 'categories' table
    $category_sql = "SELECT id, category_id FROM categories WHERE name = :cat_name OR category_id = :cat_slug";
    $cstmt = $pdo->prepare($category_sql);
    $cstmt->execute([':cat_name' => $category, ':cat_slug' => $category]);
    $cat = $cstmt->fetch(PDO::FETCH_ASSOC);
    if ($cat) {
        $where[] = "category_id = :category_id";
        $params[':category_id'] = $cat['id'];
    } else {
        // If not found, set to no results
        $where[] = "0=1";
    }
}

if ($q) {
    $where[] = "(product_name LIKE :query OR generic_name LIKE :query OR brand LIKE :query OR full_description LIKE :query)";
    $params[':query'] = '%' . $q . '%';
}

if ($min_price !== null) {
    $where[] = "price >= :min_price";
    $params[':min_price'] = $min_price;
}

if ($max_price !== null) {
    $where[] = "price <= :max_price";
    $params[':max_price'] = $max_price;
}

// Support `price` or `pricing` query like "100-500", ">100", "<500" or a single value
if ($price_param !== null && $price_param !== '') {
    // hyphen range
    if (strpos($price_param, '-') !== false) {
        [$pmin, $pmax] = array_map('trim', explode('-', $price_param, 2));
        if (is_numeric($pmin)) {
            $where[] = "price >= :min_price";
            $params[':min_price'] = (float)$pmin;
        }
        if ($pmax !== '' && is_numeric($pmax)) {
            $where[] = "price <= :max_price";
            $params[':max_price'] = (float)$pmax;
        }
    } elseif (preg_match('/^>(\s*)?(\d+(?:\.\d+)?)$/', $price_param, $m)) {
        $where[] = "price >= :min_price";
        $params[':min_price'] = (float)$m[2];
    } elseif (preg_match('/^<(\s*)?(\d+(?:\.\d+)?)$/', $price_param, $m)) {
        $where[] = "price <= :max_price";
        $params[':max_price'] = (float)$m[2];
    } elseif (is_numeric($price_param)) {
        $where[] = "price = :exact_price";
        $params[':exact_price'] = (float)$price_param;
    }
}

if ($badge) {
    if ($badge === 'new') {
        $where[] = "is_new_arrival = 1";
    } elseif ($badge === 'limited') {
        $where[] = "show_in_card_slider = 1";
    }
    // else ignore unrecognized badge
}

// Filter by recommended flag if provided (recommended=1/true/yes)
if ($recommended !== null) {
    if (in_array($recommended, ['1','true','yes','y'], true)) {
        $where[] = "show_in_card_slider = 1";
    } elseif (in_array($recommended, ['0','false','no','n'], true)) {
        $where[] = "show_in_card_slider = 0";
    }
}

// Only show live/published products by default
$where[] = "(status = 'published' OR status = 'live' OR is_live = 1)";

$where_clause = implode(" AND ", $where);

// ============================ SORTING ============================
switch ($sort) {
    case 'price-asc':
        $order_by = "price ASC";
        break;
    case 'price-desc':
        $order_by = "price DESC";
        break;
    case 'newest':
        $order_by = "created_at DESC";
        break;
    case 'recommended':
    default:
        $order_by = "show_in_card_slider DESC, is_new_arrival DESC, created_at DESC";
        break;
}

// ============================ COUNT TOTAL ============================
$count_sql = "SELECT COUNT(*) AS total FROM products WHERE $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$count_row = $count_stmt->fetch(PDO::FETCH_ASSOC);
$total = $count_row ? (int)$count_row['total'] : 0;

// ============================ FETCH PRODUCTS ============================
$offset = ($page - 1) * $limit;

// All necessary columns for API output
$product_sql = "SELECT 
    p.id,
    p.product_id,
    p.product_name,
    p.generic_name,
    p.brand,
    p.category_id,
    p.short_description,
    p.full_description,
    p.price,
    p.discount_price,
    p.mrp,
    p.stock,
    p.status AS is_live,
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
    p.created_at
FROM products p
WHERE $where_clause
ORDER BY $order_by
LIMIT :limit OFFSET :offset";

$product_stmt = $pdo->prepare($product_sql);
foreach($params as $k => $v) {
    $product_stmt->bindValue($k, $v);
}
$product_stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
$product_stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

$product_stmt->execute();
$rows = $product_stmt->fetchAll(PDO::FETCH_ASSOC);

$product_ids = [];
foreach ($rows as $row) {
    $product_ids[] = $row['id'];
}

// ============================ IMAGES AND COLORS ============================
$image_map = [];
$all_images_map = []; // for each product: id => [array of URLs]
if (count($product_ids) > 0) {
    $in_query = implode(',', array_fill(0, count($product_ids), '?'));
    // Primary (main first; fallback to any image)
    $img_sql =
        "SELECT product_id, MAX(is_main) as is_main, MIN(id) AS min_img_id, image_url
         FROM product_images
         WHERE product_id IN ($in_query)
         GROUP BY product_id, image_url
         ORDER BY is_main DESC, min_img_id ASC";
    $img_stmt = $pdo->prepare($img_sql);
    $img_stmt->execute($product_ids);
    $img_rows = $img_stmt->fetchAll(PDO::FETCH_ASSOC);
    $tmp_imgs = [];
    foreach ($img_rows as $img) {
        $pid = $img['product_id'];
        if (!isset($tmp_imgs[$pid])) $tmp_imgs[$pid] = [];
        $img_url = $img['image_url'];
        // If not absolute, prefix
        if (!preg_match('#^https?://#', $img_url) && strpos($img_url, '/') !== 0) {
            $img_url = '/uploads/products/' . ltrim($img_url, '/');
        }
        $tmp_imgs[$pid][] = $img_url;
    }
    foreach ($tmp_imgs as $pid => $arr) {
        $all_images_map[$pid] = $arr;
        $image_map[$pid] = $arr[0] ?? null;
    }
}

// Get colors for products as array, if needed (from color column, splitting by comma, space, or stored as array in future)
function parse_colors($raw) {
    if ($raw === null || $raw === '') return [];
    if (is_array($raw)) return $raw;
    // Support "Red, Blue" or "Red|Blue"
    $raw = str_replace(['|', ';'], ',', $raw);
    return array_values(array_filter(array_map('trim', explode(',', $raw)), fn($v) => $v !== ''));
}

// ============================ CATEGORY SLUG MAP ============================
function get_category_info($pdo, $id) {
    $stmt = $pdo->prepare("SELECT category_id, name FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ============================ TRANSFORM PRODUCTS ============================
$products = [];
foreach ($rows as $row) {
    $pid = $row['id'];
    $cat_info = get_category_info($pdo, $row['category_id']);
    $discount_percent = null;
    if (
        isset($row['price'], $row['discount_price']) &&
        is_numeric($row['price']) &&
        is_numeric($row['discount_price']) &&
        $row['price'] > 0 && $row['discount_price'] > 0
    ) {
        $discount_percent = round(100 * ($row['price'] - $row['discount_price']) / $row['price']);
    }

    // Badge calculation
    $badge_val = null;
    if (!empty($row['is_new_arrival'])) $badge_val = "New";
    if (!empty($row['show_in_card_slider'])) $badge_val = $badge_val ? $badge_val . ",Limited" : "Limited";
// print_r($row);
    $products[] = [
        "product_id"         => $row['product_id'],
        "product_name"       => $row['product_name'],
        "generic_name"       => $row['generic_name'] ?? null,
        "brand"              => $row['brand'],
        "category"           => $cat_info['category_id'] ?? null,
        "category_id"        => (string)($cat_info['category_id'] ?? $row['category_id']),
        "short_description"  => $row['short_description'] ?? null,
        "full_description"   => $row['full_description'] ?? "NNNNNNNN",
        "price"              => isset($row['price']) ? (float)$row['price'] : null,
        "selling_price"      => isset($row['discount_price']) && $row['discount_price'] ? (float)$row['discount_price'] : (float)$row['price'],
        "mrp"                => isset($row['mrp']) && $row['mrp'] ? (float)$row['mrp'] : (float)$row['price'],
        "discount_percent"   => $discount_percent,
        "stock"              => isset($row['stock']) ? (int)$row['stock'] : 0,
        "is_live"            => (isset($row['is_live']) && ($row['is_live'] == 'published' || $row['is_live'] == 'live' || $row['is_live'] == 1)),
        "is_new_arrival"     => (bool)$row['is_new_arrival'],
        "show_in_card_slider"=> (bool)$row['show_in_card_slider'],
        "size"               => $row['size'],
        "country_of_origin"  => $row['country_of_origin'],
        "material"           => $row['material'],
        "pattern"            => $row['pattern'],
        "gender"             => $row['gender'],
        "bag_capacity"       => $row['bag_capacity'],
        "net_weight"         => $row['net_weight'],
        "recommended_age"    => $row['recommended_age'],
        "backpack_style"     => $row['backpack_style'],
        "colors"             => parse_colors($row['color']),
        "images"             => $all_images_map[$pid] ?? [],
        "primary_image"      => $image_map[$pid] ?? null,
        "badge"              => $badge_val,
    ];
}

// ============================ RESPONSE ============================
$response = [
    "status" => true,
    "message" => "Products fetched successfully",
    "total"   => $total,
    "page"    => $page,
    "limit"   => $limit,
    "products"=> $products,

];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>