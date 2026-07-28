<?php
session_start();
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$lock = __DIR__.'/../storage/install.lock';
if(file_exists($lock) && !isset($_GET['force'])){ die('<h2>Installed</h2><p>Delete storage/install.lock to re-run. <a href="?force=1">Force</a></p>'); }
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Installer</title>
<style>body{font-family:system-ui;max-width:720px;margin:40px auto;background:#0a0a0f;color:#eee;padding:24px;border-radius:16px} .card{background:#16161f;padding:20px;border-radius:12px;margin:16px 0;border:1px solid #222} input,select{width:100%;padding:11px;margin:6px 0;border-radius:8px;border:1px solid #333;background:#0f0f17;color:#fff;box-sizing:border-box} button{background:#7c3aed;color:#fff;padding:12px 20px;border:0;border-radius:10px;cursor:pointer;font-weight:600} .ok{color:#22c55e}.fail{color:#ef4444}</style></head><body>
<h1>🎬 Marine Video Portal - 5 Minute Installer</h1><small>Step <?= $step ?> / 5</small>

<?php if($step===1): $checks=['php'=>version_compare(PHP_VERSION,'8.1.0','>='),'pdo'=>extension_loaded('pdo'),'curl'=>extension_loaded('curl'),'openssl'=>extension_loaded('openssl')]; ?>
<div class="card"><h3>Environment</h3><ul><?php foreach($checks as $k=>$v): ?><li class="<?= $v?'ok':'fail' ?>"><?=h($k)?>: <?=$v?'OK':'FAIL'?></li><?php endforeach; ?><li>PHP <?=h(PHP_VERSION)?></li></ul><a href="?step=2"><button>Continue</button></a></div>
<?php elseif($step===2): ?>
<div class="card"><h3>Database (MySQL)</h3><form method="post" action="?step=3"><input name="host" value="127.0.0.1" required><input name="port" placeholder="3306" value="3306"><input name="database" placeholder="Database name" required><input name="username" placeholder="Username" required><input name="password" type="password" placeholder="Password"><button type="submit">Test</button></form></div>
<?php elseif($step===3): ?>
<?php $_SESSION['db']=$_POST; $cfg=$_SESSION['db']; try{ $dsn="mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset=utf8mb4"; new PDO($dsn,$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); $ok=true; }catch(Throwable $e){$ok=false;$err=$e->getMessage();} ?>
<div class="card"><?php if(!$ok): ?><p class="fail">Fail: <?=h($err)?></p><a href="?step=2"><button>Back</button></a><?php else: ?><p class="ok">DB OK</p><form method="post" action="?step=4"><input name="app_url" value="<?=h('https://'.$_SERVER['HTTP_HOST'])?>" required><input name="auth0_domain" placeholder="Auth0 Domain" required><input name="auth0_client_id" placeholder="Client ID" required><input name="auth0_client_secret" placeholder="Client Secret" required><input name="auth0_secret" value="<?=bin2hex(random_bytes(16))?>" required><input name="admin_emails" placeholder="admin@example.com" required><button>Continue</button></form><?php endif; ?></div>
<?php elseif($step===4): $_SESSION['app']=$_POST; ?>
<div class="card"><h3>Bunny + Email</h3><form method="post" action="?step=5"><input name="bunny_library_id" placeholder="Library ID" required><input name="bunny_api_key" placeholder="API Key" required><input name="bunny_token_key" placeholder="Token Key" required><input name="bunny_cdn_host" placeholder="CDN Host vz-..."><select name="mail_driver"><option value="resend">Resend (default)</option><option value="smtp">SMTP</option><option value="sendgrid">SendGrid</option><option value="log">Log</option></select><input name="mail_from" placeholder="From email"><input name="resend_key" placeholder="Resend API Key"><input name="smtp_host" placeholder="SMTP Host"><input name="smtp_user" placeholder="SMTP User"><input name="smtp_pass" type="password" placeholder="SMTP Pass"><input name="sendgrid_key" placeholder="SendGrid Key"><button>Install</button></form></div>
<?php elseif($step===5):
$_SESSION['bunny']=$_POST; $db=$_SESSION['db']; $app=$_SESSION['app']; $bunny=$_SESSION['bunny'];
$env="APP_URL={$app['app_url']}
APP_ENV=production
APP_KEY=base64:".bin2hex(random_bytes(16))."
APP_NAME="Marine Video Portal"
DB_CONNECTION=mysql
DB_HOST={$db['host']}
DB_PORT={$db['port']}
DB_DATABASE={$db['database']}
DB_USERNAME={$db['username']}
DB_PASSWORD={$db['password']}
AUTH0_DOMAIN={$app['auth0_domain']}
AUTH0_CLIENT_ID={$app['auth0_client_id']}
AUTH0_CLIENT_SECRET={$app['auth0_client_secret']}
AUTH0_SECRET={$app['auth0_secret']}
ADMIN_EMAILS={$app['admin_emails']}
BUNNY_LIBRARY_ID={$bunny['bunny_library_id']}
BUNNY_API_KEY={$bunny['bunny_api_key']}
BUNNY_TOKEN_AUTH_KEY={$bunny['bunny_token_key']}
BUNNY_CDN_HOSTNAME={$bunny['bunny_cdn_host']}
MAIL_DRIVER={$bunny['mail_driver']}
MAIL_FROM_ADDRESS={$bunny['mail_from']}
MAIL_FROM_NAME="Marine Video Portal"
RESEND_API_KEY={$bunny['resend_key']}
SMTP_HOST={$bunny['smtp_host']}
SMTP_USERNAME={$bunny['smtp_user']}
SMTP_PASSWORD={$bunny['smtp_pass']}
SENDGRID_API_KEY={$bunny['sendgrid_key']}
";
file_put_contents(__DIR__.'/../.env',$env);
echo '<div class="card">';
try{
  $pdo=new PDO("mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset=utf8mb4",$db['username'],$db['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $sql=file_get_contents(__DIR__.'/../database/migrations/schema.sql');
  $pdo->exec($sql);
  @mkdir(__DIR__.'/../storage',0755,true); file_put_contents(__DIR__.'/../storage/install.lock',date('c'));
  echo '<h3 class="ok">Installed!</h3><a href="/"><button>Go to site</button></a>';
}catch(Throwable $e){ echo '<p class="fail">'.h($e->getMessage()).'</p>'; }
echo '</div>';
endif; ?>
</body></html>
