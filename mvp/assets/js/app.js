// --- Idle timeout: auto sign-out after IDLE_TIMEOUT_MS of inactivity ---
(function () {
  if (typeof window.IDLE_TIMEOUT_MS !== 'number') return;
  let timer;
  function reset() {
    clearTimeout(timer);
    timer = setTimeout(() => { window.location.href = window.LOGOUT_URL; }, window.IDLE_TIMEOUT_MS);
  }
  ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(evt =>
    document.addEventListener(evt, reset, { passive: true })
  );
  reset();
})();

// --- Playback progress + resume (for local <video> files) ---
(function () {
  const player = document.getElementById('player');
  if (!player || typeof window.PROGRESS_ENDPOINT === 'undefined') return;

  const resume = parseInt(player.dataset.resume || '0', 10);
  if (resume > 5) {
    player.addEventListener('loadedmetadata', () => {
      if (resume < player.duration - 5) player.currentTime = resume;
    }, { once: true });
  }

  const startOver = document.getElementById('start-over');
  if (startOver) {
    startOver.addEventListener('click', (e) => {
      e.preventDefault();
      player.currentTime = 0;
      player.play();
    });
  }

  let lastSent = 0;
  function report() {
    const pos = Math.floor(player.currentTime || 0);
    const dur = Math.floor(player.duration || 0);
    if (!dur || pos === lastSent) return;
    lastSent = pos;
    fetch(window.PROGRESS_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ video_id: window.VIDEO_ID, position: pos, duration: dur }),
      keepalive: true,
    }).catch(() => {});
  }
  player.addEventListener('timeupdate', () => {
    // throttle to roughly once every 5 seconds
    if (Math.floor(player.currentTime) % 5 === 0) report();
  });
  player.addEventListener('pause', report);
  window.addEventListener('beforeunload', report);
})();
