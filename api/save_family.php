<?php
header('Content-Type: application/json; charset=utf-8');
require '../config.php';
require '../includes/auth.php';
requireRole(['admin', 'ngo_operator']);
requireCsrf();

$nid       = trim($_POST['nid'] ?? '');
$idType    = $_POST['id_type'] ?? 'NID';
$name      = trim($_POST['name'] ?? '');
$phone     = trim($_POST['phone'] ?? '');
$size      = (int)($_POST['size'] ?? 1);
$force     = (int)($_POST['force'] ?? 0);
$upazilaId = (int)($_POST['upazila_id'] ?? 0);

if (!in_array($idType, ['NID', 'Birth Certificate'], true)) {
    $idType = 'NID';
}

$members = json_decode($_POST['members'] ?? '[]', true);
if (!is_array($members)) $members = [];

if ($nid === '' || $name === '' || $upazilaId === 0) {
    echo json_encode(['status' => 'error', 'message' => t('api_family_required')]);
    exit;
}

$areaStmt = $pdo->prepare("SELECT area_id FROM area WHERE upazila_id = ?");
$areaStmt->execute([$upazilaId]);
$areaId = $areaStmt->fetchColumn() ?: null;

// head counts as member #1
if (count($members) !== max(0, $size - 1)) {
    echo json_encode(['status' => 'error', 'message' => t('api_member_count_mismatch')]);
    exit;
}
foreach ($members as $m) {
    $mName = trim($m['name'] ?? '');
    $mIdNum = trim($m['id_number'] ?? '');
    $mIdType = $m['id_type'] ?? '';
    if ($mName === '' || $mIdNum === '' || !in_array($mIdType, ['NID', 'Birth Certificate'], true)) {
        echo json_encode(['status' => 'error', 'message' => t('api_member_fields_required')]);
        exit;
    }
}

$hash = hashNID($nid);

// checks whole registry (heads + members), not just family table
$identityStmt = $pdo->prepare("SELECT family_id, person_name FROM v_person_registry WHERE id_hash = ? LIMIT 1");
$identityStmt->execute([$hash]);
if ($existing = $identityStmt->fetch()) {
    echo json_encode(['status' => 'error', 'message' => sprintf(t('api_identity_conflict'), $existing['person_name'], $existing['family_id'])]);
    exit;
}

$seenHashes = [];
foreach ($members as $m) {
    $mHash = hashNID(trim($m['id_number']));
    $identityStmt->execute([$mHash]);
    if ($existing = $identityStmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => sprintf(t('api_identity_conflict'), $existing['person_name'], $existing['family_id'])]);
        exit;
    }
    if (in_array($mHash, $seenHashes, true)) {
        echo json_encode(['status' => 'error', 'message' => t('api_member_fields_required')]);
        exit;
    }
    $seenHashes[] = $mHash;
}

if (!$force) {
    $prefix = mb_substr($name, 0, 2);
    $stmt = $pdo->prepare("SELECT f.head_name, a.area_name
                            FROM family f LEFT JOIN area a ON f.area_id = a.area_id
                            WHERE f.head_name LIKE CONCAT(?, '%') OR f.head_name LIKE CONCAT('%', ?, '%')
                            LIMIT 50");
    $stmt->execute([$prefix, $name]);
    $matches = [];
    foreach ($stmt->fetchAll() as $c) {
        $dist = mb_levenshtein($name, $c['head_name']);
        if ($dist <= 2 && $dist > 0) {
            $matches[] = ['name' => $c['head_name'], 'area_name' => $c['area_name'], 'distance' => $dist];
        }
    }
    if ($matches) {
        echo json_encode(['status' => 'fuzzy_warning', 'matches' => $matches]);
        exit;
    }
}

$ins = $pdo->prepare("INSERT INTO family (nid_hash, id_type, head_name, phone, family_size, area_id, upazila_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
$memberIns = $pdo->prepare("INSERT INTO family_member (family_id, member_name, id_type, id_hash) VALUES (?, ?, ?, ?)");

try {
    $pdo->beginTransaction();

    $ins->execute([$hash, $idType, $name, $phone, $size, $areaId, $upazilaId]);
    $familyId = (int)$pdo->lastInsertId();

    foreach ($members as $m) {
        $mHash = hashNID(trim($m['id_number']));
        $memberIns->execute([$familyId, trim($m['name']), $m['id_type'], $mHash]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => sprintf(t('api_family_saved'), $name, $size)]);
} catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() === '23000') {
        echo json_encode(['status' => 'error', 'message' => t('api_member_dup')]);
    } elseif (str_contains($e->getMessage(), 'DUPLICATE BLOCKED')) {
        echo json_encode(['status' => 'error', 'message' => t('api_identity_conflict_db')]);
    } else {
        echo json_encode(['status' => 'error', 'message' => sprintf(t('api_save_failed'), $e->getMessage())]);
    }
}
