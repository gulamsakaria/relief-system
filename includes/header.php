<?php
// Expects $pageTitle to be set by the including page.
// Expects config.php + auth.php to already be included (session + $pdo ready).
$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
$lang = currentLang();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>(function(){try{if(localStorage.getItem('reliefx_theme')==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}})();</script>
<title><?= htmlspecialchars($pageTitle ?? t('brand_title')) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
<link rel="icon" type="image/png" href="img/logo-icon.png">
<?= $extraHead ?? '' ?>
</head>
<body>
<script>
const CUR_LANG = <?= json_encode($lang) ?>;
const CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;
</script>

<div class="topbar">
  <div class="brand">
    <div class="brand-mark"><img src="img/logo-icon.png" alt="ReliefX logo"></div>
    <div class="brand-text">
      <h1><?= t('brand_title') ?></h1>
      <span><?= t('brand_sub') ?></span>
    </div>
  </div>
  <div class="top-controls">
    <button type="button" class="theme-toggle-btn" aria-label="Toggle light/dark mode">
      <span class="icon-sun"><?= icon('sun',15) ?></span>
      <span class="icon-moon"><?= icon('moon',15) ?></span>
    </button>
    <div class="lang-toggle">
      <a href="switch_lang.php?lang=bn" class="<?= $lang==='bn'?'active':'' ?>">বাংলা</a>
      <a href="switch_lang.php?lang=en" class="<?= $lang==='en'?'active':'' ?>">EN</a>
    </div>
    <?php if ($user): ?>
    <span class="role-badge">
      <?= $user['role']==='admin' ? 'Admin' : htmlspecialchars($user['ngo_name'] ?? 'NGO Operator') ?>
      — <?= htmlspecialchars($user['username']) ?>
    </span>
    <a href="logout.php"><?= t('logout') ?></a>
    <?php endif; ?>
  </div>
</div>

<?php if ($user): ?>
<div class="tabs">
  <a class="tab-btn <?= $currentPage==='distribute.php'?'active':'' ?>" href="distribute.php"><?= icon('package',16) ?><span><?= t('tab_distribute') ?></span></a>
  <a class="tab-btn <?= $currentPage==='register_family.php'?'active':'' ?>" href="register_family.php"><?= icon('users',16) ?><span><?= t('tab_register_family') ?></span></a>
  <a class="tab-btn <?= $currentPage==='stock.php'?'active':'' ?>" href="stock.php"><?= icon('box',16) ?><span><?= t('tab_stock') ?></span></a>
  <a class="tab-btn <?= $currentPage==='profile.php'?'active':'' ?>" href="profile.php"><?= icon('user',16) ?><span><?= t('tab_profile') ?></span></a>
  <?php if ($user['role']==='admin'): ?>
  <a class="tab-btn <?= $currentPage==='dashboard.php'?'active':'' ?>" href="dashboard.php"><?= icon('scale',16) ?><span><?= t('tab_dashboard') ?></span></a>
  <a class="tab-btn <?= $currentPage==='family_list.php'?'active':'' ?>" href="family_list.php"><?= icon('users',16) ?><span><?= t('tab_family_list') ?></span></a>
  <a class="tab-btn <?= $currentPage==='duplicate_log.php'?'active':'' ?>" href="duplicate_log.php"><?= icon('ban',16) ?><span><?= t('tab_duplicate_log') ?></span></a>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="wrap">
