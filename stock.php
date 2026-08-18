<?php
$pageTitle = 'NGO Stock — Relief Distribution System';
require 'config.php';
require 'includes/auth.php';
requireRole(['admin', 'ngo_operator']);
$user = currentUser();

// Admin sees every NGO's stock; ngo_operator only sees their own
if ($user['role'] === 'admin') {
    $stock = $pdo->query("
        SELECT s.stock_id, n.ngo_name, ri.item_name, ri.category, s.ngo_id, s.item_id,
               s.quantity_available, ri.unit, s.updated_at
        FROM ngo_stock s
        JOIN ngo n ON s.ngo_id = n.ngo_id
        JOIN relief_item ri ON s.item_id = ri.item_id
        ORDER BY n.ngo_name, ri.category, ri.item_name
    ")->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT s.stock_id, n.ngo_name, ri.item_name, ri.category, s.ngo_id, s.item_id,
               s.quantity_available, ri.unit, s.updated_at
        FROM ngo_stock s
        JOIN ngo n ON s.ngo_id = n.ngo_id
        JOIN relief_item ri ON s.item_id = ri.item_id
        WHERE s.ngo_id = ?
        ORDER BY ri.category, ri.item_name
    ");
    $stmt->execute([$user['ngo_id']]);
    $stock = $stmt->fetchAll();
}

require 'includes/header.php';
?>

<div class="card">
  <h2><?= icon('box') ?><?= t('stock_h2') ?></h2>
  <p class="sub"><?= t('stock_sub') ?></p>

  <?php if (!$stock): ?>
    <div class="empty"><?= t('stock_empty') ?></div>
  <?php else: ?>
    <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <?php if ($user['role'] === 'admin'): ?><th><?= t('ngo') ?></th><?php endif; ?>
          <th><?= t('relief_item') ?></th>
          <th><?= t('stock_available') ?></th>
          <th><?= t('stock_add') ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($stock as $s):
          $low = (float)$s['quantity_available'] < 20;
      ?>
        <tr>
          <?php if ($user['role'] === 'admin'): ?><td><?= htmlspecialchars($s['ngo_name']) ?></td><?php endif; ?>
          <td><?= htmlspecialchars($s['item_name']) ?> <span class="tag <?= strtolower($s['category']) ?>"><?= $s['category'] ?></span></td>
          <td class="num" style="<?= $low ? 'color:var(--alert);font-weight:700;' : '' ?>" id="qty-<?= $s['ngo_id'] ?>-<?= $s['item_id'] ?>">
            <?= number_format($s['quantity_available'], 2) ?> <?= htmlspecialchars($s['unit']) ?>
          </td>
          <td>
            <div style="display:flex; gap:6px;">
              <input type="number" min="0.5" step="0.5" value="50" style="width:90px;" id="add-<?= $s['ngo_id'] ?>-<?= $s['item_id'] ?>">
              <button class="btn btn-ghost" type="button" id="restockBtn-<?= $s['ngo_id'] ?>-<?= $s['item_id'] ?>" onclick="restock(<?= $s['ngo_id'] ?>, <?= $s['item_id'] ?>)"><?= t('stock_add_btn') ?></button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>

  <div id="stockAlert" class="alert"></div>
  <div class="note"><?= t('stock_note') ?></div>
</div>

<script>
async function restock(ngoId, itemId) {
  const input = document.getElementById(`add-${ngoId}-${itemId}`);
  const qty = parseFloat(input.value);
  const alertBox = document.getElementById('stockAlert');
  if (!qty || qty <= 0) return;

  const btn = document.getElementById(`restockBtn-${ngoId}-${itemId}`);
  toggleBtnLoading(btn, true);
  const body = new URLSearchParams({ ngo_id: ngoId, item_id: itemId, add_quantity: qty, csrf_token: CSRF_TOKEN });

  let data;
  try {
    const res = await fetch('api/save_stock.php', { method: 'POST', body });
    data = await res.json();
  } catch (err) {
    toggleBtnLoading(btn, false);
    alertBox.className = 'alert red show';
    alertBox.textContent = jt('network_error');
    return;
  }
  toggleBtnLoading(btn, false);

  if (data.status === 'success') {
    document.getElementById(`qty-${ngoId}-${itemId}`).textContent = data.new_quantity + ' ' + data.unit;
    alertBox.className = 'alert green show';
    alertBox.textContent = '✔ ' + data.message;
  } else {
    alertBox.className = 'alert red show';
    alertBox.textContent = '✖ ' + data.message;
  }
  setTimeout(() => { alertBox.className = 'alert'; }, 2500);
}
</script>

<?php require 'includes/footer.php'; ?>
