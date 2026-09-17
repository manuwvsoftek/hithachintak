<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= $this->include('settings/_tabs') ?>

<?php
  $hasAnyTrustInfo = static fn ($p) => ! empty($p['trust_name']) || ! empty($p['trust_address']) || ! empty($p['trust_pincode']) || ! empty($p['trust_pan']) || ! empty($p['trust_phone']) || ! empty($p['trust_email']);
  $configured = count(array_filter($prants, $hasAnyTrustInfo));
?>
<div class="kpi-grid" style="margin-top:16px">
  <div class="kpi-card"><div class="kpi-label">Prants with Trust Details</div><div class="kpi-value num"><?= $configured ?> / <?= count($prants) ?></div></div>
  <div class="kpi-card"><div class="kpi-label">Pending Setup</div><div class="kpi-value num"><?= count($prants) - $configured ?></div></div>
  <div class="kpi-card"><div class="kpi-label">Shown on</div><div class="kpi-value" style="font-size:18px">Contact Us page</div><div class="kpi-sub">Public, selected by Prant</div></div>
</div>

<?php if ($editPrant): ?>
<div class="panel">
  <div class="panel-title" style="margin-bottom:12px">Edit Trust details — <?= esc($editPrant['name']) ?></div>
  <form action="<?= site_url('admin/settings/trust/' . $editPrant['id']) ?>" method="post">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field"><label class="f-label">Registered Trust Name</label><input class="f-input" name="trust_name" value="<?= esc($editPrant['trust_name'] ?? '') ?>" placeholder="e.g. Vishwa Hindu Parishad, Mahakoshal"></div>
      <div class="field" style="grid-column:1/-1"><label class="f-label">Address</label><textarea class="f-input" name="trust_address" rows="2" placeholder="Registered office address"><?= esc($editPrant['trust_address'] ?? '') ?></textarea></div>
      <div class="field"><label class="f-label">PIN Code</label><input class="f-input" name="trust_pincode" maxlength="6" inputmode="numeric" value="<?= esc($editPrant['trust_pincode'] ?? '') ?>" placeholder="6-digit PIN code"></div>
      <div class="field"><label class="f-label">PAN</label><input class="f-input" name="trust_pan" maxlength="10" style="text-transform:uppercase" value="<?= esc($editPrant['trust_pan'] ?? '') ?>" placeholder="e.g. AAATV1234C"></div>
      <div class="field"><label class="f-label">Phone</label><input class="f-input" name="trust_phone" maxlength="10" inputmode="numeric" value="<?= esc($editPrant['trust_phone'] ?? '') ?>" placeholder="10-digit contact number"></div>
      <div class="field"><label class="f-label">Email</label><input class="f-input" type="email" name="trust_email" value="<?= esc($editPrant['trust_email'] ?? '') ?>" placeholder="trust@example.org"></div>
    </div>
    <div class="row-actions">
      <button type="submit" class="btn btn-primary btn-sm">Save</button>
      <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/settings/trust') ?>">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="panel" style="background:var(--surface-alt)">
  <div class="panel-head">
    <div>
      <div class="panel-title">Bulk upload Trust details</div>
      <div class="panel-note" style="margin-top:3px">Upload a spreadsheet to set the Trust name, address, PIN, PAN, phone and email for many Prants at once — rows are matched to an existing Prant by name, never create a new one.</div>
    </div>
    <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/settings/trust/bulk-import/template') ?>">Download template (.xlsx)</a>
  </div>
  <form action="<?= site_url('admin/settings/trust/bulk-import') ?>" method="post" enctype="multipart/form-data" class="row-actions" style="align-items:flex-end">
    <?= csrf_field() ?>
    <div class="field" style="margin:0;flex:1;min-width:240px">
      <label class="f-label">Spreadsheet file (.xlsx, .xls or .csv)</label>
      <input class="f-input" type="file" name="file" accept=".xlsx,.xls,.csv" required>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Import</button>
  </form>
</div>

<div class="panel">
  <div class="panel-head">
    <div>
      <div class="panel-title">Trust Details — by Prant</div>
      <div class="panel-note" style="margin-top:3px">Each Prant's own registered Trust — shown to donors on the public Contact Us page</div>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Prant</th><th>Registered Trust Name</th><th>PIN Code</th><th>PAN</th><th>Phone</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($prants as $p): ?>
        <?php $hasInfo = $hasAnyTrustInfo($p); ?>
        <tr>
          <td><?= esc($p['name']) ?></td>
          <td><?= esc($p['trust_name'] ?? '—') ?></td>
          <td class="num"><?= esc($p['trust_pincode'] ?? '—') ?></td>
          <td class="num masked"><?= esc($p['trust_pan'] ?? '—') ?></td>
          <td class="num"><?= esc($p['trust_phone'] ?? '—') ?></td>
          <td><span class="pill <?= status_pill_class($hasInfo ? 'configured' : 'pending') ?>"><?= $hasInfo ? 'Configured' : 'Pending' ?></span></td>
          <td><a class="btn btn-ghost btn-sm" href="?edit=<?= $p['id'] ?>"><?= $hasInfo ? 'Edit' : 'Configure' ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
