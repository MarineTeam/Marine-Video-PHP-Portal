<?php
require_once __DIR__ . '/../config.php';

$seriesId = (int)($_GET['series_id'] ?? 0);
$admin = require_capability('manage_videos', 'series', $seriesId);

if (!bunny_stream_configured()) { flash('error', 'bunny.net is not configured.'); redirect('series.php'); }

$sStmt = db()->prepare('SELECT * FROM series WHERE id = ?'); $sStmt->execute([$seriesId]);
$series = $sStmt->fetch();
if (!$series) { flash('error', 'Pick a series first.'); redirect('series.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'import') {
        $guid = $_POST['guid'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $info = bunny_get_video($guid);
        if (!$info) {
            flash('error', 'Could not fetch that video from bunny.net.');
        } else {
            $maxPos = (int)(db()->query("SELECT COALESCE(MAX(position),0) m FROM videos WHERE series_id = $seriesId")->fetch()['m']);
            $status = bunny_status_to_local((int)($info['status'] ?? 0));
            $duration = (int)($info['length'] ?? 0);
            $finalTitle = $title !== '' ? $title : ($info['title'] ?? $guid);
            $slug = unique_slug('videos', $series['title'] . '-' . $finalTitle);
            db()->prepare('INSERT INTO videos (series_id, title, slug, bunny_video_id, position, status, duration_seconds) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$seriesId, $finalTitle, $slug, $guid, $maxPos + 1, $status, $duration]);
            audit_log('video.import_bunny', $guid);
            if ($status === 'ready') do_action('content_published', 'video', (int)db()->lastInsertId());
            flash('success', 'Imported.');
        }
    }
    redirect("bunny_import.php?series_id=$seriesId&page=" . (int)($_GET['page'] ?? 1));
}

$page = max(1, (int)($_GET['page'] ?? 1));
$result = bunny_list_videos($page, 50);

$trackedStmt = db()->query('SELECT bunny_video_id FROM videos WHERE bunny_video_id IS NOT NULL');
$tracked = array_flip(array_column($trackedStmt->fetchAll(), 'bunny_video_id'));

$pageTitle = 'Admin · Import from bunny.net · ' . $series['title'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/nav.php';
?>
<p class="muted"><a href="videos.php?series_id=<?= (int)$seriesId ?>">← Back to videos</a></p>
<section>
  <h2>Import from bunny.net into "<?= h($series['title']) ?>"</h2>
  <p class="muted small">Videos already uploaded to your bunny.net library that aren't tracked by any series yet. Importing links to the existing bunny.net video — nothing is re-uploaded.</p>

  <?php if ($result['error']): ?>
    <p class="flash flash-error"><?= h($result['error']) ?></p>
  <?php elseif (!$result['items']): ?>
    <p class="muted">No videos found in your bunny.net library.</p>
  <?php else: ?>
    <table class="admin-table">
      <thead><tr><th>Title</th><th>Status</th><th>Uploaded</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($result['items'] as $v): $isTracked = isset($tracked[$v['guid']]); ?>
          <tr>
            <td><?= h($v['title'] ?? $v['guid']) ?></td>
            <td><?= h(bunny_status_to_local((int)($v['status'] ?? 0))) ?></td>
            <td><?= h(substr($v['dateUploaded'] ?? '', 0, 10)) ?></td>
            <td>
              <?php if ($isTracked): ?>
                <span class="muted small">Already in a series</span>
              <?php else: ?>
                <form method="post" class="inline-form">
                  <?= csrf_field() ?><input type="hidden" name="action" value="import">
                  <input type="hidden" name="guid" value="<?= h($v['guid']) ?>">
                  <input type="text" name="title" value="<?= h($v['title'] ?? '') ?>" placeholder="Title">
                  <button class="btn small" type="submit">Import</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="pagination">
      <?php if ($page > 1): ?><a href="?series_id=<?= $seriesId ?>&page=<?= $page - 1 ?>">Previous</a><?php endif; ?>
      <span class="muted">Page <?= $page ?> · <?= $result['totalItems'] ?> total videos in library</span>
      <?php if ($page * 50 < $result['totalItems']): ?><a href="?series_id=<?= $seriesId ?>&page=<?= $page + 1 ?>">Next</a><?php endif; ?>
    </div>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
