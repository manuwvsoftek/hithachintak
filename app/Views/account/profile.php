<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div>
    <div class="page-title">My Account</div>
    <div class="page-desc">Your own profile and password — visible to every signed-in user, regardless of role.</div>
  </div>
</div>

<div class="panel" style="max-width:520px">
  <div class="panel-title" style="margin-bottom:12px">Profile</div>
  <form action="<?= site_url('account/profile') ?>" method="post">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field"><label class="f-label">Full name</label><input class="f-input" name="name" value="<?= esc(old('name') ?? $user->name) ?>" required></div>
      <div class="field"><label class="f-label">Phone (login ID)</label><input class="f-input" value="<?= esc($user->phone) ?>" disabled></div>
      <div class="field"><label class="f-label">Email ID (optional)</label><input class="f-input" type="email" name="email" value="<?= esc(old('email') ?? $user->email ?? '') ?>" placeholder="name@example.org"></div>
       <div class="field">
        <label class="f-label">DOB</label>
        <input type="date" class="f-input" id="dob" name="dob" value="<?= esc($user->dob ?? '') ?>">
      </div>
      <div class="field"><label class="f-label">Ayam</label>
        <select class="f-input" id="ayamSelect" name="ayam">
          <option value="">— Ayam —</option>
          <?php foreach ($ayams as $a): ?>
            <option value="<?= esc($a) ?>" <?= $user->ayam ===  $a ? 'selected' : '' ?>><?= esc($a) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="f-label">Gender</label>
        <select class="f-input" id="genderSelect" name="gender" required>
          <option value="Male" <?= $user->gender === 'Male' ? 'selected' : '' ?>>Male</option>
          <option value="Female" <?= $user->gender === 'Female' ? 'selected' : '' ?>>Female</option>
          <option value="Other" <?= $user->gender === 'Other' ? 'selected' : '' ?>>Other</option>
        </select>
      </div>
      <div class="field"><label class="f-label">Profession (optional)</label><input class="f-input" name="profession" value="<?= esc(old('profession') ?? $user->profession ?? '') ?>" placeholder="Occupation"></div>
      <div class="field" style="grid-column:1/-1"><label class="f-label">Address (optional)</label><input class="f-input" name="address" value="<?= esc(old('address') ?? $user->address ?? '') ?>" placeholder="Address"></div>
      <div class="field"><label class="f-label">ID — Aadhar number (optional)</label><input class="f-input" name="aadhar_number" value="<?= esc(old('aadhar_number') ?? $user->aadhar_number ?? '') ?>" maxlength="20" placeholder="12-digit Aadhar number"></div>
    </div>
    <p class="panel-note" style="margin:10px 0">Phone number is your login ID — contact an Admin to change it.</p>
    <button type="submit" class="btn btn-primary btn-sm">Save profile</button>
  </form>
</div>

<div class="panel" style="max-width:520px">
  <div class="panel-title" style="margin-bottom:12px">Change Password</div>
  <form action="<?= site_url('account/change-password') ?>" method="post">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field" style="grid-column:1/-1"><label class="f-label">Current password</label><input class="f-input" type="password" name="current_password" required></div>
      <div class="field"><label class="f-label">New password</label><input class="f-input" type="password" name="password" minlength="8" placeholder="At least 8 characters" required></div>
      <div class="field"><label class="f-label">Confirm new password</label><input class="f-input" type="password" name="password_confirm" minlength="8" required></div>
    </div>
    <button type="submit" class="btn btn-secondary btn-sm">Change password</button>
  </form>
</div>

<?= $this->endSection() ?>
