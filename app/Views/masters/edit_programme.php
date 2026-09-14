<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div><div class="page-title">Edit Programme</div></div>
  <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/masters?tab=programmes') ?>">Back</a>
</div>

<div class="panel" style="max-width:420px">
  <form action="<?= site_url('admin/masters/programmes/' . $programme['id']) ?>" method="post">
    <?= csrf_field() ?>
    <div class="field">
      <label class="f-label">Name</label>
      <input class="f-input" name="name" value="<?= esc($programme['name']) ?>" required>
    </div>
    <div class="field">
      <label class="f-label">Amount rule</label>
      <select class="f-input" name="mode" id="modeSelect">
        <option value="min" <?= $programme['mode'] === 'min' ? 'selected' : '' ?>>Minimum amount per person</option>
        <option value="open" <?= $programme['mode'] === 'open' ? 'selected' : '' ?>>Open — entered at enrolment</option>
      </select>
    </div>
    <div class="field" id="rateField" style="<?= $programme['mode'] === 'min' ? '' : 'display:none' ?>">
      <label class="f-label">Minimum amount (₹)</label>
      <input class="f-input" type="number" name="rate" value="<?= esc($programme['rate'] ?? '') ?>">
    </div>
    <div class="field" style="flex-direction:row;align-items:center;gap:8px">
      <input type="checkbox" name="is_recurring" id="isRecurring" value="1" style="width:auto" <?= $programme['is_recurring'] ? 'checked' : '' ?>>
      <label class="f-label" for="isRecurring" style="margin:0">Recurring — collected via Cashfree Autopay monthly, not one-off</label>
    </div>
    <div class="field">
      <label class="f-label">Status</label>
      <select class="f-input" name="status">
        <option value="active" <?= $programme['status'] === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $programme['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Save</button>
  </form>
</div>

<script <?= csp_script_nonce() ?>>
document.getElementById('modeSelect').addEventListener('change', function(){
  document.getElementById('rateField').style.display = this.value === 'min' ? '' : 'none';
});
</script>

<?= $this->endSection() ?>
