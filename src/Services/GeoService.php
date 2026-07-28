<?php
namespace MarineVideoPortal\Services;
class GeoService {
  public static function isAllowed(?string $c=null): bool {
    if(($_ENV['GEO_WHITELIST_ENABLED']??'false')!=='true') return true;
    $bypass=array_map('strtolower', array_map('trim', explode(',', $_ENV['GEO_BYPASS_EMAILS']??'')));
    $user=strtolower($_SESSION['user']['email']??$_SESSION['viewer_email']??'');
    if($user && in_array($user,$bypass,true)) return true;
    if(!$c) $c=$_SERVER['HTTP_CF_IPCOUNTRY']??null;
    if(!$c) return true;
    $allowed=array_map('strtoupper', array_map('trim', explode(',', $_ENV['GEO_WHITELIST_COUNTRIES']??'US,CA')));
    return in_array(strtoupper($c),$allowed,true);
  }
}
