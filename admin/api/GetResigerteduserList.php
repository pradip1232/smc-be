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

// filters
$filterName = isset($_GET['name']) ? trim((string)$_GET['name']) : '';
$filterEmail = isset($_GET['email']) ? trim((string)$_GET['email']) : '';
$filterPhone = isset($_GET['phone']) ? trim((string)$_GET['phone']) : '';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
$offset = ($page - 1) * $perPage;

// Filters for the actual users table columns you provided:
// first_name, last_name, email, phone_number
$where = [];
$params = [];

if ($filterName !== '') {
    $where[] = '(first_name LIKE ? OR last_name LIKE ?)';
    $like = '%' . $filterName . '%';
    $params[] = $like;
    $params[] = $like;
}

if ($filterEmail !== '') {
    $where[] = 'email LIKE ?';
    $params[] = '%' . $filterEmail . '%';
}

if ($filterPhone !== '') {
    $where[] = 'phone_number LIKE ?';
    $params[] = '%' . $filterPhone . '%';
}

$whereSql = !empty($where) ? (' WHERE ' . implode(' AND ', $where)) : '';

// total records
$countSql = 'SELECT COUNT(*) FROM users' . $whereSql;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalCount = (int)($countStmt->fetchColumn() ?? 0);
$totalPages = $perPage > 0 ? (int)ceil($totalCount / $perPage) : 0;

// list (return columns as in your query)
$listSql = 'SELECT `id`, `user_unique_id`, `first_name`, `last_name`, `email`, `phone_number`, `city`, `state`, `country`, `landmark_address`, `password`, `status`, `created_at`, `updated_at`'
    . ' FROM `users`'
    . $whereSql
    . ' ORDER BY id DESC'
    . ' LIMIT ' . ((int)$perPage)
    . ' OFFSET ' . ((int)$offset);

$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);


echo json_encode([
    'status' => true,
    'data' => [
        'users' => $rows,
        'page' => $page,
        'perPage' => $perPage,
        'totalRecords' => $totalCount,
        'totalPages' => $totalPages,
    ]
]);

