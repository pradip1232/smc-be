<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => false, 'message' => $msg]);
    exit;
}

function generate_category_id(PDO $pdo) {
    $prefix = 'SMC-CATE-';
    $stmt = $pdo->prepare("SELECT category_id FROM categories WHERE category_id LIKE ? ORDER BY category_id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    if ($last && preg_match('/^' . preg_quote($prefix, '/') . '(\d{4})$/', $last, $matches)) {
        $next = (int)$matches[1] + 1;
    } else {
        $next = 1;
    }

    $categoryId = $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);

    // Double-check uniqueness in case of race or manual inserts
    $check = $pdo->prepare('SELECT 1 FROM categories WHERE category_id = ? LIMIT 1');
    $check->execute([$categoryId]);
    if ($check->fetchColumn()) {
        return generate_category_id($pdo);
    }

    return $categoryId;
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$categoryIdRaw = $input['category_id'] ?? ($_POST['category_id'] ?? '');
$nameRaw = $input['category_name'] ?? ($_POST['category_name'] ?? ($input['name'] ?? ($_POST['name'] ?? '')));
$descriptionRaw = $input['description'] ?? ($_POST['description'] ?? null);

// Ensure values are strings before trimming
$category_id = is_string($categoryIdRaw) ? trim($categoryIdRaw) : '';
$name = is_string($nameRaw) ? trim($nameRaw) : '';
$description = is_string($descriptionRaw) ? $descriptionRaw : null;

if (!$category_id) {
    $category_id = generate_category_id($pdo);
}
if (!$name) fail('Missing category_name');

try {
    $stmt = $pdo->prepare('INSERT INTO categories (category_id, name, description) VALUES (?, ?, ?)');
    $stmt->execute([$category_id, $name, $description]);
    echo json_encode([
        'status' => true,
        'message' => 'Category created successfully',
        'id' => $pdo->lastInsertId(),
        'category_id' => $category_id,
        'category_name' => $name
    ]);
} catch (PDOException $e) {
    if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
        fail('Duplicate category_id', 409);
    }
    fail('Database error', 500);
}
