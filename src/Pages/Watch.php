<?php
namespace MarineVideoPortal\Pages;
use MarineVideoPortal\Bunny\BunnyService;
use MarineVideoPortal\Models\Video;
use MarineVideoPortal\Core\Database;
use MarineVideoPortal\Services\WatermarkService;
class Watch {
  public static function render(string $guid){
    \MarineVideoPortal\Auth\Auth0Service::requireApproved();
    $meta=Video::find($guid); if(!$meta){ http_response_code(404); die('Not found'); }
    $bunny=new BunnyService(); $embed=$bunny->signedEmbedUrl($guid);
    $email=$_SESSION['user']['email']??'';
    $wmMode=WatermarkService::resolve($email,$guid);
    $wmText=WatermarkService::displayText($wmMode,$email);
    $title=htmlspecialchars($meta['title']??$guid);
    $related=Database::fetchAll("SELECT * FROM videos_meta WHERE guid!=? ORDER BY custom_order ASC, created_at DESC LIMIT 12",[$guid]);
    echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>$title - Marine</title><link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap' rel='stylesheet'><style>
*{box-sizing:border-box} body{margin:0;background:#050508;color:#e8e8ec;font-family:Inter,system-ui}
header{height:56px;display:flex;align-items:center;justify-content:space-between;padding:0 20px;border-bottom:1px solid rgba(255,255,255,.06);background:rgba(10,10,14,.8);backdrop-filter:blur(20px);position:sticky;top:0;z-index:10}
.layout{max-width:1680px;margin:0 auto;display:grid;grid-template-columns:1fr 360px;gap:0;min-height:calc(100vh - 56px)}
@media(max-width:1024px){.layout{grid-template-columns:1fr} .side{border-left:0!important;border-top:1px solid rgba(255,255,255,.06)}}
.player-col{background:#000}
.player-wrap{position:relative;aspect-ratio:16/9;background:#000}
.player-wrap iframe{width:100%;height:100%;border:0}
.side{background:#0a0a0e;border-left:1px solid rgba(255,255,255,.06);padding:20px;overflow:auto}
.side h2{font-size:16px;line-height:1.4;margin:0 0 8px}
.meta-row{color:#6b6b78;font-size:12px;margin-bottom:16px}
.related{display:grid;gap:12px}
.rel{display:flex;gap:10px;cursor:pointer;border-radius:10px;padding:6px;transition:.2s}
.rel:hover{background:#15151d}
.rel-thumb{width:120px;aspect-ratio:16/9;background:#14141c;border-radius:8px;overflow:hidden;flex-shrink:0}
.rel-thumb img{width:100%;height:100%;object-fit:cover}
.rel-title{font-size:13px;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.wm{position:absolute;bottom:16px;right:16px;background:rgba(0,0,0,.35);padding:4px 8px;border-radius:6px;font-size:11px;opacity:.5}
a{color:#a78bfa;text-decoration:none}
.btn{padding:8px 14px;border-radius:999px;background:#fff;color:#000;font-size:13px;font-weight:500;text-decoration:none;display:inline-block}
</style></head><body>";
    echo "<header><div style='display:flex;align-items:center;gap:12px'><a href='/' style='color:#fff;text-decoration:none;display:flex;align-items:center;gap:8px'><span style='width:24px;height:24px;background:#fff;color:#000;border-radius:6px;display:grid;place-items:center;font-size:10px'>▶</span> <b style='font-size:13px;letter-spacing:.08em'>MARINE</b></a> <span style='color:#3a3a44'>/</span> <span style='font-size:13px;color:#9a9aa8'>Watch</span></div><div><a href='/' class='btn'>Back to library</a></div></header>";
    echo "<div class='layout'><div class='player-col'><div class='player-wrap'><iframe src='$embed' allowfullscreen allow='autoplay; fullscreen; picture-in-picture'></iframe>";
    if($wmText!=='') echo "<div class='wm'>".htmlspecialchars($wmText)."</div>";
    echo "</div><div style='padding:20px'><h1 style='margin:0 0 8px;font-size:20px;font-weight:600'>$title</h1><div class='meta-row'>Private • GUID ".substr($guid,0,8)."</div><div style='margin-top:16px;display:flex;gap:8px'><span style='background:#15151d;border:1px solid rgba(255,255,255,.08);padding:6px 10px;border-radius:999px;font-size:12px;color:#9a9aa8'>HD</span><span style='background:#15151d;border:1px solid rgba(255,255,255,.08);padding:6px 10px;border-radius:999px;font-size:12px;color:#9a9aa8'>Bunny Stream</span></div></div></div>";
    echo "<div class='side'><div style='font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#6b6b78;margin-bottom:12px;font-weight:600'>Up next</div><div class='related'>";
    foreach($related as $r){
      $rg=htmlspecialchars($r['guid']); $rt=htmlspecialchars($r['title']??$rg); $thumb=$bunny->thumbnailUrl($r['guid'],86400);
      echo "<div class='rel' onclick="location.href='/watch/$rg'"><div class='rel-thumb'><img src='$thumb' loading='lazy'></div><div><div class='rel-title'>$rt</div><div style='font-size:11px;color:#6b6b78;margin-top:4px'>".htmlspecialchars(substr($r['guid'],0,8))."</div></div></div>";
    }
    echo "</div></div></div></body></html>";
  }
}
