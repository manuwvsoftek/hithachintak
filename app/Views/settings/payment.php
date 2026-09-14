<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= $this->include('settings/_tabs') ?>

<?php $configured = count(array_filter($gateways, fn ($g) => $g['status'] === 'configured')); ?>
<div class="kpi-grid" style="margin-top:16px">
  <div class="kpi-card"><div class="kpi-label">Prants Configured</div><div class="kpi-value num"><?= $configured ?> / <?= count($gateways) ?></div></div>
  <div class="kpi-card"><div class="kpi-label">Pending Setup</div><div class="kpi-value num"><?= count($gateways) - $configured ?></div></div>
  <div class="kpi-card"><div class="kpi-label">Gateway Provider</div><div class="kpi-value" style="font-size:18px">Cashfree</div><div class="kpi-sub">One merchant account per Prant</div></div>
</div>

<?php if ($editPrant): ?>
<div class="panel">
  <div class="panel-title" style="margin-bottom:12px">Configure gateway — <?= esc($editPrant['name']) ?></div>
  <form action="<?= site_url('admin/settings/payment/' . $editPrant['id']) ?>" method="post">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field"><label class="f-label">Cashfree App ID</label><input class="f-input" name="app_id" placeholder="e.g. CF••••••••"></div>
      <div class="field"><label class="f-label">Cashfree Secret Key</label><input class="f-input" type="password" name="secret_key" placeholder="••••••••••••"></div>
      <div class="field"><label class="f-label">Merchant ID</label><input class="f-input" name="merchant_id" value="<?= esc($editRow['merchant_id'] ?? '') ?>"></div>
      <div class="field"><label class="f-label">Environment</label>
        <select class="f-input" name="environment">
          <option value="sandbox" <?= ($editRow['environment'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
          <option value="production" <?= ($editRow['environment'] ?? '') === 'production' ? 'selected' : '' ?>>Production</option>
        </select>
      </div>
    </div>
    <div class="row-actions">
      <button type="submit" class="btn btn-primary btn-sm">Save &amp; verify</button>
      <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/settings/payment') ?>">Cancel</a>
    </div>
  </form>

  <div class="field" style="margin:18px 0 0;padding-top:16px;border-top:1px solid var(--border-soft)">
    <label class="f-label">Webhook URL — paste this into Cashfree's dashboard for this merchant account</label>
    <div class="row-actions">
      <input class="f-input" id="webhook-url-<?= $editPrant['id'] ?>" value="<?= esc(base_url('webhooks/cashfree/' . $editPrant['id'])) ?>" readonly style="flex:1">
      <button type="button" class="btn btn-secondary btn-sm" data-copy-target="#webhook-url-<?= $editPrant['id'] ?>">Copy</button>
    </div>
    <p class="panel-note" style="margin-top:6px">Cashfree posts payment and Autopay events here — set it under Developers &rarr; Webhooks on <?= esc($editPrant['name']) ?>'s Cashfree account, or these payments will never be confirmed.</p>
  </div>
</div>
<?php endif; ?>

<div class="panel">
  <div class="panel-head">
    <div>
      <div class="panel-title">Payment Gateway — by Prant</div>
      <div class="panel-note" style="margin-top:3px">Each Prant settles to its own account; VHP holds the merchant agreement</div>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Prant</th><th>Provider</th><th>Merchant ID</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($gateways as $g): ?>
        <tr>
          <td><?= esc($g['prant']['name']) ?></td>
          <td>Cashfree</td>
          <td class="num masked"><?= esc($g['merchant'] ?? '—') ?></td>
          <td><span class="pill <?= status_pill_class($g['status']) ?>"><?= esc(ucfirst($g['status'])) ?></span></td>
          <td><a class="btn btn-ghost btn-sm" href="?edit=<?= $g['prant']['id'] ?>"><?= $g['status'] === 'configured' ? 'Edit' : 'Configure' ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
