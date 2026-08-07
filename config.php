<?php
session_start();
define('DB_HOST','localhost');
define('DB_NAME','u619448402_madulds');
define('DB_USER','u619448402_madulds');
define('DB_PASS','MadUlds@4');
function db(){static $p=null;if(!$p)$p=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);return $p;}
function out($x,$c=200){http_response_code($c);header('Content-Type: application/json');echo json_encode($x,JSON_UNESCAPED_UNICODE);exit;}
function login_required(){if(empty($_SESSION['user']))out(['ok'=>false,'error'=>'Login required'],401);}
function admin_required(){login_required();if(($_SESSION['user']['role']??'')!=='admin')out(['ok'=>false,'error'=>'Admin only'],403);}
?>