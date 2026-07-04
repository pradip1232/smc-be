<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => false, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    fail('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$id = isset($input['id']) ? (int)$input['id'] : null;
$productId = $input['product_id'] ?? null;
if (!$id && !$productId) fail('Missing id or product_id for update');

$allowedUp = ['product_name','generic_name','brand','category_id','color','material','pattern','character_name','gender','class_type','backpack_style','capacity','net_quantity','recommended_age','size','country_of_origin','net_weight','price','discount_price','stock','description','status'];

$sets=[];
$params=[];
foreach ($allowedUp as $f) {
    if (array_key_exists($f,$input)) {
        $sets[] = "$f = ?";
        $params[] = $input[$f];
    }
}
if (empty($sets)) fail('No update fields provided');

if ($id) {
    $params[] = $id;
    $sql = 'UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = ?';
} else {
    $params[] = $productId;
    $sql = 'UPDATE products SET ' . implode(', ', $sets) . ' WHERE product_id = ?';
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
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

echo json_encode(['status' => true, 'message' => 'Product updated successfully']);
