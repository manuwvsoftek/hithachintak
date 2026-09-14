<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= $this->include('settings/_tabs') ?>

<div class="panel" style="margin-top:16px">
  <div class="panel-head">
    <div>
      <div class="panel-title">WhatsApp Business API — MSG91</div>
      <div class="panel-note" style="margin-top:3px">Used for receipts, OTP alerts and reminders</div>
    </div>
    <span class="pill <?= status_pill_class($settings['status'] ?? 'inactive') ?>"><?= esc(ucfirst($settings['status'] ?? 'inactive')) ?></span>
  </div>
  <form action="<?= site_url('admin/settings/whatsapp') ?>" method="post">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field"><label class="f-label">Integrated WhatsApp number</label><input class="f-input" name="integrated_number" value="<?= esc($settings['integrated_number'] ?? '') ?>" placeholder="+91 80000 55221"></div>
      <div class="field"><label class="f-label">API key</label><input class="f-input" type="password" name="api_key" placeholder="<?= empty($settings['api_key_enc']) ? 'Not set' : '•••••••••••• (leave blank to keep)' ?>"></div>
      <div class="field"><label class="f-label">WABA / Namespace ID</label><input class="f-input" name="waba_namespace_id" value="<?= esc($settings['waba_namespace_id'] ?? '') ?>"></div>
    </div>
    <button type="submit" class="btn btn-navy btn-sm">Save changes</button>
  </form>
</div>

<div class="panel">
  <div class="panel-title" style="margin-bottom:12px">Send test message</div>
  <form action="<?= site_url('admin/settings/whatsapp/test') ?>" method="post" class="row-actions" style="align-items:flex-end">
    <?= csrf_field() ?>
    <div class="field" style="margin:0"><label class="f-label">Phone number</label><input class="f-input" name="phone" placeholder="10-digit mobile" required></div>
    <button type="submit" class="btn btn-secondary btn-sm">Send test message</button>
  </form>
</div>

<div class="panel">
  <div class="panel-head"><div class="panel-title">Message Templates</div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Template</th><th>Language</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($templates as $t): ?>
        <tr>
          <td class="num"><?= esc($t['name']) ?></td>
          <td><?= esc($t['lang']) ?></td>
          <td style="white-space:normal">
            <form action="<?= site_url('admin/settings/whatsapp/templates/' . $t['id']) ?>" method="post" class="row-actions">
              <?= csrf_field() ?>
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
      </tbody>
    </table>
  </div>
  <p class="panel-note" style="margin-top:12px">Template approvals go through WhatsApp / Meta directly — typically 3–5 working days per template.</p>
</div>

<?= $this->endSection() ?>
