<?php
require 'config.php';
require 'includes/auth.php';

if (isset($_SESSION['user'])) {
    header('Location: distribute.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT u.*, n.ngo_name FROM users u LEFT JOIN ngo n ON u.ngo_id = n.ngo_id WHERE username = ?");
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if ($row && password_verify($password, $row['password_hash'])) {
        if (!$row['is_verified']) {
            $error = t('err_not_verified');
        } else {
            $_SESSION['user'] = [
                'user_id'  => $row['user_id'],
                'username' => $row['username'],
                'role'     => $row['role'],
                'ngo_id'   => $row['ngo_id'],
                'ngo_name' => $row['ngo_name'],
            ];
            header('Location: distribute.php');
            exit;
        }
    } else {
        $error = t('err_bad_login');
    }
}
$lang = currentLang();
$brandNgo = $pdo->query("SELECT COUNT(*) c FROM ngo")->fetch()['c'];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script>(function(){try{if(localStorage.getItem('reliefx_theme')==='light'){document.documentElement.setAttribute('data-theme','light');}}catch(e){}})();</script>
<title><?= t('login_page_title') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&family=Baloo+Da+2:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
<link rel="icon" type="image/png" href="img/logo-icon.png">
</head>
<body class="login-page">

<div class="scroll-progress"><div class="scroll-progress-fill" id="scrollProgress"></div></div>

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

    <div class="auth-form-inner">
      <img src="img/logo-icon.png" alt="ReliefX" class="login-logo mobile-only-logo">
      <h1><?= t('login_title') ?></h1>
      <p class="sub"><?= t('login_sub') ?></p>

      <?php if ($error): ?>
        <div class="alert red show"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="field">
          <label><?= t('username') ?></label>
          <div class="input-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <input type="text" name="username" required autofocus>
          </div>
        </div>
        <div class="field">
          <label><?= t('password') ?></label>
          <div class="input-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <input type="password" name="password" required>
          </div>
          <p style="text-align:right; margin-top:6px;">
            <a href="forgot_password.php" style="font-size:12.5px;"><?= t('forgot_pw_link') ?></a>
          </p>
        </div>
        <button class="btn" type="submit"><?= t('login_btn') ?></button>
      </form>

      <p style="text-align:center; margin-top:18px; font-size:13.5px;">
        <?= t('new_foundation') ?> <a href="register_ngo.php"><?= t('register_with_email') ?></a>
      </p>

      <div class="demo-creds">
        <b><?= t('admin_account') ?></b>:<br>
        admin / Admin@123 (District Relief Officer)
      </div>
    </div>
  </div>

  <div class="scroll-hint" onclick="document.getElementById('howItWorks').scrollIntoView({behavior:'smooth'})">
    <span><?= t('scroll_hint') ?></span>
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
  </div>
</div>

<section class="landing-section" id="howItWorks">
  <div class="reveal">
    <div class="section-eyebrow"><?= t('steps_eyebrow') ?></div>
    <h2 class="section-title"><?= t('steps_title') ?></h2>
    <p class="section-sub"><?= t('steps_sub') ?></p>
  </div>
  <div class="steps-row reveal-stagger reveal">
    <div class="step-card tilt"><div class="step-icon"><?= icon('file-plus',22) ?><span class="step-num">1</span></div><h3><?= t('step1_title') ?></h3><p><?= t('step1_desc') ?></p></div>
    <div class="step-card tilt"><div class="step-icon"><?= icon('shield-check',22) ?><span class="step-num">2</span></div><h3><?= t('step2_title') ?></h3><p><?= t('step2_desc') ?></p></div>
    <div class="step-card tilt"><div class="step-icon"><?= icon('package',22) ?><span class="step-num">3</span></div><h3><?= t('step3_title') ?></h3><p><?= t('step3_desc') ?></p></div>
    <div class="step-card tilt"><div class="step-icon"><?= icon('scale',22) ?><span class="step-num">4</span></div><h3><?= t('step4_title') ?></h3><p><?= t('step4_desc') ?></p></div>
  </div>
</section>

<section class="landing-section tight impact-section">
  <div class="reveal">
    <div class="section-eyebrow"><?= t('impact_eyebrow') ?></div>
    <h2 class="section-title"><?= t('impact_title') ?></h2>
    <p class="section-sub"><?= t('impact_sub') ?></p>
  </div>
  <div class="impact-grid reveal-stagger reveal">
    <div class="impact-card tilt">
      <div class="impact-icon"><?= icon('users',22) ?></div>
      <div class="impact-num"><b data-count="<?= $brandFamilies ?>" data-suffix="+">0</b></div>
      <div class="impact-label"><?= t('stat_families') ?></div>
    </div>
    <div class="impact-card tilt">
      <div class="impact-icon"><?= icon('package',22) ?></div>
      <div class="impact-num"><b data-count="<?= $brandDist ?>" data-suffix="+">0</b></div>
      <div class="impact-label"><?= t('stat_distributions') ?></div>
    </div>
    <div class="impact-card tilt">
      <div class="impact-icon"><?= icon('map-pin',22) ?></div>
      <div class="impact-num"><b data-count="<?= $brandZones ?>">0</b></div>
      <div class="impact-label"><?= t('stat_zones') ?></div>
    </div>
    <div class="impact-card tilt">
      <div class="impact-icon"><?= icon('shield-check',22) ?></div>
      <div class="impact-num"><b data-count="<?= $brandNgo ?>" data-suffix="+">0</b></div>
      <div class="impact-label"><?= t('impact_stat_ngo') ?></div>
    </div>
  </div>
</section>

<div class="landing-section tinted">
  <div class="landing-section">
    <div class="reveal">
      <div class="section-eyebrow"><?= t('features_eyebrow') ?></div>
      <h2 class="section-title"><?= t('features_title') ?></h2>
      <p class="section-sub"><?= t('features_sub') ?></p>
    </div>
    <div class="feature-grid reveal-stagger reveal">
      <div class="feature-card tilt"><div class="feature-icon"><?= icon('lock',21) ?></div><h3><?= t('feature1_title') ?></h3><p><?= t('feature1_desc') ?></p></div>
      <div class="feature-card tilt"><div class="feature-icon"><?= icon('map-pin',21) ?></div><h3><?= t('feature2_title') ?></h3><p><?= t('feature2_desc') ?></p></div>
      <div class="feature-card tilt"><div class="feature-icon"><?= icon('package',21) ?></div><h3><?= t('feature3_title') ?></h3><p><?= t('feature3_desc') ?></p></div>
      <div class="feature-card tilt"><div class="feature-icon"><?= icon('search',21) ?></div><h3><?= t('feature4_title') ?></h3><p><?= t('feature4_desc') ?></p></div>
    </div>
  </div>
</div>

<section class="trust-band reveal">
  <div class="section-eyebrow"><?= t('trust_eyebrow') ?></div>
  <h2 class="section-title" style="margin-bottom:28px;"><?= t('trust_title') ?></h2>
  <div class="marquee">
    <div class="marquee-track">
      <?php
        $trustItems = [
          ['lock','trust_item1'], ['zap','trust_item2'], ['search','trust_item3'],
          ['shield-check','trust_item4'], ['users','trust_item5'], ['check','trust_item6'],
        ];
        for ($r = 0; $r < 2; $r++):
          foreach ($trustItems as $ti):
      ?>
        <div class="trust-chip"><?= icon($ti[0],16) ?><span><?= t($ti[1]) ?></span></div>
      <?php endforeach; endfor; ?>
    </div>
  </div>
</section>

<section class="landing-section">
  <div class="showcase-row">
    <div class="showcase-mockup reveal">
      <div class="device-mockup">
        <div class="device-mockup-bar"><span></span><span></span><span></span></div>
        <img src="img/distribute-preview.png" alt="ReliefX distribution entry preview">
      </div>
      <div class="showcase-live-badge"><span class="live-dot"></span> <?= $lang==='bn' ? 'লাইভ ডেটা' : 'Live Data' ?></div>
    </div>
    <div class="showcase-text reveal">
      <h2><?= t('showcase_title') ?></h2>
      <p style="color:var(--ink-soft); font-size:13.5px; line-height:1.7; margin:0 0 20px;"><?= t('showcase_desc') ?></p>
      <ul class="showcase-list">
        <li><span class="tick">✓</span><?= t('showcase_point1') ?></li>
        <li><span class="tick">✓</span><?= t('showcase_point2') ?></li>
        <li><span class="tick">✓</span><?= t('showcase_point3') ?></li>
        <li><span class="tick">✓</span><?= t('showcase_point4') ?></li>
      </ul>
    </div>
  </div>
</section>

<div class="landing-section tinted">
  <div class="landing-section">
    <div class="reveal">
      <div class="section-eyebrow"><?= t('testimonials_eyebrow') ?></div>
      <h2 class="section-title"><?= t('testimonials_title') ?></h2>
      <p class="section-sub"><?= t('testimonials_sub') ?></p>
    </div>
    <div class="testimonial-grid reveal-stagger reveal">
      <div class="testimonial-card tilt">
        <div class="testimonial-quote-mark">“</div>
        <p class="testimonial-text"><?= t('testimonial1_quote') ?></p>
        <div class="testimonial-person">
          <div class="testimonial-avatar"><?= mb_substr(t('testimonial1_name'), 0, 1) ?></div>
          <div><b><?= t('testimonial1_name') ?></b><span><?= t('testimonial1_role') ?></span></div>
        </div>
      </div>
      <div class="testimonial-card tilt">
        <div class="testimonial-quote-mark">“</div>
        <p class="testimonial-text"><?= t('testimonial2_quote') ?></p>
        <div class="testimonial-person">
          <div class="testimonial-avatar"><?= mb_substr(t('testimonial2_name'), 0, 1) ?></div>
          <div><b><?= t('testimonial2_name') ?></b><span><?= t('testimonial2_role') ?></span></div>
        </div>
      </div>
      <div class="testimonial-card tilt">
        <div class="testimonial-quote-mark">“</div>
        <p class="testimonial-text"><?= t('testimonial3_quote') ?></p>
        <div class="testimonial-person">
          <div class="testimonial-avatar"><?= mb_substr(t('testimonial3_name'), 0, 1) ?></div>
          <div><b><?= t('testimonial3_name') ?></b><span><?= t('testimonial3_role') ?></span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<section class="landing-section" id="faq">
  <div class="reveal">
    <div class="section-eyebrow"><?= t('faq_eyebrow') ?></div>
    <h2 class="section-title"><?= t('faq_title') ?></h2>
    <p class="section-sub"><?= t('faq_sub') ?></p>
  </div>
  <div class="faq-list reveal">
    <?php for ($i = 1; $i <= 5; $i++): ?>
    <div class="faq-item">
      <button type="button" class="faq-question">
        <span><?= t("faq{$i}_q") ?></span>
        <?= icon('chevron-down',18) ?>
      </button>
      <div class="faq-answer-wrap"><div class="faq-answer"><?= t("faq{$i}_a") ?></div></div>
    </div>
    <?php endfor; ?>
  </div>
</section>

<section class="landing-section cta-banner-wrap">
  <div class="cta-banner reveal">
    <div class="cta-banner-glow"></div>
    <h2><?= t('cta_title') ?></h2>
    <p><?= t('cta_sub') ?></p>
    <div class="cta-banner-actions">
      <a href="register_ngo.php" class="btn btn-accent"><?= t('cta_btn') ?></a>
      <a href="#howItWorks" class="btn btn-ghost-light"><?= t('cta_btn_secondary') ?></a>
    </div>
  </div>
</section>

<footer class="site-footer">
  <img src="img/logo-icon.png" alt="ReliefX" class="footer-logo">
  <div class="footer-brand">ReliefX</div>
  <p style="font-size:12.5px; opacity:.8; max-width:380px; margin:0 auto;"><?= t('footer_tagline') ?></p>
  <div class="footer-credits">
    <b>ReliefX</b> — Flood Relief Management System<br>
    Developed by Team <b>StratifyX</b> · Section 67_F1<br>
    Gulam Sakaria (242-15-218) · MD. Ashraful Islam (242-15-487) ·
    Mir Samiul Haque (242-15-350) · Nafil Ardul Ridin (242-15-705) ·
    Zidne Hasan (242-15-356)
  </div>
</footer>

<button type="button" id="backToTop" class="back-to-top" aria-label="Back to top">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
</button>

<script src="js/theme.js"></script>
<script src="js/auth.js"></script>
</body>
</html>
