<?php
namespace MarinePortal\Controllers;
class HomeController {
 public function __construct(private array $config){}
 public function index(): void {
   $guard=new \MarinePortal\Auth\AuthGuard($this->config);
   if(!$guard->isApprovedViewer()){ echo "<h1>Not approved yet</h1><p>Contact admin.</p><a href='/auth/logout'>Logout</a>"; return; }
   $bunny=new \MarinePortal\Video\BunnyService($this->config['bunny']);
   echo "<h1>".htmlspecialchars($this->config['app']['name'])."</h1><p>Welcome ".$_SESSION['user']['email']." | <a href='/admin'>Admin</a> | <a href='/auth/logout'>Logout</a></p>";
   echo "<p>Library loading via API /api/videos - implement grid here. Bunny signed embed URLs generated per request.</p>";
 }
}
