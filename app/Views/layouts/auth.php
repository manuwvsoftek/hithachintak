<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="author" content="Shreedhar Bhat, Manu K, World Vision Softek">
  <title><?= esc($title ?? 'Hithachintak Abhiyan') ?></title>
  <link rel="icon" href="<?= base_url('assets/images/logo-placeholder.jpeg') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/app.css') ?>">
  <?= $this->include('partials/pwa_head') ?>
</head>
<body>
  <div class="auth-page">
    <div class="auth-shell">
      <div class="auth-brand">
        <div class="brand-mark"><img src="<?= base_url('assets/images/logo-placeholder.jpeg') ?>" alt=""></div>
        <div>
          <h1>VHP Hithachintak Abhiyan</h1>
          <p>Enrolment, Collection &amp; Receipting Platform<br>Hithachintak &middot; Dharma Raksha Nidhi &middot; Magazine Subscription</p>
        </div>
        <div class="auth-facts">
          <div class="auth-fact">Field enrolment with instant OTP verification</div>
          <div class="auth-fact">UPI/QR or cash collection, reconciled per Karyakarta</div>
          <div class="auth-fact">Receipts by WhatsApp, SMS and email in 13 languages</div>
        </div>
      </div>
      <div class="auth-form-wrap">
        <div class="auth-card">
          <?= $this->include('partials/flash') ?>
          <?= $this->renderSection('content') ?>
        </div>
      </div>
    </div>
  </div>
  <?= $this->include('partials/pwa_register') ?>
</body>
</html>
