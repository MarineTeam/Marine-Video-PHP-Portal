<?php
namespace MarineVideoPortal\Pages;
use MarineVideoPortal\Core\Database;
use MarineVideoPortal\Bunny\BunnyService;
class BundlePage {
  public static function render(string $id){
    $bundle=Database::fetchOne("SELECT * FROM bundles WHERE id=?",[$id]); if(!$bundle) die('Not found');
    $shares=Database::fetchAll("SELECT s.*, vm.title FROM shares s LEFT JOIN videos_meta vm ON vm.guid=s.video_guid WHERE s.bundle_id=? AND s.revoked=0 ORDER BY s.created_at DESC",[$id]);
    $bunny=new BunnyService();
    echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>Private Collection - Marine</title><link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap' rel='stylesheet'><style>
*{box-sizing:border-box} body{margin:0;background:#050508;color:#e8e8ec;font-family:Inter,system-ui}
header{height:64px;display:flex;align-items:center;justify-content:space-between;padding:0 24px;border-bottom:1px solid rgba(255,255,255,.06);background:rgba(10,10,14,.8);backdrop-filter:blur(20px);position:sticky;top:0}
.hero{max-width:1680px;margin:0 auto;padding:40px 24px 20px}
.hero h1{font-size:28px;font-weight:700;margin:0 0 8px;letter-spacing:-.02em}
.hero p{color:#6b6b78;margin:0;font-size:14px}
.grid{max-width:1680px;margin:0 auto;padding:20px 24px;display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px}
.card{background:#111119;border:1px solid rgba(255,255,255,.06);border-radius:16px;overflow:hidden;transition:.25s;cursor:pointer}
.card:hover{transform:translateY(-2px);border-color:rgba(255,255,255,.12)}
.thumb{aspect-ratio:16/9;background:#0d0d12;position:relative;overflow:hidden}
.thumb img{width:100%;height:100%;object-fit:cover}
.thumb:after{content:'';position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.6),transparent)}
.play{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:44px;height:44px;background:#fff;border-radius:50%;display:grid;place-items:center}
.meta{padding:14px}
.meta h3{margin:0 0 4px;font-size:14px;font-weight:500;line-height:1.35}
.badge{font-size:10px;letter-spacing:.08em;text-transform:uppercase;padding:4px 8px;background:#15151d;border:1px solid rgba(255,255,255,.08);border-radius:999px;color:#9a9aa8}
a{color:inherit;text-decoration:none}
</style></head><body>";
    echo "<header><div style='display:flex;align-items:center;gap:10px'><span style='width:28px;height:28px;background:#fff;color:#000;border-radius:8px;display:grid;place-items:center'>▶</span><b style='letter-spacing:.08em;font-size:13px'>MARINE VIDEO</b></div><div style='font-size:12px;color:#6b6b78'>Private Collection</div></header>";
    echo "<div class='hero'><h1>Your private collection</h1><p>".count($shares)." videos • This link is private to you</p></div><div class='grid'>";
    foreach($shares as $s){
      $title=htmlspecialchars($s['title']??$s['video_guid']); $token=htmlspecialchars($s['token']); $guid=htmlspecialchars($s['video_guid']);
      $thumb=$bunny->thumbnailUrl($s['video_guid'],86400);
      $expired=strtotime($s['expires_at'])<time();
      echo "<a href='/s/$token'><div class='card'><div class='thumb'><img src='$thumb' loading='lazy'><div class='play'><svg width='16' height='16' viewBox='0 0 24 24' fill='#000'><path d='M8 5.14v14l11-7-11-7z'/></svg></div></div><div class='meta'><h3>$title</h3><span class='badge'>".($expired?"Expired":"Available")."</span></div></div></a>";
    }
    echo "</div></body></html>";
  }
}
