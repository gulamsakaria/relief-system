<?php
// Shared left-hand branding panel for the auth pages. Hidden on narrow screens.
$brandFamilies = $pdo->query("SELECT COUNT(*) c FROM family")->fetch()['c'];
$brandDist     = $pdo->query("SELECT COUNT(*) c FROM distribution")->fetch()['c'];
$brandZones    = $pdo->query("SELECT COUNT(*) c FROM area")->fetch()['c'];
?>
<div class="auth-brand">
  <div class="auth-brand-shape-glow"></div>
  <div class="auth-brand-shape"></div>
  <div class="auth-brand-grid"></div>

  <div class="auth-brand-content">
    <div class="brand-top-row">
      <img src="img/logo-icon.png" alt="ReliefX" class="brand-mini-logo">
      <span class="brand-name">ReliefX</span>
    </div>

    <h2 class="brand-headline">
      <?= t('brand_headline_1') ?><br>
      <span class="accent-text"><?= t('brand_headline_2') ?></span>
    </h2>
    <p class="brand-tagline-text"><?= t('brand_tagline') ?></p>

    <div class="device-mockup-wrap">
      <div class="float-badge fb-1"><?= icon('lock',13) ?><span>SHA-256</span></div>
      <div class="float-badge fb-2"><?= icon('zap',13) ?><span><?= $lang==='bn' ? 'রিয়েল-টাইম চেক' : 'Real-time Check' ?></span></div>
      <div class="float-badge fb-3"><?= icon('shield-check',13) ?><span><?= $lang==='bn' ? 'নিরাপদ' : 'Secured' ?></span></div>

      <div class="device-mockup">
        <div class="device-mockup-bar">
          <span class="dot"></span><span class="dot"></span><span class="dot"></span>
          <span class="device-mockup-url">app.reliefx.bd/dashboard</span>
        </div>
        <img src="img/dashboard-preview.png" alt="ReliefX dashboard — Fairness Map preview">
      </div>
    </div>

    <div class="brand-stats">
      <div class="brand-stat"><b data-count="<?= $brandFamilies ?>" data-suffix="+">0</b><span><?= t('stat_families') ?></span></div>
      <div class="brand-stat"><b data-count="<?= $brandDist ?>" data-suffix="+">0</b><span><?= t('stat_distributions') ?></span></div>
      <div class="brand-stat"><b data-count="<?= $brandZones ?>">0</b><span><?= t('stat_zones') ?></span></div>
    </div>
  </div>
</div>
