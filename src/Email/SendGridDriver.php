<?php
namespace MarinePortal\Email;
use GuzzleHttp\Client;
class SendGridDriver implements EmailInterface {
    public function __construct(private array $cfg, private string $from, private string $replyTo=''){}
    public function send(string $to, string $subject, string $html, string $text=null, array $opts=[]): bool {
        if(empty($this->cfg['api_key'])) return false;
        $client=new Client();
        $payload=['personalizations'=>[['to'=>[['email'=>$to]]]],'from'=>['email'=>$this->parseEmail($this->from),'name'=>$this->parseName($this->from)],'subject'=>$subject,'content'=>[['type'=>'text/html','value'=>$html]]];
        try{
            $res=$client->post('https://api.sendgrid.com/v3/mail/send',['headers'=>['Authorization'=>"Bearer {$this->cfg['api_key']}",'Content-Type'=>'application/json'],'json'=>$payload]);
            return $res->getStatusCode()>=200 && $res->getStatusCode()<300;
        }catch(\Throwable $e){ error_log("SendGrid error: ".$e->getMessage()); return false; }
    }
    private function parseEmail(string $f): string { if(preg_match('/<(.*?)>/',$f,$m)) return $m[1]; return $f; }
    private function parseName(string $f): string { if(preg_match('/^(.*?)\s*</',$f,$m)) return trim($m[1],' "'); return ''; }
}
