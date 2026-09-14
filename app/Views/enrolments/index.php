<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?php $activeFilters = array_filter(array_intersect_key($_GET, array_flip(['q', 'programme', 'status', 'mode', 'from', 'to']))); ?>
<div class="page-header">
  <div>
    <div class="page-title">Enrolments</div>
    <div class="page-desc">Consolidated register across all programmes — collection and remittance in one place</div>
  </div>
  <div class="header-actions">
    <?php if (current_permission_level('Enrolments') !== 'None' && current_permission_level('Enrolments') !== 'View'): ?>
      <a class="btn btn-primary btn-sm" href="<?= site_url('admin/enrolments/new') ?>">+ New Enrolment</a>
    <?php endif; ?>
    <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/enrolments/subscriptions') ?>">Autopay Subscriptions</a>
    <a class="btn btn-secondary btn-sm" href="<?= site_url('admin/enrolments/export.csv') . '?' . http_build_query($_GET) ?>">Export</a>
  </div>
</div>

<div class="tabs">
  <a class="tab <?= $view === 'list' ? 'active' : '' ?>" href="<?= site_url('admin/enrolments') ?>">All Enrolments</a>
  <a class="tab <?= $view === 'drafts' ? 'active' : '' ?>" href="<?= site_url('admin/enrolments') ?>?view=drafts">Pending Drafts<?= $draftCount ? ' (' . $draftCount . ')' : '' ?></a>
</div>

<?php if ($view === 'drafts'): ?>
<div class="panel">
  <div class="panel-head">
    <div>
      <div class="panel-title">Pending Drafts</div>
      <div class="panel-note" style="margin-top:3px">Enrolments started but not yet submitted — autosaved as the Karyakarta types, and resumable here even after a refresh, an accidental close, or a network drop.</div>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Member</th><th>Programme</th><th>Last saved</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($drafts as $d): ?>
        <?php $programmeName = ''; foreach ($programmes as $p) { if ($p['code'] === $d['programme_code']) { $programmeName = $p['name']; break; } } ?>
        <tr>
          <td style="font-weight:700"><?= esc($d['member_name'] ?: '(no name yet)') ?></td>
          <td><?= esc($programmeName ?: '—') ?></td>
          <td><?= esc(date('d M Y, g:i a', strtotime($d['updated_at']))) ?></td>
          <td class="row-actions">
            <a class="btn btn-primary btn-sm" href="<?= site_url('admin/enrolments/new') ?>?resume=<?= esc($d['id'], 'url') ?>">Resume</a>
            <form action="<?= site_url('admin/enrolments/draft/' . $d['id'] . '/delete') ?>" method="post" style="margin:0">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Discard this draft? This can't be undone.">Discard</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (! $drafts): ?>
        <tr><td colspan="4" class="panel-note">No pending drafts.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>

<div class="kpi-grid">
  <div class="kpi-card"><div class="kpi-label">Enrolments<?= $activeFilters ? ' (filtered)' : '' ?></div><div class="kpi-value num"><?= number_format($totalCount) ?></div></div>
  <div class="kpi-card"><div class="kpi-label">Total Amount</div><div class="kpi-value num"><?= fmt_rupees($totalAmount) ?></div></div>
  <div class="kpi-card"><div class="kpi-label">Via UPI / QR / Autopay</div><div class="kpi-value num"><?= fmt_rupees($upiAmount) ?></div></div>
  <div class="kpi-card"><div class="kpi-label">Via Cash</div><div class="kpi-value num"><?= fmt_rupees($cashAmount) ?></div></div>
</div>

