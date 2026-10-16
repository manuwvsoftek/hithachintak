<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div><div class="page-title"><?= esc($t['completePaymentTitle']) ?></div></div>
</div>

<div class="panel" style="max-width:420px;text-align:center">
   <?= $this->include('enrolments/_inapp_browser_notice') ?>
  <p class="panel-note" style="margin-bottom:16px"><?= esc($t['redirectingGateway']) ?></p>
  <p class="panel-note"><?= esc($t['ifNothingHappens']) ?> <button id="manualLaunch" class="btn btn-primary btn-sm" style="display:inline-flex"><?= esc($t['tapToContinue']) ?></button></p>
</div>

<?php if ($order['gateway'] === 'razorpay'): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script <?= csp_script_nonce() ?>>
(function(){
  function launch(){
    var rzp = new Razorpay({
      key: "<?= esc($order['key_id'], 'js') ?>",
      amount: <?= (int) $order['amount_paise'] ?>,
      currency: "INR",
      order_id: "<?= esc($order['order_id'], 'js') ?>",
      name: "Vishwa Hindu Parishad",
      description: "Hithachintak Abhiyan",
      prefill: {
        name: "<?= esc($order['customer_name'] ?? '', 'js') ?>",
        contact: "<?= esc($order['customer_phone'] ?? '', 'js') ?>",
        email: "<?= esc($order['customer_email'] ?? '', 'js') ?>"
      },
      handler: function(){
        window.location.href = "<?= esc($returnUrl, 'js') ?>";
      },
      modal: {
        ondismiss: function(){
          window.location.href = "<?= esc($returnUrl, 'js') ?>";
        }
      }
    });
    rzp.open();
  }
  document.getElementById('manualLaunch').addEventListener('click', launch);
  launch();
})();
</script>
<?php else: ?>
<script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
<script <?= csp_script_nonce() ?>>
(function(){
  var cashfree = Cashfree({ mode: "<?= esc($cashfreeEnv) ?>" });
  function launch(){
    cashfree.checkout({
      paymentSessionId: "<?= esc($order['payment_session_id'], 'js') ?>",
      redirectTarget: "_self"
    });
  }
  document.getElementById('manualLaunch').addEventListener('click', launch);
  launch();
})();
</script>
<?php endif; ?>

<?= $this->endSection() ?>
