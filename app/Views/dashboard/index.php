<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div>
    <div class="page-title">Dashboard</div>
    <div class="page-desc">
      Your scope: <?= esc(session('user_prant_id') ? 'your Prant & below' : 'All India') ?>
    </div>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-label">Total Enrolments</div>
    <div class="kpi-value num"><?= number_format($totalEnrolments) ?></div>
    <div class="kpi-sub"><?= number_format($thisWeek) ?> this week</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label">Amount Collected</div>
    <div class="kpi-value num"><?= fmt_rupees($collected) ?></div>
    <div class="kpi-sub">Across all programmes</div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label">Active Karyakartas</div>
    <div class="kpi-value num"><?= number_format($activeKaryakartas) ?></div>
  </div>
</div>

<div class="grid-2">
  <div class="panel">
    <div class="panel-head">
      <div class="panel-title">Enrolments by Programme</div>
      <span class="panel-note">This month</span>
    </div>
    <?php if (! $byProgramme): ?>
      <p class="panel-note">No enrolments yet this month.</p>
    <?php endif; ?>
    <div class="kv-list">
      <?php foreach ($byProgramme as $p): ?>
        <div class="kv-row">
          <span><?= esc($p['name']) ?></span><span class="num" style="font-weight:700"><?= number_format($p['cnt']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="panel">
    <div class="panel-head">
      <div class="panel-title">Collection by Mode</div>
      <span class="panel-note">This month</span>
    </div>
    <?php if (! $byMode): ?>
      <p class="panel-note">No collections yet this month.</p>
    <?php endif; ?>
    <div class="kv-list">
      <?php foreach ($byMode as $m): ?>
        <div class="kv-row">
          <span><?= esc(ucfirst($m['mode'])) ?></span><span class="num" style="font-weight:700"><?= fmt_rupees($m['total']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-head">
    <div class="panel-title">Recent Enrolments</div>
    <?php if (current_permission_level('Enrolments') !== 'None'): ?>
      <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/enrolments') ?>">View all</a>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Member</th><th>Programme</th><th>Location</th><th>Karyakarta</th><th>Amount</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $e): ?>
        <tr>
          <td><?= esc($e->member_name) ?></td>
          <td><?= esc($e->programme_name) ?></td>
          <td><?= esc(trim(($e->jila_name ?? '') . ' · ' . ($e->prakhand_name ?? ''), ' ·')) ?></td>
          <td><?= esc($e->karyakarta_name) ?></td>
          <td class="num"><?= fmt_rupees($e->amount) ?></td>
          <td><span class="pill <?= status_pill_class($e->status) ?>"><?= esc(ucwords(str_replace('_', ' ', $e->status))) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (! $recent): ?>
        <tr><td colspan="6" class="panel-note">No enrolments recorded yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
