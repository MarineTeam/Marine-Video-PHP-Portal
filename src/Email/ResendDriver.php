<?php
namespace MarinePortal\Email;
use GuzzleHttp\Client;
class ResendDriver implements EmailInterface { public function __construct(private array $cfg, private string $from, private string $replyTo=''){} public function send(string $to, string $subject, string $html, ?string $text=null, array $opts=[]): bool { if(empty($this->cfg['api_key'])) return false; $c=new Client(); try{ $r=$c->post('https://api.resend.com/emails',['headers'=>['Authorization'=>"Bearer {$this->cfg['api_key']}"],'json'=>['from'=>$this->from,'to'=>[$to],'subject'=>$subject,'html'=>$html]]); return $r->getStatusCode()<300; }catch(\Throwable $e){ error_log($e->getMessage()); return false; } } }
