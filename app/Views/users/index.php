<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= $this->include('users/_tabs') ?>


<div class="panel">
  <div class="panel-head"><div class="panel-title">Access Hierarchy</div><span class="panel-note">Count of active users per level<?= ($filterPrantId || $filterJilaId || $filterPrakhandId || ($_GET['q'] ?? '')) ? ' (filtered)' : '' ?></span></div>
  <div class="kpi-grid">
    <?php foreach ($hierarchy as $h): ?>
      <div class="kpi-card">
        <div class="kpi-label"><?= esc($h['role']) ?></div>
        <div class="kpi-value num"><?= number_format($h['count']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <div class="panel-head">
    <div>
      <div class="panel-title">User Directory</div>
      <div class="panel-note" style="margin-top:3px">Blocking an account restricts login access only — no enrolment, collection or receipt data is deleted.</div>
    </div>
  </div>
  <form class="filter-row" method="get" id="userDirectoryFilter">
    <select name="role" class="js-auto-submit">
      <option value="">All roles</option>
      <?php foreach ($roleLabels as $code => $label): ?>
        <option value="<?= $code ?>" <?= ($_GET['role'] ?? '') === $code ? 'selected' : '' ?>><?= esc($label) ?></option>
      <?php endforeach; ?>
    </select>
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
    <input type="text" name="q" placeholder="Search name or phone" value="<?= esc($_GET['q'] ?? '') ?>">
    <button type="submit" class="btn btn-secondary btn-sm">Search</button>
    <?php if ($filterPrantId || $filterJilaId || $filterPrakhandId): ?>
      <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/users') ?>">Clear filters</a>
    <?php endif; ?>
  </form>

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
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Phone</th><th>Role</th><th>Location</th><th>Created By</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= esc($u->name) ?></td>
          <td class="num"><?= esc(mask_phone($u->phone)) ?></td>
          <td><?= esc($roleLabels[$u->role] ?? $u->role) ?></td>
          <td><?= esc($locationLabels[$u->id] ?? '—') ?></td>
          <td><?= esc($u->creator_name ?? '—') ?></td>
          <td><span class="pill <?= status_pill_class($u->status) ?>"><?= esc(ucfirst($u->status)) ?></span></td>
          <td>
            <?php if ($u->id !== $currentUserId && $actor->outranks($u->role)): ?>
            <?php
              // Edit/Block are narrowed to the account's own creator for
              // every non-globally-scoped role — see
              // UsersController::isOwnCreation(). Everyone in scope can
              // still see this row and look up its password; only the
              // admin who actually created it can change or block it.
             // $canManage = $actor->hasGlobalAccess() || (int) $u->created_by === (int) $actor->id;
             $canManage = $canManageMap[$u->id] ?? false;
            ?>
            <div class="row-actions">
              <?php if ($canManage): ?>
              <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/users/' . $u->id . '/edit') ?>">Edit</a>
              <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/users/' . $u->id . '/password') ?>">Password</a>
              <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/users/' . $u->id . '/activity') ?>">Activity</a>
              <form action="<?= site_url('admin/users/' . $u->id . '/block') ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm <?= $u->status === 'blocked' ? 'btn-success' : 'btn-danger' ?>">
                  <?= $u->status === 'blocked' ? 'Unblock' : 'Block' ?>
                </button>
              </form>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (! $users): ?>
        <tr><td colspan="6" class="panel-note">No users match these filters.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
   <div style="margin-top:14px"><?= $pager->links() ?></div>
</div>







<?= $this->endSection() ?>