<div class="panel">
  <form method="get">
    <div class="field" style="margin-bottom:12px">
      <input class="f-input" type="search" name="q" value="<?= esc($_GET['q'] ?? '') ?>" placeholder="Search by member name, phone, receipt number or Karyakarta…">
    </div>
    <div class="filter-row" style="margin-bottom:0">
      <select name="programme" class="js-auto-submit">
        <option value="">All programmes</option>
        <?php foreach ($programmes as $p): ?>
          <option value="<?= esc($p['code']) ?>" <?= ($_GET['programme'] ?? '') === $p['code'] ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="js-auto-submit">
        <option value="">All statuses</option>
        <?php foreach (['otp_pending', 'awaiting_payment', 'paid', 'cash_collected', 'cash_pending_remit', 'remitted'] as $s): ?>
          <option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $s))) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="mode" class="js-auto-submit">
        <option value="">All modes</option>
        <?php foreach (['upi' => 'UPI', 'qr' => 'QR', 'cash' => 'Cash', 'autopay' => 'Autopay'] as $code => $label): ?>
          <option value="<?= $code ?>" <?= ($_GET['mode'] ?? '') === $code ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="from" class="js-auto-submit" value="<?= esc($_GET['from'] ?? '') ?>" title="From date">
      <input type="date" name="to" class="js-auto-submit" value="<?= esc($_GET['to'] ?? '') ?>" title="To date">
      <button type="submit" class="btn btn-secondary btn-sm">Search</button>
      <?php if ($activeFilters): ?>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/enrolments') ?>">Clear filters</a>
      <?php endif; ?>
    </div>
  </form>

  <div class="table-wrap" style="margin-top:16px">
    <table>
      <thead>
        <tr><th>Member</th><th>Programme</th><th>Location</th><th>Karyakarta</th><th>Amount</th><th>Mode</th><th>Status</th><th>Receipt</th></tr>
      </thead>
      <tbody>
      <?php foreach ($enrolments as $e): ?>
        <?php $family = $familyByEnrolment[$e->id] ?? []; ?>
        <tr>
          <td>
            <div style="font-weight:700"><?= esc($e->member_name) ?></div>
            <?php if ($family): ?>
              <?php $familyLabel = count($family) . ' family member' . (count($family) === 1 ? '' : 's'); ?>
              <button type="button" class="family-toggle" data-family-toggle="<?= esc($familyLabel) ?>" aria-expanded="false">+ <?= esc($familyLabel) ?></button>
              <ul class="family-list" hidden>
                <?php foreach ($family as $fm): ?>
                  <li>
                    <?= esc($fm['name']) ?><?= $fm['age_or_dob'] ? ', ' . esc($fm['age_or_dob']) : '' ?><?= $fm['profession'] ? ' — ' . esc($fm['profession']) : '' ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </td>
          <td><?= esc($e->programme_name) ?></td>
          <td><?= esc(trim(($e->jila_name ?? '') . ' · ' . ($e->prakhand_name ?? ''), ' ·')) ?></td>
          <td><?= esc($e->karyakarta_name) ?></td>
          <td class="num"><?= fmt_rupees($e->amount) ?></td>
          <td><?= esc($e->payment_mode ? ucfirst($e->payment_mode) : '—') ?></td>
          <td><span class="pill <?= status_pill_class($e->status) ?>"><?= esc(ucwords(str_replace('_', ' ', $e->status))) ?></span></td>
          <td>
            <?php if ($e->hasReceipt()): ?>
              <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/enrolments/' . $e->id . '/receipt') ?>">View</a>
            <?php else: ?>
              <span class="panel-note">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (! count($enrolments)): ?>
        <tr><td colspan="8" class="panel-note">No enrolments match these filters.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div style="margin-top:14px"><?= $pager->links() ?></div>
</div>

<?php if ($dueRows): ?>
<div class="panel">
  <div class="panel-head">
    <div>
      <div class="panel-title">Cash remittance pending</div>
      <div class="panel-note" style="margin-top:3px">Hithachintak cash remits instantly via the gateway — this is only ever other programmes' cash, remitted separately.</div>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Collected by</th><th>Amount</th><th>Since</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($dueRows as $r): ?>
        <tr>
          <td><?= esc($r['user_name']) ?></td>
          <td class="num"><?= fmt_rupees($r['amount']) ?></td>
          <td><?= esc($r['created_at']) ?></td>
          <td>
            <form action="<?= site_url('admin/enrolments/remit-cash') ?>" method="post" class="row-actions">
              <?= csrf_field() ?>
              <input type="hidden" name="remittance_id" value="<?= $r['id'] ?>">
              <input class="f-input" name="upi_ref" placeholder="UPI ref (optional)" style="width:150px;padding:6px 8px;font-size:12px">
              <button type="submit" class="btn btn-secondary btn-sm">Mark remitted</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?= $this->endSection() ?>
