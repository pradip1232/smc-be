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
    if (!$expected || $provided !== $expected) {
        fail('Forbidden', 403);
    }
}

// enforce admin access
// Temporarily disabled admin token check for development. Re-enable by uncommenting the line below.
// require_admin_token();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    fail('Method not allowed', 405);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$category_id = $_GET['category_id'] ?? null;
if (!$id && !$category_id) fail('Missing id or category_id for delete');

if ($id) {
    $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare('DELETE FROM categories WHERE category_id = ?');
    $stmt->execute([$category_id]);
}

if ($stmt->rowCount() === 0) fail('Category not found', 404);
echo json_encode(['status' => true, 'message' => 'Category deleted successfully']);
