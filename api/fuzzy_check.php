<?php
header('Content-Type: application/json; charset=utf-8');
require '../config.php';
require '../includes/auth.php';
requireLogin();

$name = trim($_POST['name'] ?? '');
if (mb_strlen($name) < 2) {
    echo json_encode([]);
    exit;
}

$prefix = mb_substr($name, 0, 2);
$stmt = $pdo->prepare("SELECT f.head_name, a.area_name
                        FROM family f JOIN area a ON f.area_id = a.area_id
                        WHERE f.head_name LIKE CONCAT(?, '%')
                           OR f.head_name LIKE CONCAT('%', ?, '%')
                        LIMIT 50");
$stmt->execute([$prefix, $name]);
$candidates = $stmt->fetchAll();

$matches = [];
foreach ($candidates as $c) {
    if (mb_strtolower(trim($c['head_name'])) === mb_strtolower($name)) continue; // exact match handled separately
    $dist = mb_levenshtein($name, $c['head_name']);
    if ($dist <= 2) {
        $matches[] = ['name' => $c['head_name'], 'area_name' => $c['area_name'], 'distance' => $dist];
    }
}
usort($matches, fn($a,$b) => $a['distance'] <=> $b['distance']);
echo json_encode($matches);
