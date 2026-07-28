<?php
namespace MarineVideoPortal\Pages;
use MarineVideoPortal\Core\Database;
use MarineVideoPortal\Bunny\BunnyService;
class BundlePage {
  public static function render(string $id){
    $bundle=Database::fetchOne("SELECT * FROM bundles WHERE id=?",[$id]); if(!$bundle) die('Not found');
    $shares=Database::fetchAll("SELECT s.*, vm.title FROM shares s LEFT JOIN videos_meta vm ON vm.guid=s.video_guid WHERE s.bundle_id=? AND s.revoked=0 ORDER BY s.created_at DESC",[$id]);
    $bunny=new BunnyService();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Private Collection</title><style>body{margin:0;background:#050508;color:#eee;font-family:Inter}header{height:64px;display:flex;align-items:center;padding:0 20px;border-bottom:1px solid rgba(255,255,255,.06)}.grid{max-width:1680px;margin:0 auto;padding:20px;display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px}.card{background:#111119;border:1px solid rgba(255,255,255,.06);border-radius:16px;overflow:hidden;text-decoration:none;color:inherit}.thumb{aspect-ratio:16/9;background:#0d0d12}.thumb img{width:100%;height:100%;object-fit:cover}.meta{padding:12px}</style></head><body>
<header><b>Private Collection</b> <span style="color:#666;margin-left:8px"><?php echo count($shares); ?> videos</span></header>
<div class="grid"><?php foreach($shares as $s): $title=htmlspecialchars($s['title']??$s['video_guid']); $token=htmlspecialchars($s['token']); $thumb=$bunny->thumbnailUrl($s['video_guid'],86400); ?><a class="card" href="/s/<?php echo $token; ?>"><div class="thumb"><img src="<?php echo $thumb; ?>" loading="lazy"></div><div class="meta"><h3><?php echo $title; ?></h3></div></a><?php endforeach; ?></div>
</body></html>
<?php
  }
}
