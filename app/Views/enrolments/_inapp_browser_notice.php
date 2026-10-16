<?php
/**
 * Shown above the UPI/Autopay checkout launcher when the page looks like
 * it's running inside WhatsApp/Instagram/Facebook's own in-app browser
 * (a WebView) rather than the phone's real Chrome/Safari — that WebView
 * doesn't register the upi://, gpay://, phonepe:// etc. custom schemes,
 * so Cashfree/Razorpay's UPI app icons silently fail to appear there
 * (see detect_inapp_browser() in app_helper.php). Checkout still
 * launches as normal underneath — this is an additional hint, not a
 * blocker, since the QR code still works inside the WebView either way.
 *
 * Expects $inAppBrowser (array{inApp,isAndroid,isIOS}) and $t in scope.
 */
?>
<?php if (! empty($inAppBrowser) && $inAppBrowser['inApp']): ?>
<div class="flash flash-info" style="margin-bottom:12px;text-align:left">
  <div><?= esc($t['inAppBrowserWarning'] ?? 'UPI apps (GPay, PhonePe, Paytm…) may not show up inside this in-app browser. For the best experience, open this page in Chrome or Safari.') ?></div>
  <?php if ($inAppBrowser['isAndroid']): ?>
    <button type="button" class="btn btn-secondary btn-sm" id="openInChromeBtn" style="margin-top:8px"><?= esc($t['inAppBrowserOpenInChrome'] ?? 'Open in Chrome') ?></button>
  <?php elseif ($inAppBrowser['isIOS']): ?>
    <div class="panel-note" style="margin-top:6px"><?= esc($t['inAppBrowserIosHint'] ?? 'Tap the ⋯ or share icon above and choose "Open in Safari" or "Open in Browser".') ?></div>
  <?php endif; ?>
</div>
<?php if ($inAppBrowser['isAndroid']): ?>
<script <?= csp_script_nonce() ?>>
(function(){
  var btn = document.getElementById('openInChromeBtn');
  if (!btn) { return; }
  btn.addEventListener('click', function(){
    // Standard Android escape hatch — most in-app WebViews (including
    // WhatsApp's) honor this intent:// URL to force-open the link in
    // Chrome specifically, where UPI app intents work normally.
    var target = window.location.href.replace(/^https?:\/\//, '');
    window.location.href = 'intent://' + target + '#Intent;scheme=https;package=com.android.chrome;end;';
  });
})();
</script>
<?php endif; ?>
<?php endif; ?>
