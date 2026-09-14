<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div><div class="page-title"><?= esc($t['authorizeAutopay']) ?></div></div>
</div>

<div class="panel" style="max-width:420px;text-align:center">
  <p class="panel-note" style="margin-bottom:16px"><?= esc($t['handDeviceNote']) ?></p>
  <p class="panel-note"><?= esc($t['ifNothingHappens']) ?> <button id="manualLaunch" class="btn btn-primary btn-sm" style="display:inline-flex"><?= esc($t['tapToContinue']) ?></button></p>
</div>

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
      subSessionId: "<?= esc($subscriptionSessionId, 'js') ?>",
      redirectTarget: "_self"
    });
  }
  document.getElementById('manualLaunch').addEventListener('click', launch);
  launch();
})();
</script>

<?= $this->endSection() ?>
