(function () {
  const iframe = document.getElementById('bunny-player');
  if (!iframe || typeof window.PROGRESS_ENDPOINT === 'undefined') return;
  const resume = parseInt(iframe.dataset.resume || '0', 10);
  let lastSent = 0, sentResume = false;

  function post(method, value) {
    iframe.contentWindow.postMessage(JSON.stringify({ context: 'player.js', version: '0.0.1', method, value }), '*');
  }
  function report(seconds, duration, completed) {
    const pos = Math.floor(seconds || 0), dur = Math.floor(duration || 0);
    if (!dur || (pos === lastSent && !completed)) return;
    lastSent = pos;
    fetch(window.PROGRESS_ENDPOINT, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ video_id: window.VIDEO_ID, position: pos, duration: dur, completed: !!completed }),
      keepalive: true,
    }).catch(() => {});
  }
  window.addEventListener('message', (e) => {
    let msg;
    try { msg = JSON.parse(e.data); } catch (err) { return; }
    if (!msg || msg.context !== 'player.js') return;
    if (msg.event === 'ready' && resume > 5 && !sentResume) { sentResume = true; post('setCurrentTime', resume); post('play'); }
    if (msg.event === 'timeupdate' && msg.value) report(msg.value.seconds, msg.value.duration, false);
    if (msg.event === 'pause' && msg.value) report(msg.value.seconds, msg.value.duration, false);
    if (msg.event === 'ended' && msg.value) report(msg.value.seconds, msg.value.duration, true);
  });
})();
