<?php
header('Content-Type: application/json; charset=utf-8');
require '../config.php';
require '../includes/auth.php';
requireLogin();

$nid    = trim($_POST['nid'] ?? '');
$itemId = (int)($_POST['item_id'] ?? 0);

if ($nid === '' || $itemId === 0) {
    echo json_encode(['status' => 'invalid']);
    exit;
}

$hash = hashNID($nid);
$user = currentUser();

$famStmt = $pdo->prepare("SELECT f.family_id, f.head_name, f.family_size, a.area_name,
                                  pr.person_name AS matched_person_name, pr.person_role AS matched_person_role, pr.member_id
                           FROM v_person_registry pr
                           JOIN family f ON f.family_id = pr.family_id
                           LEFT JOIN area a ON f.area_id = a.area_id
                           WHERE pr.id_hash = ? LIMIT 1");
$famStmt->execute([$hash]);
$family = $famStmt->fetch();

// audit trail
$logStmt = $pdo->prepare("INSERT INTO query_log (ngo_id, queried_nid_hash, matched_family_id, matched_member_id) VALUES (?, ?, ?, ?)");
$logStmt->execute([$user['ngo_id'], $hash, $family['family_id'] ?? null, $family['member_id'] ?? null]);

if (!$family) {
    echo json_encode(['status' => 'not_found']);
    exit;
}

$itemStmt = $pdo->prepare("SELECT category FROM relief_item WHERE item_id = ?");
$itemStmt->execute([$itemId]);
$item = $itemStmt->fetch();

$dupStmt = $pdo->prepare("
    SELECT d.dist_date, n.ngo_name
    FROM distribution d
    JOIN relief_item ri ON d.item_id = ri.item_id
    JOIN ngo n ON d.ngo_id = n.ngo_id
    WHERE d.family_id = ?
      AND ri.category = ?
      AND d.dist_date >= NOW() - INTERVAL 7 DAY
    ORDER BY d.dist_date DESC LIMIT 1
");
$dupStmt->execute([$family['family_id'], $item['category']]);
$dup = $dupStmt->fetch();

$matchedAsMember = $family['matched_person_role'] === 'Member'
    ? ['matched_name' => $family['matched_person_name']]
    : [];

if ($dup) {
    echo json_encode([
        'status'     => 'duplicate',
        'head_name'  => $family['head_name'],
        'area_name'  => $family['area_name'],
        'family_size'=> $family['family_size'],
        'last_ngo'   => $dup['ngo_name'],
        'last_date'  => (new DateTime($dup['dist_date']))->format('d M Y'),
    ] + $matchedAsMember);
} else {
    echo json_encode([
        'status'      => 'ok',
        'head_name'   => $family['head_name'],
        'area_name'   => $family['area_name'],
        'family_size' => $family['family_size'],
    ] + $matchedAsMember);
}
