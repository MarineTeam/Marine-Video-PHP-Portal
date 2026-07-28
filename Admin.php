<?php
namespace MarineVideoPortal\Pages;
use MarineVideoPortal\Core\Database;
use MarineVideoPortal\Bunny\BunnyService;
use MarineVideoPortal\Models\Video;
use MarineVideoPortal\Mail\MailManager;
class Admin {
  public static function render(){
    $user=\MarineVideoPortal\Auth\Auth0Service::currentUser(); if(!$user){ header('Location: /auth/login'); exit; }
    if(!\MarineVideoPortal\Auth\Auth0Service::isAdmin($user['email']??'')){ die('Not admin'); }
    $tab=$_GET['tab']??'videos'; $bunny=new BunnyService();
    if(isset($_POST['action'])){
      $a=$_POST['action'];
      if($a==='sync_bunny'){ try{ $c=$bunny->syncToDb(); $msg="Synced $c videos"; }catch(\Throwable $e){ $msg="Sync error: ".$e->getMessage(); } }
      elseif($a==='update_video'){ Video::updateMeta($_POST['guid'],['title'=>$_POST['title'],'watermark_mode'=>$_POST['watermark_mode']??'default','custom_order'=>(int)($_POST['custom_order']??0)]); $msg="Updated"; }
      elseif($a==='delete_video'){ $g=$_POST['guid']; try{ $bunny->deleteVideo($g); }catch(\Throwable $e){} Video::deleteMeta($g); $msg="Deleted $g"; }
      elseif($a==='create_share'){
        $guid=$_POST['guid']; $email=strtolower(trim($_POST['email'])); $expiry=$_POST['expiry']??date('Y-m-d',strtotime('+30 days')); $wm=$_POST['watermark_override']??'default';
        $bundle=Database::fetchOne("SELECT * FROM bundles WHERE recipient_email=?",[$email]);
        if(!$bundle){ $bid=bin2hex(random_bytes(8)); Database::exec("INSERT INTO bundles (id,recipient_email) VALUES (?,?)",[$bid,$email]); $bundle=['id'=>$bid]; }
        $sid=bin2hex(random_bytes(8)); $token=bin2hex(random_bytes(16));
        Database::exec("INSERT INTO shares (id,token,video_guid,recipient_email,bundle_id,expires_at,watermark_override) VALUES (?,?,?,?,?,?,?)",[$sid,$token,$guid,$email,$bundle['id'],$expiry.' 23:59:59',$wm]);
        $video=Video::find($guid); $watch=rtrim($_ENV['APP_URL'],'/')."/s/$token"; $burl=rtrim($_ENV['APP_URL'],'/')."/b/{$bundle['id']}";
        if(!empty($_POST['notify'])) MailManager::sendShareEmail($email,$video['title']??$guid,$watch,$burl);
        Database::exec("INSERT INTO private_lists (video_guid,email,share_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE share_id=VALUES(share_id)",[$guid,$email,$sid]);
        $msg="Shared with $email - $watch";
      }
      elseif($a==='revoke_share'){ Database::exec("UPDATE shares SET revoked=1 WHERE id=?",[$_POST['share_id']]); $msg="Revoked"; }
      elseif($a==='resend_share'){ $s=Database::fetchOne("SELECT s.*, vm.title FROM shares s LEFT JOIN videos_meta vm ON vm.guid=s.video_guid WHERE s.id=?",[$_POST['share_id']]); if($s){ $watch=rtrim($_ENV['APP_URL'],'/')."/s/{$s['token']}"; $burl=rtrim($_ENV['APP_URL'],'/')."/b/{$s['bundle_id']}"; MailManager::sendShareEmail($s['recipient_email'],$s['title']??$s['video_guid'],$watch,$burl); $msg="Resent"; } }
      elseif($a==='extend_share'){ Database::exec("UPDATE shares SET expires_at=? WHERE id=?",[$_POST['new_expiry'].' 23:59:59',$_POST['share_id']]); $msg="Extended"; }
      elseif($a==='add_viewer'){ $email=strtolower(trim($_POST['email'])); $name=$_POST['name']??''; $ap=isset($_POST['approved'])?1:0; $ex=Database::fetchOne("SELECT id FROM viewers WHERE email=?",[$email]); if($ex) Database::exec("UPDATE viewers SET name=?, is_approved=? WHERE email=?",[$name,$ap,$email]); else Database::exec("INSERT INTO viewers (id,email,name,is_approved) VALUES (?,?,?,?)",[bin2hex(random_bytes(8)),$email,$name,$ap]); $msg="Viewer saved"; }
    }
    $videos=Video::all($_GET['q']??'',null,100,0); $viewers=Database::fetchAll("SELECT * FROM viewers ORDER BY created_at DESC LIMIT 100"); $shares=Database::fetchAll("SELECT s.*, vm.title FROM shares s LEFT JOIN videos_meta vm ON vm.guid=s.video_guid ORDER BY s.created_at DESC LIMIT 100");
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Admin</title><style>body{font-family:system-ui;background:#0a0a0f;color:#eee;margin:0} header{padding:14px 20px;background:#16161f;display:flex;gap:16px} header a{color:#aaa} header a.active{color:#fff;font-weight:600} .tab{padding:20px} table{width:100%;border-collapse:collapse} th,td{padding:8px 10px;border-bottom:1px solid #222;font-size:13px} input,select{background:#0f0f17;color:#fff;border:1px solid #333;border-radius:6px;padding:7px} button{background:#7c3aed;color:#fff;border:0;padding:7px 12px;border-radius:6px;cursor:pointer} .msg{background:#1a2e1a;padding:10px;border-radius:8px;margin:10px}</style></head><body>';
    echo '<header><b>Admin</b> <a href="?tab=videos" class="'.($tab==='videos'?'active':'').'">Videos</a> <a href="?tab=viewers" class="'.($tab==='viewers'?'active':'').'">Viewers</a> <a href="?tab=shares" class="'.($tab==='shares'?'active':'').'">Shares</a> <a href="/" style="margin-left:auto">Site</a></header>';
    if(isset($msg)) echo '<div class="msg">'.htmlspecialchars($msg).'</div>';
    if($tab==='videos'){
      echo '<div class="tab"><h2>Videos ('.Video::count().')</h2><form method="post"><input type="hidden" name="action" value="sync_bunny"><button>Sync from Bunny.net (Import)</button> <small>Pulls library</small></form><table><tr><th>Title</th><th>Actions</th></tr>';
      foreach($videos as $v){ echo '<tr><td><form method="post" style="display:flex;gap:4px"><input type="hidden" name="action" value="update_video"><input type="hidden" name="guid" value="'.$v['guid'].'"><input name="title" value="'.htmlspecialchars($v['title']).'" style="width:220px"><input name="custom_order" value="'.$v['custom_order'].'" style="width:50px"><select name="watermark_mode"><option value="default" '.($v['watermark_mode']==='default'?'selected':'').'>Default</option><option value="email">Email</option><option value="none">None</option></select><button>Save</button></form><small>'.htmlspecialchars($v['guid']).'</small></td><td><a href="/watch/'.$v['guid'].'" target="_blank">Watch</a> <form method="post" style="display:inline"><input type="hidden" name="action" value="delete_video"><input type="hidden" name="guid" value="'.$v['guid'].'"><button style="background:#ef4444">Delete</button></form><hr><form method="post"><input type="hidden" name="action" value="create_share"><input type="hidden" name="guid" value="'.$v['guid'].'"><input name="email" placeholder="recipient@email.com" required style="width:160px"><input name="expiry" type="date" value="'.date('Y-m-d',strtotime('+30 days')).'"><select name="watermark_override"><option value="default">Default WM</option><option value="email">Email</option><option value="none">None</option></select><label><input type="checkbox" name="notify" checked> Notify</label><button>Share</button></form></td></tr>'; }
      echo '</table></div>';
    } elseif($tab==='viewers'){ echo '<div class="tab"><h2>Viewers</h2><form method="post"><input type="hidden" name="action" value="add_viewer"><input name="email" placeholder="email" required><input name="name" placeholder="Name"><label><input type="checkbox" name="approved" checked> Approved</label><button>Add</button></form><table><tr><th>Email</th><th>Approved</th></tr>'; foreach($viewers as $v){ echo '<tr><td>'.htmlspecialchars($v['email']).'</td><td>'.($v['is_approved']?'Yes':'No').'</td></tr>'; } echo '</table></div>'; }
    else { echo '<div class="tab"><h2>Shares</h2><table><tr><th>Video</th><th>Recipient</th><th>Token</th><th>Expires</th><th>Views</th><th>Actions</th></tr>'; foreach($shares as $s){ echo '<tr><td>'.htmlspecialchars($s['title']??$s['video_guid']).'</td><td>'.htmlspecialchars($s['recipient_email']).'</td><td><a href="/s/'.$s['token'].'">'.substr($s['token'],0,8).'</a></td><td>'.$s['expires_at'].'</td><td>'.$s['view_count'].'</td><td><form method="post" style="display:inline"><input type="hidden" name="action" value="resend_share"><input type="hidden" name="share_id" value="'.$s['id'].'"><button>Resend</button></form> <form method="post" style="display:inline"><input type="hidden" name="action" value="revoke_share"><input type="hidden" name="share_id" value="'.$s['id'].'"><button style="background:#ef4444">Revoke</button></form></td></tr>'; } echo '</table></div>'; }
    echo '</body></html>';
  }
}
