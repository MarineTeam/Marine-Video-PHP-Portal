<?php
namespace MarinePortal\Email;
use PHPMailer\PHPMailer\PHPMailer;
class SmtpDriver implements EmailInterface {
    public function __construct(private array $cfg, private string $from, private string $replyTo=''){}
    public function send(string $to, string $subject, string $html, string $text=null, array $opts=[]): bool {
        $mail=new PHPMailer(true);
        try{
            $mail->isSMTP();
            $mail->Host=$this->cfg['host']; $mail->Port=(int)($this->cfg['port']??587);
            $mail->SMTPAuth=!empty($this->cfg['username']);
            if($mail->SMTPAuth){ $mail->Username=$this->cfg['username']; $mail->Password=$this->cfg['password']; }
            $mail->SMTPSecure=$this->cfg['encryption']??'tls';
            $mail->setFrom($this->parseEmail($this->from),$this->parseName($this->from));
            $mail->addAddress($to);
            if($this->replyTo) $mail->addReplyTo($this->replyTo);
            $mail->isHTML(true); $mail->Subject=$subject; $mail->Body=$html; $mail->AltBody=$text??strip_tags($html);
            return $mail->send();
        }catch(\Throwable $e){ error_log("SMTP error: ".$e->getMessage()); return false; }
    }
    private function parseEmail(string $f): string { if(preg_match('/<(.*?)>/',$f,$m)) return $m[1]; return $f; }
    private function parseName(string $f): string { if(preg_match('/^(.*?)\s*</',$f,$m)) return trim($m[1],' "'); return ''; }
}
