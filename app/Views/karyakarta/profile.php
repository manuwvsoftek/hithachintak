<?= $this->extend('layouts/mobile') ?>
<?php $active = 'profile'; ?>
<?= $this->section('content') ?>

<div style="text-align:center;margin:6px 0 20px">
  <div style="width:56px;height:56px;border-radius:16px;background:var(--blue-100);color:var(--blue-700);display:flex;align-items:center;justify-content:center;margin:0 auto 10px;font-weight:800;font-family:Montserrat,sans-serif">
    <?= esc(strtoupper(substr($user->name, 0, 2))) ?>
  </div>
  <h2 style="font-size:16px"><?= esc($user->name) ?></h2>
  <div class="panel-note"><?= esc(\App\Entities\User::ROLE_LABELS[$user->role] ?? '') ?><?= $prakhand ? ' · ' . esc($prakhand) : '' ?></div>
</div>

<div class="panel">
  <div class="kv-list">
    <div class="kv-row"><span class="panel-note"><?= esc($t['phoneWord']) ?></span><span class="num"><?= esc(mask_phone($user->phone)) ?></span></div>
    <div class="kv-row"><span class="panel-note"><?= esc($t['statusWord']) ?></span><span class="pill <?= status_pill_class($user->status) ?>"><?= esc(ucfirst($user->status)) ?></span></div>
  </div>
</div>

<form action="<?= site_url('logout') ?>" method="post">
  <?= csrf_field() ?>
  <button type="submit" class="btn btn-secondary btn-block"><?= esc($t['signOut']) ?></button>
</form>

<?= $this->endSection() ?>
