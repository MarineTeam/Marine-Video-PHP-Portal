document.addEventListener('DOMContentLoaded', () => {
  const cells = document.querySelectorAll('[data-video-status]');
  if (!cells.length) return;

  async function check(cell) {
    const id = cell.dataset.videoId;
    try {
      const res = await fetch('bunny_status_check.php?id=' + id);
      const data = await res.json();
      if (data.error) {
        cell.querySelector('[data-refresh-status]').insertAdjacentHTML('afterend', ' <span style="color:var(--danger)" title="' + data.error.replace(/"/g, '&quot;') + '">⚠ check failed</span>');
        return;
      }
      if (data.status !== 'processing') {
        window.location.reload();
      }
    } catch (e) {
      // network hiccup — leave the row as-is, the manual Refresh button still works
    }
  }

  cells.forEach((cell) => {
    const btn = cell.querySelector('[data-refresh-status]');
    if (btn) btn.addEventListener('click', () => { btn.textContent = 'Checking…'; check(cell); });
  });

  // Auto-poll every 8s for any row still "Processing…" so it updates without
  // needing a manual click or a full page reload.
  const processingCells = Array.from(cells).filter((c) => c.textContent.includes('Processing'));
  if (processingCells.length) {
    setInterval(() => processingCells.forEach(check), 8000);
  }
});
