<?php
namespace MarinePortal\Controllers\Api;
class AdminApi {
 public function __construct(private array $config){}
 public function dispatch(string $uri): void {
   $guard=new \MarinePortal\Auth\AuthGuard($this->config);
   if(!$guard->isAdmin()) json_response(['error'=>'forbidden'],403);
   $pdo=\MarinePortal\Database\Connection::getInstance()->pdo;
   if($uri==='/api/admin/videos'){ $bunny=new \MarinePortal\Video\BunnyService($this->config['bunny']); $videos=$bunny->listVideos(); json_response($videos); }
   if($uri==='/api/admin/viewers'){ $rows=$pdo->query("SELECT * FROM approved_viewers")->fetchAll(); json_response($rows); }
   // ... implement rest: viewers add/remove, shares list/revoke/resend/extend, settings, audit, upload ticket, collections etc per original README
   json_response(['ok'=>true,'uri'=>$uri]);
 }
}
