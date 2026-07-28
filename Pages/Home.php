<?php
namespace MarineVideoPortal\Pages;
use MarineVideoPortal\Core\Database;
use MarineVideoPortal\Models\Video;
use MarineVideoPortal\Bunny\BunnyService;
class Home {
  public static function render(){
    $search=$_GET['q']??''; $collection=$_GET['collection']??''; $page=max(1,(int)($_GET['page']??1));
    $perPage=(int)($_ENV['HOMEPAGE_COUNT']??'24'); $offset=($page-1)*$perPage;
    $videos=Video::all($search,$collection?:null,$perPage,$offset);
    $total=Video::count($search,$collection?:null);
    $collections=Database::fetchAll("SELECT * FROM collections ORDER BY name");
    $bunny=new BunnyService();
    $user=\MarineVideoPortal\Auth\Auth0Service::currentUser();
    $searchEsc=htmlspecialchars($search);
    echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>Marine Video Portal</title><style>body{font-family:system-ui;background:#07070b;color:#eee;margin:0} header{padding:16px 24px;background:#0f0f17;display:flex;justify-content:space-between;align-items:center} .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;padding:24px} .card{background:#16161f;border-radius:14px;overflow:hidden;border:1px solid #222} .thumb{aspect-ratio:16/9;background:#0a0a0f;display:flex;align-items:center;justify-content:center} .meta{padding:12px} input,select{background:#0f0f17;color:#fff;border:1px solid #333;border-radius:8px;padding:10px} a{color:#a78bfa} button{background:#7c3aed;color:#fff;border:0;padding:10px 16px;border-radius:8px;cursor:pointer}</style></head><body>";
    echo "<header><div><b>Marine Video Portal</b></div><div><form method='get' style='display:inline-flex;gap:8px'><input name='q' placeholder='Search' value='$searchEsc'><select name='collection'><option value=''>All</option>";
    foreach($collections as $c){ $sel=$collection===$c['id']?'selected':''; $name=htmlspecialchars($c['name']); $id=htmlspecialchars($c['id']); echo "<option value='$id' $sel>$name</option>"; }
    echo "</select><button>Search</button></form> ";
    if($user) echo "<a href='/admin' style='margin-left:12px'>Admin</a> <a href='/auth/logout' style='margin-left:8px;color:#777'>Logout</a>";
    else echo "<a href='/auth/login'><button>Login</button></a>";
    echo "</div></header><div class='grid'>";
    foreach($videos as $v){
      $thumb=$bunny->thumbnailUrl($v['guid']);
      $title=htmlspecialchars($v['title']??$v['guid']);
      $guid=htmlspecialchars($v['guid']);
      echo "<div class='card'><a href='/watch/$guid'><div class='thumb'><img src='$thumb' style='width:100%;height:100%;object-fit:cover' loading='lazy'></div></a><div class='meta'><h3><a href='/watch/$guid'>$title</a></h3></div></div>";
    }
    echo "</div>";
    $pages=ceil($total/$perPage);
    if($pages>1){
      echo "<div style='padding:24px;text-align:center'>";
      for($i=1;$i<=$pages;$i++){ $q=urlencode($search); echo "<a href='?q=$q&page=$i'><button style='margin:2px'>$i</button></a>"; }
      echo "</div>";
    }
    if(empty($videos)){ echo "<div style='padding:40px;text-align:center;color:#777'><h3>No videos yet</h3><p>Go to <a href='/admin?tab=videos'>Admin - Sync from Bunny</a></p></div>"; }
    echo "</body></html>";
  }
}
