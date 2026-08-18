<?php
require 'config.php';
require 'includes/auth.php';
require 'includes/mailer.php';

$prefillEmail = trim($_GET['email'] ?? '');
$mode = 'request'; // 'request' -> email form, 'reset' -> code+new password form
$status = null;    // null | 'sent' | 'ok' | 'invalid' | 'expired' | 'mail_failed'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $status = 'invalid';
        $mode = $_POST['action'] === 'reset' ? 'reset' : 'request';
    } elseif ($_POST['action'] === 'request') {
        $email = trim($_POST['email'] ?? '');
        $prefillEmail = $email;
        if ($email === '') {
            $status = 'invalid';
        } else {
            $stmt = $pdo->prepare("SELECT user_id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user) {
                $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $upd = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE user_id = ?");
                $upd->execute([$code, $user['user_id']]);
                $mailResult = sendPasswordResetEmail($email, $user['username'], $code);
                if ($mailResult !== true) {
                    $status = 'mail_failed';
                }
            }
            // Same "sent" message whether or not the email matched, so we don't leak which emails are registered.
            if ($status !== 'mail_failed') {
                $status = 'sent';
            }
        }
    } elseif ($_POST['action'] === 'reset') {
        $mode = 'reset';
        $email = trim($_POST['email'] ?? '');
        $code  = trim($_POST['code'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $prefillEmail = $email;

        if ($email === '' || $code === '' || $newPassword === '') {
            $status = 'invalid';
        } elseif (strlen($newPassword) < 6) {
            $status = 'password_length';
        } elseif ($newPassword !== $confirmPassword) {
            $status = 'password_mismatch';
        } else {
            $stmt = $pdo->prepare("SELECT user_id, reset_expires FROM users WHERE email = ? AND reset_token = ?");
            $stmt->execute([$email, $code]);
            $row = $stmt->fetch();
            if (!$row) {
                $status = 'invalid';
            } elseif (strtotime($row['reset_expires']) < time()) {
                $status = 'expired';
            } else {
                $upd = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE user_id = ?");
                $upd->execute([password_hash($newPassword, PASSWORD_BCRYPT), $row['user_id']]);
                $status = 'ok';
            }
        }
    }
} elseif (isset($_GET['reset'])) {
    $mode = 'reset';
}

$lang = currentLang();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>(function(){try{if(localStorage.getItem('reliefx_theme')==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}})();</script>
<title><?= t('forgot_pw_title') ?></title>
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
      <h1><?= t('reset_ok_h1') ?></h1>
      <p class="sub"><?= t('reset_ok_sub') ?></p>
      <a class="btn" href="index.php" style="display:inline-block;text-decoration:none;"><?= t('go_to_login') ?></a>

    <?php elseif ($status === 'sent'): ?>
      <h1><?= t('forgot_pw_sent_h1') ?></h1>
      <p class="sub"><?= t('forgot_pw_sent_sub') ?></p>
      <a class="btn" href="forgot_password.php?reset=1&email=<?= urlencode($prefillEmail) ?>" style="display:inline-block;text-decoration:none;"><?= t('enter_reset_code_btn') ?></a>

    <?php elseif ($status === 'mail_failed'): ?>
      <h1><?= t('forgot_pw_sent_h1') ?></h1>
      <p class="sub"><?= t('forgot_pw_sent_sub') ?></p>
      <div class="alert amber show"><?= t('err_mail_failed_generic') ?></div>
      <a class="btn" href="forgot_password.php?reset=1&email=<?= urlencode($prefillEmail) ?>" style="display:inline-block;text-decoration:none;"><?= t('enter_reset_code_btn') ?></a>

    <?php elseif ($status === 'expired'): ?>
      <h1><?= t('reset_expired_h1') ?></h1>
      <p class="sub"><?= t('reset_expired_sub') ?></p>
      <a class="btn" href="forgot_password.php" style="display:inline-block;text-decoration:none;"><?= t('request_again') ?></a>

    <?php elseif ($mode === 'reset'): ?>
      <h1><?= t('reset_pw_h1') ?></h1>
      <p class="sub"><?= t('reset_pw_sub') ?></p>
      <?php if ($status === 'invalid'): ?>
        <div class="alert red show"><?= t('err_reset_invalid') ?></div>
      <?php elseif ($status === 'password_length'): ?>
        <div class="alert red show"><?= t('err_password_length') ?></div>
      <?php elseif ($status === 'password_mismatch'): ?>
        <div class="alert red show"><?= t('err_password_mismatch') ?></div>
      <?php endif; ?>
      <form method="POST" style="text-align:left;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="reset">
        <div class="field">
          <label><?= t('official_email_label') ?></label>
          <input type="email" name="email" value="<?= htmlspecialchars($prefillEmail) ?>" required autofocus>
        </div>
        <div class="field">
          <label><?= t('reset_code_label') ?></label>
          <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required style="text-align:center; letter-spacing:6px; font-size:20px; font-weight:700;">
        </div>
        <div class="field">
          <label><?= t('new_password_label') ?></label>
          <input type="password" name="new_password" required minlength="6">
        </div>
        <div class="field">
          <label><?= t('confirm_password_label') ?></label>
          <input type="password" name="confirm_password" required minlength="6">
        </div>
        <button class="btn" type="submit"><?= t('reset_submit_btn') ?></button>
      </form>
      <p style="text-align:center; margin-top:18px; font-size:13.5px;">
        <a href="index.php"><?= t('go_to_login') ?></a>
      </p>

    <?php else: ?>
      <h1><?= t('forgot_pw_h1') ?></h1>
      <p class="sub"><?= t('forgot_pw_sub') ?></p>
      <?php if ($status === 'invalid'): ?>
        <div class="alert red show"><?= t('err_required_fields') ?></div>
      <?php endif; ?>
      <form method="POST" style="text-align:left;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="action" value="request">
        <div class="field">
          <label><?= t('official_email_label') ?></label>
          <input type="email" name="email" value="<?= htmlspecialchars($prefillEmail) ?>" required autofocus>
        </div>
        <button class="btn" type="submit"><?= t('forgot_pw_request_btn') ?></button>
      </form>
      <p style="text-align:center; margin-top:18px; font-size:13.5px;">
        <a href="index.php"><?= t('go_to_login') ?></a>
      </p>
    <?php endif; ?>
    </div>
  </div>

</div>
<script src="js/theme.js"></script>
<script src="js/auth.js"></script>
</body>
</html>
