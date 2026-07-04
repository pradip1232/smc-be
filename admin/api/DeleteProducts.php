<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => false, 'message' => $msg]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Support both DELETE and POST (JSON) for frontend convenience.
// - DELETE .../DeleteProducts.php?id=3
// - POST   .../DeleteProducts.php with JSON body: {"id":3} OR {"product_id":"..."}
if (!in_array($method, ['DELETE', 'POST'], true)) {
    fail('Method not allowed', 405);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$productId = $_GET['product_id'] ?? null;

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (!$id && isset($input['id'])) {
        $id = (int)$input['id'];
    }
    if (empty($productId) && !empty($input['product_id'])) {
        $productId = $input['product_id'];
    }
}

if (!$id && !$productId) fail('Missing id or product_id for delete');

if ($id) {
    $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare('DELETE FROM products WHERE product_id = ?');
    $stmt->execute([$productId]);
}

if (empty($stmt->rowCount())) fail('Product not found', 404);
echo json_encode(['status' => true, 'message' => 'Product deleted successfully']);

