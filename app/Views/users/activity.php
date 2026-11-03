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
      <div class="panel-note" style="margin-top:3px">Each row's Hithachintak count is that account's own receipted Hithachintak enrolments — not this account's own. Filter by Prant/Jila/Prakhand to report on a specific location.</div>
    </div>
  </div>
    <form class="filter-row" method="get" id="activityLocationFilter">
    <select name="prant_id" id="filterPrant" class="js-auto-submit">
      <option value="">All Prants</option>
      <?php foreach ($prants as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $filterPrantId === (int) $p['id'] ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="jila_id" id="filterJila" class="js-auto-submit">
      <option value="">All Jilas</option>
      <?php foreach ($jilas as $j): ?>
        <option value="<?= $j['id'] ?>" <?= $filterJilaId === (int) $j['id'] ? 'selected' : '' ?>><?= esc($j['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="prakhand_id" id="filterPrakhand" class="js-auto-submit">
      <option value="">All Prakhands</option>
      <?php foreach ($prakhands as $pk): ?>
        <option value="<?= $pk['id'] ?>" <?= $filterPrakhandId === (int) $pk['id'] ? 'selected' : '' ?>><?= esc($pk['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($filterPrantId || $filterJilaId || $filterPrakhandId): ?>
      <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/users/' . $target->id . '/activity') ?>">Clear filters</a>
    <?php endif; ?>
  </form>
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
       <tr><td colspan="6" class="panel-note"><?= ($filterPrantId || $filterJilaId || $filterPrakhandId) ? 'No created users match this location filter.' : 'This account hasn\'t created any other users.' ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script <?= csp_script_nonce() ?>>
(function(){
  // A changed Prant/Jila invalidates whatever was picked downstream —
  // clearing it before the auto-submit (see app.js's .js-auto-submit)
  // keeps a stale Jila/Prakhand id from riding along with the new Prant.
  var prant = document.getElementById('filterPrant');
  var jila = document.getElementById('filterJila');
  var prakhand = document.getElementById('filterPrakhand');
  if (prant) {
    prant.addEventListener('change', function(){ jila.value = ''; prakhand.value = ''; });
  }
  if (jila) {
    jila.addEventListener('change', function(){ prakhand.value = ''; });
  }
})();
</script>

<?= $this->endSection() ?>
