<?php
namespace MarineVideoPortal\Pages;
use MarineVideoPortal\Core\Database;
class BundlePage {
  public static function render(string $id){
    $bundle=Database::fetchOne("SELECT * FROM bundles WHERE id=?",[$id]); if(!$bundle) die('Bundle not found');
    $shares=Database::fetchAll("SELECT s.*, vm.title FROM shares s LEFT JOIN videos_meta vm ON vm.guid=s.video_guid WHERE s.bundle_id=? AND s.revoked=0 ORDER BY s.created_at DESC",[$id]);
    echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>Your videos</title><style>body{font-family:system-ui;background:#07070b;color:#eee;padding:24px} .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px} .card{background:#16161f;padding:14px;border-radius:12px;border:1px solid #222} a{color:#a78bfa} button{background:#7c3aed;color:#fff;border:0;padding:8px 14px;border-radius:8px;cursor:pointer}</style></head><body>";
    echo "<h1>Your private videos</h1><p>".count($shares)." videos in this private collection</p><div class='grid'>";
    foreach($shares as $s){
      $title=htmlspecialchars($s['title']??$s['video_guid']);
      $expired=strtotime($s['expires_at'])<time();
      echo "<div class='card'><h3>$title</h3><p style='color:#888'>".($expired?"Expired":"Available")."</p><a href='/s/".htmlspecialchars($s['token'])."'><button>".($expired?"Unavailable":"Watch")."</button></a></div>";
    }
    echo "</div><p style='margin-top:30px;color:#555;font-size:12px'>Private link - do not share</p></body></html>";
  }
}
