<?= $this->extend('layouts/mobile') ?>
<?php $active = 'collections'; ?>
<?= $this->section('content') ?>

<div class="panel">
  <div class="panel-head"><div class="panel-title"><?= esc($t['dueForRemittance']) ?></div></div>
  <div class="kv-list">
    <?php foreach ($due as $d): ?>
      <div class="kv-row">
        <span><?= esc(date('d M', strtotime($d['created_at']))) ?></span>
        <span class="num" style="font-weight:700"><?= fmt_rupees($d['amount']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (! $due): ?><p class="panel-note"><?= esc($t['nothingDue']) ?></p><?php endif; ?>
</div>

<div class="panel">
  <div class="panel-head"><div class="panel-title"><?= esc($t['remittanceHistory']) ?></div></div>
  <div class="kv-list">
    <?php foreach ($history as $h): ?>
      <div class="kv-row">
        <span><?= esc(date('d M', strtotime($h['remitted_at']))) ?><?= $h['upi_ref'] ? ' · ' . esc($h['upi_ref']) : '' ?></span>
        <span class="num" style="font-weight:700;color:var(--green-600)"><?= fmt_rupees($h['amount']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (! $history): ?><p class="panel-note"><?= esc($t['noRemittancesYet']) ?></p><?php endif; ?>
</div>

<?= $this->endSection() ?>
