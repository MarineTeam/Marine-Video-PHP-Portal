(function () {
  if (typeof window.IDLE_TIMEOUT_MS !== 'number') return;
  let timer;
  function reset() {
    clearTimeout(timer);
    timer = setTimeout(() => { window.location.href = window.LOGOUT_URL; }, window.IDLE_TIMEOUT_MS);
  }
  ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(evt => document.addEventListener(evt, reset, { passive: true }));
  reset();
})();

(function () {
  const player = document.getElementById('player');
  if (!player || typeof window.PROGRESS_ENDPOINT === 'undefined') return;
  const resume = parseInt(player.dataset.resume || '0', 10);
  if (resume > 5) {
    player.addEventListener('loadedmetadata', () => { if (resume < player.duration - 5) player.currentTime = resume; }, { once: true });
  }
  let lastSent = 0;
  function report(completedOverride) {
    const pos = Math.floor(player.currentTime || 0);
    const dur = Math.floor(player.duration || 0);
    if (!dur || pos === lastSent) return;
    lastSent = pos;
    fetch(window.PROGRESS_ENDPOINT, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ video_id: window.VIDEO_ID, position: pos, duration: dur, completed: !!completedOverride }),
      keepalive: true,
    }).catch(() => {});
  }
  player.addEventListener('timeupdate', () => { if (Math.floor(player.currentTime) % 5 === 0) report(); });
  player.addEventListener('pause', () => report());
  player.addEventListener('ended', () => report(true));
  window.addEventListener('beforeunload', () => report());
})();

// Announcement banner dismissal (per-browser-session, matches spec)
document.addEventListener('DOMContentLoaded', () => {
  const banner = document.getElementById('announcement-banner');
  if (!banner) return;
  const key = 'dismissed-announcement-' + banner.dataset.id;
  if (sessionStorage.getItem(key)) { banner.style.display = 'none'; return; }
  const closeBtn = banner.querySelector('button');
  if (closeBtn) closeBtn.addEventListener('click', () => {
    sessionStorage.setItem(key, '1');
    banner.style.display = 'none';
  });
});
