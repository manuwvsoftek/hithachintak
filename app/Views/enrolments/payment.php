<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div><div class="page-title"><?= esc($t['paymentTitle']) ?></div></div>
</div>

<div style="max-width:420px">
  <?php if (! $gatewayReady): ?>
    <div class="flash flash-info"><?= esc($t['gatewayNotConfigured']) ?><?= $programme['is_recurring'] ? '.' : ' ' . esc($t['cashCollectionStillWorks']) ?></div>
  <?php endif; ?>

  <?php if ($programme['is_recurring']): ?>
    <?php if ($enrolment->autopay_subscription_id): ?>
      <div class="panel">
        <div class="panel-title" style="margin-bottom:4px"><?= esc($t['waitingConfirm']) ?></div>
        <p class="panel-note" style="margin-bottom:12px">
          <?= esc($t['autopaySetupWaitNote']) ?>
        </p>
        <a class="btn btn-secondary btn-block" href="<?= site_url('admin/enrolments/' . $enrolment->id . '/payment') ?>"><?= esc($t['refreshWord']) ?></a>
      </div>
    <?php else: ?>
      <div class="panel">
        <div class="panel-title" style="margin-bottom:4px"><?= esc($t['setupAutopay']) ?></div>
        <p class="panel-note" style="margin-bottom:12px">
          <?= esc($t['autopayAuthNote']) ?>
        </p>
        <form action="<?= site_url('admin/enrolments/' . $enrolment->id . '/pay/autopay') ?>" method="post" id="autopayForm">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-primary btn-block" <?= $gatewayReady ? '' : 'disabled' ?>><?= esc($t['authorizeAutopay']) ?></button>
        </form>
      </div>
    <?php endif; ?>
  <?php elseif ($programme['code'] === 'hc' && $enrolment->status === \App\Entities\Enrolment::STATUS_CASH_COLLECTED): ?>
    <div class="panel">
      <div class="panel-title" style="margin-bottom:4px"><?= esc($t['cashPendingTitle']) ?></div>
      <p class="panel-note" style="margin-bottom:12px">
        <?= fmt_rupees($enrolment->amount) ?> <?= esc($t['cashPendingNote']) ?>
      </p>
      <a class="btn btn-primary btn-block" href="<?= site_url('admin/enrolments/' . $enrolment->id . '/remit') ?>"><?= esc($t['remitNowGateway']) ?> (<?= fmt_rupees($enrolment->amount) ?>)</a>
    </div>
  <?php else: ?>
    <div class="panel">
      <div class="panel-title" style="margin-bottom:4px"><?= esc($t['upiTitle']) ?></div>
      <p class="panel-note" style="margin-bottom:12px"><?= esc($t['upiNote']) ?></p>
      <form action="<?= site_url('admin/enrolments/' . $enrolment->id . '/pay/upi') ?>" method="post" id="upiForm">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary btn-block" <?= $gatewayReady ? '' : 'disabled' ?>><?= esc($t['collectViaUpi']) ?></button>
      </form>
    </div>

    <div class="panel">
      <div class="panel-title" style="margin-bottom:4px"><?= esc($t['cashCollectedTitle']) ?></div>
      <p class="panel-note" style="margin-bottom:12px">
        <?= esc($programme['code'] === 'hc' ? $t['cashHcNote'] : $t['cashOtherNote']) ?>
      </p>
      <form action="<?= site_url('admin/enrolments/' . $enrolment->id . '/pay/cash') ?>" method="post">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-secondary btn-block"><?= esc($t['markCollectedCash']) ?></button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php if ($autoPay && $gatewayReady): ?>
<script <?= csp_script_nonce() ?>>
(function(){
  // Chosen "Online" on the New Enrolment form — launch the gateway step
  // straight away instead of making the Karyakarta click again.
  var form = document.getElementById('upiForm') || document.getElementById('autopayForm');
  if (form) { form.submit(); }
})();
</script>
<?php endif; ?>

<?= $this->endSection() ?>
