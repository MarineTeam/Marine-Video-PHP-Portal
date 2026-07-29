<?php
namespace MarinePortal\Email;
class EmailManager {
    public static function driver(?array $config=null): EmailInterface {
        $cfg=$config ?? (require ROOT.'/config.php')['email'];
        $driver=$cfg['driver'] ?? 'log';
        return match($driver){
            'resend' => new ResendDriver($cfg['resend']??[],$cfg['from'],$cfg['reply_to']??''),
            'smtp' => new SmtpDriver($cfg['smtp']??[],$cfg['from'],$cfg['reply_to']??''),
            'sendgrid' => new SendGridDriver($cfg['sendgrid']??[],$cfg['from'],$cfg['reply_to']??''),
            'mailgun' => new MailgunDriver($cfg['mailgun']??[],$cfg['from'],$cfg['reply_to']??''),
            default => new LogDriver(),
        };
    }
}
