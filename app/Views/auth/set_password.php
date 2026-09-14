<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>

<h2>Set your password</h2>
<p class="panel-note">This is your first sign-in. Choose a password for your account before continuing.</p>

<form action="<?= site_url('account/set-password') ?>" method="post">
  <?= csrf_field() ?>
  <div class="field">
    <label class="f-label" for="password">New password</label>
    <input class="f-input" type="password" id="password" name="password" minlength="8" placeholder="At least 8 characters" required autofocus>
  </div>
  <div class="field">
    <label class="f-label" for="password_confirm">Confirm password</label>
    <input class="f-input" type="password" id="password_confirm" name="password_confirm" minlength="8" required>
  </div>
  <button type="submit" class="btn btn-primary btn-block">Continue</button>
</form>

<?= $this->endSection() ?>
