<div class="page-header">
  <div>
    <div class="page-title">Settings &amp; Integrations</div>
    <div class="page-desc">Restricted to Dev Admin — payment, SMS, WhatsApp, email credentials, and Roles &amp; Permissions</div>
  </div>
</div>
<div class="tabs">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="tab <?= $activeTab === $key ? 'active' : '' ?>" href="<?= site_url('admin/settings/' . $key) ?>"><?= esc($label) ?></a>
  <?php endforeach; ?>
</div>
