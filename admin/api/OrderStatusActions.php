<?php
// admin/api/OrderStatusActions.php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$data = json_decode(file_get_contents('php://input'), true);

$orderIdentifier = $data['order_id'] ?? null;
$action_type     = $data['action_type'] ?? null;
$reason          = $data['reason'] ?? null;
$admin_id        = $data['admin_id'] ?? 1;
$old_status      = $data['old_status'] ?? null;

if (!$orderIdentifier || !$action_type) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'order_id and action_type are required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Find the actual order (support both id and order_id string)
    $stmt = $pdo->prepare("
        SELECT id, status, order_id 
        FROM orders 
        WHERE id = ? OR order_id = ? 
        LIMIT 1
    ");
    $stmt->execute([$orderIdentifier, $orderIdentifier]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $pdo->rollBack();
        echo json_encode(['status' => false, 'message' => 'Order not found']);
        exit;
    }

    $actualOrderId = $order['id'];           // Integer ID required for FK
    $currentStatus = $order['status'];

    $new_status = ($action_type === 'approved') ? 'approved' : 'rejected';

    // Update Orders Table
    $updateStmt = $pdo->prepare("
        UPDATE orders 
        SET status = ?, 
           
            last_action_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$new_status, $actualOrderId]);

    // Insert Action Log
    $actionStmt = $pdo->prepare("
        INSERT INTO order_actions 
        (order_id, action_type, action_by, reason, old_status, new_status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    $actionStmt->execute([
        $actualOrderId,
        $action_type,
        $admin_id,
        $reason,
        $old_status ?? $currentStatus,
        $new_status
    ]);

    $pdo->commit();

    echo json_encode([
        'status' => true,
        'message' => $action_type === 'approved' 
                        ? 'Order Approved Successfully' 
                        : 'Order Rejected Successfully',
        'order_id' => $order['order_id'],
        'actual_id' => $actualOrderId
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'status' => false,
        'message' => $e->getMessage()
    ]);
}