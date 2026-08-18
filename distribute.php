<?php
$pageTitle = 'ত্রাণ বিতরণ — Relief Distribution System';
require 'config.php';
require 'includes/auth.php';
requireRole(['admin', 'ngo_operator']);
$user = currentUser();

$items  = $pdo->query("SELECT * FROM relief_item ORDER BY category, item_name")->fetchAll();
$ngos   = $pdo->query("SELECT * FROM ngo ORDER BY ngo_name")->fetchAll();
$points = $pdo->query("SELECT * FROM distribution_point ORDER BY point_name")->fetchAll();

// Live stock levels, keyed by ngo_id then item_id
if ($user['role'] === 'admin') {
    $stockRows = $pdo->query("SELECT ngo_id, item_id, quantity_available FROM ngo_stock")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT ngo_id, item_id, quantity_available FROM ngo_stock WHERE ngo_id = ?");
    $stmt->execute([$user['ngo_id']]);
    $stockRows = $stmt->fetchAll();
}
$stockMap = [];
foreach ($stockRows as $r) {
    $stockMap[(int)$r['ngo_id']][(int)$r['item_id']] = (float)$r['quantity_available'];
}

// For the "+ Add New Distribution Point" panel
$dangerZones = $pdo->query("SELECT area_id, area_name, upazila_id FROM area WHERE upazila_id IS NOT NULL ORDER BY area_name")->fetchAll();
$divisions   = $pdo->query("SELECT division_id, division_name FROM division ORDER BY division_name")->fetchAll();
$districts   = $pdo->query("SELECT district_id, district_name, division_id FROM district ORDER BY district_name")->fetchAll();
$upazilas    = $pdo->query("SELECT upazila_id, upazila_name, district_id FROM upazila ORDER BY upazila_name")->fetchAll();

