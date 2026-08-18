<?php
header('Content-Type: application/json; charset=utf-8');
require '../config.php';
require '../includes/auth.php';
requireRole(['admin', 'ngo_operator']);
requireCsrf();

$pointName = trim($_POST['point_name'] ?? '');
$upazilaId = (int)($_POST['upazila_id'] ?? 0);

if ($pointName === '' || $upazilaId === 0) {
    echo json_encode(['status' => 'error', 'message' => t('api_point_required')]);
    exit;
}

$areaStmt = $pdo->prepare("SELECT area_id, area_name FROM area WHERE upazila_id = ?");
$areaStmt->execute([$upazilaId]);
$area = $areaStmt->fetch();

$ins = $pdo->prepare("INSERT INTO distribution_point (point_name, area_id, upazila_id) VALUES (?, ?, ?)");
try {
    $ins->execute([$pointName, $area['area_id'] ?? null, $upazilaId]);
    echo json_encode([
        'status'      => 'success',
        'point_id'    => (int)$pdo->lastInsertId(),
        'point_name'  => $pointName,
        'area_name'   => $area['area_name'] ?? null,
        'message'     => sprintf(t('api_point_saved'), $pointName),
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => sprintf(t('api_save_failed'), $e->getMessage())]);
}
