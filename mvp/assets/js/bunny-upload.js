// Minimal TUS 1.0.0 resumable-upload client, scoped to what bunny.net's
// Stream TUS endpoint needs. No library dependency — bunny's TUS server
// supports CORS specifically so browsers can upload directly.
async function bunnyTusUpload(file, ticket, onProgress) {
  const authHeaders = {
    'AuthorizationSignature': ticket.signature,
    'AuthorizationExpire': String(ticket.expire),
    'VideoId': ticket.video_id,
    'LibraryId': ticket.library_id,
  };

  const metadata = [
    'filetype ' + btoa(file.type || 'video/mp4'),
    'title ' + btoa(ticket.title || file.name),
  ].join(',');

  const createRes = await fetch(ticket.endpoint, {
    method: 'POST',
    headers: {
      ...authHeaders,
      'Tus-Resumable': '1.0.0',
      'Upload-Length': String(file.size),
      'Upload-Metadata': metadata,
    },
  });
  if (!createRes.ok) {
    throw new Error('Could not start the bunny.net upload (HTTP ' + createRes.status + ').');
  }
  let location = createRes.headers.get('Location') || ticket.endpoint;
  if (location && !location.startsWith('http')) {
    location = new URL(location, ticket.endpoint).toString();
  }

  const chunkSize = 20 * 1024 * 1024; // 20MB chunks
  let offset = 0;
  while (offset < file.size) {
    const chunk = file.slice(offset, Math.min(offset + chunkSize, file.size));
    const patchRes = await fetch(location, {
      method: 'PATCH',
      headers: {
        ...authHeaders,
        'Tus-Resumable': '1.0.0',
        'Upload-Offset': String(offset),
        'Content-Type': 'application/offset+octet-stream',
      },
      body: chunk,
    });
    if (!patchRes.ok) {
      throw new Error('Upload interrupted at byte ' + offset + ' (HTTP ' + patchRes.status + ').');
    }
    const newOffset = parseInt(patchRes.headers.get('Upload-Offset') || '', 10);
    offset = Number.isFinite(newOffset) ? newOffset : offset + chunk.size;
    if (onProgress) onProgress(Math.min(100, Math.round((offset / file.size) * 100)));
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('bunny-upload-form');
  if (!form) return;

  const statusEl = document.getElementById('bunny-upload-status');
  const barEl = document.getElementById('bunny-upload-bar');
  const submitBtn = form.querySelector('button[type="submit"]');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const title = form.querySelector('[name="title"]').value.trim();
    const collectionId = form.querySelector('[name="collection_id"]').value;
    const file = form.querySelector('[name="video_file"]').files[0];
    if (!title || !file) {
      statusEl.textContent = 'Title and a video file are both required.';
      return;
    }

    submitBtn.disabled = true;
    statusEl.textContent = 'Creating video on bunny.net…';
    try {
      const createRes = await fetch('bunny_create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, collection_id: collectionId, csrf: window.CSRF_TOKEN }),
      });
      const createData = await createRes.json();
      if (!createRes.ok || !createData.ok) throw new Error(createData.error || 'Could not create the video.');

      statusEl.textContent = 'Uploading to bunny.net…';
      barEl.style.display = 'block';
      await bunnyTusUpload(file, { ...createData.upload, title }, (pct) => {
        barEl.querySelector('span').style.width = pct + '%';
        statusEl.textContent = `Uploading to bunny.net… ${pct}%`;
      });

      statusEl.textContent = 'Upload complete — bunny.net is encoding the video…';
      const finalizeRes = await fetch('bunny_finalize.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ video_row_id: createData.video_row_id, csrf: window.CSRF_TOKEN }),
      });
      const finalizeData = await finalizeRes.json();
      statusEl.textContent = finalizeData.status === 'ready'
        ? 'Ready! Reloading…'
        : 'Uploaded — still encoding on bunny.net. It will show as ready once finished (reload the page to check).';
      setTimeout(() => window.location.reload(), 1500);
    } catch (err) {
      statusEl.textContent = 'Error: ' + err.message;
      submitBtn.disabled = false;
    }
  });
});
