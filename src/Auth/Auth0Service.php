<?php
namespace MarineVideoPortal\Auth;
use MarineVideoPortal\Core\Database;
class Auth0Service {
  public static function normalizeEmail($e){return strtolower(trim((string)$e));}
  public static function isAdmin($email){
    $list=array_filter(array_map([self::class,'normalizeEmail'], explode(',', $_ENV['ADMIN_EMAILS']??'')));
    return in_array(self::normalizeEmail($email),$list,true);
  }
  public static function isApprovedViewer($email): bool {
    if(self::isAdmin($email)) return true;
    $email=self::normalizeEmail($email);
    if(($_ENV['ENFORCE_VIEWER_LIST']??'true')!=='true') return true;
    $row=Database::fetchOne("SELECT is_approved FROM viewers WHERE email=?",[$email]);
    return $row && (int)$row['is_approved']===1;
  }
  public static function loginUrl(){
    $d=$_ENV['AUTH0_DOMAIN']??''; $c=$_ENV['AUTH0_CLIENT_ID']??'';
    $r=rtrim($_ENV['APP_URL']??'','/').'/auth/callback';
    $s=bin2hex(random_bytes(16)); $_SESSION['oauth_state']=$s;
    return "https://$d/authorize?".http_build_query(['response_type'=>'code','client_id'=>$c,'redirect_uri'=>$r,'scope'=>'openid profile email','state'=>$s]);
  }
  public static function handleCallback(){
    $d=$_ENV['AUTH0_DOMAIN']??''; $c=$_ENV['AUTH0_CLIENT_ID']??''; $sec=$_ENV['AUTH0_CLIENT_SECRET']??'';
    $r=rtrim($_ENV['APP_URL']??'','/').'/auth/callback';
    if(($_GET['state']??'')!==($_SESSION['oauth_state']??'')) throw new \Exception('Invalid state');
    $ch=curl_init("https://$d/oauth/token");
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['grant_type'=>'authorization_code','client_id'=>$c,'client_secret'=>$sec,'code'=>$_GET['code']??'','redirect_uri'=>$r]),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true]);
    $res=curl_exec($ch); curl_close($ch); $data=json_decode($res,true);
    if(empty($data['access_token'])) throw new \Exception('Token fail: '.$res);
    $ch=curl_init("https://$d/userinfo"); curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$data['access_token']],CURLOPT_RETURNTRANSFER=>true]);
    $u=curl_exec($ch); curl_close($ch); $info=json_decode($u,true);
    $email=self::normalizeEmail($info['email']??'');
    if(!self::isApprovedViewer($email)){
      try{ Database::exec("INSERT INTO audit_log (actor_email,action,payload) VALUES (?,?,?)",[$email,'login_denied',json_encode(['reason'=>'not_in_viewers'])]); }catch(\Throwable $e){}
      session_destroy(); session_start();
      die("<!doctype html><html><head><meta charset='utf-8'><title>Access denied</title><style>body{font-family:system-ui;background:#0a0a0f;color:#eee;display:flex;align-items:center;justify-content:center;height:100vh} .card{background:#16161f;padding:32px;border-radius:16px;max-width:480px;border:1px solid #222} a{color:#a78bfa}</style></head><body><div class='card'><h2>Access denied</h2><p>Your email <b>".htmlspecialchars($email)."</b> is not on the approved viewers list.</p><p>Contact admin to be added via Admin > Viewers.</p><p><a href='/'>Home</a> | <a href='/auth/logout'>Logout</a></p></div></body></html>");
    }
    try{
      $exists=Database::fetchOne("SELECT id FROM viewers WHERE email=?",[$email]);
      if($exists){ Database::exec("UPDATE viewers SET last_seen_at=NOW(), name=? WHERE email=?",[$info['name']??'',$email]); }
      else { Database::exec("INSERT INTO viewers (id,email,name,is_approved,last_seen_at) VALUES (?,?,?,?,NOW())",[bin2hex(random_bytes(8)),$email,$info['name']??'', 1]); }
    }catch(\Throwable $e){}
    $_SESSION['user']=$info; return $info;
  }
  public static function currentUser(){return $_SESSION['user']??null;}
  public static function requireApproved(){
    $user=self::currentUser();
    if(!$user){ header('Location: /auth/login'); exit; }
    $email=self::normalizeEmail($user['email']??'');
    if(!self::isApprovedViewer($email)){
      http_response_code(403);
      die("<!doctype html><html><body style='font-family:system-ui;background:#0a0a0f;color:#eee;padding:40px'><h2>Not approved</h2><p>".htmlspecialchars($email)." is not approved. Ask admin to approve in Viewers tab.</p><a href='/auth/logout' style='color:#a78bfa'>Logout</a></body></html>");
    }
  }
  public static function logoutUrl(){ $d=$_ENV['AUTH0_DOMAIN']??''; $c=$_ENV['AUTH0_CLIENT_ID']??''; $r=rtrim($_ENV['APP_URL']??'','/'); return "https://$d/v2/logout?client_id=$c&returnTo=".urlencode($r); }
}
