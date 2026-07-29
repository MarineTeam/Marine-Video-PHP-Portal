<?php
session_start();
define('INSTALLER', true);
$step = $_GET['step'] ?? 1;
$steps = [1=>'Environment Check',2=>'Database',3=>'App & Auth0',4=>'Bunny.net',5=>'Email Service',6=>'Finalize'];

function checkReq(): array {
    $checks=[];
    $checks[]=['PHP >= 8.1', version_compare(PHP_VERSION,'8.1.0','>='), PHP_VERSION];
    foreach(['pdo','curl','json','mbstring','openssl'] as $ext){ $checks[]=["ext-$ext", extension_loaded($ext), extension_loaded($ext)?'OK':'Missing']; }
    $checks[]=['pdo_mysql', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql')?'OK':'Optional'];
    $checks[]=['pdo_pgsql', extension_loaded('pdo_pgsql'), extension_loaded('pdo_pgsql')?'OK':'Optional'];
    $checks[]=['pdo_sqlite', extension_loaded('pdo_sqlite'), extension_loaded('pdo_sqlite')?'OK':'Optional'];
    $checks[]=['config writable', is_writable(__DIR__.'/..') || is_writable(__DIR__.'/../config') || !file_exists(__DIR__.'/../config.php'), ''];
    $checks[]=['storage writable', is_writable(__DIR__.'/../storage') || mkdir(__DIR__.'/../storage',0755,true), ''];
    return $checks;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    $_SESSION['install'][ $step ] = $_POST;
    header('Location: /install/?step='.($step+1)); exit;
}

?>
<!doctype html><html><head><meta charset="utf-8"><title>Marine Portal Installer</title>
<style>
body{font-family:Inter,system-ui;background:#0f1117;color:#e8e8ea;max-width:820px;margin:40px auto;padding:20px}
.card{background:#1a1d27;border-radius:16px;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.4)}
input,select{width:100%;padding:12px;border-radius:8px;border:1px solid #2a2d3a;background:#12141c;color:#fff;margin:6px 0 14px}
button{background:linear-gradient(135deg,#7c5cff,#4f46e5);color:#fff;border:0;padding:12px 20px;border-radius:10px;cursor:pointer;font-weight:600}
.ok{color:#22c55e}.fail{color:#ef4444}
.steps{display:flex;gap:8px;margin-bottom:20px} .steps div{flex:1;padding:8px;border-radius:8px;background:#222636;text-align:center;font-size:12px} .steps .active{background:#4f46e5}
</style></head><body>
<h1>🎬 Marine Video Portal - 5 Minute Installer</h1>
<div class="steps"><?php foreach($steps as $i=>$name): ?><div class="<?= $i==$step?'active':'' ?>"><?= $i ?>. <?= $name ?></div><?php endforeach; ?></div>
<div class="card">
<?php if($step==1): $checks=checkReq(); $allOk=true; ?>
<h2>Environment Check</h2>
<table style="width:100%"><?php foreach($checks as $c){ if(!$c[1]) $allOk=false; echo "<tr><td>{$c[0]}</td><td class='".($c[1]?'ok':'fail')."'>".($c[1]?'✓':'✗')." {$c[2]}</td></tr>"; } ?></table>
<?php if(!$allOk): ?><p class="fail">Fix failures before continuing.</p><?php endif; ?>
<form method="post"><button type="submit">Continue →</button></form>
<?php elseif($step==2): ?>
<h2>Database Configuration</h2>
<p>Multi-driver: MySQL, PostgreSQL, SQLite (like WordPress, choose your driver)</p>
<form method="post">
<label>Driver<select name="driver"><option value="mysql">MySQL</option><option value="pgsql">PostgreSQL</option><option value="sqlite">SQLite (zero-config)</option></select></label>
<div id="mysql-fields">
<label>Host<input name="host" value="127.0.0.1"></label>
<label>Port<input name="port" value="3306"></label>
<label>Database<input name="database" value="marine_portal"></label>
<label>Username<input name="username" value="root"></label>
<label>Password<input type="password" name="password"></label>
</div>
<label>SQLite Path (if SQLite)<input name="sqlite_path" value="<?= __DIR__ ?>/../storage/database.sqlite"></label>
<button type="submit">Continue →</button></form>
<?php elseif($step==3): ?>
<h2>App & Auth0</h2>
<form method="post">
<label>Site URL<input name="app_url" value="<?= (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'] ?>"></label>
<label>Site Name<input name="site_name" value="Marine Video Portal"></label>
<label>Auth0 Domain (without https://)<input name="auth0_domain" placeholder="your-tenant.us.auth0.com"></label>
<label>Auth0 Client ID<input name="auth0_client_id"></label>
<label>Auth0 Client Secret<input type="password" name="auth0_client_secret"></label>
<label>Auth0 Secret (openssl rand -hex 32)<input name="auth0_secret" value="<?= bin2hex(random_bytes(16)) ?>"></label>
<label>Admin Emails (comma separated)<input name="admin_emails" placeholder="admin@example.com"></label>
<label>Gate Secret<input name="gate_secret" value="<?= bin2hex(random_bytes(32)) ?>"></label>
<button type="submit">Continue →</button></form>
<?php elseif($step==4): ?>
<h2>Bunny.net Stream</h2>
<form method="post">
<label>Library ID<input name="library_id"></label>
<label>API Key<input type="password" name="api_key"></label>
<label>Token Auth Key<input type="password" name="token_auth_key"></label>
<label>CDN Hostname (for thumbnails)<input name="cdn_hostname" placeholder="vz-xxx.b-cdn.net"></label>
<label>CDN Token Key (if different)<input type="password" name="cdn_token_key"></label>
<button type="submit">Continue →</button></form>
<?php elseif($step==5): ?>
<h2>Email Service - Configurable</h2>
<p>Resend is default, but you can switch to SMTP, SendGrid, Mailgun, or Log (dev)</p>
<form method="post">
<label>Driver<select name="email_driver"><option value="resend">Resend (default)</option><option value="smtp">SMTP (any provider)</option><option value="sendgrid">SendGrid</option><option value="mailgun">Mailgun</option><option value="log">Log to file (dev)</option></select></label>
<label>From Address<input name="from" value="Marine Video Portal <noreply@example.com>"></label>
<label>Resend API Key<input type="password" name="resend_key"></label>
<hr>
<label>SMTP Host<input name="smtp_host"></label>
<label>SMTP Port<input name="smtp_port" value="587"></label>
<label>SMTP User<input name="smtp_user"></label>
<label>SMTP Pass<input type="password" name="smtp_pass"></label>
<label>SMTP Encryption<select name="smtp_enc"><option value="tls">TLS</option><option value="ssl">SSL</option><option value="">None</option></select></label>
<hr>
<label>SendGrid API Key<input type="password" name="sendgrid_key"></label>
<label>Mailgun Domain<input name="mailgun_domain"></label>
<label>Mailgun API Key<input type="password" name="mailgun_key"></label>
<button type="submit">Continue →</button></form>
<?php elseif($step==6):
$inst=$_SESSION['install']??[];
// Build config.php
$dbDriver=$inst[2]['driver']??'mysql';
$config=[
 'installed'=>true,
 'app'=>['name'=>$inst[3]['site_name']??'Marine Video Portal','url'=>$inst[3]['app_url']??'http://localhost','debug'=>false,'timezone'=>'UTC'],
 'database'=>[
   'driver'=>$dbDriver,
   'mysql'=>['host'=>$inst[2]['host']??'127.0.0.1','port'=>(int)($inst[2]['port']??3306),'database'=>$inst[2]['database']??'marine_portal','username'=>$inst[2]['username']??'root','password'=>$inst[2]['password']??'','charset'=>'utf8mb4'],
   'pgsql'=>['host'=>$inst[2]['host']??'127.0.0.1','port'=>(int)($inst[2]['port']??5432),'database'=>$inst[2]['database']??'marine_portal','username'=>$inst[2]['username']??'postgres','password'=>$inst[2]['password']??'','charset'=>'utf8'],
   'sqlite'=>['path'=>$inst[2]['sqlite_path']??__DIR__.'/../storage/database.sqlite'],
 ],
 'auth0'=>['domain'=>$inst[3]['auth0_domain']??'','client_id'=>$inst[3]['auth0_client_id']??'','client_secret'=>$inst[3]['auth0_client_secret']??'','secret'=>$inst[3]['auth0_secret']??''],
 'bunny'=>['library_id'=>$inst[4]['library_id']??'','api_key'=>$inst[4]['api_key']??'','token_auth_key'=>$inst[4]['token_auth_key']??'','cdn_hostname'=>$inst[4]['cdn_hostname']??'','cdn_token_key'=>$inst[4]['cdn_token_key']??''],
 'email'=>[
   'driver'=>$inst[5]['email_driver']??'resend',
   'from'=>$inst[5]['from']??'Marine Video Portal <noreply@example.com>',
   'reply_to'=>'',
   'resend'=>['api_key'=>$inst[5]['resend_key']??''],
   'smtp'=>['host'=>$inst[5]['smtp_host']??'','port'=>(int)($inst[5]['smtp_port']??587),'username'=>$inst[5]['smtp_user']??'','password'=>$inst[5]['smtp_pass']??'','encryption'=>$inst[5]['smtp_enc']??'tls'],
   'sendgrid'=>['api_key'=>$inst[5]['sendgrid_key']??''],
   'mailgun'=>['domain'=>$inst[5]['mailgun_domain']??'','api_key'=>$inst[5]['mailgun_key']??'','endpoint'=>'api.mailgun.net'],
 ],
 'admin_emails'=>array_map('trim', explode(',', $inst[3]['admin_emails']??'admin@example.com')),
 'security'=>['gate_secret'=>$inst[3]['gate_secret']??bin2hex(random_bytes(32)),'geo_whitelist'=>'','admin_geo_whitelist'=>'','admin_geo_bypass_emails'=>''],
 'vapid'=>['public_key'=>'','private_key'=>'','subject'=>'mailto:'.($inst[3]['admin_emails']??'admin@example.com')],
 'sentry_dsn'=>'',
];
file_put_contents(__DIR__.'/../config.php','<?php return '.var_export($config,true).';');
file_put_contents(__DIR__.'/../storage/install.lock', date('c'));
// Run migrations
require __DIR__.'/../src/Database/Connection.php';
use MarinePortal\Database\Connection;
try{
 $conn=new Connection($config['database']['driver'],$config['database'][$config['database']['driver']]);
 $pdo=$conn->pdo;
 $sql=file_get_contents(__DIR__.'/migrations.sql');
 // naive split
 foreach(explode(';', $sql) as $stmt){ $stmt=trim($stmt); if($stmt) $pdo->exec($stmt); }
 echo "<h2>✅ Installation Complete!</h2><p>Config written, database migrated.</p>";
 echo "<p><a href='/'><button>Go to Site</button></a> <a href='/admin'><button>Go to Admin</button></a></p>";
 echo "<p style='color:#f59e0b'>⚠️ Delete /install folder after install for security (or it auto-locks via install.lock).</p>";
} catch(Throwable $e){ echo "<h2 class='fail'>DB Error: ".$e->getMessage()."</h2><pre>".$e->getTraceAsString()."</pre>"; }
endif; ?>
</div></body></html>
