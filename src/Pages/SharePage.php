<?php
namespace MarineVideoPortal\Pages;
use MarineVideoPortal\Core\Database;
use MarineVideoPortal\Bunny\BunnyService;
class SharePage {
  public static function render(string $token){
    $share=Database::fetchOne("SELECT s.*, vm.title FROM shares s LEFT JOIN videos_meta vm ON s.video_guid=vm.guid WHERE s.token=?",[$token]);
    if(!$share) die('Invalid'); if($share['revoked']) die('Revoked'); if(strtotime($share['expires_at'])<time()) die('Expired');
    $email=strtolower($_SESSION['viewer_email']??''); $recipient=strtolower($share['recipient_email']);
    if(!$email){
      if($_SERVER['REQUEST_METHOD']==='POST'){
        $input=strtolower(trim($_POST['email']??'')); if($input!==$recipient) die('Access denied');
        $_SESSION['viewer_email']=$input; $email=$input;
      } else {
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Private video</title><style>body{margin:0;background:#050508;color:#eee;font-family:Inter;display:flex;align-items:center;justify-content:center;min-height:100vh}.card{background:#111119;border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:28px;max-width:400px;width:90%}input{width:100%;padding:12px;border-radius:999px;border:1px solid rgba(255,255,255,.1);background:#15151d;color:#fff;margin-top:12px}button{width:100%;margin-top:12px;padding:12px;border-radius:999px;border:0;background:#fff;color:#000;font-weight:600}</style></head><body><div class="card"><h2>Private video</h2><p style="color:#666;font-size:14px">Verify your email to watch</p><form method="post"><input name="email" type="email" required placeholder="Your email"><button>Continue</button></form></div></body></html>
<?php return; } }
    if($email!==$recipient) die('Wrong recipient');
    Database::exec("UPDATE shares SET view_count=view_count+1 WHERE token=?",[$token]);
    $bunny=new BunnyService(); $embed=$bunny->signedEmbedUrl($share['video_guid'],21600); $title=htmlspecialchars($share['title']??'Private');
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo $title; ?></title><style>body{margin:0;background:#050508;color:#eee;font-family:Inter}header{height:56px;display:flex;align-items:center;justify-content:space-between;padding:0 20px;border-bottom:1px solid rgba(255,255,255,.06)}.wrap{max-width:1200px;margin:0 auto;aspect-ratio:16/9;background:#000}iframe{width:100%;height:100%;border:0}</style></head><body>
<header><a href="/b/<?php echo htmlspecialchars($share['bundle_id']); ?>" style="color:#999;text-decoration:none;font-size:13px">← All videos</a><b style="font-size:13px"><?php echo $title; ?></b></header>
<div class="wrap"><iframe src="<?php echo $embed; ?>" allowfullscreen></iframe></div>
</body></html>
<?php
  }
}
