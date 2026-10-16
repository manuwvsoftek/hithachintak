<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div><div class="page-title"><?= esc($t['authorizeAutopay']) ?></div></div>
</div>

<div class="panel" style="max-width:420px;text-align:center">
  <?= $this->include('enrolments/_inapp_browser_notice') ?>
  <p class="panel-note" style="margin-bottom:16px"><?= esc($t['handDeviceNote']) ?></p>
  <p class="panel-note"><?= esc($t['ifNothingHappens']) ?> <button id="manualLaunch" class="btn btn-primary btn-sm" style="display:inline-flex"><?= esc($t['tapToContinue']) ?></button></p>
</div>

<?php if ($subscription['gateway'] === 'razorpay'): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script <?= csp_script_nonce() ?>>
(function(){
  function launch(){
    var rzp = new Razorpay({
      key: "<?= esc($subscription['key_id'], 'js') ?>",
      subscription_id: "<?= esc($subscription['subscription_id'], 'js') ?>",
      name: "Vishwa Hindu Parishad",
      description: "VHP Autopay Monthly Donation",
      prefill: {
        name: "<?= esc($subscription['customer_name'] ?? '', 'js') ?>",
        contact: "<?= esc($subscription['customer_phone'] ?? '', 'js') ?>",
        email: "<?= esc($subscription['customer_email'] ?? '', 'js') ?>"
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
  // subscriptionsCheckout() follows Cashfree's documented Subscriptions
  // JS SDK conventions but hasn't been verified against a live sandbox
  // session (no network access to docs.cashfree.com from this build
  // environment) — confirm the method name/params before go-live.
  var cashfree = Cashfree({ mode: "<?= esc($cashfreeEnv) ?>" });
  function launch(){
    cashfree.subscriptionsCheckout({
      subSessionId: "<?= esc($subscription['subscription_session_id'], 'js') ?>",
      redirectTarget: "_self"
    });
  }
  document.getElementById('manualLaunch').addEventListener('click', launch);
  launch();
})();
</script>
<?php endif; ?>
<?= $this->endSection() ?>
