<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/config.php';
function J(array $d,int $s=200):never{http_response_code($s);echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function D():array{$r=file_get_contents('php://input');if($r!==false&&trim($r)!==''){$j=json_decode($r,true);if(is_array($j))return$j;}return$_POST;}
function adminUser():array{if(empty($_SESSION['user']))J(['ok'=>false,'error'=>'Login required'],401);$u=$_SESSION['user'];if(strtoupper((string)($u['role']??''))!=='ADMIN')J(['ok'=>false,'error'=>'Administrator access required'],403);return$u;}
function validULD(string $u):bool{return in_array(strtoupper($u),['AKE','PAJ','PMC','PAG'],true);}
function col(PDO $p,string $t,string $c):bool{$q=$p->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$q->execute([$t,$c]);return(int)$q->fetchColumn()>0;}
try{
$p=db();adminUser();$a=trim((string)($_GET['action']??''));$d=D();
if($a==='edit'){
 $id=(int)($d['id']??0);$uld=strtoupper(trim((string)($d['uld_type']??'')));$type=strtoupper(trim((string)($d['movement_type']??$d['type']??'')));$qty=(int)($d['quantity']??$d['qty']??0);$flight=strtoupper(trim((string)($d['flight_number']??$d['flight']??'')));$ref=trim((string)($d['reference']??''));$remarks=trim((string)($d['remarks']??''));
 if($id<1||!validULD($uld)||!in_array($type,['IN','OUT'],true)||$qty<1)J(['ok'=>false,'error'=>'Invalid movement data'],400);if($flight!==''&&!preg_match('/^[A-Z]{2,3}[0-9]{1,5}$/',$flight))J(['ok'=>false,'error'=>'Invalid flight number'],400);
 $p->beginTransaction();try{
  $q=$p->prepare('SELECT id,movement_type,uld_type,quantity FROM uld_movements WHERE id=? FOR UPDATE');$q->execute([$id]);$old=$q->fetch(PDO::FETCH_ASSOC);if(!$old)throw new RuntimeException('Movement not found');$oldUld=strtoupper((string)$old['uld_type']);
  $ulds=array_values(array_unique([$oldUld,$uld]));sort($ulds,SORT_STRING);$locked=[];foreach($ulds as $x){$q=$p->prepare('SELECT current_stock FROM uld_stock WHERE uld_type=? FOR UPDATE');$q->execute([$x]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException('ULD type not found: '.$x);$locked[$x]=(int)$r['current_stock'];}
  $base=$old['movement_type']==='IN'?$locked[$oldUld]-(int)$old['quantity']:$locked[$oldUld]+(int)$old['quantity'];if($base<0)throw new RuntimeException('Stock is inconsistent with this movement history');
  if($oldUld!==$uld){$q=$p->prepare('UPDATE uld_stock SET current_stock=?,updated_at=CURRENT_TIMESTAMP WHERE uld_type=?');$q->execute([$base,$oldUld]);$base=$locked[$uld];}
  if($type==='OUT'&&$qty>$base)throw new RuntimeException("Insufficient $uld stock. Current stock: $base");$new=$type==='IN'?$base+$qty:$base-$qty;$q=$p->prepare('UPDATE uld_stock SET current_stock=?,updated_at=CURRENT_TIMESTAMP WHERE uld_type=?');$q->execute([$new,$uld]);
  if(col($p,'uld_movements','flight_number')){$q=$p->prepare('UPDATE uld_movements SET movement_type=?,uld_type=?,quantity=?,reference=?,remarks=?,flight_number=? WHERE id=?');$q->execute([$type,$uld,$qty,$ref,$remarks,$flight?:null,$id]);}else{$stored=$remarks;if($flight)$stored=$stored!==''?$stored.' | Flight: '.$flight:'Flight: '.$flight;$q=$p->prepare('UPDATE uld_movements SET movement_type=?,uld_type=?,quantity=?,reference=?,remarks=? WHERE id=?');$q->execute([$type,$uld,$qty,$ref,$stored,$id]);}
  $p->commit();J(['ok'=>true,'new_stock'=>$new]);
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw$e;}
}
if($a==='delete'){
 $id=(int)($d['id']??0);if($id<1)J(['ok'=>false,'error'=>'Invalid movement id'],400);$p->beginTransaction();try{$q=$p->prepare('SELECT movement_type,uld_type,quantity FROM uld_movements WHERE id=? FOR UPDATE');$q->execute([$id]);$old=$q->fetch(PDO::FETCH_ASSOC);if(!$old)throw new RuntimeException('Movement not found');$uld=strtoupper((string)$old['uld_type']);$q=$p->prepare('SELECT current_stock FROM uld_stock WHERE uld_type=? FOR UPDATE');$q->execute([$uld]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException('ULD type not found');$cur=(int)$r['current_stock'];$new=$old['movement_type']==='IN'?$cur-(int)$old['quantity']:$cur+(int)$old['quantity'];if($new<0)throw new RuntimeException('Cannot delete movement: stock would become negative');$q=$p->prepare('UPDATE uld_stock SET current_stock=?,updated_at=CURRENT_TIMESTAMP WHERE uld_type=?');$q->execute([$new,$uld]);$q=$p->prepare('DELETE FROM uld_movements WHERE id=?');$q->execute([$id]);$p->commit();J(['ok'=>true,'new_stock'=>$new]);}catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw$e;}
}
J(['ok'=>false,'error'=>'Unknown action'],400);
}catch(Throwable $e){error_log('MADAPT MOVEMENT ADMIN ERROR: '.$e->getMessage());J(['ok'=>false,'error'=>'Internal server error'],500);}
