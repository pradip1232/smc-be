    <?php
    header('Content-Type: application/json');
    require_once __DIR__ . '/db.php';

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $email = trim((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');

    $firstName = trim((string)($input['first_name'] ?? ''));
    $lastName = trim((string)($input['last_name'] ?? ''));
    $phoneNumber = trim((string)($input['phone_number'] ?? ''));
    $city = trim((string)($input['city'] ?? ''));
    $state = trim((string)($input['state'] ?? ''));
    $country = trim((string)($input['country'] ?? ''));
    $landmarkAddress = trim((string)($input['landmark_address'] ?? ''));

    // Validate required fields that schema.sql + login.php expect
    if ($email === '' || $password === '' || $firstName === '' || $lastName === '' || $phoneNumber === '') {
        http_response_code(400);
        echo json_encode([
            'status' => false,
            'message' => 'Missing fields',
            'required' => ['first_name', 'last_name', 'email', 'phone_number', 'password']
        ]);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        // users table expected by login.php:
        // users(id, first_name, last_name, email, phone_number, password, status, created_at, updated_at)
        $status = isset($input['status']) ? (int)$input['status'] : 1;

        $stmt = $pdo->prepare(
            'INSERT INTO users (first_name, last_name, email, phone_number, city, state, country, landmark_address, password, status) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $firstName,
            $lastName,
            $email,
            $phoneNumber,
            $city !== '' ? $city : null,
            $state !== '' ? $state : null,
            $country !== '' ? $country : null,
            $landmarkAddress !== '' ? $landmarkAddress : null,
            $hash,
            $status
        ]);

        echo json_encode([
            'status' => true,
            'success' => true,
            'message' => 'Registered successfully',
            'user_id' => (int)$pdo->lastInsertId()
        ]);
    } catch (PDOException $e) {
        // Duplicate entry
        if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
            http_response_code(409);
            echo json_encode(['status' => false, 'message' => 'Email or phone number already exists']);
        } else {
            http_response_code(500);
            echo json_encode([
                'status' => false,
                'message' => 'Database error',
                'debug' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
        }
    }


