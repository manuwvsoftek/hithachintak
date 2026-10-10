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
  <header class="landing-nav">
    <a class="landing-nav-brand" href="<?= site_url('/') ?>">
      <img src="<?= base_url('assets/images/logo-placeholder.jpeg') ?>" alt="VHP Hithachintak Abhiyan">
      <span>Hithachintak Abhiyan</span>
    </a>
    <nav class="landing-nav-links">
      <a href="<?= site_url('services') ?>">Our Services</a>
      <a href="<?= site_url('about') ?>">About</a>
      <a href="<?= site_url('contact-us') ?>">Contact Us</a>
    </nav>
    <div class="landing-nav-actions">
      <a class="btn btn-secondary btn-sm" href="<?= site_url('login') ?>">Donate</a>
      <a class="btn btn-primary btn-sm" href="<?= site_url('login') ?>">Login</a>
    </div>
  </header>

  <?= $this->renderSection('content') ?>

  <footer class="landing-footer">
    <div class="landing-footer-brand">
      <img src="<?= base_url('assets/images/logo-placeholder.jpeg') ?>" alt="VHP Hithachintak Abhiyan">
      <span>VHP Hithachintak Abhiyan</span>
    </div>
    <nav class="landing-footer-links">
      <a href="<?= site_url('about') ?>">About this platform</a>
      <a href="<?= site_url('services') ?>">Our Services</a>
      <a href="<?= site_url('contact-us') ?>">Contact Us</a>
      <a href="<?= site_url('privacypolicy') ?>">Privacy Policy</a>
      <a href="<?= site_url('termsconditions') ?>">Terms &amp; Conditions</a>
    </nav>
    <div class="landing-footer-copy">&copy; <?= date('Y') ?> Vishwa Hindu Parishad. All rights reserved.</div>
  </footer>

  <?= $this->include('partials/pwa_register') ?>
</body>
</html>
