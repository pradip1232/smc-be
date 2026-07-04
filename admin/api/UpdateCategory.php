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

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    fail('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$id = isset($input['id']) ? (int)$input['id'] : null;
$category_id = $input['category_id'] ?? null;
if (!$id && !$category_id) fail('Missing id or category_id for update');

$fields = [];
$params = [];
if (isset($input['name'])) { $fields[] = 'name = ?'; $params[] = $input['name']; }
if (array_key_exists('description', $input)) { $fields[] = 'description = ?'; $params[] = $input['description']; }
if (isset($input['category_id'])) { $fields[] = 'category_id = ?'; $params[] = $input['category_id']; }

if (empty($fields)) fail('No fields to update');

if ($id) {
    $params[] = $id;
    $sql = 'UPDATE categories SET ' . implode(', ', $fields) . ' WHERE id = ?';
} else {
    $params[] = $category_id;
    $sql = 'UPDATE categories SET ' . implode(', ', $fields) . ' WHERE category_id = ?';
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
if ($stmt->rowCount() === 0) fail('Category not found or no changes made', 404);
echo json_encode(['status' => true, 'message' => 'Category updated successfully']);
