<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => false, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$category_id = $_GET['category_id'] ?? null;

if ($id || $category_id) {
    $sql = 'SELECT id, category_id, name, description, created_at, updated_at FROM categories WHERE ' . ($id ? 'id = ?' : 'category_id = ?') . ' LIMIT 1';
    $param = $id ? $id : $category_id;
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$param]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) fail('Category not found', 404);
    echo json_encode($row);
    exit;
}

$stmt = $pdo->query('SELECT id, category_id, name, description, created_at, updated_at FROM categories ORDER BY created_at DESC');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['categories' => $rows]);
