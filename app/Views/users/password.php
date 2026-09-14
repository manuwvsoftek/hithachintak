<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div><div class="page-title">Password — <?= esc($target->name) ?></div></div>
  <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/users') ?>">Back</a>
</div>

<div class="panel" style="max-width:480px">
  <?php if ($password !== null): ?>
    <div class="field">
      <label class="f-label">Current password</label>
      <div class="row-actions">
        <input class="f-input" id="current-password" value="<?= esc($password) ?>" readonly style="flex:1">
        <button type="button" class="btn btn-secondary btn-sm" data-copy-target="#current-password">Copy</button>
      </div>
      <p class="panel-note" style="margin-top:6px">This is what <?= esc($target->name) ?> currently logs in with.</p>
    </div>
  <?php else: ?>
    <p class="panel-note"><?= esc($target->name) ?> has since set their own password — it isn't visible here. Issue a new one below if they need it reset.</p>
  <?php endif; ?>

  <form action="<?= site_url('admin/users/' . $target->id . '/reset-password') ?>" method="post"
        style="<?= $password !== null ? 'margin-top:18px;padding-top:16px;border-top:1px solid var(--border-soft)' : '' ?>">
    <?= csrf_field() ?>
    <div class="field">
      <label class="f-label">Set a new password (optional)</label>
      <input class="f-input" name="password" minlength="6" placeholder="Leave blank to generate one automatically">
    </div>
    <button type="submit" class="btn btn-primary btn-sm" data-confirm="Update this user's password? New credentials will be sent by SMS.">Update password</button>
  </form>
</div>

<?= $this->endSection() ?>
