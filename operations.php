<?php

declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function opJson(array $data, int $status=200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function opUser(): array { if (empty($_SESSION['user'])) opJson(['ok'=>false,'error'=>'Login required'],401); return $_SESSION['user']; }
function opCanManage(array $u): bool { return in_array(strtoupper((string)($u['role']??'')),['ADMIN','SUPERVISOR'],true); }
function ensureOperationsTables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS madapt_operations (
      id INT UNSIGNED NOT NULL,
      op_group VARCHAR(80) NOT NULL,
      operation_no INT NOT NULL,
      operation_name VARCHAR(255) NOT NULL,
      planned_start VARCHAR(20) NOT NULL DEFAULT '',
      planned_end VARCHAR(20) NOT NULL DEFAULT '',
      allocated VARCHAR(20) NOT NULL DEFAULT '',
      status ENUM('Started','Completed') NULL,
      started_at DATETIME NULL,
      completed_at DATETIME NULL,
      operator_name VARCHAR(190) NULL,
      PRIMARY KEY(id), KEY idx_op_group(op_group), KEY idx_op_status(status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $count=(int)$pdo->query('SELECT COUNT(*) FROM madapt_operations')->fetchColumn();
    if($count===0){
      $ops=[
        ['Passenger Services',1,'Engine Shutdown / Chocks on','00:00:00','',''],
        ['Passenger Services',2,'Position Ground Support Equip.','00:00:00','00:02:00','00:02'],
        ['Passenger Services',3,'Deplane Passengers at 23 pax per minute','00:02:00','00:16:00','00:14'],
        ['Passenger Services',4,'Cabin Cleaning','00:17:00','00:35:00','00:18'],
        ['Passenger Services',5,'Catering Forward & Mid Galley','00:17:00','00:34:00','00:17'],
        ['Passenger Services',6,'Catering Aft Galley','00:17:00','00:33:00','00:16'],
        ['Passenger Services',7,'Cabin Inspection (Security)','00:35:00','00:40:00','00:05'],
        ['Passenger Services',8,'Boarding clearance','00:41:00','',''],
        ['Passenger Services',9,'Passenger boarding at 13 per minute','00:42:00','01:06:00','00:24'],
        ['Passenger Services',10,'Documentation and passenger Count','01:06:00','01:08:00','00:02'],
        ['Passenger Services',11,'Door Closure and removal of passenger bridge / stairs','01:08:00','01:10:00','00:02'],
        ['Bag / CGO Handling',1,'Unload Aft Compartment','00:00:00','00:17:00','00:15'],
        ['Bag / CGO Handling',2,'Unload Forward Compartment','00:02:00','00:22:00','00:20'],
        ['Bag / CGO Handling',3,'Unload Bulk hold','00:02:00','00:10:00','00:08'],
        ['Bag / CGO Handling',4,'Load Forward Compartment','00:22:00','00:42:00','00:20'],
        ['Bag / CGO Handling',5,'Load Aft Compartment','00:32:00','01:02:00','00:30'],
        ['Bag / CGO Handling',6,'Load Bulk hold','01:02:00','01:10:00','00:08'],
        ['A/C Servicing',1,'Refueling','00:00:00','00:47:00','00:30'],
        ['A/C Servicing',2,'Service Toilets','00:02:00','00:14:00','00:12'],
        ['A/C Servicing',3,'Service Potable water','00:02:00','00:13:00','00:11']
      ];
      $q=$pdo->prepare('INSERT INTO madapt_operations(id,op_group,operation_no,operation_name,planned_start,planned_end,allocated) VALUES(?,?,?,?,?,?,?)');
      $id=1; foreach($ops as $r){$q->execute([$id++,$r[0],$r[1],$r[2],$r[3],$r[4],$r[5]]);}
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS madapt_operations_meta (meta_key VARCHAR(50) NOT NULL PRIMARY KEY, meta_value VARCHAR(190) NULL)");
    $q=$pdo->prepare("INSERT INTO madapt_operations_meta(meta_key,meta_value) VALUES('block_in',NULL) ON DUPLICATE KEY UPDATE meta_key=meta_key");$q->execute();
}
function opPayload(PDO $pdo): array {
  $block=$pdo->query("SELECT meta_value FROM madapt_operations_meta WHERE meta_key='block_in'")->fetchColumn();
  $rows=$pdo->query('SELECT id,op_group,operation_no,operation_name,planned_start,planned_end,allocated,status,started_at,completed_at,operator_name FROM madapt_operations ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
  $done=0;$started=0;foreach($rows as $r){if($r['status']==='Completed')$done++;elseif($r['status']==='Started')$started++;}
  return ['ok'=>true,'serverNow'=>time()*1000,'blockIn'=>$block?$block:null,'operations'=>$rows,'summary'=>['total'=>count($rows),'started'=>$started,'completed'=>$done,'pending'=>count($rows)-$started-$done]];
}
try {
  $u=opUser();$pdo=db();ensureOperationsTables($pdo);$action=trim((string)($_GET['action']??'data'));$raw=file_get_contents('php://input');$d=[];if($raw!==false&&trim($raw)!==''){ $j=json_decode($raw,true);if(is_array($j))$d=$j; }if(!$d)$d=$_POST;
  if($action==='data')opJson(opPayload($pdo));
  if($action==='block_in'){$iso=trim((string)($d['iso']??''));$dt=$iso!==''?new DateTime($iso,new DateTimeZone('Europe/Madrid')):new DateTime('now',new DateTimeZone('Europe/Madrid'));$q=$pdo->prepare("UPDATE madapt_operations_meta SET meta_value=? WHERE meta_key='block_in'");$q->execute([$dt->format('Y-m-d H:i:s')]);opJson(opPayload($pdo));}
  if($action==='update'){$id=(int)($d['id']??0);$actionName=trim((string)($d['operation_action']??$d['action']??''));if($id<1||!in_array($actionName,['Started','Completed'],true))opJson(['ok'=>false,'error'=>'Invalid operation or action'],400);$q=$pdo->prepare('SELECT status,started_at,operator_name FROM madapt_operations WHERE id=?');$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)opJson(['ok'=>false,'error'=>'Operation not found'],404);$now=(new DateTime('now',new DateTimeZone('Europe/Madrid')))->format('Y-m-d H:i:s');$name=trim((string)($d['operator']??$u['full_name']??$u['username']??''));if($actionName==='Started'){if($row['status']==='Completed')opJson(['ok'=>false,'error'=>'Operation is already completed'],409);$q=$pdo->prepare("UPDATE madapt_operations SET status='Started',started_at=?,completed_at=NULL,operator_name=? WHERE id=?");$q->execute([$now,$name,$id]);}else{if($row['status']!=='Started')opJson(['ok'=>false,'error'=>'Start the operation before completing it'],409);$q=$pdo->prepare("UPDATE madapt_operations SET status='Completed',completed_at=?,operator_name=? WHERE id=?");$q->execute([$now,$name?:$row['operator_name'],$id]);}opJson(opPayload($pdo));}
  if($action==='reset'){$ok=opCanManage($u);if(!$ok)opJson(['ok'=>false,'error'=>'Supervisor or administrator access required'],403);$pdo->exec('UPDATE madapt_operations SET status=NULL,started_at=NULL,completed_at=NULL,operator_name=NULL');opJson(opPayload($pdo));}
  opJson(['ok'=>false,'error'=>'Unknown operation action'],400);
} catch(Throwable $e){error_log('MADAPT OPERATIONS ERROR: '.$e->getMessage());opJson(['ok'=>false,'error'=>'Internal server error'],500);}
