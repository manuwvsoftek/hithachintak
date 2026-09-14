<script <?= csp_script_nonce() ?>>
(function(){
  // Registered on every logged-in page, not just /app, so the browser's
  // own "Install app" affordance (address-bar icon, browser menu entry)
  // is available no matter which page a Karyakarta or Admin happens to
  // be on — the install banner with an explicit button/instructions is
  // still specific to the /app mobile surface (layouts/mobile.php).
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function(){
      navigator.serviceWorker.register('<?= base_url('sw.js') ?>', { scope: '<?= base_url('/') ?>' })
        .catch(function(err){ console.warn('Service worker registration failed:', err); });
    });
  }
})();
</script>
