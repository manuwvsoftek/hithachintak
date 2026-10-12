<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= $this->include('users/_tabs') ?>


<div class="panel">
  <div class="panel-head"><div class="panel-title">Access Hierarchy</div><span class="panel-note">Count of active users per level</span></div>
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
  <form class="filter-row" method="get">
    <select name="role" class="js-auto-submit">
      <option value="">All roles</option>
      <?php foreach ($roleLabels as $code => $label): ?>
        <option value="<?= $code ?>" <?= ($_GET['role'] ?? '') === $code ? 'selected' : '' ?>><?= esc($label) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="q" placeholder="Search name or phone" value="<?= esc($_GET['q'] ?? '') ?>">
    <button type="submit" class="btn btn-secondary btn-sm">Search</button>
  </form>
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
