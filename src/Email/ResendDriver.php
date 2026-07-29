<?php
namespace MarinePortal\Email;
use GuzzleHttp\Client;
class ResendDriver implements EmailInterface {
    public function __construct(private array $cfg, private string $from, private string $replyTo=''){}
    public function send(string $to, string $subject, string $html, string $text=null, array $opts=[]): bool {
        if(empty($this->cfg['api_key'])) return false;
        $client=new Client();
        $payload=['from'=>$this->from,'to'=>[$to],'subject'=>$subject,'html'=>$html];
        if($text) $payload['text']=$text;
        if($this->replyTo) $payload['reply_to']=$this->replyTo;
        try{
            $res=$client->post('https://api.resend.com/emails',['headers'=>['Authorization'=>"Bearer {$this->cfg['api_key']}",'Content-Type'=>'application/json'],'json'=>$payload]);
            return $res->getStatusCode()>=200 && $res->getStatusCode()<300;
        }catch(\Throwable $e){ error_log("Resend error: ".$e->getMessage()); return false; }
    }
}
