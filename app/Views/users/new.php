<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?= $this->include('users/_tabs') ?>

<?php
  // How deep a role needs the location picker to go: 0 = Prant only,
  // 1 = + Jila, 2 = + Prakhand. Drives which fields the role select
  // shows/hides below (only meaningful for Super Admin, whose
  // manageable-role list spans every depth — every other actor's
  // manageable roles all need at least as much as their own fixed scope).
  // Kendreeya Admin is assigned a set of Prants, same tier/depth as
  // Pranta Admin — not a new location tier of its own (see
  // User::ROLE_RANK's comment), so it slots into this exact same
  // depth/own-tier shape, one role above the one it outranks.
  $locDepth = [
      \App\Entities\User::ROLE_KENDREEYA_ADMIN => 0,
      \App\Entities\User::ROLE_PRANTA_ADMIN    => 0,
      \App\Entities\User::ROLE_SUB_ADMIN       => 1,
      \App\Entities\User::ROLE_PRAKHAND_ADMIN  => 2,
      \App\Entities\User::ROLE_KARYAKARTA      => 2,
  ];
  // Which location level is the role's OWN tier — that level gets the
  // multi-select + "All" toggle instead of a single picker. Karyakarta
  // has no own tier here (its location is always a single fixed value).
  $ownTier = [
      \App\Entities\User::ROLE_KENDREEYA_ADMIN => 0,
      \App\Entities\User::ROLE_PRANTA_ADMIN    => 0,
      \App\Entities\User::ROLE_SUB_ADMIN       => 1,
      \App\Entities\User::ROLE_PRAKHAND_ADMIN  => 2,
  ];
?>

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
      <?php elseif (in_array($actor->role, ['pranta_admin', 'kendreeya_admin'], true)): ?>
        <?php if ($actorPrants !== null): ?>
          <div class="field"><label class="f-label">Prant</label>
            <select class="f-input" name="prant_id" data-prant-select>
              <option value="">Select Prant…</option>
              <?php foreach ($actorPrants as $p): ?><option value="<?= $p['id'] ?>"><?= esc($p['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        <?php else: ?>
          <div class="field"><label class="f-label">Prant</label><input class="f-input" value="Your Prant (assigned automatically)" disabled></div>
        <?php endif; ?>
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
      <div class="field" data-karyakarta-field hidden>
        <label class="f-label">DOB <span class="required-mark">*</span></label>
        <input type="date" class="f-input" id="dob" name="dob" disabled>
      </div>
      <div class="field" data-karyakarta-field hidden>
        <label class="f-label">Ayam <span class="required-mark">*</span></label>
        <select class="f-input" id="ayamSelect" name="ayam" disabled>
          <option value="">— Ayam —</option>
          <?php foreach ($ayams as $a): ?>
            <option value="<?= esc($a) ?>"><?= esc($a) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="f-label">Gender</label>
        <select class="f-input" id="genderSelect" name="gender" required>
          <option value="Male">Male</option>
          <option value="Female">Female</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <div class="field"><label class="f-label">Password</label><input class="f-input" name="password" minlength="6" placeholder="Leave blank to generate one automatically"></div>
      <div class="field"><label class="f-label">Address </label><input class="f-input" name="address" placeholder="Address"></div>
      <div class="field"><label class="f-label">ID — Aadhar number </label><input class="f-input" name="aadhar_number" maxlength="20" placeholder="12-digit Aadhar number"></div>
      <div class="field"><label class="f-label">Email ID </label><input class="f-input" type="email" name="email" placeholder="name@example.org"></div>
      <div class="field"><label class="f-label">Profession </label><input class="f-input" name="profession" placeholder="Occupation"></div>
    </div>
    <p class="panel-note" style="margin-bottom:10px">Whatever password is set here (typed or generated) can be viewed again later from this account's "Password" action.</p>
    <button type="submit" class="btn btn-primary btn-sm">Create Account</button>
  </form>
</div>
<?php if ($resumeDraft): ?>
  <script <?= csp_script_nonce() ?>>window.VHP_USER_RESUME_DRAFT = <?= json_encode($resumeDraft) ?>;</script>
<?php endif; ?>

<?= $this->endSection() ?>
