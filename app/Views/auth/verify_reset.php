<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>

<h2>Verify it's you</h2>
<p class="panel-note">Enter the 6-digit code sent by SMS to your registered number.</p>

<form action="<?= site_url('forgot-password/verify') ?>" method="post">
  <?= csrf_field() ?>
  <div class="otp-row">
    <?php for ($i = 0; $i < 6; $i++): ?>
      <input class="otp-box" type="text" inputmode="numeric" maxlength="1" name="otp_<?= $i ?>" data-otp-index="<?= $i ?>">
    <?php endfor; ?>
  </div>
  <input type="hidden" name="otp" id="otpCombined">
  <button type="submit" class="btn btn-primary btn-block">Verify</button>
  <div class="resend">Didn't get it? <a href="<?= site_url('forgot-password') ?>">Resend OTP</a></div>
</form>

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
