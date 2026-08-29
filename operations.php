<?php

declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function opJson(array $data, int $status=200): never { http_response_code($status); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function opUser(): array { if (empty($_SESSION['user'])) opJson(['ok'=>false,'error'=>'Login required'],401); return $_SESSION['user']; }
function opAdmin(array $u): bool { return in_array(strtoupper((string)($u['role']??'')),['ADMIN','SUPERVISOR'],true); }
function opDb(): PDO { return db(); }
function ensureOperationsTables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS madapt_turnarounds (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        flight_number VARCHAR(20) NOT NULL,
        block_in DATETIME NOT NULL,
        planned_block_out DATETIME NOT NULL,
        status ENUM('ACTIVE','COMPLETED','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
        created_by VARCHAR(190) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id), KEY idx_turnaround_status(status), KEY idx_turnaround_flight(flight_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS madapt_turnaround_tasks (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        turnaround_id BIGINT UNSIGNED NOT NULL,
        task_group VARCHAR(80) NOT NULL,
        task_name VARCHAR(190) NOT NULL,
        sequence_no INT NOT NULL,
        planned_minutes INT NOT NULL DEFAULT 0,
        status ENUM('PENDING','STARTED','COMPLETED') NOT NULL DEFAULT 'PENDING',
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        started_by VARCHAR(190) NULL,
        completed_by VARCHAR(190) NULL,
        PRIMARY KEY(id), KEY idx_task_turnaround(turnaround_id), CONSTRAINT fk_task_turnaround FOREIGN KEY(turnaround_id) REFERENCES madapt_turnarounds(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function taskTemplate(): array {
    return [
        ['Passenger Services','Cabin preparation / cabin check',15],
        ['Passenger Services','Passenger boarding coordination',20],
        ['Bag / CGO Handling','Unload bags / cargo',15],
        ['Bag / CGO Handling','Load bags / cargo',15],
        ['A/C Servicing','Catering / cabin supplies',10],
        ['A/C Servicing','Water / lavatory servicing',10],
        ['A/C Servicing','Fuel / technical coordination',10],
        ['A/C Servicing','Final walk-around / doors closed',5],
    ];
}

try {
    $u=opUser(); $pdo=opDb(); ensureOperationsTables($pdo);
    $action=trim((string)($_GET['action']??'list'));
    $payload=[]; $raw=file_get_contents('php://input'); if($raw!==false&&trim($raw)!==''){ $j=json_decode($raw,true); if(is_array($j))$payload=$j; } if(!$payload)$payload=$_POST;

    if($action==='list') {
        $turn=$pdo->query("SELECT id,flight_number,block_in,planned_block_out,status,created_by,created_at FROM madapt_turnarounds WHERE status='ACTIVE' ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
        $result=[];
        foreach($turn as $t){
            $q=$pdo->prepare('SELECT id,task_group,task_name,sequence_no,planned_minutes,status,started_at,completed_at,started_by,completed_by FROM madapt_turnaround_tasks WHERE turnaround_id=? ORDER BY sequence_no,id');$q->execute([(int)$t['id']);$tasks=$q->fetchAll(PDO::FETCH_ASSOC);
            $done=0; foreach($tasks as $x) if($x['status']==='COMPLETED')$done++;
            $result[]=['turnaround'=>$t,'tasks'=>$tasks,'completed'=>$done,'total'=>count($tasks)];
        }
        opJson(['ok'=>true,'turnarounds'=>$result,'template'=>taskTemplate(),'can_manage'=>opAdmin($u)]);
    }
    if($action==='start_turnaround') {
        $flight=strtoupper(trim((string)($payload['flight_number']??'')));
        if(!preg_match('/^[A-Z]{2,3}[0-9]{1,5}$/',$flight)) opJson(['ok'=>false,'error'=>'Invalid flight number'],400);
        $q=$pdo->prepare("SELECT id FROM madapt_turnarounds WHERE flight_number=? AND status='ACTIVE' LIMIT 1");$q->execute([$flight]);if($q->fetch())opJson(['ok'=>false,'error'=>'An active turnaround already exists for this flight'],409);
        $pdo->beginTransaction();
        try {
            $now=new DateTimeImmutable('now',new DateTimeZone('Europe/Madrid'));$out=$now->modify('+70 minutes');
            $q=$pdo->prepare('INSERT INTO madapt_turnarounds(flight_number,block_in,planned_block_out,created_by) VALUES(?,?,?,?)');$q->execute([$flight,$now->format('Y-m-d H:i:s'),$out->format('Y-m-d H:i:s'),$u['full_name']??$u['username']??'']);$id=(int)$pdo->lastInsertId();
            $q=$pdo->prepare('INSERT INTO madapt_turnaround_tasks(turnaround_id,task_group,task_name,sequence_no,planned_minutes) VALUES(?,?,?,?,?)');$n=1;foreach(taskTemplate() as $r){$q->execute([$id,$r[0],$r[1],$n++,$r[2]]);}
            $pdo->commit();opJson(['ok'=>true,'id'=>$id]);
        } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    if($action==='start_task'||$action==='complete_task') {
        $id=(int)($payload['id']??0);if($id<1)opJson(['ok'=>false,'error'=>'Invalid task'],400);
        $q=$pdo->prepare('SELECT id,status,turnaround_id FROM madapt_turnaround_tasks WHERE id=? LIMIT 1');$q->execute([$id]);$task=$q->fetch(PDO::FETCH_ASSOC);if(!$task)opJson(['ok'=>false,'error'=>'Task not found'],404);
        $now=(new DateTimeImmutable('now',new DateTimeZone('Europe/Madrid')))->format('Y-m-d H:i:s');$name=$u['full_name']??$u['username']??'';
        if($action==='start_task') { if($task['status']!=='PENDING')opJson(['ok'=>false,'error'=>'Task is not pending'],409);$q=$pdo->prepare('UPDATE madapt_turnaround_tasks SET status="STARTED",started_at=?,started_by=? WHERE id=?');$q->execute([$now,$name,$id]); }
        else { if($task['status']!=='STARTED')opJson(['ok'=>false,'error'=>'Task must be started first'],409);$q=$pdo->prepare('UPDATE madapt_turnaround_tasks SET status="COMPLETED",completed_at=?,completed_by=? WHERE id=?');$q->execute([$now,$name,$id]); }
        opJson(['ok'=>true]);
    }
    if($action==='reset_task') {
        if(!opAdmin($u))opJson(['ok'=>false,'error'=>'Supervisor or administrator access required'],403);$id=(int)($payload['id']??0);$q=$pdo->prepare('UPDATE madapt_turnaround_tasks SET status="PENDING",started_at=NULL,completed_at=NULL,started_by=NULL,completed_by=NULL WHERE id=?');$q->execute([$id]);opJson(['ok'=>true]);
    }
    if($action==='complete_turnaround') {
        if(!opAdmin($u))opJson(['ok'=>false,'error'=>'Supervisor or administrator access required'],403);$id=(int)($payload['id']??0);$q=$pdo->prepare('SELECT COUNT(*) FROM madapt_turnaround_tasks WHERE turnaround_id=? AND status<>"COMPLETED"');$q->execute([$id]);if((int)$q->fetchColumn()>0)opJson(['ok'=>false,'error'=>'All turnaround tasks must be completed first'],409);$q=$pdo->prepare('UPDATE madapt_turnarounds SET status="COMPLETED" WHERE id=?');$q->execute([$id]);opJson(['ok'=>true]);
    }
    opJson(['ok'=>false,'error'=>'Unknown operation action'],400);
} catch(Throwable $e) { error_log('MADAPT OPERATIONS ERROR: '.$e->getMessage()); opJson(['ok'=>false,'error'=>'Internal server error'],500); }
