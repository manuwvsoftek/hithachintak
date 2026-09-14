<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div>
    <div class="page-title">Users &amp; Hierarchy</div>
    <div class="page-desc">Manage user accounts across the Prant &rarr; Jila &rarr; Prakhand &rarr; Karyakarta hierarchy</div>
  </div>
</div>

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


<?php if (current_permission_level('Users & Hierarchy') === 'Edit' || current_permission_level('Users & Hierarchy') === 'Full'): ?>
<?php
  // How deep a role needs the location picker to go: 0 = Prant only,
  // 1 = + Jila, 2 = + Prakhand. Drives which fields the role select
  // shows/hides below (only meaningful for Super Admin, whose
  // manageable-role list spans every depth — every other actor's
  // manageable roles all need at least as much as their own fixed scope).
  $locDepth = [
      \App\Entities\User::ROLE_PRANTA_ADMIN   => 0,
      \App\Entities\User::ROLE_SUB_ADMIN      => 1,
      \App\Entities\User::ROLE_PRAKHAND_ADMIN => 2,
      \App\Entities\User::ROLE_KARYAKARTA     => 2,
  ];
  // Which location level is the role's OWN tier — that level gets the
  // multi-select + "All" toggle instead of a single picker. Karyakarta
  // has no own tier here (its location is always a single fixed value).
  $ownTier = [
      \App\Entities\User::ROLE_PRANTA_ADMIN   => 0,
      \App\Entities\User::ROLE_SUB_ADMIN      => 1,
      \App\Entities\User::ROLE_PRAKHAND_ADMIN => 2,
  ];
?>

