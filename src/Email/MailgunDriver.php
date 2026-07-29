<?php
namespace MarinePortal\Email;
use GuzzleHttp\Client;
class MailgunDriver implements EmailInterface {
    public function __construct(private array $cfg, private string $from, private string $replyTo=''){}
    public function send(string $to, string $subject, string $html, string $text=null, array $opts=[]): bool {
        if(empty($this->cfg['api_key'])||empty($this->cfg['domain'])) return false;
        $client=new Client();
        $endpoint=$this->cfg['endpoint']??'api.mailgun.net';
        try{
            $res=$client->post("https://$endpoint/v3/{$this->cfg['domain']}/messages",['auth'=>['api',$this->cfg['api_key']],'form_params'=>['from'=>$this->from,'to'=>$to,'subject'=>$subject,'html'=>$html,'text'=>$text??strip_tags($html)]]);
            return $res->getStatusCode()>=200 && $res->getStatusCode()<300;
        }catch(\Throwable $e){ error_log("Mailgun error: ".$e->getMessage()); return false; }
    }
}
class LogDriver implements EmailInterface {
    public function send(string $to, string $subject, string $html, string $text=null, array $opts=[]): bool {
        $log=ROOT.'/storage/logs/email.log';
        @file_put_contents($log,date('c')." TO:$to SUBJECT:$subject\n$html\n---\n",FILE_APPEND);
        return true;
    }
}
