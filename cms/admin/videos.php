<?php
require_once __DIR__ . '/../config.php';
$seriesId = (int)($_GET['series_id'] ?? 0);
$admin = require_capability('manage_videos', 'series', $seriesId);

$sStmt = db()->prepare('SELECT * FROM series WHERE id = ?'); $sStmt->execute([$seriesId]);
$series = $sStmt->fetch();
if (!$series) { flash('error', 'Pick a series first.'); redirect('series.php'); }

if (bunny_stream_configured()) {
    $pending = db()->prepare("SELECT id, bunny_video_id FROM videos WHERE series_id = ? AND status = 'processing' AND bunny_video_id IS NOT NULL");
    $pending->execute([$seriesId]);
    foreach ($pending->fetchAll() as $p) {
        $info = bunny_get_video($p['bunny_video_id']);
        if ($info) {
            $newStatus = bunny_status_to_local((int)($info['status'] ?? 0));
            if ($newStatus !== 'processing') db()->prepare('UPDATE videos SET status = ? WHERE id = ?')->execute([$newStatus, $p['id']]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $title = trim($_POST['title'] ?? '');
        $embedUrl = trim($_POST['embed_url'] ?? '');
        $memberOnly = !empty($_POST['member_only']) ? 1 : 0;
        if ($title === '') { flash('error', 'Title required.'); redirect("videos.php?series_id=$seriesId"); }

        $filename = null;
        if (!empty($_FILES['video_file']['name'])) {
            $ext = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['mp4', 'webm', 'mov', 'm4v'], true)) {
                $filename = bin2hex(random_bytes(16)) . '.' . $ext;
                @mkdir(__DIR__ . '/../uploads/videos', 0755, true);
                move_uploaded_file($_FILES['video_file']['tmp_name'], __DIR__ . '/../uploads/videos/' . $filename);
            }
        }
        if (!$filename && $embedUrl === '') { flash('error', 'Provide a file or an embed URL.'); redirect("videos.php?series_id=$seriesId"); }

        $slug = unique_slug('videos', $series['title'] . '-' . $title);
        $maxPos = (int)(db()->query("SELECT COALESCE(MAX(position),0) m FROM videos WHERE series_id = $seriesId")->fetch()['m']);
        db()->prepare('INSERT INTO videos (series_id, title, slug, filename, embed_url, member_only, position) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$seriesId, $title, $slug, $filename, $embedUrl ?: null, $memberOnly, $maxPos + 1]);
        $newVideoId = (int)db()->lastInsertId();
        audit_log('video.upload', $title);
        do_action('content_published', 'video', $newVideoId);
        flash('success', 'Video added.');
        redirect("videos.php?series_id=$seriesId");
    }

    if ($action === 'rename') {
        db()->prepare('UPDATE videos SET title = ? WHERE id = ?')->execute([trim($_POST['title'] ?? ''), (int)$_POST['id']]);
        redirect("videos.php?series_id=$seriesId");
    }

    if ($action === 'toggle_published') {
        db()->prepare('UPDATE videos SET published = 1 - published WHERE id = ?')->execute([(int)$_POST['id']]);
        redirect("videos.php?series_id=$seriesId");
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = db()->prepare('SELECT filename, bunny_video_id, title FROM videos WHERE id = ?'); $stmt->execute([$id]);
        $v = $stmt->fetch();
        if ($v) {
            db()->prepare('DELETE FROM videos WHERE id = ?')->execute([$id]);
            if ($v['filename'] && is_file(__DIR__ . '/../uploads/videos/' . $v['filename'])) unlink(__DIR__ . '/../uploads/videos/' . $v['filename']);
            if ($v['bunny_video_id'] && bunny_stream_configured()) bunny_delete_video($v['bunny_video_id']);
            audit_log('video.delete', $v['title']);
        }
        redirect("videos.php?series_id=$seriesId");
    }

    if ($action === 'move') {
        $id = (int)$_POST['id']; $dir = $_POST['dir'] === 'up' ? 'up' : 'down';
        $stmt = db()->prepare('SELECT id, position FROM videos WHERE series_id = ? ORDER BY position ASC'); $stmt->execute([$seriesId]);
        $rows = $stmt->fetchAll();
        $idx = null;
        foreach ($rows as $i => $r) if ((int)$r['id'] === $id) { $idx = $i; break; }
        if ($idx !== null) {
            $swap = $dir === 'up' ? $idx - 1 : $idx + 1;
            if (isset($rows[$swap])) {
                db()->prepare('UPDATE videos SET position = ? WHERE id = ?')->execute([$rows[$swap]['position'], $rows[$idx]['id']]);
                db()->prepare('UPDATE videos SET position = ? WHERE id = ?')->execute([$rows[$idx]['position'], $rows[$swap]['id']]);
            }
        }
        redirect("videos.php?series_id=$seriesId");
    }
}

$videosStmt = db()->prepare('SELECT * FROM videos WHERE series_id = ? ORDER BY position ASC'); $videosStmt->execute([$seriesId]);
$videos = $videosStmt->fetchAll();

$pageTitle = 'Admin · Videos · ' . $series['title'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>
<p class="muted"><a href="series.php">← All series</a></p>
<h2>Videos in "<?= h($series['title']) ?>"</h2>
<?php if ($series['require_sequential']): ?><p class="muted small">Sequential unlock is ON — this order is also the required watch order.</p><?php endif; ?>

<section>
  <h3>Add a video</h3>
  <form method="post" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?><input type="hidden" name="action" value="upload">
    <label>Title <input type="text" name="title" required></label>
    <label>Video file (mp4/webm/mov) <input type="file" name="video_file" accept="video/*"></label>
    <label>…or embed URL <input type="url" name="embed_url" placeholder="https://"></label>
    <label><input type="checkbox" name="member_only"> Members only</label>
    <button class="btn" type="submit">Add</button>
  </form>
</section>

<?php if (bunny_stream_configured()): ?>
<section>
  <h3>Upload to bunny.net</h3>
  <p class="muted small">Uploads go straight from your browser to bunny.net over resumable TUS. Already have videos on bunny.net from before? <a href="bunny_import.php?series_id=<?= (int)$seriesId ?>">Import them</a> instead of re-uploading.</p>
  <form id="bunny-upload-form" class="card" data-series-id="<?= (int)$seriesId ?>">
    <label>Title <input type="text" name="title" required></label>
    <label>Video file <input type="file" name="video_file" accept="video/*" required></label>
    <label><input type="checkbox" name="member_only"> Members only</label>
    <div id="bunny-upload-bar" style="display:none;height:8px;border-radius:4px;background:rgba(255,255,255,0.1);overflow:hidden;margin:10px 0;"><span style="display:block;height:100%;width:0;background:linear-gradient(90deg,var(--accent1),var(--accent2));"></span></div>
    <p id="bunny-upload-status" class="muted small"></p>
    <button class="btn" type="submit">Upload to bunny.net</button>
  </form>
</section>
<?php endif; ?>

<section>
  <table class="admin-table">
    <thead><tr><th>#</th><th>Title</th><th>Source</th><th>Status</th><th>Views</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($videos as $i => $v): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td>
          <form method="post" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <input type="text" name="title" value="<?= h($v['title']) ?>"><button class="link-btn" type="submit">Save</button>
          </form>
        </td>
        <td><?= $v['bunny_video_id'] ? 'bunny.net' : ($v['embed_url'] ? 'Embed' : 'Local') ?><?= $v['member_only'] ? ' · Members' : '' ?></td>
        <td data-video-status data-video-id="<?= (int)$v['id'] ?>">
          <?= $v['status'] === 'processing' ? 'Processing…' : ($v['status'] === 'failed' ? '<span style="color:var(--danger)">Failed</span>' : ($v['published'] ? 'Published' : 'Draft')) ?>
          <?php if (in_array($v['status'], ['processing', 'failed'], true) && $v['bunny_video_id']): ?>
            <button class="link-btn" data-refresh-status type="button"><?= $v['status'] === 'failed' ? 'Retry check' : 'Refresh' ?></button>
          <?php endif; ?>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_published"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><button class="link-btn" type="submit">Toggle</button></form>
        </td>
        <td><?= (int)$v['view_count'] ?></td>
        <td>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><input type="hidden" name="dir" value="up"><button class="link-btn" type="submit">↑</button></form>
          <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><input type="hidden" name="dir" value="down"><button class="link-btn" type="submit">↓</button></form>
          <a class="link-btn" href="permissions.php?scope_type=video&scope_id=<?= (int)$v['id'] ?>">Access</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this video?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><button class="link-btn danger" type="submit">Delete</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>

<?php if (bunny_stream_configured()): ?>
<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
<script src="../assets/js/bunny-upload.js"></script>
<script src="../assets/js/bunny-status-poll.js"></script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
