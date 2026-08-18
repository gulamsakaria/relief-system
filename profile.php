<?php
$pageTitle = 'প্রোফাইল — Relief Distribution System';
require 'config.php';
require 'includes/auth.php';
requireRole(['admin', 'ngo_operator']);
$user = currentUser();

$ngo = null;
if ($user['role'] === 'ngo_operator') {
    $stmt = $pdo->prepare("SELECT * FROM ngo WHERE ngo_id = ?");
    $stmt->execute([$user['ngo_id']]);
    $ngo = $stmt->fetch();
}

require 'includes/header.php';
?>

<div class="card">
  <h2><?= icon('user') ?><?= t('profile_h2') ?></h2>
  <p class="sub"><?= t('profile_sub') ?></p>

  <?php if ($user['role'] === 'admin'): ?>
    <div class="field"><label><?= t('username') ?></label><input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled></div>
    <div class="note" style="margin-top:10px;"><?= t('admin_profile_note') ?></div>
  <?php else: ?>
    <div class="profile-header">
      <div class="profile-logo-wrap">
        <img id="ngoLogoPreview" src="<?= $ngo['logo_path'] ? htmlspecialchars($ngo['logo_path']) : 'img/logo-icon.png' ?>" alt="<?= htmlspecialchars($ngo['ngo_name']) ?>" class="profile-logo">
        <label class="profile-logo-edit" for="ngoLogoInput" aria-label="<?= t('profile_change_photo') ?>"><?= icon('camera', 15) ?></label>
        <input type="file" id="ngoLogoInput" accept="image/png,image/jpeg,image/webp" hidden>
      </div>
      <div>
        <h3 style="margin:0 0 4px;"><?= htmlspecialchars($ngo['ngo_name']) ?></h3>
        <div class="note" style="margin:0;"><?= t('reg_no_label') ?>: <?= htmlspecialchars($ngo['reg_no']) ?></div>
      </div>
    </div>

    <div id="profileAlert" class="alert"></div>

    <div class="grid2">
      <div class="field"><label><?= t('ngo_name_label') ?></label><input type="text" value="<?= htmlspecialchars($ngo['ngo_name']) ?>" disabled></div>
      <div class="field"><label><?= t('official_email_label') ?></label><input type="text" value="<?= htmlspecialchars($ngo['official_email']) ?>" disabled></div>
    </div>
    <div class="field">
      <label><?= t('contact_phone_label') ?></label>
      <input type="text" id="profilePhone" value="<?= htmlspecialchars($ngo['contact_phone'] ?? '') ?>">
    </div>

    <button class="btn" id="profileSaveBtn" onclick="saveNgoProfile()"><?= t('save_profile_btn') ?></button>
    <div class="note" style="margin-top:10px;"><?= t('profile_logo_note') ?></div>
  <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
