<?php
namespace MarineVideoPortal\Models;
use MarineVideoPortal\Core\Database;
class Video {
  public static function all(string $search='',?string $collection=null,int $limit=50,int $offset=0): array {
    $sql="SELECT vm.*, c.name as collection_name FROM videos_meta vm LEFT JOIN collections c ON vm.collection_id=c.id WHERE 1=1"; $params=[];
    if($search){ $sql.=" AND vm.title LIKE ?"; $params[]="%$search%"; }
    if($collection){ $sql.=" AND vm.collection_id=?"; $params[]=$collection; }
    $sql.=" ORDER BY vm.custom_order ASC, vm.created_at DESC LIMIT $limit OFFSET $offset";
    return Database::fetchAll($sql,$params);
  }
  public static function count(string $search='',?string $col=null): int {
    $sql="SELECT COUNT(*) as cnt FROM videos_meta vm WHERE 1=1"; $params=[];
    if($search){ $sql.=" AND title LIKE ?"; $params[]="%$search%"; }
    if($col){ $sql.=" AND collection_id=?"; $params[]=$col; }
    $row=Database::fetchOne($sql,$params); return (int)($row['cnt']??0);
  }
  public static function find(string $guid){ return Database::fetchOne("SELECT * FROM videos_meta WHERE guid=?",[$guid]); }
  public static function updateMeta(string $guid,array $data){
    $fields=[]; $params=[];
    foreach(['title','collection_id','watermark_mode','custom_order'] as $k){ if(isset($data[$k])){ $fields[]="$k=?"; $params[]=$data[$k]; } }
    if(!$fields) return; $params[]=$guid; Database::exec("UPDATE videos_meta SET ".implode(',',$fields)." WHERE guid=?",$params);
  }
  public static function deleteMeta(string $guid){ Database::exec("DELETE FROM videos_meta WHERE guid=?",[$guid]); }
}
