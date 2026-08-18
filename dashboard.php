<?php
$pageTitle = 'Fairness Dashboard — Relief Distribution System';
require 'config.php';
require 'includes/auth.php';
requireRole(['admin']);

$totalFamilies = $pdo->query("SELECT COUNT(*) c FROM family")->fetch()['c'];
$totalDist     = $pdo->query("SELECT COUNT(*) c FROM distribution")->fetch()['c'];
$totalBlocked  = $pdo->query("SELECT COUNT(*) c FROM duplicate_log")->fetch()['c'];
$totalNgo      = $pdo->query("SELECT COUNT(*) c FROM ngo")->fetch()['c'];

$fairness = $pdo->query("SELECT * FROM v_area_fairness")->fetchAll();

// NGO-wise totals, most active first
$ngoTotals = $pdo->query("
    SELECT n.ngo_name, COUNT(*) AS dist_count, COALESCE(SUM(d.quantity), 0) AS total_qty
    FROM ngo n
    LEFT JOIN distribution d ON d.ngo_id = n.ngo_id
    GROUP BY n.ngo_id, n.ngo_name
    ORDER BY dist_count DESC
")->fetchAll();
$maxNgoCount = max(1, ...array_column($ngoTotals, 'dist_count'));

// Weekly trend, last 8 weeks
$weekly = $pdo->query("
    SELECT YEARWEEK(dist_date, 3) AS yw, DATE(MIN(dist_date)) AS week_start, COUNT(*) AS cnt
    FROM distribution
    WHERE dist_date >= NOW() - INTERVAL 56 DAY
    GROUP BY yw
    ORDER BY yw
")->fetchAll();
$maxWeekCount = max(1, ...array_column($weekly, 'cnt'));

// Centroid coordinates for the 8 relief zones
$areaCoords = [
    1 => [25.0658, 91.3950], // সুনামগঞ্জ সদর
    2 => [25.5423, 89.6969], // কুড়িগ্রাম চর (Chilmari)
    3 => [25.4318, 89.5397], // গাইবান্ধা উত্তর (Sundarganj)
    4 => [25.0333, 92.1667], // সিলেট কোম্পানীগঞ্জ
    5 => [25.0500, 90.6833], // নেত্রকোণা দুর্গাপুর
    6 => [25.1500, 89.8333], // জামালপুর ইসলামপুর
    7 => [24.7667, 89.5833], // বগুড়া সারিয়াকান্দি
    8 => [25.7167, 89.4333], // রংপুর কাউনিয়া
];
$mapAreas = [];
foreach ($fairness as $row) {
    if (!isset($areaCoords[$row['area_id']])) continue;
    $ratio = $row['fairness_ratio'] === null ? 0 : (float)$row['fairness_ratio'];
    $mapAreas[] = [
        'name'       => $row['area_name'],
        'lat'        => $areaCoords[$row['area_id']][0],
        'lng'        => $areaCoords[$row['area_id']][1],
        'population' => (int)$row['population'],
        'received'   => (int)$row['total_received'],
        'ratio'      => $ratio,
    ];
}

$extraHead = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">';

require 'includes/header.php';
?>

<div class="kpis">
  <div class="kpi"><div class="n" data-count="<?= $totalFamilies ?>">0</div><div class="l"><?= t('kpi_families') ?></div></div>
  <div class="kpi"><div class="n" data-count="<?= $totalDist ?>">0</div><div class="l"><?= t('kpi_distributions') ?></div></div>
  <div class="kpi"><div class="n" data-count="<?= $totalBlocked ?>">0</div><div class="l"><?= t('kpi_blocked') ?></div></div>
  <div class="kpi"><div class="n" data-count="<?= $totalNgo ?>">0</div><div class="l"><?= t('kpi_ngo') ?></div></div>
</div>

<div class="card">
  <h2><?= icon('map-pin') ?><?= t('map_h2') ?></h2>
  <p class="sub"><?= t('map_sub') ?></p>
  <div id="reliefMap" class="map-box"></div>
  <div class="map-legend">
    <span><i style="background:var(--alert)"></i> <?= t('legend_low') ?></span>
    <span><i style="background:var(--warn)"></i> <?= t('legend_partial') ?></span>
    <span><i style="background:var(--safe)"></i> <?= t('legend_fair') ?></span>
  </div>
</div>

<div class="card">
  <h2><?= icon('scale') ?><?= t('fairness_h2') ?></h2>
  <p class="sub"><?= t('fairness_sub') ?></p>

  <?php foreach ($fairness as $row):
      $ratio = $row['fairness_ratio'] === null ? 0 : (float)$row['fairness_ratio'];
      $pct = min($ratio / 1.5, 1) * 100;
      $color = $ratio < 0.5 ? 'var(--alert)' : ($ratio < 1 ? 'var(--warn)' : 'var(--safe)');
  ?>
  <div class="gauge-row">
    <div class="gauge-label"><?= htmlspecialchars($row['area_name']) ?>
      <small><?= t('population') ?>: <?= number_format($row['population']) ?> · <?= t('received') ?>: <?= $row['total_received'] ?> · <?= t('expected') ?>: <?= $row['expected_share'] ?></small>
    </div>
    <div class="gauge-track">
      <div class="gauge-mark" style="left:<?= (1/1.5)*100 ?>%;"></div>
      <div class="gauge-fill" data-width="<?= $pct ?>" style="background:<?= $color ?>;"></div>
    </div>
    <div class="gauge-val" style="color:<?= $color ?>;"><?= number_format($ratio, 2) ?>×</div>
  </div>
  <?php endforeach; ?>

  <div class="note"><?= t('fairness_note') ?></div>
</div>

<div class="card">
  <h2><?= icon('bar-chart') ?><?= t('ngo_perf_h2') ?></h2>
  <p class="sub"><?= t('ngo_perf_sub') ?></p>
  <?php if (!$ngoTotals): ?>
    <div class="empty"><?= t('no_records_yet') ?></div>
  <?php else: foreach ($ngoTotals as $row):
      $pct = $maxNgoCount > 0 ? ($row['dist_count'] / $maxNgoCount) * 100 : 0;
  ?>
  <div class="bar-row">
    <div class="bar-label"><?= htmlspecialchars($row['ngo_name']) ?></div>
    <div class="bar-track"><div class="bar-fill" data-width="<?= $pct ?>"></div></div>
    <div class="bar-val"><?= (int)$row['dist_count'] ?></div>
  </div>
  <?php endforeach; endif; ?>
</div>

<div class="card">
  <h2><?= icon('trending-up') ?><?= t('weekly_trend_h2') ?></h2>
  <p class="sub"><?= t('weekly_trend_sub') ?></p>
  <?php if (!$weekly): ?>
    <div class="empty"><?= t('no_records_yet') ?></div>
  <?php else: foreach ($weekly as $row):
      $pct = $maxWeekCount > 0 ? ($row['cnt'] / $maxWeekCount) * 100 : 0;
  ?>
  <div class="bar-row">
    <div class="bar-label"><?= (new DateTime($row['week_start']))->format('d M') ?></div>
    <div class="bar-track"><div class="bar-fill trend" data-width="<?= $pct ?>"></div></div>
    <div class="bar-val"><?= (int)$row['cnt'] ?></div>
  </div>
  <?php endforeach; endif; ?>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
const RELIEF_AREAS = <?= json_encode($mapAreas) ?>;
const MAP_I18N = {
  population: <?= json_encode(t('population')) ?>,
  received: <?= json_encode(t('received')) ?>,
  fairness: <?= json_encode(t('fairness_val_label')) ?>,
};
</script>

<?php require 'includes/footer.php'; ?>
