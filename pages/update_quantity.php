<?php
session_start();
require '../includes/db_conn.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);
if (!is_array($data)) {
    $data = $_POST;
}

if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit();
}

$id = isset($data['id']) ? (int)$data['id'] : 0;
$has_delta = isset($data['delta']);
$has_new_qty = isset($data['new_quantity']);

if ($id <= 0 || (!$has_delta && !$has_new_qty)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid item ID or quantity payload']);
    exit();
}

// Fetch current quantity, name, and min_quantity
$stmt = $conn->prepare("SELECT item_name, quantity, min_quantity FROM inventory WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    $stmt->close();
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Item not found']);
    exit();
}

$row_data = $res->fetch_assoc();
$item_name = $row_data['item_name'];
$current_qty = (int)$row_data['quantity'];
$min_qty = (int)($row_data['min_quantity'] ?? 0);
$stmt->close();

if ($has_new_qty) {
    $new_qty = max(0, (int)$data['new_quantity']);
    $actual_delta = $new_qty - $current_qty;
} else {
    $delta = (int)$data['delta'];
    $new_qty = max(0, $current_qty + $delta);
    $actual_delta = $new_qty - $current_qty;
}

$upd = $conn->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
$upd->bind_param("ii", $new_qty, $id);
if ($upd->execute()) {
    $upd->close();

    // Log stock change in audit trail
    $user_notes = !empty($data['notes']) ? trim($data['notes']) : null;
    $change_type = !empty($data['change_type']) && in_array($data['change_type'], ['quick_adjust', 'restock', 'manual_edit'], true)
        ? $data['change_type']
        : (!empty($user_notes) ? 'restock' : 'quick_adjust');

    $log_notes = $user_notes ?: ($actual_delta > 0 ? "Quick Stepper (+{$actual_delta})" : "Quick Stepper ({$actual_delta})");

    log_stock_movement(
        $conn,
        $id,
        $item_name,
        (int)($_SESSION['user_id'] ?? 0),
        $_SESSION['username'] ?? 'System',
        $change_type,
        $actual_delta,
        $current_qty,
        $new_qty,
        $log_notes
    );

    $status = get_stock_status($new_qty, $min_qty);
    echo json_encode([
        'success'      => true,
        'id'           => $id,
        'item_name'    => $item_name,
        'quantity'     => $new_qty,
        'min_quantity' => $min_qty,
        'delta'        => $actual_delta,
        'stock_status' => $status
    ]);
} else {
    $err = $upd->error;
    $upd->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $err]);
}
