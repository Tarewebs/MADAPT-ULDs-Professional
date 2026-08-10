<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/config.php';

function reply(array $data,int $status=200): never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}

try{
    if(empty($_SESSION['user'])||strtoupper((string)($_SESSION['user']['role']??''))!=='ADMIN')reply(['ok'=>false,'error'=>'Administrator access required'],403);
    if(empty($_FILES['favicon'])||!is_array($_FILES['favicon']))reply(['ok'=>false,'error'=>'No PNG file received'],400);
    $f=$_FILES['favicon'];
    if((int)($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)reply(['ok'=>false,'error'=>'PNG upload failed'],400);
    if((int)$f['size']>2*1024*1024)reply(['ok'=>false,'error'=>'PNG must be 2 MB or smaller'],400);
    $tmp=(string)$f['tmp_name'];
    if(!is_uploaded_file($tmp))reply(['ok'=>false,'error'=>'Invalid upload'],400);
    $info=@getimagesize($tmp);
    if($info===false||($info['mime']??'')!=='image/png')reply(['ok'=>false,'error'=>'Only PNG images are allowed'],400);
    $root=__DIR__.'/uploads/branding';
    if(!is_dir($root)&&!mkdir($root,0755,true)&&!is_dir($root))throw new RuntimeException('Could not create upload directory');
    $name='favicon_'.bin2hex(random_bytes(8)).'.png';
    $target=$root.'/'.$name;
    if(!move_uploaded_file($tmp,$target))throw new RuntimeException('Could not save favicon');
    $path='uploads/branding/'.$name;
    $pdo=db();
    $q=$pdo->prepare('INSERT INTO madapt_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $q->execute(['favicon_path',$path]);
    reply(['ok'=>true,'path'=>$path]);
}catch(Throwable $e){error_log('MADAPT FAVICON ERROR: '.$e->getMessage());reply(['ok'=>false,'error'=>$e->getMessage()],500);}
