<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/config.php';

function J(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function D(): array {
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) return $json;
    }
    return $_POST;
}
function currentUser(): array {
    if (empty($_SESSION['user'])) J(['ok' => false, 'error' => 'Login required'], 401);
    return $_SESSION['user'];
}
function adminUser(): array {
    $user = currentUser();
    if (strtoupper((string)($user['role'] ?? '')) !== 'ADMIN') {
        J(['ok' => false, 'error' => 'Administrator access required'], 403);
    }
    return $user;
}
function validULD(string $uld): bool {
    return in_array(strtoupper($uld), ['AKE', 'PAJ', 'PMC', 'PAG'], true);
}
function col(PDO $pdo, string $table, string $column): bool {
    $q = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $q->execute([$table, $column]);
    return (int)$q->fetchColumn() > 0;
}
function setting(PDO $pdo, string $key, string $default = ''): string {
    $q = $pdo->prepare('SELECT setting_value FROM madapt_settings WHERE setting_key=? LIMIT 1');
    $q->execute([$key]);
    $value = $q->fetchColumn();
    return $value === false ? $default : (string)$value;
}
function saveSetting(PDO $pdo, string $key, string $value): void {
    $q = $pdo->prepare('INSERT INTO madapt_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $q->execute([$key, $value]);
}

try {
    $pdo = db();
    $action = trim((string)($_GET['action'] ?? ''));

    if ($action === 'login') {
        $data = D();
        $username = trim((string)($data['username'] ?? ''));
        $password = (string)($data['password'] ?? '');
        if ($username === '' || $password === '') J(['ok' => false, 'error' => 'Username and password required'], 400);
        $q = $pdo->prepare('SELECT id,username,password_hash,full_name,email,profile_photo,role,active,created_at FROM users WHERE username=? LIMIT 1');
        $q->execute([$username]);
        $user = $q->fetch(PDO::FETCH_ASSOC);
        if (!$user || !(int)$user['active'] || !password_verify($password, $user['password_hash'])) J(['ok' => false, 'error' => 'Invalid username or password'], 401);
        unset($user['password_hash']);
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        J(['ok' => true, 'user' => $user]);
    }

    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        J(['ok' => true]);
    }

    if ($action === 'me') J(['ok' => true, 'user' => currentUser()]);

    if ($action === 'register') {
        $data = D();
        $username = trim((string)($data['username'] ?? ''));
        $name = trim((string)($data['full_name'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        if ($username === '' || $name === '' || $password === '') J(['ok' => false, 'error' => 'Username, full name and password are required'], 400);
        if (strlen($password) < 8) J(['ok' => false, 'error' => 'Password must contain at least 8 characters'], 400);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) J(['ok' => false, 'error' => 'Invalid email address'], 400);
        $q = $pdo->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
        $q->execute([$username]);
        if ($q->fetch()) J(['ok' => false, 'error' => 'Username already exists'], 409);
        $q = $pdo->prepare('INSERT INTO users(username,password_hash,full_name,email,role,active) VALUES(?,?,?,?,"OPERATOR",0)');
        $q->execute([$username, password_hash($password, PASSWORD_DEFAULT), $name, $email ?: null]);
        J(['ok' => true, 'message' => 'Account created and waiting for administrator approval.']);
    }

    $me = currentUser();

    if ($action === 'stock') {
        $stock = $pdo->query("SELECT uld_type,opening_stock,current_stock,minimum_level,updated_at FROM uld_stock ORDER BY FIELD(uld_type,'AKE','PAJ','PMC','PAG')")->fetchAll(PDO::FETCH_ASSOC);
        $sql = col($pdo, 'uld_movements', 'flight_number')
            ? "SELECT id,movement_type,uld_type,quantity,reference,remarks,flight_number,user_name,created_at FROM uld_movements ORDER BY id DESC LIMIT 500"
            : "SELECT id,movement_type,uld_type,quantity,reference,remarks,NULL AS flight_number,user_name,created_at FROM uld_movements ORDER BY id DESC LIMIT 500";
        $movements = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $low = array_values(array_filter($stock, static fn($row) => (int)$row['current_stock'] <= (int)$row['minimum_level']));
        $flights = [];
        try {
            $flights = $pdo->query("SELECT id,flight_number,active FROM madapt_flights WHERE active=1 ORDER BY flight_number ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}
        J(['ok' => true, 'stock' => $stock, 'low_stock' => $low, 'movements' => $movements, 'flights' => $flights]);
    }

    if ($action === 'history') {
        $sql = col($pdo, 'uld_movements', 'flight_number')
            ? "SELECT id,movement_type,uld_type,quantity,reference,remarks,flight_number,user_name,created_at FROM uld_movements ORDER BY id DESC LIMIT 1000"
            : "SELECT id,movement_type,uld_type,quantity,reference,remarks,NULL AS flight_number,user_name,created_at FROM uld_movements ORDER BY id DESC LIMIT 1000";
        $history = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        J(['ok' => true, 'history' => $history, 'movements' => $history]);
    }

    if ($action === 'movement') {
        $data = D();
        $uld = strtoupper(trim((string)($data['uld_type'] ?? '')));
        $movementType = strtoupper(trim((string)($data['movement_type'] ?? $data['type'] ?? '')));
        $quantity = (int)($data['quantity'] ?? $data['qty'] ?? 0);
        $flight = strtoupper(trim((string)($data['flight_number'] ?? $data['flight'] ?? '')));
        $reference = trim((string)($data['reference'] ?? ''));
        $remarks = trim((string)($data['remarks'] ?? ''));
        if (!validULD($uld)) J(['ok' => false, 'error' => 'Invalid ULD type'], 400);
        if (!in_array($movementType, ['IN', 'OUT'], true)) J(['ok' => false, 'error' => 'Invalid movement type'], 400);
        if ($quantity < 1) J(['ok' => false, 'error' => 'Quantity must be at least 1'], 400);
        if ($flight !== '' && !preg_match('/^[A-Z]{2,3}[0-9]{1,5}$/', $flight)) J(['ok' => false, 'error' => 'Invalid flight number'], 400);
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare('SELECT current_stock FROM uld_stock WHERE uld_type=? FOR UPDATE');
            $q->execute([$uld]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('ULD type not found');
            $current = (int)$row['current_stock'];
            if ($movementType === 'OUT' && $quantity > $current) throw new RuntimeException("Insufficient $uld stock. Current stock: $current");
            $newStock = $movementType === 'IN' ? $current + $quantity : $current - $quantity;
            $q = $pdo->prepare('UPDATE uld_stock SET current_stock=?,updated_at=CURRENT_TIMESTAMP WHERE uld_type=?');
            $q->execute([$newStock, $uld]);
            if (col($pdo, 'uld_movements', 'flight_number')) {
                $q = $pdo->prepare('INSERT INTO uld_movements(movement_type,uld_type,quantity,reference,remarks,flight_number,user_name) VALUES(?,?,?,?,?,?,?)');
                $q->execute([$movementType, $uld, $quantity, $reference, $remarks, $flight ?: null, $me['full_name'] ?? $me['username']]);
            } else {
                $storedRemarks = $remarks;
                if ($flight) $storedRemarks = $storedRemarks !== '' ? $storedRemarks . ' | Flight: ' . $flight : 'Flight: ' . $flight;
                $q = $pdo->prepare('INSERT INTO uld_movements(movement_type,uld_type,quantity,reference,remarks,user_name) VALUES(?,?,?,?,?,?)');
                $q->execute([$movementType, $uld, $quantity, $reference, $storedRemarks, $me['full_name'] ?? $me['username']]);
            }
            $pdo->commit();
            J(['ok' => true, 'new_stock' => $newStock]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    if ($action === 'users') {
        adminUser();
        $users = $pdo->query('SELECT id,username,full_name,email,role,active,profile_photo,created_at FROM users ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
        J(['ok' => true, 'users' => $users]);
    }
    if ($action === 'approve') { adminUser(); $data=D(); $q=$pdo->prepare('UPDATE users SET active=? WHERE id=?'); $q->execute([strtolower((string)($data['status']??'approved'))==='approved'?1:0,(int)($data['id']??0)]); J(['ok'=>true]); }
    if ($action === 'set_role') { adminUser(); $data=D(); $role=strtoupper(trim((string)($data['role']??''))); if(!in_array($role,['ADMIN','SUPERVISOR','OPERATOR'],true))J(['ok'=>false,'error'=>'Invalid role'],400); $q=$pdo->prepare('UPDATE users SET role=? WHERE id=?');$q->execute([$role,(int)($data['id']??0)]);J(['ok'=>true]); }
    if ($action === 'delete_user') { adminUser(); $id=(int)(D()['id']??0); if($id===(int)$me['id'])J(['ok'=>false,'error'=>'You cannot delete your own account'],400);$q=$pdo->prepare('DELETE FROM users WHERE id=?');$q->execute([$id]);J(['ok'=>true]); }

    if ($action === 'change_password') {
        $data=D(); $old=(string)($data['old_password']??''); $new=(string)($data['new_password']??'');
        $q=$pdo->prepare('SELECT password_hash FROM users WHERE id=?');$q->execute([$me['id']]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row||!password_verify($old,$row['password_hash']))J(['ok'=>false,'error'=>'Current password is incorrect'],400);
        if(strlen($new)<8)J(['ok'=>false,'error'=>'New password must contain at least 8 characters'],400);
        $q=$pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');$q->execute([password_hash($new,PASSWORD_DEFAULT),$me['id']]);J(['ok'=>true]);
    }

    if ($action === 'set_minimum') {
        adminUser(); $data=D(); $uld=strtoupper((string)($data['uld_type']??'')); $minimum=(int)($data['minimum_level']??0);
        if(!validULD($uld)||$minimum<0)J(['ok'=>false,'error'=>'Invalid ULD or minimum level'],400);
        $q=$pdo->prepare('UPDATE uld_stock SET minimum_level=? WHERE uld_type=?');$q->execute([$minimum,$uld]);J(['ok'=>true]);
    }

    if ($action === 'portals') {
        try { $portals=$pdo->query("SELECT id,name,url,description,logo_path,active,sort_order FROM madapt_portals WHERE active=1 ORDER BY sort_order,name")->fetchAll(PDO::FETCH_ASSOC); }
        catch(Throwable $e) { J(['ok'=>false,'error'=>'Portals table is not installed. Import madapt_portals.sql first.'],500); }
        J(['ok'=>true,'portals'=>$portals]);
    }
    if ($action === 'portal_add') { adminUser(); $data=D(); $name=trim((string)($data['name']??'')); $url=trim((string)($data['url']??'')); if($name===''||$url==='')J(['ok'=>false,'error'=>'Portal name and URL are required'],400);$q=$pdo->prepare('INSERT INTO madapt_portals(name,url,description,logo_path,active,sort_order) VALUES(?,?,?,?,1,0)');$q->execute([$name,$url,trim((string)($data['description']??'')),trim((string)($data['logo_path']??''))]);J(['ok'=>true,'id'=>(int)$pdo->lastInsertId()]); }
    if ($action === 'portal_update') { adminUser(); $data=D(); $q=$pdo->prepare('UPDATE madapt_portals SET name=?,url=?,description=?,logo_path=? WHERE id=?');$q->execute([trim((string)($data['name']??'')),trim((string)($data['url']??'')),trim((string)($data['description']??'')),trim((string)($data['logo_path']??'')),(int)($data['id']??0)]);J(['ok'=>true]); }
    if ($action === 'portal_delete') { adminUser(); $q=$pdo->prepare('DELETE FROM madapt_portals WHERE id=?');$q->execute([(int)(D()['id']??0)]);J(['ok'=>true]); }

    J(['ok'=>false,'error'=>'Unknown action','action'=>$action],400);
} catch (Throwable $e) {
    error_log('MADAPT API ERROR: ' . $e->getMessage());
    J(['ok'=>false,'error'=>'Internal server error'],500);
}
