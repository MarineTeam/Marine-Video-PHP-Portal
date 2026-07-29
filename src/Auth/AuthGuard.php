<?php
namespace MarinePortal\Auth;
class AuthGuard {
    public function __construct(private array $config){}
    public function check(): bool { return !empty($_SESSION['user']['email']); }
    public function user(): ?array { return $_SESSION['user']??null; }
    public function isAdmin(?string $email=null): bool {
        $email=strtolower(trim($email??$_SESSION['user']['email']??''));
        $admins=array_map(fn($e)=>strtolower(trim($e)),$this->config['admin_emails']??[]);
        return in_array($email,$admins);
    }
    public function isApprovedViewer(?string $email=null): bool {
        $email=strtolower(trim($email??$_SESSION['user']['email']??''));
        if($this->isAdmin($email)) return true;
        try{
            $pdo=\MarinePortal\Database\Connection::getInstance()->pdo;
            $stmt=$pdo->prepare("SELECT id FROM approved_viewers WHERE email=:e LIMIT 1");
            $stmt->execute(['e'=>$email]);
            return (bool)$stmt->fetch();
        }catch(\Throwable){ return false; }
    }
}
