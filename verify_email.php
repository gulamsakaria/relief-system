<?php
require 'config.php';
require 'includes/auth.php';

$prefillEmail = trim($_GET['email'] ?? '');
$status = null; // null = show the form, 'ok' | 'expired' | 'invalid' = show a result

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $code  = trim($_POST['code'] ?? '');
    $prefillEmail = $email;

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $status = 'invalid';
    } elseif ($email === '' || $code === '') {
        $status = 'invalid';
    } else {
        $stmt = $pdo->prepare("SELECT user_id, verify_expires FROM users WHERE email = ? AND verify_token = ? AND is_verified = 0");
        $stmt->execute([$email, $code]);
        $row = $stmt->fetch();

        if (!$row) {
            $status = 'invalid';
        } elseif (strtotime($row['verify_expires']) < time()) {
            $status = 'expired';
        } else {
            $upd = $pdo->prepare("UPDATE users SET is_verified = 1, verify_token = NULL, verify_expires = NULL WHERE user_id = ?");
            $upd->execute([$row['user_id']]);
            $status = 'ok';
        }
    }
}
$lang = currentLang();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>(function(){try{if(localStorage.getItem('reliefx_theme')==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}})();</script>
<title><?= t('verify_title') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Baloo+Da+2:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
<link rel="icon" type="image/png" href="img/logo-icon.png">
</head>
<body class="login-page">
<div class="auth-shell">

  <?php require 'includes/auth_brand.php'; ?>

  <div class="auth-form-side">
    <div style="position:absolute; top:20px; right:24px; display:flex; align-items:center; gap:10px; z-index:5;">
      <button type="button" class="theme-toggle-btn-light" aria-label="Toggle light/dark mode">
        <span class="icon-sun"><?= icon('sun',14) ?></span>
        <span class="icon-moon"><?= icon('moon',14) ?></span>
      </button>
      <div class="lang-toggle-light" style="margin:0;">
        <a href="switch_lang.php?lang=bn" class="<?= $lang==='bn'?'active':'' ?>">বাংলা</a>
        <a href="switch_lang.php?lang=en" class="<?= $lang==='en'?'active':'' ?>">EN</a>
      </div>
    </div>

    <div class="auth-form-inner" style="text-align:center;">
    <img src="img/logo-icon.png" alt="ReliefX" class="login-logo mobile-only-logo">
    <?php if ($status === 'ok'): ?>
      <h1><?= t('verify_ok_h1') ?></h1>
      <p class="sub"><?= t('verify_ok_sub') ?></p>
      <a class="btn" href="index.php" style="display:inline-block;text-decoration:none;"><?= t('go_to_login') ?></a>
    <?php elseif ($status === 'expired'): ?>
      <h1><?= t('verify_expired_h1') ?></h1>
      <p class="sub"><?= t('verify_expired_sub') ?></p>
      <a class="btn" href="register_ngo.php" style="display:inline-block;text-decoration:none;"><?= t('register_again') ?></a>
    <?php else: ?>
      <h1><?= t('verify_form_h1') ?></h1>
      <p class="sub"><?= t('verify_form_sub') ?></p>
      <?php if ($status === 'invalid'): ?>
        <div class="alert red show"><?= t('err_verify_invalid') ?></div>
      <?php endif; ?>
      <form method="POST" style="text-align:left;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <div class="field">
          <label><?= t('official_email_label') ?></label>
          <div class="input-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>
            <input type="email" name="email" value="<?= htmlspecialchars($prefillEmail) ?>" required autofocus<?= $prefillEmail !== '' ? ' readonly' : '' ?>>
          </div>
          <?php if ($prefillEmail !== ''): ?>
            <div class="note" style="margin-top:6px;"><?= t('verify_email_locked_note') ?></div>
          <?php endif; ?>
        </div>
        <div class="field">
          <label><?= t('verify_code_label') ?></label>
          <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required style="text-align:center; letter-spacing:6px; font-size:20px; font-weight:700;">
        </div>
        <button class="btn" type="submit"><?= t('verify_submit_btn') ?></button>
      </form>
      <p style="text-align:center; margin-top:18px; font-size:13.5px;">
        <a href="register_ngo.php"><?= t('register_again') ?></a>
      </p>
    <?php endif; ?>
    </div>
  </div>

</div>
<script src="js/theme.js"></script>
<script src="js/auth.js"></script>
</body>
</html>
