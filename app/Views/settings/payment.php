<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= $this->include('settings/_tabs') ?>

<?php
  $configured = count(array_filter($gateways, fn ($g) => $g['status'] === 'configured'));
  $enabled    = count(array_filter($gateways, fn ($g) => $g['enabled']));
  $providerLabels = ['cashfree' => 'Cashfree', 'razorpay' => 'Razorpay'];
?>
<div class="kpi-grid" style="margin-top:16px">
  <div class="kpi-card"><div class="kpi-label">Gateways Configured</div><div class="kpi-value num"><?= $configured ?> / <?= count($gateways) ?></div></div>
  <div class="kpi-card"><div class="kpi-label">Gateways Enabled</div><div class="kpi-value num"><?= $enabled ?></div></div>
  <div class="kpi-card"><div class="kpi-label">Providers</div><div class="kpi-value" style="font-size:18px">Cashfree + Razorpay</div><div class="kpi-sub">One merchant account per Prant per provider</div></div>
</div>

<?php if ($editPrant): ?>
<div class="panel">
  <div class="panel-title" style="margin-bottom:12px">Configure <?= esc($providerLabels[$editProvider]) ?> — <?= esc($editPrant['name']) ?></div>

  <div class="row-actions" style="margin-bottom:14px">
    <?php foreach ($providers as $p): ?>
      <a class="btn btn-sm <?= $p === $editProvider ? 'btn-primary' : 'btn-ghost' ?>" href="?edit=<?= $editPrant['id'] ?>&provider=<?= $p ?>"><?= esc($providerLabels[$p]) ?></a>
    <?php endforeach; ?>
  </div>

  <form action="<?= site_url('admin/settings/payment/' . $editPrant['id']) ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="provider" value="<?= esc($editProvider) ?>">
    <div class="form-grid">
      <?php if ($editProvider === 'razorpay'): ?>
        <div class="field"><label class="f-label">Razorpay Key ID</label><input class="f-input" name="app_id" placeholder="e.g. rzp_live_••••••••"></div>
        <div class="field"><label class="f-label">Razorpay Key Secret</label><input class="f-input" type="password" name="secret_key" placeholder="••••••••••••"></div>
      <?php else: ?>
        <div class="field"><label class="f-label">Cashfree App ID</label><input class="f-input" name="app_id" placeholder="e.g. CF••••••••"></div>
        <div class="field"><label class="f-label">Cashfree Secret Key</label><input class="f-input" type="password" name="secret_key" placeholder="••••••••••••"></div>
      <?php endif; ?>
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
    <label class="f-label">Webhook URL — paste this into <?= esc($providerLabels[$editProvider]) ?>'s dashboard for this merchant account</label>
    <div class="row-actions">
      <input class="f-input" id="webhook-url-<?= $editPrant['id'] ?>-<?= $editProvider ?>" value="<?= esc(base_url('webhooks/' . $editProvider . '/' . $editPrant['id'])) ?>" readonly style="flex:1">
      <button type="button" class="btn btn-secondary btn-sm" data-copy-target="#webhook-url-<?= $editPrant['id'] ?>-<?= $editProvider ?>">Copy</button>
    </div>
    <p class="panel-note" style="margin-top:6px">
      <?= esc($providerLabels[$editProvider]) ?> posts payment<?= $editProvider === 'razorpay' ? '' : ' and Autopay' ?> events here — set it under
      <?= $editProvider === 'razorpay' ? 'Account &amp; Settings &rarr; Webhooks' : 'Developers &rarr; Webhooks' ?>
      on <?= esc($editPrant['name']) ?>'s <?= esc($providerLabels[$editProvider]) ?> account, or these payments will never be confirmed.
    </p>
  </div>
</div>
<?php endif; ?>

<div class="panel">
  <div class="panel-head">
    <div>
      <div class="panel-title">Payment Gateways — by Prant</div>
      <div class="panel-note" style="margin-top:3px">Each Prant settles to its own account; VHP holds the merchant agreement. A Prant with both gateways enabled lets the payer choose which one to use at checkout.</div>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Prant</th><th>Provider</th><th>Merchant ID</th><th>Status</th><th>Enabled</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($gateways as $g): ?>
        <tr>
          <td><?= esc($g['prant']['name']) ?></td>
          <td><?= esc($providerLabels[$g['provider']]) ?></td>
          <td class="num masked"><?= esc($g['merchant'] ?? '—') ?></td>
          <td><span class="pill <?= status_pill_class($g['status']) ?>"><?= esc(ucfirst($g['status'])) ?></span></td>
          <td>
            <?php if ($g['status'] === 'configured'): ?>
              <form action="<?= site_url('admin/settings/payment/' . $g['prant']['id'] . '/toggle') ?>" method="post" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="provider" value="<?= esc($g['provider']) ?>">
                <input type="hidden" name="enabled" value="<?= $g['enabled'] ? '0' : '1' ?>">
                <button type="submit" class="btn btn-sm <?= $g['enabled'] ? 'btn-success' : 'btn-secondary' ?>"><?= $g['enabled'] ? 'Enabled' : 'Disabled' ?></button>
              </form>
            <?php else: ?>
              <span class="pill">Not configured</span>
            <?php endif; ?>
          </td>
          <td><a class="btn btn-ghost btn-sm" href="?edit=<?= $g['prant']['id'] ?>&provider=<?= $g['provider'] ?>"><?= $g['status'] === 'configured' ? 'Edit' : 'Configure' ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
