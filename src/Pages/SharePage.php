<?php
namespace MarineVideoPortal\Pages;
use MarineVideoPortal\Core\Database;
use MarineVideoPortal\Bunny\BunnyService;
use MarineVideoPortal\Services\WatermarkService;
class SharePage {
  public static function render(string $token){
    $share=Database::fetchOne("SELECT s.*, vm.title FROM shares s LEFT JOIN videos_meta vm ON s.video_guid=vm.guid WHERE s.token=?",[$token]);
    if(!$share) die('Invalid link'); if($share['revoked']) die('Revoked'); if(strtotime($share['expires_at'])<time()) die('Expired');
    $email=strtolower($_SESSION['viewer_email']??''); $recipient=strtolower($share['recipient_email']);
    if(!$email){
      if($_SERVER['REQUEST_METHOD']==='POST'){
        $input=strtolower(trim($_POST['email']??'')); if($input!==$recipient) die('Access denied');
        $_SESSION['viewer_email']=$input; $email=$input;
      } else {
        echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>Private video - Marine</title><link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap' rel='stylesheet'><style>body{margin:0;background:#050508;color:#e8e8ec;font-family:Inter,system-ui;display:flex;align-items:center;justify-content:center;min-height:100vh} .card{background:#111119;border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:32px;max-width:420px;width:90%} h2{margin:0 0 8px} p{color:#6b6b78;font-size:14px} input{width:100%;padding:12px 14px;border-radius:999px;border:1px solid rgba(255,255,255,.1);background:#15151d;color:#fff;margin-top:16px} button{width:100%;margin-top:12px;padding:12px;border-radius:999px;border:0;background:#fff;color:#000;font-weight:600;cursor:pointer}</style></head><body><div class='card'><h2>Private video</h2><p>This video is shared privately. Verify your email to watch.</p><form method='post'><input name='email' type='email' required placeholder='Your email'><button>Continue to watch</button></form></div></body></html>"; return;
      }
    }
    if($email!==$recipient) die('Wrong recipient');
    Database::exec("UPDATE shares SET view_count=view_count+1, last_viewed_at=? WHERE token=?",[date('Y-m-d H:i:s'),$token]);
    $bunny=new BunnyService(); $embed=$bunny->signedEmbedUrl($share['video_guid'],21600);
    $wmMode=WatermarkService::resolve($email,$share['video_guid'],$share['watermark_override']);
    $wmText=WatermarkService::displayText($wmMode,$email);
    $title=htmlspecialchars($share['title']??'Private Video');
    echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>$title - Marine</title><link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500&display=swap' rel='stylesheet'><style>body{margin:0;background:#050508;color:#e8e8ec;font-family:Inter,system-ui} header{height:56px;display:flex;align-items:center;justify-content:space-between;padding:0 20px;border-bottom:1px solid rgba(255,255,255,.06);background:rgba(10,10,14,.8);backdrop-filter:blur(20px)} .wrap{max-width:1200px;margin:0 auto;aspect-ratio:16/9;background:#000;position:relative} iframe{width:100%;height:100%;border:0} .wm{position:absolute;bottom:16px;right:16px;background:rgba(0,0,0,.35);padding:4px 8px;border-radius:6px;font-size:11px;opacity:.5}</style></head><body>";
    echo "<header><div style='display:flex;align-items:center;gap:10px'><a href='/b/".htmlspecialchars($share['bundle_id'])."' style='color:#9a9aa8;text-decoration:none;font-size:13px'>← All videos</a><b style='margin-left:8px;font-size:13px'>$title</b></div><div style='font-size:11px;color:#444;letter-spacing:.08em'>PRIVATE</div></header>";
    echo "<div class='wrap'><iframe src='$embed' allowfullscreen allow='fullscreen; picture-in-picture'></iframe>";
    if($wmText!=='') echo "<div class='wm'>".htmlspecialchars($wmText)."</div>";
    echo "</div></body></html>";
  }
}
