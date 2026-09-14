<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="author" content="Shreedhar Bhat, Manu K, World Vision Softek">
  <title>Access restricted</title>
  <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
</head>
<body>
  <div class="auth-page">
    <div class="panel" style="max-width:420px">
      <h2>Access restricted</h2>
      <p class="panel-note" style="margin-top:8px">
        Your role doesn't have access to <b><?= esc($module ?? 'this section') ?></b>.
        Contact your Pranta Admin or Super Admin if you believe this is a mistake.
      </p>
      <a class="btn btn-secondary" style="margin-top:16px" href="<?= site_url('admin') ?>">Back to Dashboard</a>
    </div>
  </div>
</body>
</html>
