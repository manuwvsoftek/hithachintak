<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= $this->include('settings/_tabs') ?>

<div class="panel" style="margin-top:16px">
  <div class="panel-head">
    <div>
      <div class="panel-title">Email Gateway</div>
      <div class="panel-note" style="margin-top:3px">Used for receipts and admin notifications</div>
    </div>
    <span class="pill <?= status_pill_class($settings['status'] ?? 'inactive') ?>"><?= esc(ucfirst($settings['status'] ?? 'inactive')) ?></span>
  </div>
  <form action="<?= site_url('admin/settings/email') ?>" method="post" id="emailForm">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field">
        <label class="f-label">Provider</label>
        <select class="f-input" name="provider" id="providerSelect">
          <option value="smtp" <?= ($settings['provider'] ?? 'smtp') === 'smtp' ? 'selected' : '' ?>>SMTP Relay</option>
          <option value="ses" <?= ($settings['provider'] ?? '') === 'ses' ? 'selected' : '' ?>>Amazon SES (SMTP credentials)</option>
          <option value="sendgrid" <?= ($settings['provider'] ?? '') === 'sendgrid' ? 'selected' : '' ?>>SendGrid (API)</option>
        </select>
      </div>
      <div class="field"><label class="f-label">From address</label><input class="f-input" type="email" name="from_address" value="<?= esc($settings['from_address'] ?? '') ?>" placeholder="receipts@vhp-hithachintak.org"></div>
      <div class="field"><label class="f-label">From name</label><input class="f-input" name="from_name" value="<?= esc($settings['from_name'] ?? 'Hithachintak Abhiyan') ?>"></div>
      <div class="field"><label class="f-label">Daily send quota</label><input class="f-input" type="number" name="daily_quota" value="<?= esc($settings['daily_quota'] ?? '') ?>" placeholder="10000"></div>
    </div>

    <div id="smtpFields" class="form-grid">
      <div class="field"><label class="f-label">SMTP host</label><input class="f-input" name="smtp_host" value="<?= esc($settings['smtp_host'] ?? '') ?>" placeholder="smtp.example.org"></div>
      <div class="field"><label class="f-label">SMTP port</label><input class="f-input" type="number" name="smtp_port" value="<?= esc($settings['smtp_port'] ?? 587) ?>"></div>
      <div class="field"><label class="f-label">SMTP username</label><input class="f-input" name="smtp_user" value="<?= esc($settings['smtp_user'] ?? '') ?>"></div>
      <div class="field"><label class="f-label">SMTP password</label><input class="f-input" type="password" name="smtp_pass" placeholder="<?= empty($settings['smtp_pass_enc']) ? 'Not set' : '•••••••••••• (leave blank to keep)' ?>"></div>
      <div class="field"><label class="f-label">Encryption</label>
        <select class="f-input" name="smtp_crypto">
          <option value="tls" <?= ($settings['smtp_crypto'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
          <option value="ssl" <?= ($settings['smtp_crypto'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
        </select>
      </div>
    </div>

    <div id="apiKeyField" class="field" style="display:none">
      <label class="f-label">SendGrid API key</label>
      <input class="f-input" type="password" name="api_key" placeholder="<?= empty($settings['api_key_enc']) ? 'Not set' : '•••••••••••• (leave blank to keep)' ?>">
    </div>

    <button type="submit" class="btn btn-navy btn-sm">Save changes</button>
  </form>
</div>

<div class="panel">
  <div class="panel-title" style="margin-bottom:12px">Send test email</div>
  <form action="<?= site_url('admin/settings/email/test') ?>" method="post" class="row-actions" style="align-items:flex-end">
    <?= csrf_field() ?>
    <div class="field" style="margin:0"><label class="f-label">Email address</label><input class="f-input" type="email" name="email" required></div>
    <button type="submit" class="btn btn-secondary btn-sm">Send test email</button>
  </form>
</div>

<script <?= csp_script_nonce() ?>>
(function(){
  var sel = document.getElementById('providerSelect');
  var smtp = document.getElementById('smtpFields');
  var api = document.getElementById('apiKeyField');
  function sync(){
    var isSendgrid = sel.value === 'sendgrid';
    smtp.style.display = isSendgrid ? 'none' : '';
    api.style.display = isSendgrid ? '' : 'none';
  }
  sel.addEventListener('change', sync);
  sync();
})();
</script>

<?= $this->endSection() ?>
