<?php
header('Content-Type: application/json; charset=utf-8');
require '../config.php';
require '../includes/auth.php';
requireRole(['admin', 'ngo_operator']);
requireCsrf();

$user = currentUser();
$nid     = trim($_POST['nid'] ?? '');
$itemId  = (int)($_POST['item_id'] ?? 0);
$pointId = (int)($_POST['point_id'] ?? 0);
$qty     = (float)($_POST['quantity'] ?? 0);

$ngoId = $user['role'] === 'admin'
    ? (int)($_POST['ngo_id'] ?? 0)
    : (int)$user['ngo_id'];

if ($nid === '' || $itemId === 0 || $pointId === 0 || $qty <= 0 || $ngoId === 0) {
    echo json_encode(['status' => 'error', 'message' => t('api_dist_fields_required')]);
    exit;
}

$hash = hashNID($nid);

try {
    $stmt = $pdo->prepare("CALL sp_distribute_relief(:hash, :ngo, :item, :point, :qty, @status)");
    $stmt->execute([
        ':hash'  => $hash,
        ':ngo'   => $ngoId,
        ':item'  => $itemId,
        ':point' => $pointId,
        ':qty'   => $qty,
    ]);
    $stmt->closeCursor(); // required before reading the OUT parameter

    $result = $pdo->query("SELECT @status AS status")->fetch();
    $message = $result['status'];

    if (str_starts_with($message, 'SUCCESS')) {
        echo json_encode(['status' => 'success', 'message' => $message]);
    } elseif (str_starts_with($message, 'BLOCKED')) {
        echo json_encode(['status' => 'blocked', 'message' => $message]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $message]);
    }
} catch (PDOException $e) {
    // trg_block_duplicate can also SIGNAL an error directly
    echo json_encode(['status' => 'blocked', 'message' => 'DUPLICATE BLOCKED (trigger): ' . $e->getMessage()]);
}
