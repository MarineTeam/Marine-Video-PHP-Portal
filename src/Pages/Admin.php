<?php
namespace MarineVideoPortal\Pages;
use MarineVideoPortal\Core\Database;
use MarineVideoPortal\Bunny\BunnyService;
use MarineVideoPortal\Models\Video;
class Admin {
  public static function render(){
    $user=\MarineVideoPortal\Auth\Auth0Service::currentUser(); if(!$user){header('Location: /auth/login');exit;}
    if(!\MarineVideoPortal\Auth\Auth0Service::isAdmin($user['email']??'')){die('Not admin');}
    $tab=$_GET['tab']??'videos'; $bunny=new BunnyService(); $msg=null; $err=null;
    if(isset($_POST['action'])){
      $a=$_POST['action'];
      if($a==='sync_bunny'){try{$c=$bunny->syncToDb();$msg="Synced $c videos from Bunny";}catch(\Throwable $e){$err=$e->getMessage();}}
      elseif($a==='add_viewer'){$em=strtolower(trim($_POST['email']));$ap=isset($_POST['approved'])?1:0;$name=trim($_POST['name']??'');if($em){$ex=Database::fetchOne("SELECT id FROM viewers WHERE email=?",[$em]);if($ex)Database::exec("UPDATE viewers SET is_approved=?, name=? WHERE email=?",[$ap,$name,$em]);else Database::exec("INSERT INTO viewers (id,email,name,is_approved) VALUES (?,?,?,?)",[bin2hex(random_bytes(8)),$em,$name,$ap]);$msg="Saved $em";}}
      elseif($a==='remove_viewer'){$em=strtolower(trim($_POST['email']));if($em){Database::exec("DELETE FROM viewers WHERE email=?",[$em]);$msg="Removed $em from viewers list";}}
      elseif($a==='toggle_approve'){$em=strtolower(trim($_POST['email']));$row=Database::fetchOne("SELECT is_approved FROM viewers WHERE email=?",[$em]);if($row){$new=(int)$row['is_approved']?0:1;Database::exec("UPDATE viewers SET is_approved=? WHERE email=?",[$new,$em]);$msg=$new?"Approved $em":"Revoked $em";}}
      elseif($a==='create_share'){$guid=$_POST['guid'];$em=strtolower(trim($_POST['email']));$exp=$_POST['expiry']??date('Y-m-d',strtotime('+30 days'));if($em&&$guid){$bundle=Database::fetchOne("SELECT * FROM bundles WHERE recipient_email=?",[$em]);if(!$bundle){$bid=bin2hex(random_bytes(8));Database::exec("INSERT INTO bundles (id,recipient_email) VALUES (?,?)",[$bid,$em]);$bundle=['id'=>$bid];}$sid=bin2hex(random_bytes(8));$tok=bin2hex(random_bytes(16));Database::exec("INSERT INTO shares (id,token,video_guid,recipient_email,bundle_id,expires_at) VALUES (?,?,?,?,?,?)",[$sid,$tok,$guid,$em,$bundle['id'],$exp.' 23:59:59']);$msg="Shared with $em";}}
      elseif($a==='revoke_share'){Database::exec("UPDATE shares SET revoked=1 WHERE id=?",[$_POST['share_id']]);$msg="Share revoked";}
      elseif($a==='delete_video'){$g=$_POST['guid'];try{$bunny->deleteVideo($g);}catch(\Throwable $e){}Database::exec("DELETE FROM videos_meta WHERE guid=?",[$g]);$msg="Deleted $g";}
    }
    $videos=Video::all($_GET['q']??'',null,200,0); $q=htmlspecialchars($_GET['q']??'');
    $viewers=Database::fetchAll("SELECT * FROM viewers ORDER BY is_approved DESC, created_at DESC LIMIT 200");
    $shares=Database::fetchAll("SELECT s.*, vm.title FROM shares s LEFT JOIN videos_meta vm ON vm.guid=s.video_guid ORDER BY s.created_at DESC LIMIT 100");
    ?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin - Marine Video</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}body{margin:0;font-family:Inter,system-ui;background:#f5f5f7;display:flex;min-height:100vh}
.sidebar{width:240px;background:#0a0a0f;color:#a1a1b5;display:flex;flex-direction:column;border-right:1px solid rgba(255,255,255,.06)}
.sidebar .brand{padding:20px;display:flex;align-items:center;gap:10px;color:#fff;font-weight:700;letter-spacing:.08em;font-size:12px;border-bottom:1px solid rgba(255,255,255,.06)}
.brand i{width:28px;height:28px;background:#fff;color:#000;border-radius:8px;display:grid;place-items:center}
.nav{flex:1;padding:12px}.nav a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;color:#8b8b9a;text-decoration:none;font-size:13px;font-weight:500;margin-bottom:4px}
.nav a.active,.nav a:hover{background:#15151d;color:#fff}
.main{flex:1;display:flex;flex-direction:column}.topbar{height:56px;background:#fff;border-bottom:1px solid #e5e5e7;display:flex;align-items:center;justify-content:space-between;padding:0 24px}
.content{padding:24px;flex:1;overflow:auto}.card{background:#fff;border:1px solid #e5e5e7;border-radius:16px;overflow:hidden;box-shadow:0 1px 2px rgba(0,0,0,.04)}.card-header{padding:16px 20px;border-bottom:1px solid #eee;display:flex;align-items:center;justify-content:space-between}
table{width:100%;border-collapse:collapse}th{text-align:left;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#8b8b9a;font-weight:600;padding:10px 16px;background:#fafafb;border-bottom:1px solid #eee}
td{padding:12px 16px;font-size:13px;border-bottom:1px solid #f0f0f2}.badge{font-size:11px;padding:4px 8px;border-radius:999px;background:#f1f1f3;color:#555;border:1px solid #e5e5e7}.badge.green{background:#dcfce7;color:#166534;border-color:#bbf7d0}.badge.red{background:#fee2e2;color:#991b1b;border-color:#fecaca}
.btn{padding:7px 12px;border-radius:999px;border:1px solid #e5e5e7;background:#fff;font-size:12px;font-weight:500;cursor:pointer}.btn:hover{border-color:#111;background:#111;color:#fff}.btn-primary{background:#111;color:#fff;border-color:#111}.btn-danger{color:#dc2626;border-color:#fecaca}.btn-danger:hover{background:#dc2626;color:#fff}
.input{padding:8px 12px;border-radius:10px;border:1px solid #e5e5e7;background:#fff;font-size:13px}
.msg{padding:12px 16px;border-radius:12px;margin-bottom:16px;font-size:13px}.msg.ok{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}.msg.err{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
</style></head><body>
<div class="sidebar"><div class="brand"><i>▶</i> MARINE ADMIN</div><div class="nav"><a class="<?php echo $tab==='videos'?'active':''; ?>" href="?tab=videos">Videos</a><a class="<?php echo $tab==='viewers'?'active':''; ?>" href="?tab=viewers">Viewers (<?php echo count($viewers); ?>)</a><a class="<?php echo $tab==='shares'?'active':''; ?>" href="?tab=shares">Shares</a><a href="/" style="margin-top:20px">← Site</a><a href="/auth/logout">Logout</a></div></div>
<div class="main"><div class="topbar"><h1 style="margin:0;font-size:15px;text-transform:capitalize"><?php echo $tab; ?></h1><?php if($tab==='videos'): ?><div style="display:flex;gap:8px"><form method="get"><input type="hidden" name="tab" value="videos"><input class="input" name="q" placeholder="Search..." value="<?php echo $q; ?>"></form><form method="post"><input type="hidden" name="action" value="sync_bunny"><button class="btn btn-primary">Sync Bunny</button></form></div><?php endif; ?></div>
<div class="content">
<?php if(isset($msg)): ?><div class="msg ok"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<?php if(isset($err)): ?><div class="msg err"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
<?php if($tab==='videos'): ?>
<div class="card"><table><thead><tr><th>Video</th><th>Share</th><th></th></tr></thead><tbody><?php foreach($videos as $v): $title=htmlspecialchars($v['title']); $guid=$v['guid']; ?><tr><td><?php echo $title; ?><br><small style="color:#888"><?php echo substr($guid,0,12); ?></small></td><td><form method="post" style="display:flex;gap:6px"><input type="hidden" name="action" value="create_share"><input type="hidden" name="guid" value="<?php echo $guid; ?>"><input class="input" name="email" placeholder="email" required style="width:160px"><input class="input" type="date" name="expiry" value="<?php echo date('Y-m-d',strtotime('+30 days')); ?>"><button class="btn btn-primary">Share</button></form></td><td><form method="post" onsubmit="return confirm('Delete?')"><input type="hidden" name="action" value="delete_video"><input type="hidden" name="guid" value="<?php echo $guid; ?>"><button class="btn btn-danger">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<?php elseif($tab==='viewers'): ?>
<div class="card" style="margin-bottom:16px"><div class="card-header"><h2>Add viewer</h2></div><div style="padding:16px"><form method="post" style="display:flex;gap:8px;flex-wrap:wrap"><input type="hidden" name="action" value="add_viewer"><input class="input" name="email" placeholder="email" required><input class="input" name="name" placeholder="name"><label><input type="checkbox" name="approved" checked> Approved</label><button class="btn btn-primary">Save</button></form></div></div>
<div class="card"><table><thead><tr><th>Email</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach($viewers as $v): $em=htmlspecialchars($v['email']); $is=(int)$v['is_approved']; ?><tr><td><?php echo $em; ?><br><small><?php echo htmlspecialchars($v['name']??''); ?></small></td><td><?php if($is): ?><span class="badge green">Approved</span><?php else: ?><span class="badge red">Pending</span><?php endif; ?></td><td><div style="display:flex;gap:6px"><form method="post"><input type="hidden" name="action" value="toggle_approve"><input type="hidden" name="email" value="<?php echo $em; ?>"><button class="btn"><?php echo $is?'Revoke':'Approve'; ?></button></form><form method="post" onsubmit="return confirm('Remove <?php echo $em; ?>?')"><input type="hidden" name="action" value="remove_viewer"><input type="hidden" name="email" value="<?php echo $em; ?>"><button class="btn btn-danger">Remove</button></form></div></td></tr><?php endforeach; ?></tbody></table></div>
<?php else: ?>
<div class="card"><table><thead><tr><th>Video</th><th>Recipient</th><th>Expires</th><th>Action</th></tr></thead><tbody><?php foreach($shares as $s): ?><tr><td><?php echo htmlspecialchars($s['title']??$s['video_guid']); ?></td><td><?php echo htmlspecialchars($s['recipient_email']); ?></td><td><?php echo htmlspecialchars($s['expires_at']); ?></td><td><form method="post"><input type="hidden" name="action" value="revoke_share"><input type="hidden" name="share_id" value="<?php echo $s['id']; ?>"><button class="btn btn-danger">Revoke</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?>
</div></div>
</body></html>
<?php
  }
}
