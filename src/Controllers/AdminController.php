<?php
namespace MarinePortal\Controllers;
class AdminController {
 public function __construct(private array $config){}
 public function index(): void {
   $guard=new \MarinePortal\Auth\AuthGuard($this->config);
   if(!$guard->isAdmin()) abort(403,'Admin only');
   echo file_get_contents(ROOT.'/src/Views/admin.html');
 }
}
