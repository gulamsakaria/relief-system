<?php
$pageTitle = 'Duplicate Log — Relief Distribution System';
require 'config.php';
require 'includes/auth.php';
requireRole(['admin']);

$logs = $pdo->query("
    SELECT dl.*, n.ngo_name
    FROM duplicate_log dl
    LEFT JOIN ngo n ON dl.attempted_ngo_id = n.ngo_id
    ORDER BY dl.attempted_at DESC
")->fetchAll();

// Every NID/Birth Certificate lookup made on the distribution page
$queries = $pdo->query("
    SELECT ql.query_time, n.ngo_name, f.head_name, fm.member_name
    FROM query_log ql
    LEFT JOIN ngo n ON ql.ngo_id = n.ngo_id
    LEFT JOIN family f ON ql.matched_family_id = f.family_id
    LEFT JOIN family_member fm ON ql.matched_member_id = fm.member_id
    ORDER BY ql.query_time DESC
    LIMIT 50
")->fetchAll();

require 'includes/header.php';
?>

<div class="card">
  <h2 class="icon-alert"><?= icon('ban') ?>Duplicate Attempt Log</h2>
  <p class="sub"><?= t('dup_log_sub') ?></p>

  <?php if (!$logs): ?>
    <div class="empty"><?= t('dup_log_empty') ?></div>
  <?php else: ?>
    <div class="table-scroll">
    <table>
      <thead><tr><th>NID Hash</th><th><?= t('th_attempted_ngo') ?></th><th><?= t('th_reason') ?></th><th><?= t('th_date') ?></th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td><span class="hash-chip"><?= substr($l['nid_hash'],0,14) ?>…</span></td>
          <td><?= htmlspecialchars($l['ngo_name'] ?? '—') ?></td>
          <td><?= htmlspecialchars($l['reason']) ?></td>
          <td class="num"><?= (new DateTime($l['attempted_at']))->format('d M Y, h:i A') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2><?= icon('search') ?><?= t('audit_h2') ?></h2>
  <p class="sub"><?= t('audit_sub') ?></p>

  <?php if (!$queries): ?>
    <div class="empty"><?= t('audit_empty') ?></div>
  <?php else: ?>
    <div class="table-scroll">
    <table>
      <thead><tr><th><?= t('th_attempted_ngo') ?></th><th><?= t('audit_resolved_to') ?></th><th><?= t('th_date') ?></th></tr></thead>
      <tbody>
      <?php foreach ($queries as $q): ?>
        <tr>
          <td><?= htmlspecialchars($q['ngo_name'] ?? '—') ?></td>
          <td>
            <?php if (!$q['head_name']): ?>
              <span style="color:var(--ink-soft);">—</span>
            <?php elseif ($q['member_name']): ?>
              <?= htmlspecialchars($q['head_name']) ?> <span class="tag shelter"><?= t('audit_via_member') ?>: <?= htmlspecialchars($q['member_name']) ?></span>
            <?php else: ?>
              <?= htmlspecialchars($q['head_name']) ?> <span class="tag food"><?= t('audit_via_head') ?></span>
            <?php endif; ?>
          </td>
          <td class="num"><?= (new DateTime($q['query_time']))->format('d M Y, h:i A') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
