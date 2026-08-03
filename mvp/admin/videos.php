<?php
require_once __DIR__ . '/../config.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        if (!rate_limit_check('upload:' . $admin['email'], RATE_LIMIT_UPLOAD_MAX)) {
            flash('error', 'Too many uploads in a short time. Try again shortly.');
        } else {
            $title = trim($_POST['title'] ?? '');
            $embedUrl = trim($_POST['embed_url'] ?? '');
            $collectionId = ($_POST['collection_id'] ?? '') !== '' ? (int)$_POST['collection_id'] : null;

            if ($title === '') {
                flash('error', 'Title is required.');
            } else {
                $filename = null;
                if (!empty($_FILES['video_file']['name'])) {
                    if ($_FILES['video_file']['size'] > MAX_UPLOAD_BYTES) {
                        flash('error', 'File exceeds the maximum upload size.');
                        redirect('videos.php');
                    }
                    $ext = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
                    $allowed = ['mp4', 'webm', 'mov', 'm4v'];
                    if (!in_array($ext, $allowed, true)) {
                        flash('error', 'Unsupported video format. Use mp4, webm, mov, or m4v — or paste an embed URL instead.');
                        redirect('videos.php');
                    }
                    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
                    if (!is_dir(UPLOAD_VIDEO_DIR)) mkdir(UPLOAD_VIDEO_DIR, 0755, true);
                    move_uploaded_file($_FILES['video_file']['tmp_name'], UPLOAD_VIDEO_DIR . '/' . $filename);
                }

                $thumbFilename = null;
                if (!empty($_FILES['thumbnail']['name'])) {
                    $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        $thumbFilename = bin2hex(random_bytes(16)) . '.' . $ext;
                        if (!is_dir(UPLOAD_THUMB_DIR)) mkdir(UPLOAD_THUMB_DIR, 0755, true);
                        move_uploaded_file($_FILES['thumbnail']['tmp_name'], UPLOAD_THUMB_DIR . '/' . $thumbFilename);
                    }
                }

                if (!$filename && $embedUrl === '') {
                    flash('error', 'Provide either a video file or an embed URL.');
                } else {
                    // New uploads float to the top (newest first) until manually reordered.
                    $minOrder = (int)(db()->query('SELECT COALESCE(MIN(sort_order), 0) m FROM videos')->fetch()['m']);
                    db()->prepare('INSERT INTO videos (title, filename, embed_url, thumbnail, collection_id, sort_order, status)
                                    VALUES (?, ?, ?, ?, ?, ?, "ready")')
                        ->execute([$title, $filename, $embedUrl ?: null, $thumbFilename, $collectionId, $minOrder - 1]);
                    log_activity('video.upload', $title);
                    flash('success', 'Video added.');
                }
            }
        }
        redirect('videos.php');
    }

    if ($action === 'rename') {
        $id = (int)$_POST['id'];
        $title = trim($_POST['title'] ?? '');
        if ($title !== '') {
            db()->prepare('UPDATE videos SET title = ? WHERE id = ?')->execute([$title, $id]);
            log_activity('video.rename', "#$id -> $title");
            flash('success', 'Renamed.');
        }
        redirect('videos.php');
    }

    if ($action === 'set_collection') {
        $id = (int)$_POST['id'];
        $collectionId = ($_POST['collection_id'] ?? '') !== '' ? (int)$_POST['collection_id'] : null;
        db()->prepare('UPDATE videos SET collection_id = ? WHERE id = ?')->execute([$collectionId, $id]);
        log_activity('video.set_collection', "#$id");
        redirect('videos.php');
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = db()->prepare('SELECT filename, thumbnail, title, bunny_video_id FROM videos WHERE id = ?');
        $stmt->execute([$id]);
        $v = $stmt->fetch();
        if ($v) {
            db()->prepare('DELETE FROM videos WHERE id = ?')->execute([$id]);
            if ($v['filename'] && is_file(UPLOAD_VIDEO_DIR . '/' . $v['filename'])) unlink(UPLOAD_VIDEO_DIR . '/' . $v['filename']);
            if ($v['thumbnail'] && is_file(UPLOAD_THUMB_DIR . '/' . $v['thumbnail'])) unlink(UPLOAD_THUMB_DIR . '/' . $v['thumbnail']);
            if ($v['bunny_video_id'] && bunny_is_configured()) bunny_delete_video($v['bunny_video_id']);
            log_activity('video.delete', $v['title']);
            flash('success', 'Video deleted.');
        }
        redirect('videos.php');
    }

    if ($action === 'move') {
        $id = (int)$_POST['id'];
        $dir = $_POST['dir'] === 'up' ? 'up' : 'down';
        $stmt = db()->prepare('SELECT id, sort_order FROM videos ORDER BY sort_order ASC, id DESC');
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $idx = null;
        foreach ($rows as $i => $r) { if ((int)$r['id'] === $id) { $idx = $i; break; } }
        if ($idx !== null) {
            $swapWith = $dir === 'up' ? $idx - 1 : $idx + 1;
            if (isset($rows[$swapWith])) {
                $a = $rows[$idx];
                $b = $rows[$swapWith];
                db()->prepare('UPDATE videos SET sort_order = ? WHERE id = ?')->execute([$b['sort_order'], $a['id']]);
                db()->prepare('UPDATE videos SET sort_order = ? WHERE id = ?')->execute([$a['sort_order'], $b['id']]);
            }
        }
        redirect('videos.php');
    }

    if ($action === 'create_collection') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            db()->prepare('INSERT INTO collections (name) VALUES (?)')->execute([$name]);
            log_activity('collection.create', $name);
            flash('success', 'Collection created.');
        }
        redirect('videos.php');
    }

    if ($action === 'delete_collection') {
        $id = (int)$_POST['id'];
        db()->prepare('DELETE FROM collections WHERE id = ?')->execute([$id]);
        log_activity('collection.delete', "#$id");
        redirect('videos.php');
    }

    if ($action === 'create_share') {
        if (!rate_limit_check('share:' . $admin['email'], RATE_LIMIT_SHARE_MAX)) {
            flash('error', 'Too many share links created recently. Try again shortly.');
            redirect('videos.php');
        }
        $videoId = (int)$_POST['video_id'];
        $email = normalize_email($_POST['recipient_email'] ?? '');
        $hours = min(SHARE_MAX_HOURS, max(1, (int)($_POST['hours'] ?? SHARE_DEFAULT_HOURS)));
        $sendEmail = !empty($_POST['send_email']);

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a valid recipient email.');
            redirect('videos.php');
        }

        $token = bin2hex(random_bytes(24));
        $expiresAt = date('Y-m-d H:i:s', time() + $hours * 3600);
        db()->prepare('INSERT INTO share_links (token, video_id, recipient_email, expires_at, created_by)
                        VALUES (?, ?, ?, ?, ?)')
            ->execute([$token, $videoId, $email, $expiresAt, $admin['email']]);
        log_activity('share.create', "video #$videoId -> $email");

        $watchUrl = SITE_URL . '/share.php?t=' . $token;
        if ($sendEmail && resend_is_configured()) {
            $vStmt = db()->prepare('SELECT title FROM videos WHERE id = ?');
            $vStmt->execute([$videoId]);
            $vTitle = $vStmt->fetch()['title'] ?? 'a video';
            [$ok, $err] = send_share_link_email($email, $vTitle, $watchUrl, date('M j, Y g:ia', strtotime($expiresAt)) . ' UTC');
            if ($ok) {
                db()->prepare('UPDATE share_links SET emailed_at = NOW() WHERE token = ?')->execute([$token]);
                log_activity('share.email', $email);
                flash('success', "Share link created and emailed to $email.");
            } else {
                flash('error', "Share link created, but the email failed to send: $err. Copy the link from the Shares tab instead.");
            }
        } else {
            flash('success', 'Share link created. Copy it from the Shares tab to send manually.');
        }
        redirect('videos.php');
    }
}

