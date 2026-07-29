<?php
function config($k=null){ static $cfg=null; if(!$cfg) $cfg=require ROOT.'/config.php'; if(!$k) return $cfg; $parts=explode('.',$k); $v=$cfg; foreach($parts as $p){ if(!isset($v[$p])) return null; $v=$v[$p]; } return $v; }
function db(){ return \MarinePortal\Database\Connection::getInstance(); }
function abort($c,$m=''){ http_response_code($c); echo $m; exit; }
function json_response($d,$c=200){ http_response_code($c); header('Content-Type: application/json'); echo json_encode($d); exit; }
