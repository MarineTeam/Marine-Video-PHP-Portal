<?php
namespace MarineVideoPortal\Core;
use PDO;
class Database {
  private static ?PDO $pdo=null;
  public static function connection(): PDO {
    if(self::$pdo) return self::$pdo;
    $driver=$_ENV['DB_CONNECTION']??'mysql';
    $host=$_ENV['DB_HOST']??'127.0.0.1';
    $port=$_ENV['DB_PORT']??'3306';
    $db=$_ENV['DB_DATABASE']??'';
    $user=$_ENV['DB_USERNAME']??'';
    $pass=$_ENV['DB_PASSWORD']??'';
    if($driver==='sqlite'){
      $dsn='sqlite:'.$db; if(!is_dir(dirname($db))) @mkdir(dirname($db),0755,true);
      self::$pdo=new PDO($dsn,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    } else {
      $dsn="mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4"; self::$pdo=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    }
    return self::$pdo;
  }
  public static function fetchAll(string $sql,array $params=[]): array { $stmt=self::connection()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC); }
  public static function fetchOne(string $sql,array $params=[]): ?array { $stmt=self::connection()->prepare($sql); $stmt->execute($params); $row=$stmt->fetch(PDO::FETCH_ASSOC); return $row?:null; }
  public static function exec(string $sql,array $params=[]): int { $stmt=self::connection()->prepare($sql); $stmt->execute($params); return $stmt->rowCount(); }
}
