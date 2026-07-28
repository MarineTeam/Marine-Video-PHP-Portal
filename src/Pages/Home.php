<?php
namespace MarineVideoPortal\Pages;
use MarineVideoPortal\Core\Database;
use MarineVideoPortal\Models\Video;
use MarineVideoPortal\Bunny\BunnyService;
class Home {
  public static function render(){
    \MarineVideoPortal\Auth\Auth0Service::requireApproved();
    $search=$_GET['q']??''; $collection=$_GET['collection']??''; $page=max(1,(int)($_GET['page']??1));
    $perPage=(int)($_ENV['HOMEPAGE_COUNT']??'24'); $offset=($page-1)*$perPage;
    $videos=Video::all($search,$collection?:null,$perPage,$offset);
    $total=Video::count($search,$collection?:null);
    $collections=Database::fetchAll("SELECT * FROM collections ORDER BY name");
    $bunny=new BunnyService();
    $user=\MarineVideoPortal\Auth\Auth0Service::currentUser();
    $searchEsc=htmlspecialchars($search);
    $totalPages=ceil($total/$perPage);
    echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>Marine</title><link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap' rel='stylesheet'><style>
*{box-sizing:border-box} body{margin:0;background:#050508;color:#e8e8ec;font-family:Inter,system-ui,-apple-system;letter-spacing:-.01em}
header{position:sticky;top:0;z-index:50;background:rgba(10,10,14,.8);backdrop-filter:blur(20px);border-bottom:1px solid rgba(255,255,255,.06);height:64px;display:flex;align-items:center;justify-content:space-between;padding:0 24px}
.logo{display:flex;align-items:center;gap:10px;font-weight:700;letter-spacing:.08em;font-size:13px}
.logo i{width:28px;height:28px;background:#fff;color:#000;border-radius:8px;display:grid;place-items:center;font-style:normal}
.search-wrap{flex:1;max-width:480px;margin:0 24px;position:relative}
.search-wrap input{width:100%;background:#14141c;border:1px solid rgba(255,255,255,.08);border-radius:999px;padding:10px 16px 10px 40px;color:#fff;outline:none;font-size:14px}
.search-wrap input::placeholder{color:#6b6b78}
.search-wrap svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);opacity:.5}
.chips{display:flex;gap:8px;overflow:auto;padding:16px 24px;scrollbar-width:none;border-bottom:1px solid rgba(255,255,255,.04)}
.chips::-webkit-scrollbar{display:none}
.pill{white-space:nowrap;padding:8px 14px;border-radius:999px;background:#15151d;border:1px solid rgba(255,255,255,.08);font-size:13px;color:#9a9aa8;text-decoration:none;transition:.2s}
.pill.active,.pill:hover{background:#fff;color:#000;border-color:#fff}
.main{max-width:1680px;margin:0 auto;padding:24px}
.section-title{font-size:13px;letter-spacing:.12em;text-transform:uppercase;color:#6b6b78;margin:8px 0 16px;font-weight:600}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px}
@media(max-width:768px){.grid{grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px}}
.card{group:card; background:#111119;border:1px solid rgba(255,255,255,.06);border-radius:16px;overflow:hidden;transition:.25s;cursor:pointer}
.card:hover{transform:translateY(-2px);border-color:rgba(255,255,255,.15);box-shadow:0 12px 32px rgba(0,0,0,.6)}
.thumb{position:relative;aspect-ratio:16/9;background:#0d0d12;overflow:hidden}
.thumb img{width:100%;height:100%;object-fit:cover;transition:.4s}
.card:hover .thumb img{transform:scale(1.05)}
.thumb:after{content:'';position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.7) 0%,transparent 50%)}
.play{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%) scale(.9);width:48px;height:48px;background:rgba(255,255,255,.9);backdrop-filter:blur(10px);border-radius:50%;display:grid;place-items:center;opacity:0;transition:.2s}
.card:hover .play{opacity:1;transform:translate(-50%,-50%) scale(1)}
.meta{padding:14px 14px 16px}
.meta h3{margin:0 0 6px;font-size:14px;line-height:1.35;font-weight:500;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:38px}
.meta small{color:#6b6b78;font-size:12px}
.topbar-right{display:flex;align-items:center;gap:12px}
.avatar{width:32px;height:32px;border-radius:50%;background:#222;display:grid;place-items:center;font-size:12px}
.btn-ghost{color:#9a9aa8;text-decoration:none;font-size:13px;padding:8px 12px;border-radius:999px;border:1px solid rgba(255,255,255,.08)}
.btn-ghost:hover{background:rgba(255,255,255,.06);color:#fff}
.continue-row{display:flex;gap:16px;overflow:auto;padding-bottom:8px}
.continue-row .card{min-width:320px}
.empty{padding:80px 24px;text-align:center;color:#6b6b78}
.empty h3{color:#fff;font-size:20px;margin-bottom:8px}
</style></head><body>";
    echo "<header><div class='logo'><i>▶</i> MARINE VIDEO</div><div class='search-wrap'><svg width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='#6b6b78' stroke-width='2'><circle cx='11' cy='11' r='6'/><path d='M21 21l-4.3-4.3'/></svg><form method='get'><input name='q' placeholder='Search videos, collections…' value='$searchEsc'></form></div><div class='topbar-right'>";
    if($collection) echo "<a class='btn-ghost' href='/?q=".urlencode($search)."'>Clear filter</a>";
    echo "<a class='btn-ghost' href='/admin'>Admin</a><div class='avatar'>".strtoupper(substr($user['email']??'U',0,1))."</div><a href='/auth/logout' class='btn-ghost'>Logout</a></div></header>";
    echo "<div class='chips'><a class='pill ".($collection===''?'active':'')."' href='/?q=".urlencode($search)."'>All Videos</a>";
    foreach($collections as $c){ $active=$collection===$c['id']?'active':''; $name=htmlspecialchars($c['name']); $id=htmlspecialchars($c['id']); echo "<a class='pill $active' href='/?collection=$id&q=".urlencode($search)."'>$name</a>"; }
    echo "</div>";
    echo "<div class='main'>";
    // Continue watching
    if(isset($_SESSION['viewer_email'])){
      $continue=Database::fetchAll("SELECT wp.*, vm.title FROM watch_progress wp JOIN videos_meta vm ON wp.video_guid=vm.guid WHERE wp.viewer_email=? AND wp.completed=0 ORDER BY wp.updated_at DESC LIMIT 8",[$_SESSION['viewer_email']]);
      if($continue){
        echo "<div class='section-title'>Continue Watching</div><div class='continue-row'>";
        foreach($continue as $c){
          $guid=htmlspecialchars($c['video_guid']); $title=htmlspecialchars($c['title']); $pct=(int)$c['progress_percent'];
          echo "<div class='card' onclick="location.href='/watch/$guid'"><div class='thumb'><div style='width:100%;height:100%;background:#1a1a24;display:grid;place-items:center;color:#444'>$title</div><div style='position:absolute;bottom:0;left:0;height:3px;width:$pct%;background:#fff'></div></div><div class='meta'><h3>$title</h3><small>$pct% watched</small></div></div>";
        }
        echo "</div>";
      }
    }
    echo "<div class='section-title' style='margin-top:24px'>".($search?"Search results for '$searchEsc'":"Latest")." <span style='color:#3a3a44;font-weight:400'>{$total} videos</span></div>";
    echo "<div class='grid'>";
    foreach($videos as $v){
      $thumb=$bunny->thumbnailUrl($v['guid'],86400);
      $title=htmlspecialchars($v['title']??$v['guid']);
      $guid=htmlspecialchars($v['guid']);
      $colName=htmlspecialchars($v['collection_name']??'');
      echo "<div class='card' onclick="location.href='/watch/$guid'"><div class='thumb'><img src='$thumb' loading='lazy' alt=''><div class='play'><svg width='18' height='18' viewBox='0 0 24 24' fill='#000'><path d='M8 5.14v14l11-7-11-7z'/></svg></div></div><div class='meta'><h3>$title</h3><small>".($colName?:'Unlisted')."</small></div></div>";
    }
    echo "</div>";
    if($totalPages>1){
      echo "<div style='display:flex;justify-content:center;gap:6px;margin:32px 0'>"; 
      for($i=1;$i<=$totalPages;$i++){ $active=$i==$page?'style="background:#fff;color:#000;border-color:#fff"':''; echo "<a href='/?q=".urlencode($search)."&collection=".urlencode($collection)."&page=$i' style='text-decoration:none'><span class='pill' $active>$i</span></a>"; }
      echo "</div>";
    }
    if(empty($videos)){ echo "<div class='empty'><h3>No videos found</h3><p>Try a different search or sync from Bunny in admin.</p><a href='/admin?tab=videos' class='pill' style='margin-top:16px;display:inline-block;background:#fff;color:#000'>Go to Admin</a></div>"; }
    echo "</div></body></html>";
  }
}
