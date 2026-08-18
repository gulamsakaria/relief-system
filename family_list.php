<?php
$pageTitle = 'সব পরিবার — Relief Distribution System';
require 'config.php';
require 'includes/auth.php';
requireRole(['admin']);

$families = $pdo->query("
    SELECT f.family_id, f.head_name, f.phone, f.family_size, a.area_name, f.nid_hash, f.id_type, f.registered_at,
           u.upazila_name, d.district_name
    FROM family f
    LEFT JOIN area a ON f.area_id = a.area_id
    LEFT JOIN upazila u ON f.upazila_id = u.upazila_id
    LEFT JOIN district d ON u.district_id = d.district_id
    ORDER BY f.registered_at DESC
")->fetchAll();

require 'includes/header.php';
?>

<div class="card">
  <h2><?= icon('users') ?><?= t('family_list_h2') ?></h2>
  <p class="sub"><?= str_replace('{count}', count($families), t('family_list_sub')) ?></p>

  <?php if (!$families): ?>
    <div class="empty"><?= t('no_families_yet') ?></div>
  <?php else: ?>
    <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th><?= t('th_id') ?></th>
          <th><?= t('th_name') ?></th>
          <th><?= t('th_phone') ?></th>
          <th><?= t('area_label') ?></th>
          <th><?= t('home_address_label') ?></th>
          <th><?= t('th_members') ?></th>
          <th><?= t('nid_hash_preview') ?></th>
          <th><?= t('th_registered_at') ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($families as $f): ?>
        <tr>
          <td><?= $f['family_id'] ?></td>
          <td><?= htmlspecialchars($f['head_name']) ?></td>
          <td><?= htmlspecialchars($f['phone'] ?? '—') ?></td>
          <td><?= $f['area_name'] ? htmlspecialchars($f['area_name']) : '—' ?></td>
          <td><?= $f['upazila_name'] ? htmlspecialchars($f['upazila_name'] . ', ' . $f['district_name']) : '—' ?></td>
          <td><?= $f['family_size'] ?></td>
          <td><span class="hash-chip"><?= substr($f['nid_hash'], 0, 14) ?>…</span> <span class="tag"><?= $f['id_type'] === 'Birth Certificate' ? t('birth_certificate') : 'NID' ?></span></td>
          <td class="num"><?= (new DateTime($f['registered_at']))->format('d M Y, h:i A') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