// Admin sees all, ngo_operator sees only their own NGO's
if ($user['role'] === 'admin') {
    $hist = $pdo->query("
        SELECT f.head_name, n.ngo_name, ri.item_name, ri.category, d.quantity, d.dist_date
        FROM distribution d
        JOIN family f ON d.family_id = f.family_id
        JOIN ngo n ON d.ngo_id = n.ngo_id
        JOIN relief_item ri ON d.item_id = ri.item_id
        ORDER BY d.dist_date DESC LIMIT 15
    ")->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT f.head_name, n.ngo_name, ri.item_name, ri.category, d.quantity, d.dist_date
        FROM distribution d
        JOIN family f ON d.family_id = f.family_id
        JOIN ngo n ON d.ngo_id = n.ngo_id
        JOIN relief_item ri ON d.item_id = ri.item_id
        WHERE d.ngo_id = ?
        ORDER BY d.dist_date DESC LIMIT 15
    ");
    $stmt->execute([$user['ngo_id']]);
    $hist = $stmt->fetchAll();
}

require 'includes/header.php';
?>

<div class="card">
  <h2><?= t('distribute_h2') ?></h2>
  <p class="sub"><?= t('distribute_sub') ?></p>

  <div class="grid2">
    <div class="field">
      <label><?= t('nid_number') ?></label>
      <input type="text" id="distNid" placeholder="e.g. 1985011234501" autocomplete="off">
    </div>
    <div class="field">
      <label><?= t('relief_item') ?></label>
      <select id="distItem">
        <?php foreach ($items as $i): $label = $i['item_name'] . ' (' . $i['category'] . ')'; ?>
          <option value="<?= $i['item_id'] ?>" data-category="<?= $i['category'] ?>" data-unit="<?= htmlspecialchars($i['unit']) ?>" data-label="<?= htmlspecialchars($label) ?>">
            <?= htmlspecialchars($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div id="familyLookupBox"></div>

  <div class="grid2" style="margin-top:12px;">
    <div class="field">
      <label><?= t('ngo') ?></label>
      <?php if ($user['role'] === 'admin'): ?>
        <select id="distNgo">
          <?php foreach ($ngos as $n): ?>
            <option value="<?= $n['ngo_id'] ?>"><?= htmlspecialchars($n['ngo_name']) ?></option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        <input type="text" id="distNgoFixed" data-ngo-id="<?= (int)$user['ngo_id'] ?>" value="<?= htmlspecialchars($user['ngo_name']) ?>" disabled>
        <div class="note" style="margin-top:6px;"><?= t('ngo_locked_note') ?></div>
      <?php endif; ?>
    </div>
    <div class="field">
      <label><?= t('distribution_point') ?></label>
      <select id="distPoint">
        <?php foreach ($points as $p): ?>
          <option value="<?= $p['point_id'] ?>"><?= htmlspecialchars($p['point_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <a href="#" id="togglePointForm" style="font-size:12px; display:inline-block; margin-top:6px;"><?= t('add_point_toggle') ?></a>
    </div>
  </div>

  <div id="newPointForm" class="card" style="display:none; background:var(--primary-tint); margin-top:4px;">
    <div class="field">
      <label><?= t('point_name_label') ?></label>
      <input type="text" id="newPointName" placeholder="<?= t('point_name_placeholder') ?>">
    </div>
    <div class="field">
      <label><?= t('quick_pick_label') ?></label>
      <select id="quickPickZone">
        <option value=""><?= t('select_placeholder') ?></option>
        <?php foreach ($dangerZones as $z): ?>
          <option value="<?= $z['upazila_id'] ?>"><?= htmlspecialchars($z['area_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label><?= t('or_full_location') ?></label>
      <div class="grid3">
        <select id="pointDivision">
          <option value=""><?= t('select_placeholder') ?> (<?= t('division_label') ?>)</option>
          <?php foreach ($divisions as $d): ?>
            <option value="<?= $d['division_id'] ?>"><?= htmlspecialchars($d['division_name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select id="pointDistrict" disabled>
          <option value=""><?= t('select_placeholder') ?> (<?= t('district_field_label') ?>)</option>
        </select>
        <select id="pointUpazila" disabled>
          <option value=""><?= t('select_placeholder') ?> (<?= t('upazila_label') ?>)</option>
        </select>
      </div>
    </div>
    <div id="newPointAlert" class="alert"></div>
    <button class="btn" type="button" id="savePointBtn" onclick="submitNewPoint()"><?= t('save_point_btn') ?></button>
  </div>

  <div id="stockIndicatorBox"></div>

  <div class="field" style="max-width:180px;">
    <label><?= t('quantity') ?></label>
    <input type="number" id="distQty" value="5" min="0.5" step="0.5">
  </div>

  <div id="distAlert" class="alert"></div>
  <button class="btn" id="distSubmitBtn" onclick="submitDistribution()" disabled><?= t('confirm_distribution_btn') ?></button>
</div>

<div class="card">
  <h2><?= $user['role']==='admin' ? t('recent_dist_all') : t('recent_dist_mine') ?></h2>
  <?php if (!$hist): ?>
    <div class="empty"><?= t('no_records_yet') ?></div>
  <?php else: ?>
    <div class="table-scroll">
    <table>
      <thead><tr><th><?= t('th_family') ?></th><th><?= t('ngo') ?></th><th><?= t('th_item') ?></th><th><?= t('th_quantity') ?></th><th><?= t('th_date') ?></th></tr></thead>
      <tbody>
        <?php foreach ($hist as $h):
          $catClass = strtolower($h['category']); ?>
          <tr>
            <td><?= htmlspecialchars($h['head_name']) ?></td>
            <td><?= htmlspecialchars($h['ngo_name']) ?></td>
            <td><?= htmlspecialchars($h['item_name']) ?> <span class="tag <?= $catClass ?>"><?= $h['category'] ?></span></td>
            <td class="num"><?= $h['quantity'] ?></td>
            <td class="num"><?= (new DateTime($h['dist_date']))->format('d M') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>

<script>
const BD_DISTRICTS = <?= json_encode(array_map(fn($d) => ['id' => (int)$d['district_id'], 'name' => $d['district_name'], 'division_id' => (int)$d['division_id']], $districts)) ?>;
const BD_UPAZILAS = <?= json_encode(array_map(fn($u) => ['id' => (int)$u['upazila_id'], 'name' => $u['upazila_name'], 'district_id' => (int)$u['district_id']], $upazilas)) ?>;
const STOCK_MAP = <?= json_encode($stockMap, JSON_FORCE_OBJECT) ?>;
const STOCK_MSG_NONE = <?= json_encode(t('stock_indicator_none')) ?>;
const STOCK_MSG_AVAILABLE = <?= json_encode(t('stock_indicator_available')) ?>;
const STOCK_MSG_LOW = <?= json_encode(t('stock_indicator_low')) ?>;
const STOCK_MIN_THRESHOLD = 10;
const NO_STOCK_SUFFIX = <?= json_encode(t('no_stock_suffix')) ?>;
</script>

<?php require 'includes/footer.php'; ?>
