<?php
namespace MarineVideoPortal\Mail;
class MailManager {
  public static function send($to,$subject,$html,$text=''){
    $driver=$_ENV['MAIL_DRIVER']??'resend';
    try{
      if($driver==='resend') return self::resend($to,$subject,$html,$text);
      if($driver==='sendgrid') return self::sendgrid($to,$subject,$html);
      if($driver==='smtp') return self::smtp($to,$subject,$html);
    }catch(\Throwable $e){ error_log($e->getMessage()); }
    return self::log($to,$subject,$html);
  }
  private static function resend($to,$subj,$html,$text){
    $key=$_ENV['RESEND_API_KEY']??''; if(!$key) return self::log($to,$subj,$html);
    $fromAddr=$_ENV['MAIL_FROM_ADDRESS']??''; $fromName=$_ENV['MAIL_FROM_NAME']??'Marine Video Portal';
    $from=$fromName?"$fromName <$fromAddr>":$fromAddr;
    $ch=curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['from'=>$from,'to'=>$to,'subject'=>$subj,'html'=>$html,'text'=>$text?:strip_tags($html)]),CURLOPT_HTTPHEADER=>["Authorization: Bearer $key",'Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true]);
    $r=curl_exec($ch); $c=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($c>=400) throw new \Exception("Resend $c $r"); return true;
  }
  private static function sendgrid($to,$subj,$html){
    $key=$_ENV['SENDGRID_API_KEY']??''; $from=$_ENV['MAIL_FROM_ADDRESS']??'';
    $ch=curl_init('https://api.sendgrid.com/v3/mail/send');
    $data=['personalizations'=>[['to'=>[['email'=>$to]]]],'from'=>['email'=>$from],'subject'=>$subj,'content'=>[['type'=>'text/html','value'=>$html]]];
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($data),CURLOPT_HTTPHEADER=>["Authorization: Bearer $key",'Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true]);
    $r=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($code>=400) throw new \Exception("SendGrid $code $r"); return true;
  }
  private static function smtp($to,$subj,$html){
    $from=$_ENV['MAIL_FROM_ADDRESS']??''; $h="From: $from\r\nContent-Type: text/html; charset=UTF-8\r\n";
    return mail($to,$subj,$html,$h);
  }
  private static function log($to,$subj,$html){ @mkdir(__DIR__.'/../../storage/logs',0755,true); file_put_contents(__DIR__.'/../../storage/logs/mail.log',"[".date('c')."] $to $subj\n",FILE_APPEND); return true; }
  public static function sendShareEmail($to,$videoTitle,$watchUrl,$bundleUrl=null){
    $html="<div style='font-family:system-ui;max-width:600px'><h2>Marine Video Portal</h2><p>You have been granted access to <b>".htmlspecialchars($videoTitle)."</b></p><p><a href='$watchUrl' style='background:#7c3aed;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none'>Watch now</a></p>".($bundleUrl?"<p>All videos: <a href='$bundleUrl'>$bundleUrl</a></p>":'')."</div>";
    return self::send($to,"Access: $videoTitle",$html);
  }
}
