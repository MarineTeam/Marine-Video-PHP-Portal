<?php
namespace MarineVideoPortal\Pages;
use MarineVideoPortal\Core\Database;
use MarineVideoPortal\Models\Video;
use MarineVideoPortal\Bunny\BunnyService;
class Home {
  public static function render(){
    \MarineVideoPortal\Auth\Auth0Service::requireApproved();
    $search=$_GET['q']??'';
    $videos=Video::all($search,null,50,0);
    $bunny=new BunnyService();
    $searchEsc=htmlspecialchars($search);
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Marine</title><style>body{margin:0;background:#050508;color:#eee;font-family:system-ui}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;padding:20px}.card{background:#111119;border:1px solid #222;border-radius:12px;overflow:hidden;display:block;color:inherit;text-decoration:none}.thumb{aspect-ratio:16/9;background:#0d0d12}.thumb img{width:100%;height:100%;object-fit:cover}</style></head><body>
<header style="padding:16px;border-bottom:1px solid #222;display:flex;justify-content:space-between"><b>MARINE VIDEO</b><form method="get"><input name="q" value="<?php echo $searchEsc; ?>" placeholder="Search"></form><a href="/admin" style="color:#a78bfa">Admin</a></header>
<div class="grid">
<?php foreach($videos as $v): $thumb=$bunny->thumbnailUrl($v['guid'],86400); $title=htmlspecialchars($v['title']??''); $guid=htmlspecialchars($v['guid']); ?>
<a class="card" href="/watch/<?php echo $guid; ?>"><div class="thumb"><img src="<?php echo $thumb; ?>" loading="lazy"></div><div style="padding:10px"><?php echo $title; ?></div></a>
<?php endforeach; ?>
</div>
</body></html>
<?php
  }
}
