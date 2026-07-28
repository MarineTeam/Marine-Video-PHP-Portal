<?php
namespace MarineVideoPortal\Pages;
use MarineVideoPortal\Core\Database;
use MarineVideoPortal\Models\Video;
use MarineVideoPortal\Bunny\BunnyService;
class Home {
  public static function render(){
    \MarineVideoPortal\Auth\Auth0Service::requireApproved();
    $search=$_GET['q']??'';
    $collection=$_GET['collection']??'';
    $videos=Video::all($search,$collection?:null,48,0);
    $collections=Database::fetchAll("SELECT * FROM collections ORDER BY name");
    $bunny=new BunnyService();
    $searchEsc=htmlspecialchars($search);
    ?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Marine Video</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}body{margin:0;background:#050508;color:#e8e8ec;font-family:Inter,system-ui}
header{position:sticky;top:0;z-index:10;background:rgba(10,10,14,.9);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.06);height:64px;display:flex;align-items:center;justify-content:space-between;padding:0 20px}
.logo{display:flex;align-items:center;gap:10px;font-weight:700;letter-spacing:.08em;font-size:13px}
.logo i{width:28px;height:28px;background:#fff;color:#000;border-radius:8px;display:grid;place-items:center}
.search-wrap{flex:1;max-width:480px;margin:0 20px}.search-wrap input{width:100%;background:#14141c;border:1px solid rgba(255,255,255,.08);border-radius:999px;padding:10px 16px;color:#fff}
.chips{display:flex;gap:8px;overflow:auto;padding:14px 20px;border-bottom:1px solid rgba(255,255,255,.04)}.pill{white-space:nowrap;padding:7px 14px;border-radius:999px;background:#15151d;border:1px solid rgba(255,255,255,.08);font-size:13px;color:#9a9aa8;text-decoration:none}.pill.active{background:#fff;color:#000}
.main{max-width:1680px;margin:0 auto;padding:20px}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px}
.card{background:#111119;border:1px solid rgba(255,255,255,.06);border-radius:16px;overflow:hidden;display:block;text-decoration:none;color:inherit;transition:.2s}.card:hover{transform:translateY(-2px);border-color:rgba(255,255,255,.15)}
.thumb{position:relative;aspect-ratio:16/9;background:#0d0d12;overflow:hidden}.thumb img{width:100%;height:100%;object-fit:cover}.play{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:44px;height:44px;background:#fff;border-radius:50%;display:grid;place-items:center;opacity:0;transition:.2s}.card:hover .play{opacity:1}
.meta{padding:12px 14px}.meta h3{margin:0 0 4px;font-size:14px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.meta small{color:#6b6b78;font-size:12px}
</style></head><body>
<header><div class="logo"><i>▶</i> MARINE VIDEO</div><div class="search-wrap"><form method="get"><input name="q" placeholder="Search..." value="<?php echo $searchEsc; ?>"></form></div><div><a href="/admin" style="color:#9a9aa8;text-decoration:none;font-size:13px;margin-right:12px">Admin</a><a href="/auth/logout" style="color:#666;text-decoration:none;font-size:13px">Logout</a></div></header>
<div class="chips"><a class="pill <?php echo $collection===''?'active':''; ?>" href="/?q=<?php echo urlencode($search); ?>">All</a><?php foreach($collections as $c): ?><a class="pill <?php echo $collection===$c['id']?'active':''; ?>" href="/?collection=<?php echo htmlspecialchars($c['id']); ?>&q=<?php echo urlencode($search); ?>"><?php echo htmlspecialchars($c['name']); ?></a><?php endforeach; ?></div>
<div class="main"><div style="color:#6b6b78;font-size:13px;margin-bottom:12px"><?php echo count($videos); ?> videos</div><div class="grid"><?php foreach($videos as $v): $thumb=$bunny->thumbnailUrl($v['guid'],86400); $title=htmlspecialchars($v['title']??$v['guid']); $guid=htmlspecialchars($v['guid']); ?><a class="card" href="/watch/<?php echo $guid; ?>"><div class="thumb"><img src="<?php echo $thumb; ?>" loading="lazy"><div class="play">▶</div></div><div class="meta"><h3><?php echo $title; ?></h3><small><?php echo htmlspecialchars($v['collection_name']??''); ?></small></div></a><?php endforeach; ?></div></div>
</body></html>
<?php
  }
}
