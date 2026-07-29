<?php
namespace MarinePortal\Auth;
class Auth0Controller {
    public function __construct(private array $config){}
    public function login(): void {
        $auth0=$this->config['auth0'];
        $state=bin2hex(random_bytes(16));
        $_SESSION['oauth_state']=$state;
        $params=http_build_query(['response_type'=>'code','client_id'=>$auth0['client_id'],'redirect_uri'=>$this->config['app']['url'].'/auth/callback','scope'=>'openid profile email','state'=>$state]);
        header('Location: https://'.$auth0['domain'].'/authorize?'.$params); exit;
    }
    public function callback(): void {
        if(($_GET['state']??'')!==($_SESSION['oauth_state']??'')) abort(400,'Invalid state');
        $auth0=$this->config['auth0'];
        $client=new \GuzzleHttp\Client();
        $res=$client->post('https://'.$auth0['domain'].'/oauth/token',['form_params'=>['grant_type'=>'authorization_code','client_id'=>$auth0['client_id'],'client_secret'=>$auth0['client_secret'],'code'=>$_GET['code'],'redirect_uri'=>$this->config['app']['url'].'/auth/callback']]);
        $data=json_decode((string)$res->getBody(),true);
        $idToken=$data['id_token']??'';
        // Decode JWT without verify for simplicity, verify signature in production with JWKS
        $parts=explode('.',$idToken); $payload=json_decode(base64_decode($parts[1]),true);
        $_SESSION['user']=['email'=>strtolower(trim($payload['email']??'')), 'name'=>$payload['name']??'', 'sub'=>$payload['sub']??''];
        // Update last seen
        try{
            $db=\MarinePortal\Database\Connection::getInstance()->pdo;
            $db->prepare("UPDATE approved_viewers SET last_seen_at=NOW() WHERE email=:e")->execute(['e'=>$_SESSION['user']['email']]);
        }catch(\Throwable){}
        header('Location: /'); exit;
    }
    public function logout(): void {
        session_destroy();
        $auth0=$this->config['auth0'];
        $returnUrl=urlencode($this->config['app']['url']);
        header('Location: https://'.$auth0['domain'].'/v2/logout?client_id='.$auth0['client_id'].'&returnTo='.$returnUrl); exit;
    }
}
