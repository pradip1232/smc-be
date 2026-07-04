<?php
// CreateOrderOnline.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once '../../../db/db_con.php';



// === IMPROVED INCLUDE HANDLING ===
$accessToken = null;
$tokenIncluded = false;

$possibleTokenPaths = [
    '../../../accessToken.php',
    '../accessToken.php',
    'accessToken.php',
    '../../accessToken.php'
];

foreach ($possibleTokenPaths as $path) {
    if (file_exists($path)) {
        include $path;
        $tokenIncluded = true;
        break;
    }
}

if (!$tokenIncluded || empty($accessToken)) {
    error_log("Access Token file missing or \$accessToken not defined");
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'Payment configuration error: accessToken.php not found or token missing'
    ]);
    exit;
}

// Parse input
$input = file_get_contents('php://input');
$body = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE || empty($body)) {
    $body = $_POST;
}

$paymentMethod = strtoupper(trim($body['payment_method'] ?? 'ONLINE'));
$user_id = isset($body['user_id']) && is_numeric($body['user_id']) ? (int) $body['user_id'] : null;
$total_amount = isset($body['total_amount']) ? (float) $body['total_amount'] : 120;
$subtotal = (float) ($body['subtotal'] ?? 0);
$tax = (float) ($body['tax'] ?? 0);
$shipping_cost = (float) ($body['shipping_cost'] ?? 0);

$items = $body['items'] ?? [];
$shipping = $body['shipping'] ?? [];

// Normalize shipping
if (empty($shipping) || !is_array($shipping)) {
    $shipping = [
        'firstName' => null,
        'lastName' => null,
        'name' => trim($body['customer_name'] ?? ''),
        'phone' => trim($body['phone'] ?? ''),
        'email' => trim($body['customer_email'] ?? $body['email'] ?? ''),
        'address' => trim($body['shipping_address'] ?? ''),
        'city' => trim($body['city'] ?? ''),
        'state' => trim($body['state'] ?? ''),
        'country' => trim($body['country'] ?? 'India'),
        'zip' => trim($body['pincode'] ?? ''),
    ];
}

