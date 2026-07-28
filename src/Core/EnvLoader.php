<?php
namespace MarineVideoPortal\Core;
class EnvLoader {
  public static function load(string $path): void {
    $file=$path.'/.env'; if(!file_exists($file)) return;
    foreach(file($file, FILE_IGNORE_NEW_LINES) as $line){
      $line=trim($line); if($line===''||str_starts_with($line,'#')) continue;
      if(!str_contains($line,'=')) continue;
      [$k,$v]=explode('=',$line,2); $k=trim($k); $v=trim($v); $v=trim($v,'"\'');
      $_ENV[$k]=$v; $_SERVER[$k]=$v; putenv("$k=$v");
    }
  }
}
