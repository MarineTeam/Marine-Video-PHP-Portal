<?php
session_start();
require __DIR__.'/../src/Core/EnvLoader.php';
require __DIR__.'/../src/Core/Database.php';
require __DIR__.'/../src/Auth/Auth0Service.php';
require __DIR__.'/../src/Bunny/BunnyService.php';
require __DIR__.'/../src/Mail/MailManager.php';
require __DIR__.'/../src/Models/Video.php';
require __DIR__.'/../src/Services/WatermarkService.php';
require __DIR__.'/../src/Services/GeoService.php';
require __DIR__.'/../src/Pages/Home.php';
require __DIR__.'/../src/Pages/Watch.php';
require __DIR__.'/../src/Pages/SharePage.php';
require __DIR__.'/../src/Pages/BundlePage.php';
require __DIR__.'/../src/Pages/Admin.php';
use MarineVideoPortal\Core\EnvLoader;
EnvLoader::load(__DIR__.'/..');
if(!file_exists(__DIR__.'/../.env') && is_dir(__DIR__.'/../install')){ header('Location: /install/'); exit; }
$uri=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($uri,'/auth/')){ require __DIR__.'/../src/Auth/routes.php'; exit; }
if($uri==='/' || $uri==='/index.php'){ \MarineVideoPortal\Pages\Home::render(); exit; }
if(preg_match('#^/watch/([^/]+)#',$uri,$m)){ \MarineVideoPortal\Pages\Watch::render($m[1]); exit; }
if(preg_match('#^/s/([^/]+)#',$uri,$m)){ \MarineVideoPortal\Pages\SharePage::render($m[1]); exit; }
if(preg_match('#^/b/([^/]+)#',$uri,$m)){ \MarineVideoPortal\Pages\BundlePage::render($m[1]); exit; }
if(str_starts_with($uri,'/admin')){ \MarineVideoPortal\Pages\Admin::render(); exit; }
if($uri==='/api/progress' && $_SERVER['REQUEST_METHOD']==='POST'){
  $data=json_decode(file_get_contents('php://input'),true);
  $email=$_SESSION['viewer_email']??$_SESSION['user']['email']??'anon';
  $guid=$data['guid']??''; $sec=(int)($data['seconds']??0); $pct=(float)($data['percent']??0);
  if($guid){ \MarineVideoPortal\Core\Database::exec("INSERT INTO watch_progress (viewer_email,video_guid,progress_seconds,progress_percent,completed) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE progress_seconds=VALUES(progress_seconds), progress_percent=VALUES(progress_percent)",[$email,$guid,$sec,$pct,$pct>90?1:0]); }
  echo json_encode(['ok'=>true]); exit;
}
http_response_code(404); echo '404 - '.htmlspecialchars($uri);
