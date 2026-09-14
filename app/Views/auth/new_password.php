<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>

<h2>Set a new password</h2>
<p class="panel-note">Choose a new password for your account.</p>

<form action="<?= site_url('forgot-password/new') ?>" method="post">
  <?= csrf_field() ?>
  <div class="field">
    <label class="f-label" for="password">New password</label>
    <input class="f-input" type="password" id="password" name="password" minlength="8" placeholder="At least 8 characters" required autofocus>
  </div>
  <div class="field">
    <label class="f-label" for="password_confirm">Confirm new password</label>
    <input class="f-input" type="password" id="password_confirm" name="password_confirm" minlength="8" placeholder="Re-enter new password" required>
  </div>
  <button type="submit" class="btn btn-primary btn-block">Update password</button>
</form>

<?= $this->endSection() ?>
