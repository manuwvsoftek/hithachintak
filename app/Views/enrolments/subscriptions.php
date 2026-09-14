<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div>
    <div class="page-title">Autopay Subscriptions</div>
    <div class="page-desc">Recurring monthly mandates under Autopay Monthly Donation to VHP</div>
  </div>
  <div class="header-actions">
    <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/enrolments') ?>">Back to Enrolments</a>
  </div>
</div>

<div class="panel">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Donor</th><th>Phone</th><th>Prant</th><th>Karyakarta</th>
          <th>Monthly amount</th><th>Status</th><th>Authorized</th><th>Last charge</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($subscriptions as $s): ?>
        <tr>
          <td><?= esc($s->member_name) ?></td>
          <td><?= esc($s->member_phone) ?></td>
          <td><?= esc($s->prant_name) ?></td>
          <td><?= esc($s->karyakarta_name) ?></td>
          <td><?= fmt_rupees($s->amount) ?></td>
          <td><span class="pill <?= status_pill_class($s->status) ?>"><?= esc(ucfirst($s->status)) ?></span></td>
          <td><?= $s->authorized_at ? esc(date('d M Y', strtotime($s->authorized_at))) : '—' ?></td>
          <td><?= $s->last_charge_at ? esc(date('d M Y', strtotime($s->last_charge_at))) : '—' ?></td>
          <td><a class="btn btn-ghost btn-sm" href="<?= site_url('admin/enrolments/' . $s->enrolment_id . '/receipt') ?>">First receipt</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (! $subscriptions): ?>
        <tr><td colspan="9" class="panel-note">No Autopay subscriptions yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
