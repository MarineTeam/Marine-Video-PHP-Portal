</main>
<?php if (current_user()): ?>
<script>
  window.IDLE_TIMEOUT_MS = <?= (int)IDLE_TIMEOUT_SECONDS * 1000 ?>;
  window.LOGOUT_URL = <?= json_encode(SITE_URL . '/logout.php') ?>;
</script>
<?php endif; ?>
<script src="<?= h(SITE_URL) ?>/assets/js/app.js"></script>
<script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?= h(SITE_URL) ?>/sw.js').catch(() => {});
  }
</script>
</body>
</html>