<?php if (! empty($drafts)): ?>
<div class="panel" style="background:var(--surface-alt)">
  <div class="panel-head">
    <div>
      <div class="panel-title">Pending user drafts</div>
      <div class="panel-note" style="margin-top:3px">New user forms started but not yet submitted — autosaved as you type, resumable even after a refresh, an accidental close, or a network drop.</div>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Name</th><th>Role</th><th>Last saved</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($drafts as $d): ?>
        <tr>
          <td style="font-weight:700"><?= esc($d['name'] ?: '(no name yet)') ?></td>
          <td><?= esc($roleLabels[$d['role']] ?? $d['role'] ?? '—') ?></td>
          <td><?= esc(date('d M Y, g:i a', strtotime($d['updated_at']))) ?></td>
          <td class="row-actions">
            <a class="btn btn-primary btn-sm" href="<?= site_url('admin/users') ?>?resume=<?= esc($d['id'], 'url') ?>#new-user-form">Resume</a>
            <form action="<?= site_url('admin/users/draft/' . $d['id'] . '/delete') ?>" method="post" style="margin:0">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Discard this draft? This can't be undone.">Discard</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="panel" id="new-user-form">
  <div class="panel-head">
    <div class="panel-title" style="margin-bottom:0">New user</div>
    <span class="panel-note" id="userAutosaveNote"></span>
  </div>
  <form action="<?= site_url('admin/users') ?>" method="post" data-user-form novalidate
        data-jilas-url="<?= site_url('api/locations/jilas') ?>" data-prakhands-url="<?= site_url('api/locations/prakhands') ?>"
        data-actor-prant="<?= (int) ($actor->prant_id ?? 0) ?>" data-actor-jila="<?= (int) ($actor->jila_id ?? 0) ?>"
        data-autosave-url="<?= site_url('admin/users/draft') ?>" data-csrf-name="<?= csrf_token() ?>"
        data-resume-draft-id="<?= esc($resumeDraftId ?? '') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="draft_id" value="<?= esc($resumeDraftId ?? '') ?>">
    <div class="form-grid">
      <div class="field"><label class="f-label">Full name</label><input class="f-input" name="name" placeholder="e.g. Ramesh Iyer" required></div>
      <div class="field"><label class="f-label">Phone (login ID)</label><input class="f-input" name="phone" inputmode="numeric" maxlength="10" placeholder="10-digit mobile" required></div>
      <div class="field">
        <label class="f-label">Role</label>
        <select class="f-input" name="role" data-role-select required>
          <?php foreach ($manageableRoles as $r): ?>
            <option value="<?= esc($r) ?>" data-depth="<?= $locDepth[$r] ?? 2 ?>" data-own-tier="<?= $ownTier[$r] ?? '' ?>" <?= $r === 'karyakarta' ? 'selected' : '' ?>><?= esc($roleLabels[$r]) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($hasGlobalAccess): ?>
        <div class="field" data-loc-level="0" data-variant="single"><label class="f-label">Prant</label>
          <select class="f-input" name="prant_id" data-prant-select>
            <option value="">Select Prant…</option>
            <?php foreach ($prants as $p): ?><option value="<?= $p['id'] ?>"><?= esc($p['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field" data-loc-level="0" data-variant="multi" hidden>
          <label class="f-label">Prant(s)</label>
          <div class="check-list" data-prant-multi>
            <?php foreach ($prants as $p): ?>
              <label class="check-item"><input type="checkbox" name="prant_ids[]" value="<?= $p['id'] ?>"> <?= esc($p['name']) ?></label>
            <?php endforeach; ?>
          </div>
          <label class="f-label" style="margin-top:6px;display:flex;align-items:center;gap:6px">
            <input type="checkbox" name="prant_scope_all" value="1" style="width:auto" data-prant-all> All Prants
          </label>
        </div>
        <div class="field" data-loc-level="1" data-variant="single"><label class="f-label">Jila</label>
          <select class="f-input" name="jila_id" data-jila-select><option value="">—</option></select>
        </div>
        <div class="field" data-loc-level="1" data-variant="multi" hidden>
          <label class="f-label">Jila(s)</label>
          <div class="check-list" data-jila-multi></div>
          <label class="f-label" style="margin-top:6px;display:flex;align-items:center;gap:6px">
            <input type="checkbox" name="jila_scope_all" value="1" style="width:auto" data-jila-all> All Jilas in this Prant
          </label>
        </div>
        <div class="field" data-loc-level="2" data-variant="single"><label class="f-label">Prakhand</label>
          <select class="f-input" name="prakhand_id" data-prakhand-select><option value="">—</option></select>
        </div>
        <div class="field" data-loc-level="2" data-variant="multi" hidden>
          <label class="f-label">Prakhand(s)</label>
          <div class="check-list" data-prakhand-multi></div>
          <label class="f-label" style="margin-top:6px;display:flex;align-items:center;gap:6px">
            <input type="checkbox" name="prakhand_scope_all" value="1" style="width:auto" data-prakhand-all> All Prakhands in this Jila
          </label>
        </div>
      <?php elseif (in_array($actor->role, ['pranta_admin'], true)): ?>
        <div class="field"><label class="f-label">Prant</label><input class="f-input" value="Your Prant (assigned automatically)" disabled></div>
        <div class="field" data-loc-level="1" data-variant="single"><label class="f-label">Jila</label>
          <select class="f-input" name="jila_id" data-jila-select><option value="">—</option></select>
        </div>
        <div class="field" data-loc-level="1" data-variant="multi" hidden>
          <label class="f-label">Jila(s)</label>
          <div class="check-list" data-jila-multi></div>
          <label class="f-label" style="margin-top:6px;display:flex;align-items:center;gap:6px">
            <input type="checkbox" name="jila_scope_all" value="1" style="width:auto" data-jila-all> All Jilas in your Prant
          </label>
        </div>
        <div class="field" data-loc-level="2" data-variant="single"><label class="f-label">Prakhand</label>
          <select class="f-input" name="prakhand_id" data-prakhand-select><option value="">—</option></select>
        </div>
        <div class="field" data-loc-level="2" data-variant="multi" hidden>
          <label class="f-label">Prakhand(s)</label>
          <div class="check-list" data-prakhand-multi></div>
          <label class="f-label" style="margin-top:6px;display:flex;align-items:center;gap:6px">
            <input type="checkbox" name="prakhand_scope_all" value="1" style="width:auto" data-prakhand-all> All Prakhands in this Jila
          </label>
        </div>
      <?php elseif ($actor->role === 'sub_admin'): ?>
        <div class="field"><label class="f-label">Prant &amp; Jila</label><input class="f-input" value="Your Jila (assigned automatically)" disabled></div>
        <div class="field" data-loc-level="2" data-variant="single"><label class="f-label">Prakhand</label>
          <select class="f-input" name="prakhand_id" data-prakhand-select><option value="">—</option></select>
        </div>
        <div class="field" data-loc-level="2" data-variant="multi" hidden>
          <label class="f-label">Prakhand(s)</label>
          <div class="check-list" data-prakhand-multi></div>
          <label class="f-label" style="margin-top:6px;display:flex;align-items:center;gap:6px">
            <input type="checkbox" name="prakhand_scope_all" value="1" style="width:auto" data-prakhand-all> All Prakhands in your Jila
          </label>
        </div>
      <?php else: ?>
        <div class="field"><label class="f-label">Location</label><input class="f-input" value="Your Prant, Jila &amp; Prakhand (assigned automatically)" disabled></div>
      <?php endif; ?>

      <div class="field"><label class="f-label">Password (optional)</label><input class="f-input" name="password" minlength="6" placeholder="Leave blank to generate one automatically"></div>
      <div class="field"><label class="f-label">Address (optional)</label><input class="f-input" name="address" placeholder="Address"></div>
      <div class="field"><label class="f-label">ID — Aadhar number (optional)</label><input class="f-input" name="aadhar_number" maxlength="20" placeholder="12-digit Aadhar number"></div>
      <div class="field"><label class="f-label">Email ID (optional)</label><input class="f-input" type="email" name="email" placeholder="name@example.org"></div>
      <div class="field"><label class="f-label">Profession (optional)</label><input class="f-input" name="profession" placeholder="Occupation"></div>
    </div>
    <p class="panel-note" style="margin-bottom:10px">Whatever password is set here (typed or generated) can be viewed again later from this account's "Password" action.</p>
    <button type="submit" class="btn btn-primary btn-sm">Create Account</button>
  </form>
</div>
<?php if ($resumeDraft): ?>
  <script <?= csp_script_nonce() ?>>window.VHP_USER_RESUME_DRAFT = <?= json_encode($resumeDraft) ?>;</script>
<?php endif; ?>
<?php endif; ?>

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
      <thead><tr><th>Name</th><th>Phone</th><th>Role</th><th>Prakhand</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= esc($u->name) ?></td>
          <td class="num"><?= esc(mask_phone($u->phone)) ?></td>
          <td><?= esc($roleLabels[$u->role] ?? $u->role) ?></td>
          <td><?= esc($u->prakhand_name ?? '—') ?></td>
          <td><span class="pill <?= status_pill_class($u->status) ?>"><?= esc(ucfirst($u->status)) ?></span></td>
          <td>
            <?php if ($u->id !== $currentUserId && $actor->outranks($u->role)): ?>
            <div class="row-actions">
              <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/users/' . $u->id . '/edit') ?>">Edit</a>
              <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/users/' . $u->id . '/password') ?>">Password</a>
              <form action="<?= site_url('admin/users/' . $u->id . '/block') ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm <?= $u->status === 'blocked' ? 'btn-success' : 'btn-danger' ?>">
                  <?= $u->status === 'blocked' ? 'Unblock' : 'Block' ?>
                </button>
              </form>
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
</div>

<?= $this->endSection() ?>
