<?php
namespace MarinePortal\Email;
use GuzzleHttp\Client;
class MailgunDriver implements EmailInterface { public function __construct(private array $cfg, private string $from, private string $replyTo=''){} public function send(string $to, string $subject, string $html, ?string $text=null, array $opts=[]): bool { if(empty($this->cfg['api_key'])) return false; $c=new Client(); $ep=$this->cfg['endpoint']??'api.mailgun.net'; try{ $r=$c->post("https://$ep/v3/{$this->cfg['domain']}/messages",['auth'=>['api',$this->cfg['api_key']],'form_params'=>['from'=>$this->from,'to'=>$to,'subject'=>$subject,'html'=>$html]]); return $r->getStatusCode()<300; }catch(\Throwable $e){ error_log($e->getMessage()); return false; } } }
class LogDriver implements EmailInterface { public function send(string $to, string $subject, string $html, ?string $text=null, array $opts=[]): bool { @file_put_contents(ROOT.'/storage/logs/email.log',date('c')." TO:$to SUBJECT:$subject\n",FILE_APPEND); return true; } }
