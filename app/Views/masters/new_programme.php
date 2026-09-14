<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div><div class="page-title">New Programme</div></div>
  <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/masters?tab=programmes') ?>">Back</a>
</div>

<div class="panel" style="max-width:420px">
  <form action="<?= site_url('admin/masters/programmes') ?>" method="post">
    <?= csrf_field() ?>
    <div class="field">
      <label class="f-label">Code</label>
      <input class="f-input" name="code" placeholder="e.g. seva" maxlength="10" required>
    </div>
    <div class="field">
      <label class="f-label">Name</label>
      <input class="f-input" name="name" placeholder="e.g. Seva Nidhi" required>
    </div>
    <div class="field">
      <label class="f-label">Amount rule</label>
      <select class="f-input" name="mode" id="modeSelect">
        <option value="open">Open — entered at enrolment</option>
        <option value="min">Minimum amount per person</option>
      </select>
    </div>
    <div class="field" id="rateField" style="display:none">
      <label class="f-label">Minimum amount (₹)</label>
      <input class="f-input" type="number" name="rate">
    </div>
    <div class="field" style="flex-direction:row;align-items:center;gap:8px">
      <input type="checkbox" name="is_recurring" id="isRecurring" value="1" style="width:auto">
      <label class="f-label" for="isRecurring" style="margin:0">Recurring — collected via Cashfree Autopay monthly, not one-off</label>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Create</button>
  </form>
</div>

<script <?= csp_script_nonce() ?>>
document.getElementById('modeSelect').addEventListener('change', function(){
  document.getElementById('rateField').style.display = this.value === 'min' ? '' : 'none';
});
</script>

<?= $this->endSection() ?>
