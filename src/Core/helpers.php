<?php
function config(string $key=null) { static $cfg=null; if(!$cfg) $cfg=require ROOT.'/config.php'; if(!$key) return $cfg; $parts=explode('.',$key); $v=$cfg; foreach($parts as $p){ if(!isset($v[$p])) return null; $v=$v[$p]; } return $v; }
function view(string $name, array $data=[]): void { extract($data); $file=ROOT."/src/Views/$name.php"; if(file_exists($file)) require $file; }
function db(): \MarinePortal\Database\Connection { return \MarinePortal\Database\Connection::getInstance(); }
function abort(int $code, string $msg=''){ http_response_code($code); echo $msg; exit; }
function json_response($data,int $code=200){ http_response_code($code); header('Content-Type: application/json'); echo json_encode($data); exit; }
