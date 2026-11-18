<?php
/**
 * Explicit "Install this app" banner + beforeinstallprompt wiring — shown
 * on the login page (before auth) and on every admin page (every role),
 * so installing isn't limited to the Karyakarta mobile surface or to
 * however obvious a given browser's own address-bar install icon is.
 * layouts/mobile.php keeps its own localized version of this same
 * banner (it already has $t for the rest of its chrome); this generic
 * copy is for the two layouts that don't. Dismissal shares the one
 * localStorage key with that banner too, so "Not now" anywhere stops the
 * prompt everywhere on this device, rather than nagging again per page.
 */
?>
<div id="pwaInstallBanner" class="install-banner" hidden>
  <span>Install this app on your phone or computer for quick access — works offline and loads faster.</span>
  <button type="button" class="btn btn-primary btn-sm" id="pwaInstallBtn">Install</button>
  <button type="button" class="btn btn-ghost btn-sm" id="pwaInstallDismiss">Not now</button>
</div>
<script <?= csp_script_nonce() ?>>
(function(){
  var banner     = document.getElementById('pwaInstallBanner');
  var installBtn = document.getElementById('pwaInstallBtn');
  var dismissBtn  = document.getElementById('pwaInstallDismiss');
  var already    = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

  function isDismissed() {
    try { return localStorage.getItem('hithachintak_install_dismissed') === '1'; } catch (err) { return false; }
  }
  function markDismissed() {
    try { localStorage.setItem('hithachintak_install_dismissed', '1'); } catch (err) { /* private browsing / storage unavailable — not fatal */ }
  }

  if (already || isDismissed()) return;

  var isIos    = /iphone|ipad|ipod/i.test(navigator.userAgent);
  var isSafari = /safari/i.test(navigator.userAgent) && !/crios|fxios|edgios/i.test(navigator.userAgent);

  if (isIos && isSafari) {
    // iOS Safari never fires beforeinstallprompt — show the banner right
    // away with manual instructions instead of waiting for an event that
    // will never come.
    installBtn.textContent = 'How?';
    installBtn.addEventListener('click', function(){
      alert('Tap the Share icon, then "Add to Home Screen".');
    });
    banner.hidden = false;
  } else {
    var deferredPrompt = null;
    window.addEventListener('beforeinstallprompt', function(e){
      e.preventDefault();
      deferredPrompt = e;
      banner.hidden = false;
    });
    installBtn.addEventListener('click', function(){
      if (!deferredPrompt) return;
      deferredPrompt.prompt();
      deferredPrompt.userChoice.finally(function(){
        banner.hidden = true;
        deferredPrompt = null;
      });
    });
  }

  dismissBtn.addEventListener('click', function(){
    banner.hidden = true;
    markDismissed();
  });
})();
</script>
