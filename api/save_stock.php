<?php
header('Content-Type: application/json; charset=utf-8');
require '../config.php';
require '../includes/auth.php';
requireRole(['admin', 'ngo_operator']);
requireCsrf();

$user = currentUser();
$ngoId = $user['role'] === 'admin'
    ? (int)($_POST['ngo_id'] ?? 0)
    : (int)$user['ngo_id'];
$itemId = (int)($_POST['item_id'] ?? 0);
$addQty = (float)($_POST['add_quantity'] ?? 0);

if ($ngoId === 0 || $itemId === 0 || $addQty <= 0) {
    echo json_encode(['status' => 'error', 'message' => t('api_dist_fields_required')]);
    exit;
}

// upsert: ngo_stock row may not exist yet
$stmt = $pdo->prepare("
    INSERT INTO ngo_stock (ngo_id, item_id, quantity_available) VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE quantity_available = quantity_available + VALUES(quantity_available)
");
$stmt->execute([$ngoId, $itemId, $addQty]);

$check = $pdo->prepare("SELECT s.quantity_available, ri.unit FROM ngo_stock s JOIN relief_item ri ON s.item_id = ri.item_id WHERE s.ngo_id = ? AND s.item_id = ?");
$check->execute([$ngoId, $itemId]);
$row = $check->fetch();

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => sprintf(t('api_save_failed'), 'ngo_stock row not found')]);
    exit;
}

echo json_encode([
    'status'       => 'success',
    'new_quantity' => number_format((float)$row['quantity_available'], 2),
    'unit'         => $row['unit'],
    'message'      => sprintf(t('stock_added_msg'), $addQty, $row['unit']),
]);
