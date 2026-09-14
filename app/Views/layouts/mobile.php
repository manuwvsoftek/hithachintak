<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="author" content="Shreedhar Bhat, Manu K, World Vision Softek">
  <meta name="theme-color" content="#0E2A52">
  <title><?= esc($title ?? 'Hithachintak Abhiyan') ?></title>

  <link rel="manifest" href="<?= base_url('manifest.webmanifest') ?>">
  <link rel="icon" href="<?= base_url('assets/icons/icon-192.png') ?>">
  <link rel="apple-touch-icon" href="<?= base_url('assets/icons/apple-touch-icon.png') ?>">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Hithachintak">

  <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/app.css') ?>">
</head>
<body style="background:var(--bg-deep)">
  <div class="mobile-page">
    <div class="mobile-appbar"><?= esc($title ?? '') ?></div>
    <div class="mobile-content">
      <div id="pwaInstallBanner" class="install-banner" hidden>
        <span><?= esc($t['installBannerText']) ?></span>
        <button type="button" class="btn btn-primary btn-sm" id="pwaInstallBtn"><?= esc($t['installBtn']) ?></button>
        <button type="button" class="btn btn-ghost btn-sm" id="pwaInstallDismiss"><?= esc($t['notNowBtn']) ?></button>
      </div>
      <?= $this->include('partials/flash') ?>
      <?= $this->renderSection('content') ?>
    </div>
  </div>
  <nav class="mobile-tabbar">
    <a class="mtab <?= ($active ?? '') === 'home' ? 'active' : '' ?>" href="<?= site_url('app') ?>"><?= esc($t['homeTab']) ?></a>
    <a class="mtab-fab" href="<?= site_url('admin/enrolments/new') ?>" title="<?= esc($t['newHcEnrolment']) ?>">+</a>
    <a class="mtab <?= ($active ?? '') === 'collections' ? 'active' : '' ?>" href="<?= site_url('app/collections') ?>"><?= esc($t['collectionsTab']) ?></a>
    <a class="mtab <?= ($active ?? '') === 'profile' ? 'active' : '' ?>" href="<?= site_url('app/profile') ?>"><?= esc($t['profileTab']) ?></a>
  </nav>

  <script src="<?= base_url('assets/js/app.js') ?>"></script>
  <script <?= csp_script_nonce() ?>>
  (function(){
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function(){
        navigator.serviceWorker.register('<?= base_url('sw.js') ?>', { scope: '<?= base_url('/') ?>' })
          .catch(function(err){ console.warn('Service worker registration failed:', err); });
      });
    }

    var banner    = document.getElementById('pwaInstallBanner');
    var installBtn = document.getElementById('pwaInstallBtn');
    var already   = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    var dismissed = localStorage.getItem('hithachintak_install_dismissed') === '1';

    if (!already && !dismissed) {
      var isIos    = /iphone|ipad|ipod/i.test(navigator.userAgent);
      var isSafari = /safari/i.test(navigator.userAgent) && !/crios|fxios|edgios/i.test(navigator.userAgent);

      if (isIos && isSafari) {
        // iOS Safari has no beforeinstallprompt — show the banner with a
        // manual instruction instead.
        installBtn.textContent = <?= json_encode($t['howBtn']) ?>;
        installBtn.addEventListener('click', function(){
          alert(<?= json_encode($t['addToHomeScreenAlert']) ?>);
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
          deferredPrompt.userChoice.finally(function(){ banner.hidden = true; deferredPrompt = null; });
        });
      }
    }

    var dismissBtn = document.getElementById('pwaInstallDismiss');
    if (dismissBtn) {
      dismissBtn.addEventListener('click', function(){
        banner.hidden = true;
        localStorage.setItem('hithachintak_install_dismissed', '1');
      });
    }
  })();
  </script>
</body>
</html>