if ($paymentMethod !== 'ONLINE') {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Only ONLINE payment is supported']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Generate Order ID
    $order_ref = generateOrderId($pdo);

    $customer_name = trim(($shipping['firstName'] ?? '') . ' ' . ($shipping['lastName'] ?? ''));
    if (empty($customer_name)) {
        $customer_name = $shipping['name'] ?: 'Guest';
    }

    // Insert Main Order
    $stmt = $pdo->prepare('
        INSERT INTO orders (
            order_id, user_id, subtotal, tax, shipping_cost, total_amount,
            payment_method, payment_status, order_status,
            customer_name, customer_email, customer_phone,
            shipping_address, city, state, country, pincode, status
        ) VALUES (
            :order_id, :user_id, :subtotal, :tax, :shipping_cost, :total_amount,
            :payment_method, :payment_status, :order_status,
            :customer_name, :customer_email, :customer_phone,
            :shipping_address, :city, :state, :country, :pincode, :status
        )
    ');

    $stmt->execute([
        ':order_id' => $order_ref,
        ':user_id' => $user_id,
        ':subtotal' => $subtotal,
        ':tax' => $tax,
        ':shipping_cost' => $shipping_cost,
        ':total_amount' => $total_amount,
        ':payment_method' => 'ONLINE',
        ':payment_status' => 'pending',
        ':order_status' => 'pending',
        ':customer_name' => $customer_name,
        ':customer_email' => $shipping['email'] ?: null,
        ':customer_phone' => $shipping['phone'],
        ':shipping_address' => $shipping['address'] ?: null,
        ':city' => $shipping['city'] ?: null,
        ':state' => $shipping['state'] ?: null,
        ':country' => $shipping['country'],
        ':pincode' => $shipping['zip'] ?: null,
        ':status' => 'pending',
    ]);

    $order_db_id = (int) $pdo->lastInsertId();

    // Insert Order Items
    $itemStmt = $pdo->prepare('
        INSERT INTO order_items (
            order_id, product_id, product_sku, product_name, quantity,
            unit_price, total_price, selected_color, selected_size, image_url
        ) VALUES (
            :order_id, :product_id, :product_sku, :product_name, :quantity,
            :unit_price, :total_price, :selected_color, :selected_size, :image_url
        )
    ');

    foreach ($items as $it) {
        $identifier = $it['product_id'] ?? $it['id'] ?? null;
        if (!$identifier) continue;

        $qty = max(1, (int) ($it['quantity'] ?? 1));

        $prodStmt = $pdo->prepare('
            SELECT id, product_id AS sku, product_name, price, discount_price 
            FROM products 
            WHERE (id = :id OR product_id = :sku) 
            LIMIT 1
        ');
        $prodStmt->execute([
            ':id' => is_numeric($identifier) ? (int) $identifier : 0,
            ':sku' => $identifier
        ]);
        $prod = $prodStmt->fetch(PDO::FETCH_ASSOC);

        if (!$prod) {
            throw new Exception('Product not found: ' . htmlspecialchars($identifier));
        }

        $unit_price = $it['price'] ?? $it['unit_price']
            ?? (($prod['discount_price'] ?? 0) > 0 ? $prod['discount_price'] : $prod['price']);

        $total_price = $it['total_price'] ?? ($unit_price * $qty);

        $itemStmt->execute([
            ':order_id' => $order_db_id,
            ':product_id' => $prod['id'],
            ':product_sku' => $prod['sku'] ?? $identifier,
            ':product_name' => $prod['product_name'] ?? ($it['product_name'] ?? ''),
            ':quantity' => $qty,
            ':unit_price' => (float) $unit_price,
            ':total_price' => (float) $total_price,
            ':selected_color' => $it['selectedColor'] ?? $it['selected_color'] ?? null,
            ':selected_size' => $it['selectedSize'] ?? $it['selected_size'] ?? null,
            ':image_url' => $it['image'] ?? $it['image_url'] ?? null,
        ]);
    }

    $pdo->commit();

    // ==================== PHONEPE PAYMENT INITIATION ====================
    $morderid = 'ORD' . time();
    $redirectUrl = 'http://localhost:5173/payment-status?morderid=' . urlencode($morderid);  // Must be full URL


    $payload = [
        'merchantOrderId' => $morderid,
        'amount' => (int) ($total_amount * 100),  // Use actual amount (in paise)
        'expireAfter' => 1200,
        'metaInfo' => [
            'udf1' => $order_ref,           // Link back to your order
            'udf2' => (string)($user_id ?? 'guest'),
        ],
        'paymentFlow' => [
            'type' => 'PG_CHECKOUT',
            'message' => 'Payment for Order ' . $order_ref,
            'merchantUrls' => [
                'redirectUrl' => $redirectUrl,  // Must be full URL
                'webhookUrl' => 'https://webhook.site/your-webhook-url' // Update this
            ]
        ]
    ];

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api-preprod.phonepe.com/apis/pg-sandbox/checkout/v2/pay',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: O-Bearer ' . trim($accessToken)
        ],
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($curlError) {
        error_log("cURL Error: " . $curlError);
        throw new Exception("Payment gateway connection failed");
    }

    $data = json_decode($response, true);

    if (isset($data['redirectUrl']) && !empty($data['redirectUrl'])) {
        session_start();
        $_SESSION['merchantOrderId'] = $morderid;
        $_SESSION['order_ref'] = $order_ref;   // Extra safety

        // header("Location: " . $data['redirectUrl']);
          echo json_encode([
            'status' => true,
            'message' => 'Order saved',
            'order_id' => $order_ref,
            'redirect_url' => $data['redirectUrl']
        ]);
        exit();
    } else {
        // Payment initiation failed but order is saved
        error_log("PhonePe Payment Failed - HTTP $httpCode: " . $response);

        http_response_code(502);
        echo json_encode([
            'status' => false,
            'message' => 'Order saved but payment initiation failed',
            'order_id' => $order_ref,
            'debug' => [
                'http_code' => $httpCode,
                'phonepe_response' => $data ?? $response
            ]
        ]);
        exit();
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[Order Creation Failed] ' . $e->getMessage() . ' | Input: ' . json_encode($body));

    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);
}

// ==================== ORDER ID GENERATOR ====================
function generateOrderId(PDO $pdo): string
{
    try {
        $stmt = $pdo->query("
            SELECT AUTO_INCREMENT 
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'orders'
        ");

        $nextId = (int) $stmt->fetchColumn();

        if ($nextId > 0) {
            return 'SMC-ODR-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
        }
    } catch (Exception $e) {
        error_log('AUTO_INCREMENT fallback failed: ' . $e->getMessage());
    }

    // Fallback
    $stmt = $pdo->query("
        SELECT order_id FROM orders 
        WHERE order_id LIKE 'SMC-ODR-%' 
        ORDER BY id DESC LIMIT 1
    ");

    $last = $stmt->fetchColumn();

    $num = $last ? (int) str_replace('SMC-ODR-', '', $last) + 1 : 1;

    return 'SMC-ODR-' . str_pad($num, 5, '0', STR_PAD_LEFT);
}