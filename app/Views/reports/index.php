<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?php
$activeFilters = array_filter(array_intersect_key($_GET, array_flip(['q', 'programme', 'status', 'mode', 'from', 'to', 'prant_id', 'jila_id', 'prakhand_id', 'karyakarta_id'])));
$exportQuery   = http_build_query($_GET);
?>

<div class="page-header">
  <div>
    <div class="page-title">Reports</div>
    <div class="page-desc">Advanced filters, column selection, saved templates and exports across every enrolment</div>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi-card"><div class="kpi-label">Records<?= $activeFilters ? ' (filtered)' : '' ?></div><div class="kpi-value num"><?= number_format($totalCount) ?></div></div>
  <div class="kpi-card"><div class="kpi-label">Total Amount</div><div class="kpi-value num"><?= fmt_rupees($totalAmount) ?></div></div>
</div>

<div class="panel">
  <form method="get" id="reportForm">
    <div class="field" style="margin-bottom:12px">
      <input class="f-input" type="search" name="q" value="<?= esc($_GET['q'] ?? '') ?>" placeholder="Search by member name, phone, receipt number or Karyakarta…">
    </div>

    <div class="section-label" style="margin-bottom:8px;font-size:11px;font-weight:800;color:var(--blue-700);text-transform:uppercase">Filters</div>
    <div class="filter-row">
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
    </div>
    <div class="filter-row">
      <select name="prant_id" class="js-auto-submit">
        <option value="">All Prants</option>
        <?php foreach ($prants as $p): ?>
          <option value="<?= $p['id'] ?>" <?= (string) ($_GET['prant_id'] ?? '') === (string) $p['id'] ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="jila_id" class="js-auto-submit" <?= empty($_GET['prant_id']) ? 'disabled' : '' ?>>
        <option value="">All Jilas</option>
        <?php foreach ($jilas as $j): ?>
          <option value="<?= $j['id'] ?>" <?= (string) ($_GET['jila_id'] ?? '') === (string) $j['id'] ? 'selected' : '' ?>><?= esc($j['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="prakhand_id" class="js-auto-submit" <?= empty($_GET['jila_id']) ? 'disabled' : '' ?>>
        <option value="">All Prakhands</option>
        <?php foreach ($prakhands as $p): ?>
          <option value="<?= $p['id'] ?>" <?= (string) ($_GET['prakhand_id'] ?? '') === (string) $p['id'] ? 'selected' : '' ?>><?= esc($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="karyakarta_id" class="js-auto-submit">
        <option value="">All Karyakartas</option>
        <?php foreach ($karyakartas as $k): ?>
          <option value="<?= $k->id ?>" <?= (string) ($_GET['karyakarta_id'] ?? '') === (string) $k->id ? 'selected' : '' ?>><?= esc($k->name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="section-label" style="margin:16px 0 8px;font-size:11px;font-weight:800;color:var(--blue-700);text-transform:uppercase">Columns</div>
    <div class="chip-row" data-chip-multi>
      <?php foreach ($fieldCatalog as $key => $label): ?>
        <label class="chip <?= in_array($key, $fields, true) ? 'selected' : '' ?>">
          <input type="checkbox" name="fields[]" value="<?= $key ?>" style="display:none" <?= in_array($key, $fields, true) ? 'checked' : '' ?>>
          <?= esc($label) ?>
        </label>
      <?php endforeach; ?>
    </div>

    <div class="row-actions" style="margin-top:14px">
      <button type="submit" class="btn btn-secondary btn-sm">Apply</button>
      <?php if ($activeFilters): ?>
        <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/reports') ?>">Clear filters</a>
      <?php endif; ?>
      <a class="btn btn-navy btn-sm" href="<?= site_url('admin/reports/export.csv') . '?' . $exportQuery ?>">Export CSV</a>
      <a class="btn btn-navy btn-sm" href="<?= site_url('admin/reports/export.xlsx') . '?' . $exportQuery ?>">Export Excel</a>
      <a class="btn btn-navy btn-sm" href="<?= site_url('admin/reports/export.pdf') . '?' . $exportQuery ?>">Export PDF</a>
    </div>
  </form>
</div>

<div class="panel">
  <div class="panel-head">
    <div>
      <div class="panel-title">Report Templates</div>
      <div class="panel-note" style="margin-top:3px">Save the current filters and columns to reopen the same report later.</div>
    </div>
  </div>

  <?php if ($templates): ?>
    <div class="kv-list" style="margin-bottom:14px">
      <?php foreach ($templates as $tpl): ?>
        <div class="kv-row">
          <span><?= esc($tpl['name']) ?></span>
          <span class="row-actions">
            <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/reports') . '?template=' . $tpl['id'] ?>">Load</a>
            <form action="<?= site_url('admin/reports/templates/' . $tpl['id'] . '/delete') ?>" method="post">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red-600)" data-confirm="Remove the &ldquo;<?= esc($tpl['name']) ?>&rdquo; template?">Delete</button>
            </form>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="panel-note" style="margin-bottom:14px">No saved templates yet — set up filters and columns above, then save them here.</p>
  <?php endif; ?>

  <form action="<?= site_url('admin/reports/templates') ?>" method="post" class="row-actions">
    <?= csrf_field() ?>
    <?php foreach ($_GET as $key => $value): ?>
      <?php if (in_array($key, ['page', 'template'], true)) {
          continue;
      } ?>
      <?php foreach ((array) $value as $v): ?>
        <input type="hidden" name="<?= esc($key) ?><?= is_array($value) ? '[]' : '' ?>" value="<?= esc($v) ?>">
      <?php endforeach; ?>
    <?php endforeach; ?>
    <input class="f-input" name="name" placeholder="Name this report template…" style="max-width:280px" required>
    <button type="submit" class="btn btn-secondary btn-sm">Save as Template</button>
  </form>
</div>

<div class="panel">
  <div class="panel-head"><div class="panel-title">Reconciliation Summary</div><span class="panel-note">Cash collected vs remitted, for this filter</span></div>
  <div class="kpi-grid" style="margin-bottom:0">
    <div class="kpi-card"><div class="kpi-label">Total Cash Collected</div><div class="kpi-value num"><?= fmt_rupees($cashCollected) ?></div></div>
    <div class="kpi-card"><div class="kpi-label">Total Remitted</div><div class="kpi-value num"><?= fmt_rupees($remitted) ?></div></div>
    <div class="kpi-card"><div class="kpi-label">Outstanding</div><div class="kpi-value num"><?= fmt_rupees($outstanding) ?></div></div>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><div class="panel-title">Enrolments</div><span class="panel-note"><?= number_format($totalCount) ?> record<?= $totalCount !== 1 ? 's' : '' ?></span></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <?php foreach ($fields as $f): ?>
            <th><?= esc($fieldCatalog[$f]) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <?php foreach ($fields as $f): ?>
            <td<?= in_array($f, ['amount'], true) ? ' class="num"' : '' ?>>
              <?php if ($f === 'status'): ?>
                <span class="pill <?= status_pill_class($r->status) ?>"><?= esc(report_field_value($r, $f)) ?></span>
              <?php else: ?>
                <?= esc(report_field_value($r, $f)) ?>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (! count($rows)): ?>
        <tr><td colspan="<?= count($fields) ?>" class="panel-note">No records match these filters.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div style="margin-top:14px"><?= $pager->links() ?></div>
</div>

<?= $this->endSection() ?>
