<?php
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

$identifier = isset($input['email']) ? trim((string)$input['email']) : '';
if ($identifier === '' && isset($input['username'])) {
    $identifier = trim((string)$input['username']);
}

$password = isset($input['password']) ? (string)$input['password'] : '';

if ($identifier === '' || $password === '') {
    fail('Missing email/username or password', 400);
}

// Query as per user requirement
// SELECT `id`, `name`, `email`, `password`, `role`, `is_active`, `last_login`, `created_at`, `updated_at` FROM `admins` WHERE 1

// If they provided email, filter by email.
// If not, allow username (existing legacy login.php uses username).
try {
    if (isset($input['email']) && trim((string)$input['email']) !== '') {
        // login by email
        $sql = "SELECT `id`, `name`, `email`, `password`, `role`, `is_active`, `last_login`, `created_at`, `updated_at` FROM `admins` WHERE email = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // login by name or username
        // Prefer `username` if it exists, otherwise fall back to `name`.
        $row = null;

        try {
            $colStmt = $pdo->query("SHOW COLUMNS FROM admins LIKE 'username'");
            if ($colStmt->fetchColumn()) {
                $sqlU = "SELECT `id`, `name`, `email`, `password`, `role`, `is_active`, `last_login`, `created_at`, `updated_at` FROM `admins` WHERE username = ? LIMIT 1";
                $stmtU = $pdo->prepare($sqlU);
                $stmtU->execute([$identifier]);
                $row = $stmtU->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Throwable $e) {
            // ignore
        }

        if (!$row) {
            $sqlN = "SELECT `id`, `name`, `email`, `password`, `role`, `is_active`, `last_login`, `created_at`, `updated_at` FROM `admins` WHERE name = ? LIMIT 1";
            $stmtN = $pdo->prepare($sqlN);
            $stmtN->execute([$identifier]);
            $row = $stmtN->fetch(PDO::FETCH_ASSOC);
        }
    }

    if (!$row) {
        fail('Invalid credentials', 401);
    }

    $stored = $row['password'];

    // Support both hashed and plain password (for dev setups)
    $isValid = false;
    if (is_string($stored) && strlen($stored) > 0 && preg_match('/^\$2y\$/', $stored)) {
        $isValid = password_verify($password, $stored);
    } else {
        // If password is stored in plain text (not recommended, but supports existing DB)
        $isValid = hash_equals((string)$stored, (string)$password);

        // Or in case it's bcrypt without $2y$ prefix
        if (!$isValid && is_string($stored) && str_starts_with($stored, '$2')) {
            $isValid = password_verify($password, $stored);
        }
    }

    if (!$isValid) {
        fail('Invalid credentials', 401);
    }


    // Do not return the password hash in response
    unset($row['password']);

    echo json_encode([
        'status' => true,
        'message' => 'Login success',
        'data' => $row
    ]);
} catch (PDOException $e) {
    fail('Database error', 500);
}

