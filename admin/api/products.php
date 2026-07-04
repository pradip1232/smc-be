<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => false, 'message' => $msg]);
    exit;
}

function require_admin_token() {
    $provided = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? null;
    $expected = defined('ADMIN_API_TOKEN') ? ADMIN_API_TOKEN : getenv('ADMIN_API_TOKEN');
    return ($expected && $provided === $expected);
}

$isAdmin = true; // admin-token check temporarily disabled for development
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Admin routes (CRUD + file upload)
if ($isAdmin) {
    switch ($method) {
        case 'GET':
            // allow admin to list or fetch single by id/product_id
            $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
            $productId = $_GET['product_id'] ?? null;

            $sql = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id";
            $conditions = [];
            $params = [];
            if ($id) { $conditions[] = 'p.id = ?'; $params[] = $id; }
            elseif ($productId) { $conditions[] = 'p.product_id = ?'; $params[] = $productId; }
            if (!empty($conditions)) $sql .= ' WHERE ' . implode(' AND ', $conditions);
            $sql .= ' ORDER BY p.created_at DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
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

            if ($id || $productId) echo json_encode($rows[0]);
            else echo json_encode(['products' => $rows]);
            break;

        case 'POST':
            // support JSON or multipart (files in images[])
            $source = [];
            foreach ($input as $k => $v) {
                $source[$k] = $v;
            }
            foreach ($_POST as $k => $v) {
                if (!array_key_exists($k, $source)) {
                    $source[$k] = $v;
                }
            }

            $aliases = [
                'productName' => 'product_name',
                'genericName' => 'generic_name',
                'character' => 'character_name',
                'productClass' => 'class_type',
                'class' => 'class_type',
                'backpackStyle' => 'backpack_style',
                'bagCapacity' => 'capacity',
                'netQuantity' => 'net_quantity',
                'recommendedAge' => 'recommended_age',
                'countryOfOrigin' => 'country_of_origin',
                'netWeight' => 'net_weight',
                'weight' => 'net_weight',
                'category' => 'category_id',
                'imageUrl' => 'imageUrl'
            ];

            $allowed = ['product_id','product_name','generic_name','brand','category_id','color','material','pattern','character_name','gender','class_type','backpack_style','capacity','net_quantity','recommended_age','size','country_of_origin','net_weight','price','discount_price','stock','description','status','images'];

            $normalized = [];
            foreach ($source as $key => $value) {
                if (isset($aliases[$key])) {
                    $normalized[$aliases[$key]] = $value;
                    continue;
                }
                if (in_array($key, $allowed, true)) {
                    $normalized[$key] = $value;
                }
            }

            if (empty($normalized['product_id'])) {
                $normalized['product_id'] = 'prod_' . bin2hex(random_bytes(8));
            }
            if (empty($normalized['status'])) {
                $normalized['status'] = 'draft';
            }

            if (empty($normalized['product_name'])) fail('Missing product_name');
            if (!isset($normalized['price']) || $normalized['price'] === '') fail('Missing price');

            // Validate category_id if provided
            if (!empty($normalized['category_id'])) {
                $catStmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
                $catStmt->execute([$normalized['category_id']]);
                if (!$catStmt->fetchColumn()) {
                    fail('Category ID does not exist', 400);
                }
            }

            if (!empty($normalized['imageUrl']) && empty($normalized['images'])) {
                $normalized['images'] = [['image_url' => $normalized['imageUrl'], 'is_main' => 1]];
            }

            $cols = [];
            $ph = [];
            $params = [];
            foreach ($allowed as $f) {
                if (array_key_exists($f, $normalized) && $f !== 'images') {
                    $cols[] = $f;
                    $ph[] = '?';
                    $params[] = $normalized[$f];
                }
            }
            $stmt = $pdo->prepare('INSERT INTO products (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')');
            $stmt->execute($params);
            $newId = $pdo->lastInsertId();

            // handle JSON image URLs
            if (!empty($input['images']) && is_array($input['images'])) {
                $pi = $pdo->prepare('INSERT INTO product_images (product_id, image_url, is_main) VALUES (?, ?, ?)');
                foreach ($input['images'] as $im) {
                    if (empty($im['image_url'])) continue;
                    $pi->execute([$newId, $im['image_url'], !empty($im['is_main']) ? 1 : 0]);
                }
            }

            // handle uploaded files
            if (!empty($_FILES['images'])) {
                $uploadDir = __DIR__ . '/../../uploads/products';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                if (is_array($_FILES['images']['name'])) {
                    $count = count($_FILES['images']['name']);
                    for ($i=0;$i<$count;$i++) {
                        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                        $tmp = $_FILES['images']['tmp_name'][$i];
                        $orig = basename($_FILES['images']['name'][$i]);
                        $safe = time() . '_' . bin2hex(random_bytes(6)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/','_',$orig);
                        $dest = $uploadDir . '/' . $safe;
                        if (move_uploaded_file($tmp, $dest)) {
                            $pdo->prepare('INSERT INTO product_images (product_id, image_url, is_main) VALUES (?, ?, ?)')
                                ->execute([$newId, 'uploads/products/' . $safe, 0]);
                        }
                    }
                } else {
                    if ($_FILES['images']['error'] === UPLOAD_ERR_OK) {
                        $tmp = $_FILES['images']['tmp_name'];
                        $orig = basename($_FILES['images']['name']);
                        $safe = time() . '_' . bin2hex(random_bytes(6)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/','_',$orig);
                        $dest = $uploadDir . '/' . $safe;
                        if (move_uploaded_file($tmp, $dest)) {
                            $pdo->prepare('INSERT INTO product_images (product_id, image_url, is_main) VALUES (?, ?, ?)')
                                ->execute([$newId, 'uploads/products/' . $safe, 0]);
                        }
                    }
                }
            }

            echo json_encode([
                'status' => true,
                'message' => 'Product stored successfully',
                'id' => $newId,
                'product_id' => $normalized['product_id'],
                'product_status' => $normalized['status']
            ]);
            break;

        case 'PUT':
            $id = isset($input['id']) ? (int)$input['id'] : null;
            $productId = $input['product_id'] ?? null;
            if (!$id && !$productId) fail('Missing id or product_id for update');
            $allowedUp = ['product_name','generic_name','brand','category_id','color','material','pattern','character_name','gender','class_type','backpack_style','capacity','net_quantity','recommended_age','size','country_of_origin','net_weight','price','discount_price','stock','description','status'];
            $sets=[]; $params=[];
            foreach ($allowedUp as $f) { if (array_key_exists($f,$input)) { $sets[] = "$f = ?"; $params[] = $input[$f]; } }
            if (empty($sets)) fail('No update fields provided');
            if ($id) { $params[] = $id; $sql = 'UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = ?'; }
            else { $params[] = $productId; $sql = 'UPDATE products SET ' . implode(', ', $sets) . ' WHERE product_id = ?'; }
            $stmt = $pdo->prepare($sql); $stmt->execute($params);
            if (empty($stmt->rowCount())) fail('Product not found or no changes made', 404);

            // handle uploaded images (append)
            if (!empty($_FILES['images'])) {
                $pid = $id;
                if (!$pid && $productId) {
                    $g = $pdo->prepare('SELECT id FROM products WHERE product_id = ? LIMIT 1');
                    $g->execute([$productId]);
                    $pid = $g->fetchColumn();
                    if (!$pid) fail('Product not found after update', 404);
                }
                $uploadDir = __DIR__ . '/../../uploads/products';
                if (!is_dir($uploadDir)) mkdir($uploadDir,0755,true);
                if (is_array($_FILES['images']['name'])) {
                    $count = count($_FILES['images']['name']);
                    for ($i=0;$i<$count;$i++) {
                        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                        $tmp = $_FILES['images']['tmp_name'][$i];
                        $orig = basename($_FILES['images']['name'][$i]);
                        $safe = time() . '_' . bin2hex(random_bytes(6)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/','_',$orig);
                        $dest = $uploadDir . '/' . $safe;
                        if (move_uploaded_file($tmp,$dest)) {
                            $pdo->prepare('INSERT INTO product_images (product_id, image_url, is_main) VALUES (?, ?, ?)')
                                ->execute([$pid, 'uploads/products/' . $safe, 0]);
                        }
                    }
                } else {
                    if ($_FILES['images']['error'] === UPLOAD_ERR_OK) {
                        $tmp = $_FILES['images']['tmp_name'];
                        $orig = basename($_FILES['images']['name']);
                        $safe = time() . '_' . bin2hex(random_bytes(6)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/','_',$orig);
                        $dest = $uploadDir . '/' . $safe;
                        if (move_uploaded_file($tmp,$dest)) {
                            $pdo->prepare('INSERT INTO product_images (product_id, image_url, is_main) VALUES (?, ?, ?)')
                                ->execute([$pid, 'uploads/products/' . $safe, 0]);
                        }
                    }
                }
            }

            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
            $productId = $_GET['product_id'] ?? null;
            if (!$id && !$productId) fail('Missing id or product_id for delete');
            if ($id) { $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?'); $stmt->execute([$id]); }
            else { $stmt = $pdo->prepare('DELETE FROM products WHERE product_id = ?'); $stmt->execute([$productId]); }
            if (empty($stmt->rowCount())) fail('Product not found',404);
            echo json_encode(['success' => true]);
            break;

        default: fail('Method not allowed',405);
    }
    exit;
}

// Public (non-admin) GET: listing only
if ($method !== 'GET') fail('Forbidden', 403);

$status = $_GET['status'] ?? 'published';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;

$sql = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.status = ?";
$params = [$status];
if ($id) { $sql .= ' AND p.id = ?'; $params[] = $id; }
if ($categoryId) { $sql .= ' AND p.category_id = ?'; $params[] = $categoryId; }
$sql .= ' ORDER BY p.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($products)) { echo json_encode(['products'=>[]]); exit; }

$productIds = array_column($products, 'id');
$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$stmt = $pdo->prepare("SELECT product_id, image_url, is_main FROM product_images WHERE product_id IN ($placeholders)");
$stmt->execute($productIds);
$images = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $images[$row['product_id']][] = [ 'image_url' => $row['image_url'], 'is_main' => (bool)$row['is_main'] ];
}

foreach ($products as &$product) {
    $imgs = $images[$product['id']] ?? [];
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $basePath = rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
    $base = $scheme . '://' . $host . $basePath;
    foreach ($imgs as &$img) {
        if (strpos($img['image_url'], 'http') !== 0) {
            $img['image_url'] = $base . '/' . ltrim($img['image_url'], '/');
        }
    }
    $product['images'] = $imgs;
}

echo json_encode(['products' => $products]);
