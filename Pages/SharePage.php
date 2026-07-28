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
        $input=strtolower(trim($_POST['email']??'')); if($input!==$recipient) die('Email mismatch'); $_SESSION['viewer_email']=$input; $email=$input;
      } else {
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Private video</title><style>body{font-family:system-ui;background:#0a0a0f;color:#eee;display:flex;align-items:center;justify-content:center;height:100vh} .card{background:#16161f;padding:32px;border-radius:16px;max-width:420px;width:100%} input{width:100%;padding:12px;margin:8px 0;border-radius:8px;border:1px solid #333;background:#0f0f17;color:#fff}</style></head><body><div class="card"><h2>Private video</h2><p>For '.htmlspecialchars($recipient).'</p><form method="post"><input name="email" type="email" required placeholder="Enter your email"><button style="background:#7c3aed;color:#fff;padding:12px;border:0;border-radius:8px;width:100%">Continue</button></form></div></body></html>'; return;
      }
    }
    Database::exec("UPDATE shares SET view_count=view_count+1, last_viewed_at=? WHERE token=?",[date('Y-m-d H:i:s'),$token]);
    $bunny=new BunnyService(); $embed=$bunny->signedEmbedUrl($share['video_guid'],3600*6); $wm=WatermarkService::resolve($email,$share['video_guid'],$share['watermark_override']);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>'.htmlspecialchars($share['title']??'').'</title><style>body{margin:0;background:#000;color:#eee;font-family:system-ui} .wrap{position:relative;aspect-ratio:16/9;max-width:1100px;margin:0 auto} iframe{width:100%;height:100%;border:0} .wm{position:absolute;bottom:16px;right:16px;background:rgba(0,0,0,.4);padding:6px 10px;border-radius:6px}</style></head><body><div style="padding:12px;background:#0f0f17"><a href="/b/'.$share['bundle_id'].'" style="color:#a78bfa">All videos</a> <b style="margin-left:10px">'.htmlspecialchars($share['title']??'').'</b></div><div class="wrap"><iframe src="'.$embed.'" allowfullscreen></iframe>'; if($wm!=='none') echo '<div class="wm">'.htmlspecialchars($email).'</div>'; echo '</div></body></html>';
  }
}
