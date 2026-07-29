<?php return [
'installed'=>false,
'app'=>['name'=>'Marine Video Portal','url'=>'http://localhost:8000','debug'=>false,'timezone'=>'UTC'],
'database'=>['driver'=>'mysql','mysql'=>['host'=>'127.0.0.1','port'=>3306,'database'=>'marine_portal','username'=>'root','password'=>'','charset'=>'utf8mb4'],'pgsql'=>['host'=>'127.0.0.1','port'=>5432,'database'=>'marine_portal','username'=>'postgres','password'=>'','charset'=>'utf8'],'sqlite'=>['path'=> __DIR__ . '/../storage/database.sqlite']],
'auth0'=>['domain'=>'','client_id'=>'','client_secret'=>'','secret'=>''],
'bunny'=>['library_id'=>'','api_key'=>'','token_auth_key'=>'','cdn_hostname'=>'','cdn_token_key'=>''],
'email'=>['driver'=>'resend','from'=>'Marine Video Portal <noreply@example.com>','reply_to'=>'','resend'=>['api_key'=>''],'smtp'=>['host'=>'','port'=>587,'username'=>'','password'=>'','encryption'=>'tls'],'sendgrid'=>['api_key'=>''],'mailgun'=>['domain'=>'','api_key'=>'','endpoint'=>'api.mailgun.net']],
'admin_emails'=>['admin@example.com'],
'security'=>['gate_secret'=>'','geo_whitelist'=>'','admin_geo_whitelist'=>'','admin_geo_bypass_emails'=>''],
'vapid'=>['public_key'=>'','private_key'=>'','subject'=>'mailto:admin@example.com'],
];
