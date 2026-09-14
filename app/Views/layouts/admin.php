<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="author" content="Shreedhar Bhat, Manu K, World Vision Softek">
  <title><?= esc($title ?? 'Hithachintak Abhiyan') ?></title>
  <link rel="icon" href="<?= base_url('assets/images/logo-placeholder.svg') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/app.css') ?>">
  <?= $this->include('partials/pwa_head') ?>
</head>
<body>
<?php
  $segment = trim(uri_string(), '/');
  $parts   = explode('/', $segment);
  $active  = $parts[1] ?? 'dashboard';
  $navLink = static function (string $path, string $label, string $module, string $minLevel = 'View') use ($active) {
      if (! (new \App\Models\RolePermissionModel())->atLeast(session('user_role'), $module, $minLevel)) {
          return '';
      }
      $key = $path === '' ? 'dashboard' : explode('/', $path)[0];
      $cls = $key === (($GLOBALS['__vhp_active'] ?? '')) ? 'side-link active' : 'side-link';

      return '<a class="' . $cls . '" href="' . site_url('admin/' . $path) . '">' . esc($label) . '</a>';
  };
  $GLOBALS['__vhp_active'] = $active === '' ? 'dashboard' : $active;
?>
  <header class="topbar">
    <a class="brand" href="<?= site_url('admin') ?>">
      <span class="brand-mark"><img src="<?= base_url('assets/images/logo-placeholder.svg') ?>" alt=""></span>
      <span class="brand-text">
        <span class="brand-name">Hithachintak Abhiyan</span>
        <span class="brand-sub">Enrolment &middot; Collection &middot; Receipting</span>
      </span>
    </a>
    <div class="topbar-right">
      <div class="topbar-user">
        <b><?= esc(session('user_name')) ?></b>
        <?= esc(\App\Entities\User::ROLE_LABELS[session('user_role')] ?? '') ?>
      </div>
      <form action="<?= site_url('account/set-language') ?>" method="post" class="topbar-lang" style="margin:0" title="Language shown on the enrolment form and the member's receipt">
        <?= csrf_field() ?>
        <select name="lang" class="js-auto-submit">
          <?php foreach (\App\Libraries\Enrolment\I18n::languageOptions() as $code => $label): ?>
            <option value="<?= $code ?>" <?= (session('ui_language') ?? 'en') === $code ? 'selected' : '' ?>><?= esc($label) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <form action="<?= site_url('logout') ?>" method="post" style="margin:0">
        <?= csrf_field() ?>
        <button type="submit" class="logout-link">Sign out</button>
      </form>
    </div>
  </header>

  <div class="stage">
    <div class="admin-shell">
      <nav class="admin-sidebar">
        <?= $navLink('', 'Dashboard', 'Dashboard', 'Own') ?>
        <?= $navLink('masters', 'Masters', 'Masters') ?>
        <?= $navLink('users', 'Users & Hierarchy', 'Users & Hierarchy') ?>
        <?= $navLink('enrolments', 'Enrolments', 'Enrolments', 'Own') ?>
        <?= $navLink('reports', 'Reports', 'Reports') ?>
        <div class="side-divider"></div>
        <?= $navLink('settings', 'Settings & Integrations', 'Settings & Integrations', 'Full') ?>
      </nav>
      <main class="admin-main">
        <?= $this->include('partials/flash') ?>
        <?= $this->renderSection('content') ?>
      </main>
    </div>
  </div>
  <script src="<?= base_url('assets/js/app.js') ?>"></script>
  <?= $this->include('partials/pwa_register') ?>
</body>
</html>
