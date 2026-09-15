<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="author" content="Shreedhar Bhat, Manu K, World Vision Softek">
  <meta name="robots" content="noindex, nofollow">
  <title><?= esc($title ?? 'Hithachintak Abhiyan') ?></title>
  <link rel="icon" href="<?= base_url('assets/images/logo-placeholder.jpeg') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/app.css') ?>">
</head>
<body>
  <div class="public-page">
    <div class="public-brand">
      <img src="<?= base_url('assets/images/logo-placeholder.jpeg') ?>" alt="">
      <span>Hithachintak Abhiyan</span>
    </div>
    <?= $this->renderSection('content') ?>
  </div>
</body>
</html>
