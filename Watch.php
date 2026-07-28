<?php
namespace MarineVideoPortal\Pages;
use MarineVideoPortal\Bunny\BunnyService;
use MarineVideoPortal\Models\Video;
use MarineVideoPortal\Services\WatermarkService;
class Watch {
  public static function render(string $guid){
    $meta=Video::find($guid); if(!$meta){ http_response_code(404); die('Video not found - sync in admin'); }
    $bunny=new BunnyService(); $embed=$bunny->signedEmbedUrl($guid);
    $email=$_SESSION['viewer_email']??$_SESSION['user']['email']??'';
    $wm=WatermarkService::resolve($email,$guid);
    $title=$meta['title']??$guid;
    echo '<!doctype html><html><head><meta charset="utf-8"><title>'.htmlspecialchars($title).'</title><style>body{margin:0;background:#000;color:#eee;font-family:system-ui} .wrap{position:relative;aspect-ratio:16/9;max-width:1200px;margin:0 auto;background:#000} iframe{width:100%;height:100%;border:0} .wm{position:absolute;bottom:18px;right:18px;background:rgba(0,0,0,.4);padding:6px 10px;border-radius:6px;opacity:.7} header{padding:14px 20px;background:#0f0f17;display:flex;justify-content:space-between}</style></head><body>';
    echo '<header><div><a href="/" style="color:#a78bfa">Back</a> <b style="margin-left:12px">'.htmlspecialchars($title).'</b></div><div>'.htmlspecialchars($email).'</div></header>';
    echo '<div class="wrap"><iframe src="'.$embed.'" allowfullscreen allow="autoplay; fullscreen"></iframe>'; if($wm!=='none') echo '<div class="wm">'.htmlspecialchars($email).'</div>'; echo '</div></body></html>';
  }
}
