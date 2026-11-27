<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div>
    <div class="page-title">Manual Payment Reconciliation</div>
    <div class="page-desc">Only for a payment that genuinely succeeded at the gateway but whose webhook never reached this platform — confirm it in the Cashfree/Razorpay dashboard first.</div>
  </div>
</div>

<div class="panel" style="max-width:640px">
  <div class="panel-head">
    <div class="panel-title">Enrolment #<?= $enrolment->id ?></div>
  </div>

  <div class="kv-list" style="margin-bottom:20px">
    <div class="kv-row"><span>Member</span><span style="font-weight:700"><?= esc($member['name'] ?? '—') ?></span></div>
    <div class="kv-row"><span>Phone</span><span class="num"><?= esc($member['phone'] ?? '—') ?></span></div>
    <div class="kv-row"><span>Programme</span><span><?= esc($programme['name'] ?? '—') ?></span></div>
    <div class="kv-row"><span>Amount</span><span class="num" style="font-weight:700"><?= fmt_rupees($enrolment->amount) ?></span></div>
    <?php if ($enrolment->cashfree_order_id): ?>
      <div class="kv-row"><span>Cashfree Order ID</span><span class="num"><?= esc($enrolment->cashfree_order_id) ?></span></div>
    <?php endif; ?>
    <?php if ($enrolment->razorpay_order_id): ?>
      <div class="kv-row"><span>Razorpay Order ID</span><span class="num"><?= esc($enrolment->razorpay_order_id) ?></span></div>
    <?php endif; ?>
    <div class="kv-row"><span>Current Status</span><span><span class="pill <?= status_pill_class($enrolment->status) ?>"><?= esc(ucwords(str_replace('_', ' ', $enrolment->status))) ?></span></span></div>
  </div>

  <form method="post" action="<?= site_url('admin/enrolments/' . $enrolment->id . '/manual-reconcile') ?>">
    <?= csrf_field() ?>

    <div class="field" style="margin-bottom:14px">
      <label style="display:block;font-weight:700;margin-bottom:6px">Gateway that actually processed this payment</label>
      <select name="gateway" class="f-input" required>
        <option value="">Select gateway…</option>
        <option value="cashfree" <?= $enrolment->cashfree_order_id ? 'selected' : '' ?>>Cashfree</option>
        <option value="razorpay" <?= $enrolment->razorpay_order_id ? 'selected' : '' ?>>Razorpay</option>
      </select>
    </div>

    <div class="field" style="margin-bottom:14px">
      <label style="display:block;font-weight:700;margin-bottom:6px">Payment reference / transaction ID <span style="font-weight:400;color:var(--blue-700)">(optional, but recommended)</span></label>
      <input class="f-input" type="text" name="payment_reference" placeholder="e.g. the UTR or gateway payment ID from their dashboard">
    </div>

    <div class="field" style="margin-bottom:18px">
      <label style="display:block;font-weight:700;margin-bottom:6px">Note <span style="font-weight:400;color:var(--blue-700)">(internal audit log only — not shown to the member)</span></label>
      <textarea class="f-input" name="note" rows="3" placeholder="Why this is being reconciled manually, e.g. 'confirmed paid in Cashfree dashboard, webhook never arrived'"></textarea>
    </div>

    <div class="row-actions">
      <button type="submit" class="btn btn-primary btn-sm" data-confirm="Confirm this payment actually succeeded at the gateway? This will mark the enrolment as paid, generate its receipt, and send SMS/email to the member immediately.">Confirm payment & generate receipt</button>
      <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/enrolments/' . $enrolment->id . '/payment') ?>">Cancel</a>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
