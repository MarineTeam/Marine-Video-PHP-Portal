<?php
namespace MarineVideoPortal\Core;
class EnvLoader{public static function load($p):void{$f=$p.'/.env';if(!file_exists($f))return;foreach(file($f,FILE_IGNORE_NEW_LINES) as $l){$l=trim($l);if($l===''||str_starts_with($l,'#'))continue;if(!str_contains($l,'='))continue;[$k,$v]=explode('=',$l,2);$k=trim($k);$v=trim(trim($v),'"\'');$_ENV[$k]=$v;$_SERVER[$k]=$v;putenv("$k=$v");}}}
