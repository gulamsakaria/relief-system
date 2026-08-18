<?php
header('Content-Type: application/json; charset=utf-8');
require '../config.php';
require '../includes/auth.php';
requireRole(['ngo_operator']);
requireCsrf();

$user = currentUser();
$ngoId = (int)$user['ngo_id'];
$phone = trim($_POST['phone'] ?? '');

$logoPath = null;

if (!empty($_FILES['logo']['name'])) {
    $file = $_FILES['logo'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => t('err_logo_upload')]);
        exit;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'message' => t('err_logo_too_large')]);
        exit;
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        echo json_encode(['status' => 'error', 'message' => t('err_logo_type')]);
        exit;
    }

    $dir = __DIR__ . '/../img/ngo_logos';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = 'ngo_' . $ngoId . '_' . time() . '.' . $allowed[$mime];
    $dest = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['status' => 'error', 'message' => t('err_logo_upload')]);
        exit;
    }

    // Remove the previous logo file, if any, now that the new one is safely written.
    $old = $pdo->prepare("SELECT logo_path FROM ngo WHERE ngo_id = ?");
    $old->execute([$ngoId]);
    $oldPath = $old->fetchColumn();
    if ($oldPath) {
        $oldFile = __DIR__ . '/../' . ltrim($oldPath, '/');
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }

    $logoPath = 'img/ngo_logos/' . $filename;
}

if ($logoPath !== null) {
    $stmt = $pdo->prepare("UPDATE ngo SET contact_phone = ?, logo_path = ? WHERE ngo_id = ?");
    $stmt->execute([$phone ?: null, $logoPath, $ngoId]);
} else {
    $stmt = $pdo->prepare("UPDATE ngo SET contact_phone = ? WHERE ngo_id = ?");
    $stmt->execute([$phone ?: null, $ngoId]);
}

echo json_encode([
    'status'    => 'success',
    'message'   => t('profile_saved_msg'),
    'logo_path' => $logoPath,
]);
