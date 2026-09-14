<?= $this->extend('layouts/mobile') ?>
<?php $active = 'home'; ?>
<?= $this->section('content') ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:6px">
  <div class="big-stat"><div class="v num"><?= number_format($todayCount) ?></div><div class="l"><?= esc($t['enrolledToday']) ?></div></div>
  <div class="big-stat"><div class="v num"><?= fmt_rupees($cashDue) ?></div><div class="l"><?= esc($t['cashToRemit']) ?></div></div>
</div>

<a class="btn btn-primary btn-block" style="margin-bottom:16px" href="<?= site_url('admin/enrolments/new?programme=hc') ?>"><?= esc($t['newHcEnrolment']) ?></a>

<div class="panel">
  <div class="panel-head"><div class="panel-title"><?= esc($t['recentWord']) ?></div></div>
  <?php foreach ($recent as $e): ?>
    <div style="display:flex;align-items:center;gap:11px;padding:10px 0;border-bottom:1px solid var(--border-soft)">
      <div style="width:34px;height:34px;border-radius:9px;background:var(--blue-100);color:var(--blue-700);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;flex:none;font-family:Montserrat,sans-serif">
        <?= esc(strtoupper(substr($e->member_name, 0, 2))) ?>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-size:13px;font-weight:700"><?= esc($e->member_name) ?></div>
        <div style="font-size:11px;color:var(--ink-faint)"><?= esc($e->programme_name) ?></div>
      </div>
      <div style="text-align:right">
        <div class="num" style="font-weight:800;font-size:12.5px"><?= fmt_rupees($e->amount) ?></div>
        <span class="pill <?= status_pill_class($e->status) ?>" style="margin-top:2px"><?= esc(ucwords(str_replace('_', ' ', $e->status))) ?></span>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (! $recent): ?><p class="panel-note"><?= esc($t['noEnrolmentsYet']) ?></p><?php endif; ?>
</div>

<?= $this->endSection() ?>
