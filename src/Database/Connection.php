<?php
namespace MarinePortal\Database;
use PDO;
class Connection {
    private static ?self $instance=null;
    public PDO $pdo;
    public string $driver;
    public static function getInstance(): self {
        if(self::$instance) return self::$instance;
        $cfg=require ROOT.'/config.php';
        $dbCfg=$cfg['database'];
        $driver=$dbCfg['driver'];
        self::$instance=new self($driver,$dbCfg[$driver] ?? []);
        return self::$instance;
    }
    public function __construct(string $driver, array $cfg){
        $this->driver=$driver;
        $dsn=$this->buildDsn($driver,$cfg);
        $user=$cfg['username']??null; $pass=$cfg['password']??null;
        $opts=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC];
        if($driver==='sqlite'){ $user=null; $pass=null; if(!file_exists($cfg['path'])){ @touch($cfg['path']); } }
        $this->pdo=new PDO($dsn,$user,$pass,$opts);
    }
    private function buildDsn(string $driver, array $cfg): string {
        return match($driver){
            'mysql' => "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}",
            'pgsql' => "pgsql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']}",
            'sqlite' => "sqlite:{$cfg['path']}",
            default => throw new \Exception("Unsupported driver $driver")
        };
    }
    // Multi-db compatible upsert helpers
    public function table(string $name): QueryBuilder { return new QueryBuilder($this->pdo,$name,$this->driver); }
}
class QueryBuilder {
    public function __construct(private PDO $pdo, private string $table, private string $driver){}
    public function all(string $where='', array $params=[]): array { $stmt=$this->pdo->prepare("SELECT * FROM {$this->table} $where"); $stmt->execute($params); return $stmt->fetchAll(); }
    public function find(int|string $id, string $key='id'): ?array { $stmt=$this->pdo->prepare("SELECT * FROM {$this->table} WHERE $key=:id LIMIT 1"); $stmt->execute(['id'=>$id]); $r=$stmt->fetch(); return $r?:null; }
    public function insert(array $data): string { $cols=implode(',',array_keys($data)); $ph=':'.implode(',:',array_keys($data)); $stmt=$this->pdo->prepare("INSERT INTO {$this->table} ($cols) VALUES ($ph)"); $stmt->execute($data); return $this->pdo->lastInsertId(); }
    public function update(array $data, string $where, array $params): int { $set=implode(',',array_map(fn($k)=>"$k=:$k",array_keys($data))); $stmt=$this->pdo->prepare("UPDATE {$this->table} SET $set WHERE $where"); $stmt->execute(array_merge($data,$params)); return $stmt->rowCount(); }
    public function delete(string $where, array $params): int { $stmt=$this->pdo->prepare("DELETE FROM {$this->table} WHERE $where"); $stmt->execute($params); return $stmt->rowCount(); }
}
