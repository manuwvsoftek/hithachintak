<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div><div class="page-title"><?= esc($t['verifyMemberTitle']) ?></div></div>
  <a class="btn btn-ghost btn-sm" href="<?= site_url('admin/enrolments/new') ?>"><?= esc($t['backWord']) ?></a>
</div>

<div class="panel" style="max-width:420px">
  <p class="panel-note" style="margin-bottom:20px;font-size:12.5px">
    <?= esc($t['otpSentNote']) ?>
  </p>

  <form action="<?= site_url('admin/enrolments/' . $enrolment->id . '/otp/verify') ?>" method="post">
    <?= csrf_field() ?>
    <div class="otp-row">
      <?php for ($i = 0; $i < 6; $i++): ?>
        <input class="otp-box" type="text" inputmode="numeric" maxlength="1" data-otp-index="<?= $i ?>">
      <?php endfor; ?>
    </div>
    <input type="hidden" name="otp" id="otpCombined">
    <button type="submit" class="btn btn-primary btn-block"><?= esc($t['verify']) ?> &amp; <?= esc($t['continueWord']) ?></button>
  </form>

  <form action="<?= site_url('admin/enrolments/' . $enrolment->id . '/otp/send') ?>" method="post" style="margin-top:10px">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-ghost btn-sm btn-block"><?= esc($t['resendOtp']) ?></button>
  </form>
</div>

<script <?= csp_script_nonce() ?>>
(function(){
  var boxes = document.querySelectorAll('.otp-box');
  var combined = document.getElementById('otpCombined');
  var form = boxes[0].closest('form');
  boxes.forEach(function(box, i){
    box.addEventListener('input', function(){
      box.value = box.value.replace(/\D/g, '').slice(0, 1);
      if (box.value && boxes[i + 1]) boxes[i + 1].focus();
    });
    box.addEventListener('keydown', function(e){
      if (e.key === 'Backspace' && !box.value && boxes[i - 1]) boxes[i - 1].focus();
    });
  });
  form.addEventListener('submit', function(){
    combined.value = Array.from(boxes).map(function(b){ return b.value; }).join('');
  });
})();
</script>

<?= $this->endSection() ?>
