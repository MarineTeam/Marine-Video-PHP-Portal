</main>
<?php if (current_user()): ?>
<script>
  window.IDLE_TIMEOUT_MS = <?= (int)IDLE_TIMEOUT_SECONDS * 1000 ?>;
  window.LOGOUT_URL = <?= json_encode(SITE_URL . '/logout.php') ?>;
</script>
<?php endif; ?>
<script src="<?= h(SITE_URL) ?>/assets/js/app.js"></script>
<?php if (is_plugin_active('notifications') && current_user()): ?>
<script>window.VAPID_PUBLIC_KEY = <?= json_encode(VAPID_PUBLIC_KEY) ?>;</script>
<script src="<?= h(SITE_URL) ?>/assets/js/push-subscribe.js"></script>
<?php endif; ?>
<script>
  if ('serviceWorker' in navigator) { navigator.serviceWorker.register('<?= h(SITE_URL) ?>/sw.js').catch(() => {}); }
</script>
</body>
</html>
