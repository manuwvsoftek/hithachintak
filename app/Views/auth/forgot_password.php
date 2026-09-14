<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>

<h2>Reset your password</h2>
<p class="panel-note">Enter the phone number registered against your account. We'll send a one-time code to verify it's you.</p>

<form action="<?= site_url('forgot-password') ?>" method="post">
  <?= csrf_field() ?>
  <div class="field">
    <label class="f-label" for="phone">Registered phone number</label>
    <input class="f-input" type="text" id="phone" name="phone" inputmode="numeric" maxlength="10"
           placeholder="10-digit mobile" value="<?= esc(old('phone')) ?>" required autofocus>
  </div>
  <button type="submit" class="btn btn-primary btn-block">Send OTP</button>
  <div class="auth-links">
    <a href="<?= site_url('login') ?>">Back to sign in</a>
  </div>
</form>

<?= $this->endSection() ?>
