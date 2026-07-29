<?php
session_start();
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['install'][$step] = $_POST;
    header('Location: /install/?step='.($step+1));
    exit;
}
$authSecret = bin2hex(random_bytes(16));
$gateSecret = bin2hex(random_bytes(32));
?>
<!doctype html><html><head><meta charset="utf-8"><title>Installer</title>
<style>body{font-family:system-ui;background:#0f1117;color:#eee;max-width:800px;margin:30px auto;padding:20px} .card{background:#1a1d27;padding:24px;border-radius:12px} input,select{width:100%;padding:10px;margin:6px 0 12px;border-radius:8px;background:#12141c;color:#fff;border:1px solid #333} button{background:#4f46e5;color:#fff;border:0;padding:12px 20px;border-radius:8px;cursor:pointer}</style></head><body>
<div class="card">
<?php if($step==1): ?>
<h2>Step 1 - Env Check</h2>
<p>PHP <?= PHP_VERSION ?> OK</p>
<form method="post"><button>Continue to DB</button></form>
<?php elseif($step==2): ?>
<h2>Step 2 - Database</h2>
<form method="post">
Driver <select name="driver"><option value="mysql">MySQL</option><option value="pgsql">PostgreSQL</option><option value="sqlite">SQLite</option></select><br>
Host <input name="host" value="127.0.0.1"><br>
Port <input name="port" value="3306"><br>
Database <input name="database" value="marine_portal"><br>
User <input name="username" value="root"><br>
Pass <input type="password" name="password"><br>
SQLite Path <input name="sqlite_path" value="<?php echo __DIR__.'/../storage/database.sqlite'; ?>"><br>
<button>Next</button></form>
<?php elseif($step==3): ?>
<h2>Step 3 - App & Auth0</h2>
<form method="post">
Site URL <input name="app_url" value="https://<?php echo $_SERVER['HTTP_HOST']; ?>"><br>
Site Name <input name="site_name" value="Marine Video Portal"><br>
Auth0 Domain <input name="auth0_domain"><br>
Client ID <input name="auth0_client_id"><br>
Client Secret <input type="password" name="auth0_client_secret"><br>
Auth0 Secret <input name="auth0_secret" value="<?php echo $authSecret; ?>"><br>
Admin Emails <input name="admin_emails"><br>
Gate Secret <input name="gate_secret" value="<?php echo $gateSecret; ?>"><br>
<button>Next</button></form>
<?php elseif($step==4): ?>
<h2>Step 4 - Bunny</h2>
<form method="post">
Library ID <input name="library_id"><br>
API Key <input type="password" name="api_key"><br>
Token Key <input type="password" name="token_auth_key"><br>
CDN Host <input name="cdn_hostname"><br>
<button>Next</button></form>
<?php elseif($step==5): ?>
<h2>Step 5 - Email</h2>
<form method="post">
Driver <select name="email_driver"><option value="resend">Resend</option><option value="smtp">SMTP</option><option value="sendgrid">SendGrid</option><option value="mailgun">Mailgun</option><option value="log">Log</option></select><br>
From <input name="from" value="Marine Portal <noreply@example.com>"><br>
Resend Key <input type="password" name="resend_key"><br>
SMTP Host <input name="smtp_host"><br>
SMTP Port <input name="smtp_port" value="587"><br>
SMTP User <input name="smtp_user"><br>
SMTP Pass <input type="password" name="smtp_pass"><br>
<button>Finalize</button></form>
<?php else:
$inst=$_SESSION['install']??[];
$driver=$inst[2]['driver']??'mysql';
$cfg=[
 'installed'=>true,
 'app'=>['name'=>$inst[3]['site_name']??'Marine','url'=>$inst[3]['app_url']??'http://localhost','timezone'=>'UTC'],
 'database'=>['driver'=>$driver,'mysql'=>['host'=>$inst[2]['host']??'127.0.0.1','port'=>(int)($inst[2]['port']??3306),'database'=>$inst[2]['database']??'marine','username'=>$inst[2]['username']??'root','password'=>$inst[2]['password']??'','charset'=>'utf8mb4'],'pgsql'=>['host'=>$inst[2]['host']??'127.0.0.1','port'=>5432,'database'=>$inst[2]['database']??'marine','username'=>$inst[2]['username']??'postgres','password'=>$inst[2]['password']??''],'sqlite'=>['path'=>$inst[2]['sqlite_path']??__DIR__.'/../storage/database.sqlite']],
 'auth0'=>['domain'=>$inst[3]['auth0_domain']??'','client_id'=>$inst[3]['auth0_client_id']??'','client_secret'=>$inst[3]['auth0_client_secret']??'','secret'=>$inst[3]['auth0_secret']??''],
 'bunny'=>['library_id'=>$inst[4]['library_id']??'','api_key'=>$inst[4]['api_key']??'','token_auth_key'=>$inst[4]['token_auth_key']??'','cdn_hostname'=>$inst[4]['cdn_hostname']??''],
 'email'=>['driver'=>$inst[5]['email_driver']??'resend','from'=>$inst[5]['from']??'','resend'=>['api_key'=>$inst[5]['resend_key']??''],'smtp'=>['host'=>$inst[5]['smtp_host']??'','port'=>(int)($inst[5]['smtp_port']??587),'username'=>$inst[5]['smtp_user']??'','password'=>$inst[5]['smtp_pass']??'','encryption'=>'tls'],'sendgrid'=>['api_key'=>$inst[5]['sendgrid_key']??''],'mailgun'=>['domain'=>$inst[5]['mailgun_domain']??'','api_key'=>$inst[5]['mailgun_key']??'']],
 'admin_emails'=>array_map('trim', explode(',', $inst[3]['admin_emails']??'admin@example.com')),
 'security'=>['gate_secret'=>$inst[3]['gate_secret']??bin2hex(random_bytes(32))],
];
@mkdir(__DIR__.'/../storage',0755,true);
file_put_contents(__DIR__.'/../config.php','<?php return '.var_export($cfg,true).';');
file_put_contents(__DIR__.'/../storage/install.lock', date('c'));
echo "<h2>Installed!</h2><a href='/'>Go to site</a>";
endif; ?>
</div></body></html>
