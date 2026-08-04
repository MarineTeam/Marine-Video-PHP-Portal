<?php
require_once __DIR__ . '/../config.php';
$seriesId = (int)($_GET['series_id'] ?? 0);
$admin = require_capability('manage_files', 'series', $seriesId);

$sStmt = db()->prepare('SELECT * FROM series WHERE id = ?'); $sStmt->execute([$seriesId]);
$series = $sStmt->fetch();
if (!$series) { flash('error', 'Pick a series first.'); redirect('series.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $title = trim($_POST['title'] ?? '');
        $memberOnly = !empty($_POST['member_only']) ? 1 : 0;
        if ($title === '' || empty($_FILES['file']['name'])) { flash('error', 'Title and a file are required.'); redirect("files.php?series_id=$seriesId"); }

        $size = $_FILES['file']['size'];
        $origName = $_FILES['file']['name'];
        $safeName = bin2hex(random_bytes(8)) . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);

        if (bunny_storage_configured() && $size > LOCAL_FILE_UPLOAD_LIMIT) {
            [$ok, $urlOrErr] = bunny_storage_upload($_FILES['file']['tmp_name'], $safeName);
            if (!$ok) { flash('error', "Upload to bunny.net Storage failed: $urlOrErr"); redirect("files.php?series_id=$seriesId"); }
            $storageUrl = $urlOrErr;
            $filename = $safeName;
        } elseif ($size <= LOCAL_FILE_UPLOAD_LIMIT) {
            @mkdir(__DIR__ . '/../uploads/files', 0755, true);
            move_uploaded_file($_FILES['file']['tmp_name'], __DIR__ . '/../uploads/files/' . $safeName);
            $storageUrl = SITE_URL . '/uploads/files/' . $safeName;
            $filename = $safeName;
        } else {
            flash('error', 'File exceeds the local upload limit and bunny.net Storage is not configured for larger files.');
            redirect("files.php?series_id=$seriesId");
        }

        $maxPos = (int)(db()->query("SELECT COALESCE(MAX(position),0) m FROM files WHERE series_id = $seriesId")->fetch()['m']);
        db()->prepare('INSERT INTO files (series_id, title, storage_url, filename, size_bytes, member_only, position) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$seriesId, $title, $storageUrl, $filename, $size, $memberOnly, $maxPos + 1]);
        audit_log('file.upload', $title);
        flash('success', 'File added.');
        redirect("files.php?series_id=$seriesId");
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = db()->prepare('SELECT filename, title FROM files WHERE id = ?'); $stmt->execute([$id]);
        $f = $stmt->fetch();
        if ($f) {
            db()->prepare('DELETE FROM files WHERE id = ?')->execute([$id]);
            if ($f['filename']) {
                $localPath = __DIR__ . '/../uploads/files/' . $f['filename'];
                if (is_file($localPath)) unlink($localPath);
                elseif (bunny_storage_configured()) bunny_storage_delete($f['filename']);
            }
            audit_log('file.delete', $f['title']);
        }
        redirect("files.php?series_id=$seriesId");
    }
}

$filesStmt = db()->prepare('SELECT * FROM files WHERE series_id = ? ORDER BY position ASC'); $filesStmt->execute([$seriesId]);
$files = $filesStmt->fetchAll();

$pageTitle = 'Admin · Files · ' . $series['title'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>
<p class="muted"><a href="series.php">← All series</a></p>
<h2>Files in "<?= h($series['title']) ?>"</h2>
<p class="muted small">Files up to <?= round(LOCAL_FILE_UPLOAD_LIMIT / 1048576, 1) ?> MB are stored on this server. Larger files require bunny.net Storage to be configured.</p>

<section>
  <form method="post" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?><input type="hidden" name="action" value="upload">
    <label>Title <input type="text" name="title" required></label>
    <label>File <input type="file" name="file" required></label>
    <label><input type="checkbox" name="member_only"> Members only</label>
    <button class="btn" type="submit">Upload</button>
  </form>
</section>

<section>
  <table class="admin-table">
    <thead><tr><th>Title</th><th>Size</th><th></th><th></th></tr></thead>
    <tbody>
      <?php foreach ($files as $f): ?>
        <tr>
          <td><?= h($f['title']) ?><?= $f['member_only'] ? ' <span class="badge">Members</span>' : '' ?></td>
          <td><?= number_format($f['size_bytes'] / 1048576, 1) ?> MB</td>
          <td><a class="link-btn" href="<?= h($f['storage_url']) ?>" target="_blank">Download</a></td>
          <td><form method="post" onsubmit="return confirm('Delete this file?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="link-btn danger" type="submit">Delete</button></form></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
