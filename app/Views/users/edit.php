<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div><div class="page-title">Edit User</div></div>
  <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/users') ?>">Back</a>
</div>

<?php
  $locDepth = [
      \App\Entities\User::ROLE_PRANTA_ADMIN   => 0,
      \App\Entities\User::ROLE_SUB_ADMIN      => 1,
      \App\Entities\User::ROLE_PRAKHAND_ADMIN => 2,
      \App\Entities\User::ROLE_KARYAKARTA     => 2,
  ];
  $ownTier = [
      \App\Entities\User::ROLE_PRANTA_ADMIN   => 0,
      \App\Entities\User::ROLE_SUB_ADMIN      => 1,
      \App\Entities\User::ROLE_PRAKHAND_ADMIN => 2,
  ];
?>
<div class="panel" style="max-width:520px">
  <form action="<?= site_url('admin/users/' . $target->id) ?>" method="post" data-user-form novalidate
        data-jilas-url="<?= site_url('api/locations/jilas') ?>" data-prakhands-url="<?= site_url('api/locations/prakhands') ?>"
        data-actor-prant="<?= (int) ($actor->prant_id ?? 0) ?>" data-actor-jila="<?= (int) ($actor->jila_id ?? 0) ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="field"><label class="f-label">Full name</label><input class="f-input" name="name" value="<?= esc($target->name) ?>" required></div>
      <div class="field"><label class="f-label">Phone (login ID)</label><input class="f-input" name="phone" value="<?= esc($target->phone) ?>" inputmode="numeric" maxlength="10" required></div>
      <div class="field">
        <label class="f-label">Role</label>
        <select class="f-input" name="role" data-role-select required>
          <?php foreach ($manageableRoles as $r): ?>
            <option value="<?= esc($r) ?>" data-depth="<?= $locDepth[$r] ?? 2 ?>" data-own-tier="<?= $ownTier[$r] ?? '' ?>" <?= $target->role === $r ? 'selected' : '' ?>><?= esc($roleLabels[$r]) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($hasGlobalAccess): ?>
        <div class="field" data-loc-level="0" data-variant="single"><label class="f-label">Prant</label>
          <select class="f-input" name="prant_id" data-prant-select>
            <option value="">Select Prant…</option>
            <?php foreach ($prants as $p): ?>
              <option value="<?= $p['id'] ?>" <?= (int) $target->prant_id === (int) $p['id'] ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" data-loc-level="0" data-variant="multi" hidden>
          <label class="f-label">Prant(s)</label>
          <div class="check-list" data-prant-multi>
            <?php foreach ($prants as $p): ?>
              <label class="check-item"><input type="checkbox" name="prant_ids[]" value="<?= $p['id'] ?>" <?= in_array((int) $p['id'], $targetPrantIds, true) ? 'checked' : '' ?>> <?= esc($p['name']) ?></label>
            <?php endforeach; ?>
          </div>
          <label class="f-label" style="margin-top:6px;display:flex;align-items:center;gap:6px">
            <input type="checkbox" name="prant_scope_all" value="1" style="width:auto" data-prant-all <?= $target->scope_all && $target->role === 'pranta_admin' ? 'checked' : '' ?>> All Prants
          </label>
        </div>
        <div class="field" data-loc-level="1" data-variant="single"><label class="f-label">Jila</label>
          <select class="f-input" name="jila_id" data-jila-select>
            <option value="">—</option>
            <?php foreach ($jilas as $j): ?>
              <option value="<?= $j['id'] ?>" <?= (int) $target->jila_id === (int) $j['id'] ? 'selected' : '' ?>><?= esc($j['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" data-loc-level="1" data-variant="multi" hidden>
          <label class="f-label">Jila(s)</label>
          <div class="check-list" data-jila-multi>
            <?php foreach ($jilas as $j): ?>
              <label class="check-item"><input type="checkbox" name="jila_ids[]" value="<?= $j['id'] ?>" <?= in_array((int) $j['id'], $targetJilaIds, true) ? 'checked' : '' ?>> <?= esc($j['name']) ?></label>
            <?php endforeach; ?>
          </div>
          <label class="f-label" style="margin-top:6px;display:flex;align-items:center;gap:6px">
            <input type="checkbox" name="jila_scope_all" value="1" style="width:auto" data-jila-all <?= $target->scope_all && $target->role === 'sub_admin' ? 'checked' : '' ?>> All Jilas in this Prant
          </label>
        </div>
        <div class="field" data-loc-level="2" data-variant="single"><label class="f-label">Prakhand</label>
          <select class="f-input" name="prakhand_id" data-prakhand-select>
            <option value="">—</option>
            <?php foreach ($prakhands as $pr): ?>
              <option value="<?= $pr['id'] ?>" <?= (int) $target->prakhand_id === (int) $pr['id'] ? 'selected' : '' ?>><?= esc($pr['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" data-loc-level="2" data-variant="multi" hidden>
          <label class="f-label">Prakhand(s)</label>
          <div class="check-list" data-prakhand-multi>
            <?php foreach ($prakhands as $pr): ?>
              <label class="check-item"><input type="checkbox" name="prakhand_ids[]" value="<?= $pr['id'] ?>" <?= in_array((int) $pr['id'], $targetPrakhandIds, true) ? 'checked' : '' ?>> <?= esc($pr['name']) ?></label>
            <?php endforeach; ?>
          </div>
          <label class="f-label" style="margin-top:6px;display:flex;align-items:center;gap:6px">
            <input type="checkbox" name="prakhand_scope_all" value="1" style="width:auto" data-prakhand-all <?= $target->scope_all && $target->role === 'prakhand_admin' ? 'checked' : '' ?>> All Prakhands in this Jila
          </label>
        </div>
      <?php elseif (in_array($actor->role, ['pranta_admin'], true)): ?>
        <div class="field"><label class="f-label">Prant</label><input class="f-input" value="Your Prant (assigned automatically)" disabled></div>
        <div class="field" data-loc-level="1" data-variant="single"><label class="f-label">Jila</label>
          <select class="f-input" name="jila_id" data-jila-select>
            <option value="">—</option>
            <?php foreach ($jilas as $j): ?>
              <option value="<?= $j['id'] ?>" <?= (int) $target->jila_id === (int) $j['id'] ? 'selected' : '' ?>><?= esc($j['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" data-loc-level="1" data-variant="multi" hidden>
          <label class="f-label">Jila(s)</label>
          <div class="check-list" data-jila-multi>
            <?php foreach ($jilas as $j): ?>
              <label class="check-item"><input type="checkbox" name="jila_ids[]" value="<?= $j['id'] ?>" <?= in_array((int) $j['id'], $targetJilaIds, true) ? 'checked' : '' ?>> <?= esc($j['name']) ?></label>
            <?php endforeach; ?>
          </div>
          <label class="f-label" style="margin-top:6px;display:flex;align-items:center;gap:6px">
            <input type="checkbox" name="jila_scope_all" value="1" style="width:auto" data-jila-all <?= $target->scope_all && $target->role === 'sub_admin' ? 'checked' : '' ?>> All Jilas in your Prant
          </label>
        </div>
        <div class="field" data-loc-level="2" data-variant="single"><label class="f-label">Prakhand</label>
          <select class="f-input" name="prakhand_id" data-prakhand-select>
            <option value="">—</option>
            <?php foreach ($prakhands as $pr): ?>
              <option value="<?= $pr['id'] ?>" <?= (int) $target->prakhand_id === (int) $pr['id'] ? 'selected' : '' ?>><?= esc($pr['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" data-loc-level="2" data-variant="multi" hidden>
          <label class="f-label">Prakhand(s)</label>
          <div class="check-list" data-prakhand-multi>
            <?php foreach ($prakhands as $pr): ?>
              <label class="check-item"><input type="checkbox" name="prakhand_ids[]" value="<?= $pr['id'] ?>" <?= in_array((int) $pr['id'], $targetPrakhandIds, true) ? 'checked' : '' ?>> <?= esc($pr['name']) ?></label>
            <?php endforeach; ?>
          </div>
          <label class="f-label" style="margin-top:6px;display:flex;align-items:center;gap:6px">
            <input type="checkbox" name="prakhand_scope_all" value="1" style="width:auto" data-prakhand-all <?= $target->scope_all && $target->role === 'prakhand_admin' ? 'checked' : '' ?>> All Prakhands in this Jila
          </label>
        </div>
      <?php elseif ($actor->role === 'sub_admin'): ?>
        <div class="field"><label class="f-label">Prant &amp; Jila</label><input class="f-input" value="Your Jila (assigned automatically)" disabled></div>
        <div class="field" data-loc-level="2" data-variant="single"><label class="f-label">Prakhand</label>
          <select class="f-input" name="prakhand_id" data-prakhand-select>
            <option value="">—</option>
            <?php foreach ($prakhands as $pr): ?>
              <option value="<?= $pr['id'] ?>" <?= (int) $target->prakhand_id === (int) $pr['id'] ? 'selected' : '' ?>><?= esc($pr['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" data-loc-level="2" data-variant="multi" hidden>
          <label class="f-label">Prakhand(s)</label>
          <div class="check-list" data-prakhand-multi>
            <?php foreach ($prakhands as $pr): ?>
              <label class="check-item"><input type="checkbox" name="prakhand_ids[]" value="<?= $pr['id'] ?>" <?= in_array((int) $pr['id'], $targetPrakhandIds, true) ? 'checked' : '' ?>> <?= esc($pr['name']) ?></label>
            <?php endforeach; ?>
          </div>
          <label class="f-label" style="margin-top:6px;display:flex;align-items:center;gap:6px">
            <input type="checkbox" name="prakhand_scope_all" value="1" style="width:auto" data-prakhand-all <?= $target->scope_all && $target->role === 'prakhand_admin' ? 'checked' : '' ?>> All Prakhands in your Jila
          </label>
        </div>
      <?php else: ?>
        <div class="field"><label class="f-label">Location</label><input class="f-input" value="Your Prant, Jila &amp; Prakhand (assigned automatically)" disabled></div>
      <?php endif; ?>

      <div class="field"><label class="f-label">Address (optional)</label><input class="f-input" name="address" value="<?= esc($target->address ?? '') ?>" placeholder="Address"></div>
      <div class="field"><label class="f-label">ID — Aadhar number (optional)</label><input class="f-input" name="aadhar_number" value="<?= esc($target->aadhar_number ?? '') ?>" maxlength="20" placeholder="12-digit Aadhar number"></div>
      <div class="field"><label class="f-label">Email ID (optional)</label><input class="f-input" type="email" name="email" value="<?= esc($target->email ?? '') ?>" placeholder="name@example.org"></div>
      <div class="field"><label class="f-label">Profession (optional)</label><input class="f-input" name="profession" value="<?= esc($target->profession ?? '') ?>" placeholder="Occupation"></div>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Save changes</button>
  </form>
</div>

<?= $this->endSection() ?>
