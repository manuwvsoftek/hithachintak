<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>

<h2>Sign in</h2>
<p class="panel-note">Enter your registered mobile number and password.</p>

<form action="<?= site_url('login') ?>" method="post">
  <?= csrf_field() ?>
  <div class="field">
    <label class="f-label" for="phone">Phone number or User ID</label>
    <input class="f-input" type="text" id="phone" name="phone" inputmode="numeric" maxlength="10"
           placeholder="10-digit registered mobile" value="<?= esc(old('phone')) ?>" required autofocus>
  </div>
  <div class="field">
    <label class="f-label" for="password">Password</label>
    <input class="f-input" type="password" id="password" name="password" required>
  </div>
  <button type="submit" class="btn btn-primary btn-block">Sign in</button>
  <div class="auth-links">
    <a href="<?= site_url('forgot-password') ?>">Forgot password?</a>
  </div>
</form>
<div class="auth-links" style="margin-top:14px;justify-content:center;gap:16px">
  <a href="<?= site_url('about') ?>">About this platform</a>
  <a href="<?= site_url('contact-us') ?>">Contact Us</a>
</div>
<div class="auth-links" style="margin-top:14px;justify-content:center;gap:16px">
<a href="<?= site_url('privacypolicy') ?>">Privacy Policy</a>
  <a href="<?= site_url('termsconditions') ?>">Terms & Conditions</a>
  <a href="<?= site_url('services') ?>">Our Services</a>
</div>
<?= $this->endSection() ?>
