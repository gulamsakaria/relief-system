<?php
$pageTitle = 'পরিবার নিবন্ধন — Relief Distribution System';
require 'config.php';
require 'includes/auth.php';
requireRole(['admin', 'ngo_operator']);
$user = currentUser();

$divisions = $pdo->query("SELECT division_id, division_name FROM division ORDER BY division_name")->fetchAll();
$districts = $pdo->query("SELECT district_id, district_name, division_id FROM district ORDER BY district_name")->fetchAll();
$upazilas  = $pdo->query("SELECT upazila_id, upazila_name, district_id FROM upazila ORDER BY upazila_name")->fetchAll();

// Maps upazila to its relief zone
$areasByUpazila = $pdo->query("SELECT area_id, area_name, upazila_id FROM area WHERE upazila_id IS NOT NULL")->fetchAll();

// Only admin can browse the family list
$families = [];
if ($user['role'] === 'admin') {
    $families = $pdo->query("
        SELECT f.head_name, f.family_size, f.nid_hash, a.area_name
        FROM family f LEFT JOIN area a ON f.area_id = a.area_id
        ORDER BY f.registered_at DESC LIMIT 30
    ")->fetchAll();
}

require 'includes/header.php';
?>

<div class="card">
  <h2><?= t('reg_family_h2') ?></h2>
  <p class="sub"><?= t('reg_family_sub') ?></p>

  <div class="grid3">
    <div class="field">
      <label><?= t('nid_number') ?></label>
      <div style="display:flex; gap:6px;">
        <select id="regIdType" style="max-width:118px; flex-shrink:0;">
          <option value="NID">NID</option>
          <option value="Birth Certificate"><?= t('birth_certificate') ?></option>
        </select>
        <input type="text" id="regNid" placeholder="10/13/17 digits" style="flex:1; min-width:0;">
      </div>
      <div class="note" style="margin-top:6px;"><?= t('child_id_note') ?></div>
    </div>
    <div class="field"><label><?= t('head_name_label') ?></label><input type="text" id="regName" placeholder="e.g. Rahim Uddin"></div>
    <div class="field"><label><?= t('phone_label') ?></label><input type="text" id="regPhone" placeholder="01XXXXXXXXX"></div>
  </div>
  <div class="grid3">
    <div class="field"><label><?= t('family_size_label') ?></label><input type="number" id="regSize" value="4" min="1" max="20"></div>
  </div>

  <div class="field" style="margin-top:8px;">
    <label><?= t('home_address_label') ?></label>
    <div class="grid3">
      <select id="regDivision">
        <option value=""><?= t('select_placeholder') ?> (<?= t('division_label') ?>)</option>
        <?php foreach ($divisions as $d): ?>
          <option value="<?= $d['division_id'] ?>"><?= htmlspecialchars($d['division_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="regDistrict" disabled>
        <option value=""><?= t('select_placeholder') ?> (<?= t('district_field_label') ?>)</option>
      </select>
      <select id="regUpazila" disabled>
        <option value=""><?= t('select_placeholder') ?> (<?= t('upazila_label') ?>)</option>
      </select>
    </div>
    <div id="regZoneStatus" class="note" style="display:none; margin-top:8px;"></div>
  </div>

  <div class="field" style="margin-top:8px;">
    <label><?= t('other_members_label') ?></label>
    <div id="memberRows"></div>
  </div>

  <div id="regAlert" class="alert"></div>
  <button class="btn" id="regSubmitBtn" onclick="submitRegistration()"><?= t('register_btn') ?></button>
</div>

<div class="card">
  <h2><?= t('registered_families_h2') ?></h2>
  <p class="sub">
    <?= $user['role']==='admin' ? t('admin_view_note') : t('operator_view_note') ?>
  </p>
  <?php if ($user['role'] === 'admin'): ?>
    <?php if (!$families): ?>
      <div class="empty"><?= t('no_families_yet') ?></div>
    <?php else: ?>
      <div class="table-scroll">
      <table>
        <thead><tr><th><?= t('th_name') ?></th><th><?= t('area_label') ?></th><th><?= t('th_members') ?></th><th><?= t('nid_hash_preview') ?></th></tr></thead>
        <tbody>
        <?php foreach ($families as $f): ?>
          <tr>
            <td><?= htmlspecialchars($f['head_name']) ?></td>
            <td><?= $f['area_name'] ? htmlspecialchars($f['area_name']) : '—' ?></td>
            <td><?= $f['family_size'] ?></td>
            <td><span class="hash-chip"><?= substr($f['nid_hash'],0,14) ?>…</span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="modal-bg" id="fuzzyModal">
  <div class="modal">
    <h3><?= t('fuzzy_modal_title') ?></h3>
    <div id="fuzzyModalBody" style="font-size:13.5px;"></div>
    <div class="actions">
      <button class="btn btn-ghost" onclick="closeFuzzyModal()"><?= t('cancel_btn') ?></button>
      <button class="btn" onclick="confirmRegistration()"><?= t('register_anyway_btn') ?></button>
    </div>
  </div>
</div>

<script>
const BD_DISTRICTS = <?= json_encode(array_map(fn($d) => ['id' => (int)$d['district_id'], 'name' => $d['district_name'], 'division_id' => (int)$d['division_id']], $districts)) ?>;
const BD_UPAZILAS = <?= json_encode(array_map(fn($u) => ['id' => (int)$u['upazila_id'], 'name' => $u['upazila_name'], 'district_id' => (int)$u['district_id']], $upazilas)) ?>;
// upazila_id -> { area_id, area_name }
const AREA_BY_UPAZILA = <?php
    $map = [];
    foreach ($areasByUpazila as $a) {
        $map[(string)$a['upazila_id']] = ['area_id' => (int)$a['area_id'], 'area_name' => $a['area_name']];
    }
    echo json_encode(empty($map) ? new stdClass() : $map);
?>;
</script>

<?php require 'includes/footer.php'; ?>
