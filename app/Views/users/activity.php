<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div><div class="page-title">Activity — <?= esc($target->name) ?></div></div>
  <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/users') ?>">Back</a>
</div>

<div class="panel">
  <div class="panel-head"><div class="panel-title">Summary</div></div>
  <div class="kpi-grid">
    <div class="kpi-card">
      <div class="kpi-label">Users Created</div>
      <div class="kpi-value num"><?= number_format(count($createdUsers)) ?></div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Hithachintak by Created Users</div>
      <div class="kpi-value num"><?= number_format($totalHc) ?></div>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-head">
    <div>
      <div class="panel-title">Users Created by <?= esc($target->name) ?></div>
      <div class="panel-note" style="margin-top:3px">Each row's Hithachintak count is that account's own receipted Hithachintak enrolments — not this account's own.</div>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Phone</th><th>Role</th><th>Status</th><th>Hithachintak Done</th></tr></thead>
      <tbody>
      <?php foreach ($createdUsers as $u): ?>
        <tr>
          <td><?= esc($u->name) ?></td>
          <td class="num"><?= esc(mask_phone($u->phone)) ?></td>
          <td><?= esc($roleLabels[$u->role] ?? $u->role) ?></td>
          <td><span class="pill <?= status_pill_class($u->status) ?>"><?= esc(ucfirst($u->status)) ?></span></td>
          <td class="num"><?= number_format($hcCountByUser[$u->id] ?? 0) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (! $createdUsers): ?>
        <tr><td colspan="5" class="panel-note">This account hasn't created any other users.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
