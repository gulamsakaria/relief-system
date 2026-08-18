<?php
require 'config.php';
require 'includes/auth.php';
require 'includes/mailer.php';

$items = $pdo->query("SELECT * FROM relief_item ORDER BY category, item_name")->fetchAll();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ngoName  = trim($_POST['ngo_name'] ?? '');
    $regNo    = trim($_POST['reg_no'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = t('api_csrf_invalid');
    }
    if ($ngoName === '' || $regNo === '' || $email === '' || $username === '' || $password === '') {
        $errors[] = t('err_required_fields');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('err_bad_email');
    }
    if (strlen($password) < 6) {
        $errors[] = t('err_password_length');
    }
    if ($password !== $confirm) {
        $errors[] = t('err_password_mismatch');
    }

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT 1 FROM ngo WHERE ngo_name = ? OR reg_no = ? OR official_email = ?");
        $stmt->execute([$ngoName, $regNo, $email]);
        if ($stmt->fetch()) {
            $errors[] = t('err_ngo_exists');
        }
    }
    if (!$errors) {
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = t('err_user_exists');
        }
    }

    if (!$errors) {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO ngo (ngo_name, contact_phone, reg_no, official_email) VALUES (?, ?, ?, ?)");
            $stmt->execute([$ngoName, $phone ?: null, $regNo, $email]);
            $ngoId = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, ngo_id, email, is_verified, verify_token, verify_expires)
                                    VALUES (?, ?, 'ngo_operator', ?, ?, 0, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $ngoId, $email, $code]);

            $stockStmt = $pdo->prepare("INSERT INTO ngo_stock (ngo_id, item_id, quantity_available) VALUES (?, ?, ?)");
            foreach ($items as $it) {
                $qty = (float)($_POST['stock'][$it['item_id']] ?? 0);
                if ($qty < 0) $qty = 0;
                $stockStmt->execute([$ngoId, $it['item_id'], $qty]);
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = t('err_registration_failed');
        }

        if (!$errors) {
            $mailResult = sendVerificationEmail($email, $ngoName, $code);
            if ($mailResult === true) {
                $success = true;
            } else {
                $errors[] = sprintf(t('err_mail_failed'), htmlspecialchars($mailResult));
            }
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
<title><?= t('ngo_reg_title') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&family=Baloo+Da+2:wght@600;700;800&display=swap" rel="stylesheet">
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

    <div class="auth-form-inner wide">
    <img src="img/logo-icon.png" alt="ReliefX" class="login-logo mobile-only-logo">
    <h1><?= t('ngo_reg_h1') ?></h1>
    <p class="sub"><?= t('ngo_reg_sub') ?></p>

    <?php if ($success): ?>
      <div class="alert green show">
        <?= sprintf(t('ngo_reg_success'), '<b>' . htmlspecialchars($email) . '</b>') ?>
      </div>
      <a class="btn" href="verify_email.php?email=<?= urlencode($email) ?>" style="display:inline-block;text-align:center;text-decoration:none;width:100%;box-sizing:border-box;"><?= t('enter_code_btn') ?></a>
    <?php else: ?>
      <?php if ($errors): ?>
        <div class="alert red show"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <div class="field">
          <label><?= t('ngo_name_label') ?></label>
          <div class="input-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 22v-4h6v4M9 6h1M9 10h1M9 14h1M14 6h1M14 10h1M14 14h1"/></svg>
            <input type="text" name="ngo_name" value="<?= htmlspecialchars($_POST['ngo_name'] ?? '') ?>" required>
          </div>
        </div>
        <div class="grid2">
          <div class="field">
            <label><?= t('reg_no_label') ?></label>
            <input type="text" name="reg_no" value="<?= htmlspecialchars($_POST['reg_no'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label><?= t('contact_phone_label') ?></label>
            <input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
          </div>
        </div>
        <div class="field">
          <label><?= t('official_email_label') ?></label>
          <div class="input-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>
            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
          </div>
        </div>
        <div class="field">
          <label><?= t('username') ?></label>
          <div class="input-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
          </div>
        </div>
        <div class="grid2">
          <div class="field">
            <label><?= t('password') ?></label>
            <div class="input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <input type="password" name="password" required>
            </div>
          </div>
          <div class="field">
            <label><?= t('confirm_password_label') ?></label>
            <div class="input-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <input type="password" name="confirm" required>
            </div>
          </div>
        </div>
        <div class="field">
          <label><?= t('reg_stock_label') ?></label>
          <p style="font-size:11.5px; color:var(--ink-soft); margin:-2px 0 8px;"><?= t('reg_stock_sub') ?></p>
          <div class="stock-init-grid">
            <?php $curCat = null; foreach ($items as $it): ?>
              <?php if ($it['category'] !== $curCat): $curCat = $it['category']; ?>
                <div class="stock-init-cat"><?= htmlspecialchars($curCat) ?></div>
              <?php endif; ?>
              <div class="stock-init-row">
                <label><?= htmlspecialchars($it['item_name']) ?> <span>(<?= htmlspecialchars($it['unit']) ?>)</span></label>
                <input type="number" name="stock[<?= $it['item_id'] ?>]" min="0" step="0.5" placeholder="0"
                       value="<?= htmlspecialchars($_POST['stock'][$it['item_id']] ?? '') ?>">
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <button class="btn" type="submit"><?= t('register_btn') ?></button>
      </form>
    <?php endif; ?>

    <div class="demo-creds">
      <?= t('already_have_account') ?> <a href="index.php"><?= t('login_btn') ?></a>
    </div>
    </div>
  </div>

</div>
<script src="js/theme.js"></script>
<script src="js/auth.js"></script>
</body>
</html>
