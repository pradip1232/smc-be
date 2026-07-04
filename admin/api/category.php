<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => false, 'message' => $msg]);
    exit;
}

function generate_category_id(PDO $pdo) {
    do {
        $categoryId = 'cat_' . bin2hex(random_bytes(8));
        $stmt = $pdo->prepare('SELECT 1 FROM categories WHERE category_id = ? LIMIT 1');
        $stmt->execute([$categoryId]);
        $exists = $stmt->fetchColumn();
    } while ($exists);
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

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($method) {
    case 'GET':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        $categoryId = $_GET['category_id'] ?? null;

        if ($id || $categoryId) {
            $sql = 'SELECT id, category_id, name, description, created_at, updated_at FROM categories WHERE ' . ($id ? 'id = ?' : 'category_id = ?') . ' LIMIT 1';
            $param = $id ? $id : $categoryId;
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
        break;

    case 'POST':
        $categoryIdRaw = $input['category_id'] ?? ($_POST['category_id'] ?? '');
        $nameRaw = $input['name'] ?? ($_POST['name'] ?? ($input['category_name'] ?? ($_POST['category_name'] ?? '')));
        $descriptionRaw = $input['description'] ?? ($_POST['description'] ?? null);
        
        // Ensure values are strings before trimming
        $category_id = is_string($categoryIdRaw) ? trim($categoryIdRaw) : '';
        $name = is_string($nameRaw) ? trim($nameRaw) : '';
        $description = is_string($descriptionRaw) ? $descriptionRaw : null;

        if (!$category_id) {
            $category_id = generate_category_id($pdo);
        }
        if (!$name) fail('Missing name or category_name');

        try {
            $stmt = $pdo->prepare('INSERT INTO categories (category_id, name, description) VALUES (?, ?, ?)');
            $stmt->execute([$category_id, $name, $description]);
            echo json_encode([
                'status' => true,
                'message' => 'Category created successfully',
                'id' => $pdo->lastInsertId(),
                'category_id' => $category_id
            ]);
        } catch (PDOException $e) {
            if (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                fail('Duplicate category_id', 409);
            }
            fail('Database error', 500);
        }
        break;

    case 'PUT':
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
        echo json_encode(['success' => true]);
        break;

    case 'DELETE':
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
        echo json_encode(['success' => true]);
        break;

    default:
        fail('Method not allowed', 405);
}