// Refresh encoding-status badges for any videos still processing on bunny.net.
if (bunny_is_configured()) {
    $pending = db()->query("SELECT id, bunny_video_id, title FROM videos WHERE status = 'processing' AND bunny_video_id IS NOT NULL")->fetchAll();
    foreach ($pending as $p) {
        $info = bunny_get_video($p['bunny_video_id']);
        if ($info) {
            $newStatus = bunny_status_to_local((int)($info['status'] ?? 0));
            if ($newStatus !== 'processing') {
                db()->prepare('UPDATE videos SET status = ? WHERE id = ?')->execute([$newStatus, $p['id']]);
            }
        }
    }
}

$search = trim($_GET['q'] ?? '');
$sql = 'SELECT v.*, c.name AS collection_name FROM videos v LEFT JOIN collections c ON c.id = v.collection_id';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE v.title LIKE ?';
    $params[] = '%' . $search . '%';
}
$sql .= ' ORDER BY v.sort_order ASC, v.id DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$videos = $stmt->fetchAll();
$collections = db()->query('SELECT * FROM collections ORDER BY name')->fetchAll();

$pageTitle = 'Admin · Videos';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>

<section>
  <h2>Upload / add a video</h2>
  <form method="post" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="upload">
    <label>Title <input type="text" name="title" required></label>
    <label>Video file (mp4/webm/mov) <input type="file" name="video_file" accept="video/*"></label>
    <label>…or an embed URL (iframe embed from any provider) <input type="url" name="embed_url" placeholder="https://"></label>
    <label>Thumbnail (optional) <input type="file" name="thumbnail" accept="image/*"></label>
    <label>Collection
      <select name="collection_id">
        <option value="">None</option>
        <?php foreach ($collections as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn" type="submit">Upload</button>
  </form>
</section>

<?php if (bunny_is_configured()): ?>
<section>
  <h2>Upload to bunny.net</h2>
  <p class="muted small">Uploads go straight from your browser to bunny.net over resumable TUS — the video never passes through this server. Encoding happens on bunny's side; the video shows "Processing…" until it's ready. Already have videos on bunny.net from before? <a href="bunny_import.php">Import them</a> instead of re-uploading.</p>
  <form id="bunny-upload-form" class="card">
    <label>Title <input type="text" name="title" required></label>
    <label>Video file <input type="file" name="video_file" accept="video/*" required></label>
    <label>Collection
      <select name="collection_id">
        <option value="">None</option>
        <?php foreach ($collections as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div id="bunny-upload-bar" style="display:none;height:8px;border-radius:4px;background:rgba(255,255,255,0.1);overflow:hidden;margin:10px 0;">
      <span style="display:block;height:100%;width:0;background:linear-gradient(90deg,var(--accent1),var(--accent2));"></span>
    </div>
    <p id="bunny-upload-status" class="muted small"></p>
    <button class="btn" type="submit">Upload to bunny.net</button>
  </form>
</section>
<?php endif; ?>

<section>
  <h2>Collections</h2>
  <div class="card">
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_collection">
      <input type="text" name="name" placeholder="New collection name" required>
      <button class="btn" type="submit">Add</button>
    </form>
    <ul class="chip-list">
      <?php foreach ($collections as $c): ?>
        <li class="chip">
          <?= h($c['name']) ?>
          <form method="post" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_collection">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button class="link-btn" onclick="return confirm('Delete this collection?')">×</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<section>
  <h2>Library (<?= count($videos) ?>)</h2>
  <form method="get" class="toolbar"><input type="search" name="q" value="<?= h($search) ?>" placeholder="Search…"><button class="btn" type="submit">Search</button></form>

  <table class="admin-table">
    <thead><tr><th></th><th>Title</th><th>Collection</th><th>Views</th><th>Watch time</th><th>Order</th><th>Share</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($videos as $v): ?>
      <tr>
        <td class="thumb-cell">
          <?php
            $adminThumb = $v['thumbnail'] ? '../uploads/thumbs/' . $v['thumbnail'] : ($v['bunny_video_id'] ? bunny_thumbnail_url($v['bunny_video_id']) : null);
          ?>
          <?php if ($adminThumb): ?><img src="<?= h($adminThumb) ?>" width="80"><?php else: ?>—<?php endif; ?>
        </td>
        <td>
          <form method="post" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="rename">
            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <input type="text" name="title" value="<?= h($v['title']) ?>">
            <button class="link-btn" type="submit">Save</button>
          </form>
          <div class="muted small" data-video-status data-video-id="<?= (int)$v['id'] ?>">
            <?php if ($v['status'] === 'processing'): ?>Processing… <button class="link-btn" data-refresh-status type="button">Refresh</button>
            <?php elseif ($v['status'] === 'failed'): ?><span style="color:var(--danger)">Encoding failed</span> <button class="link-btn" data-refresh-status type="button">Retry check</button>
            <?php elseif ($v['bunny_video_id']): ?>bunny.net
            <?php elseif ($v['embed_url']): ?>External embed
            <?php else: ?>Local file
            <?php endif; ?>
          </div>
        </td>
        <td>
          <form method="post" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="set_collection">
            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <select name="collection_id" onchange="this.form.submit()">
              <option value="">None</option>
              <?php foreach ($collections as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)$v['collection_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <td><?= (int)$v['views'] ?></td>
        <td><?= h(format_duration((int)$v['watch_seconds'])) ?></td>
        <td>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><input type="hidden" name="dir" value="up"><button class="link-btn" type="submit">↑</button></form>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><input type="hidden" name="dir" value="down"><button class="link-btn" type="submit">↓</button></form>
        </td>
        <td>
          <details>
            <summary>Share…</summary>
            <form method="post" class="share-form">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="create_share">
              <input type="hidden" name="video_id" value="<?= (int)$v['id'] ?>">
              <input type="email" name="recipient_email" placeholder="recipient@email.com" required>
              <input type="number" name="hours" value="<?= SHARE_DEFAULT_HOURS ?>" min="1" max="<?= SHARE_MAX_HOURS ?>"> hrs
              <?php if (resend_is_configured()): ?>
                <label><input type="checkbox" name="send_email" value="1" checked> Email the link</label>
              <?php endif; ?>
              <button class="btn small" type="submit">Create link</button>
            </form>
          </details>
        </td>
        <td>
          <form method="post" onsubmit="return confirm('Delete this video permanently?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <button class="link-btn danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>

<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
<script src="../assets/js/bunny-upload.js"></script>
<script src="../assets/js/bunny-status-poll.js"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
