<?php
namespace MarineVideoPortal\Services;
class WatermarkService {
  public static function resolve(string $email,string $guid,string $shareWm='default'): string {
    $email=strtolower(trim($email));
    $exempt=array_filter(array_map('trim', explode(',', $_ENV['WATERMARK_EXEMPT']??'')));
    $domain=substr(strrchr($email,'@'),1);
    foreach($exempt as $ex){
      $ex=strtolower($ex);
      if($ex===$email) return 'none';
      if($domain===$ex || str_ends_with($email,'@'.$ex)) return 'none';
    }
    if($shareWm!=='default' && $shareWm!=='') return $shareWm;
    $vm=\MarineVideoPortal\Core\Database::fetchOne("SELECT watermark_mode FROM videos_meta WHERE guid=?",[$guid]);
    if($vm && ($vm['watermark_mode']??'default')!=='default') return $vm['watermark_mode'];
    return $_ENV['WATERMARK_DEFAULT']??'email';
  }
}
