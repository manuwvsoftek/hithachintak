<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= $this->include('settings/_tabs') ?>

<div class="panel" style="margin-top:16px">
  <div class="panel-head">
    <div>
      <div class="panel-title">SMS Gateway — MSG91</div>
      <div class="panel-note" style="margin-top:3px">Transactional route, used for OTP and credential SMS</div>
    </div>
    <span class="pill <?= status_pill_class($settings['status'] ?? 'inactive') ?>"><?= esc(ucfirst($settings['status'] ?? 'inactive')) ?></span>
  </div>
  <form action="<?= site_url('admin/settings/sms') ?>" method="post">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field"><label class="f-label">Auth key</label><input class="f-input" type="password" name="auth_key" placeholder="<?= empty($settings['auth_key_enc']) ? 'Not set' : '•••••••••••• (leave blank to keep)' ?>"></div>
      <div class="field"><label class="f-label">Sender ID</label><input class="f-input" name="sender_id" value="<?= esc($settings['sender_id'] ?? 'VHPHTC') ?>"></div>
      <div class="field"><label class="f-label">DLT entity ID</label><input class="f-input" name="dlt_entity_id" value="<?= esc($settings['dlt_entity_id'] ?? '') ?>"></div>
      <div class="field"><label class="f-label">Route</label>
        <select class="f-input" name="route">
          <option value="transactional" <?= ($settings['route'] ?? '') === 'transactional' ? 'selected' : '' ?>>Transactional (OTP)</option>
          <option value="promotional" <?= ($settings['route'] ?? '') === 'promotional' ? 'selected' : '' ?>>Promotional</option>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn-navy btn-sm">Save changes</button>
  </form>
</div>

<div class="panel">
  <div class="panel-title" style="margin-bottom:12px">Send test SMS</div>
  <form action="<?= site_url('admin/settings/sms/test') ?>" method="post" class="row-actions" style="align-items:flex-end">
    <?= csrf_field() ?>
    <div class="field" style="margin:0"><label class="f-label">Phone number</label><input class="f-input" name="phone" placeholder="10-digit mobile" required></div>
    <button type="submit" class="btn btn-secondary btn-sm">Send test SMS</button>
  </form>
</div>

<div class="panel">
  <div class="panel-head"><div class="panel-title">DLT-Registered Templates</div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Purpose</th><th>Template IDs &amp; status</th></tr></thead>
      <tbody>
      <?php foreach ($templates as $t): ?>
        <tr>
          <td style="white-space:normal"><?= esc($t['purpose']) ?></td>
          <td style="white-space:normal">
            <form action="<?= site_url('admin/settings/sms/templates/' . $t['id']) ?>" method="post" class="row-actions">
              <?= csrf_field() ?>
              <input class="f-input" name="dlt_template_id" value="<?= esc($t['dlt_template_id'] ?? '') ?>" style="padding:5px 8px;font-size:11.5px;width:170px" placeholder="DLT template ID">
              <input class="f-input" name="msg91_template_id" value="<?= esc($t['msg91_template_id'] ?? '') ?>" style="padding:5px 8px;font-size:11.5px;width:170px" placeholder="MSG91 flow/template ID">
              <select name="status" class="f-input" style="padding:5px 8px;font-size:11.5px;width:auto">
                <?php foreach (['pending', 'approved', 'rejected'] as $s): ?>
                  <option value="<?= $s ?>" <?= $t['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-ghost btn-sm">Save</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (! $templates): ?>
        <tr><td colspan="2" class="panel-note">No templates yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
