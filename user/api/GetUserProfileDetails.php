<?php
// user/api/GetUserProfile.php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => false, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$userId = $input['user_id'] ?? null;
$email  = $input['email'] ?? null;

if (!$userId && !$email) {
    fail('Either user_id or email is required', 400);
}

try {
    $sql = "SELECT 
                id, first_name, last_name, email, phone_number, 
                city, state, country, landmark_address, 
                status, created_at, updated_at 
            FROM users WHERE ";

    $params = [];

    if ($userId) {
        $sql .= "id = ?";
        $params[] = $userId;
    } else {
        $sql .= "email = ?";
        $params[] = $email;
    }

    $sql .= " LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        fail('User not found', 404);
    }

    echo json_encode([
        'status' => true,
        'message' => 'Profile fetched successfully',
        'user' => [
            'id'               => (int)$user['id'],
            'first_name'       => $user['first_name'],
            'last_name'        => $user['last_name'],
            'full_name'        => trim($user['first_name'] . ' ' . $user['last_name']),
            'email'            => $user['email'],
            'phone_number'     => $user['phone_number'],
            'city'             => $user['city'],
            'state'            => $user['state'],
            'country'          => $user['country'],
            'landmark_address' => $user['landmark_address'],
            'status'           => (int)$user['status'],
            'created_at'       => $user['created_at'],
            'updated_at'       => $user['updated_at']
        ]
    ]);

} catch (Exception $e) {
    fail('Failed to fetch profile: ' . $e->getMessage(), 500);
}