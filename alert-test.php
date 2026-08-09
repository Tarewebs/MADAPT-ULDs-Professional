<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function alert_json(array $data, int $status=200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['user']) || strtoupper((string)($_SESSION['user']['role'] ?? '')) !== 'ADMIN') {
    alert_json(['ok'=>false,'error'=>'Administrator access required'],403);
}

try {
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS madapt_alert_recipients (id INT UNSIGNED NOT NULL AUTO_INCREMENT,email VARCHAR(190) NOT NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_alert_email(email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $recipients = $pdo->query("SELECT email FROM madapt_alert_recipients WHERE active=1 ORDER BY email")->fetchAll(PDO::FETCH_COLUMN);
    if (!$recipients) alert_json(['ok'=>false,'error'=>'No active alert email recipients are configured.'],400);

    $subject = 'MADAPT ULDs - LOW STOCK TEST ALERT';
    $message = "Dear Team,\n\nThis is a TEST alert from MADAPT ULDs Inventory Management.\n\nThe Low Stock alert email system has been triggered successfully.\n\nThis message does not change inventory or create a movement.\n\nKind regards,\nMADAPT ULDs Inventory Management";
    $from = trim((string)$pdo->query("SELECT setting_value FROM madapt_settings WHERE setting_key='footer_contact_email' LIMIT 1")->fetchColumn());
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) $from = 'noreply@airlinesairmat.com';
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nFrom: MADAPT ULDs <".$from.">\r\nReply-To: ".$from."\r\n";

    $results=[];
    foreach ($recipients as $email) {
        $sent = @mail((string)$email, $subject, $message, $headers);
        $results[]=['email'=>$email,'accepted_by_server'=>(bool)$sent];
    }
    $accepted = count(array_filter($results, static fn($r)=>(bool)$r['accepted_by_server']));
    alert_json(['ok'=>$accepted>0,'message'=>$accepted.' of '.count($results).' message(s) accepted by the server. Delivery depends on Hostinger mail configuration.','results'=>$results]);
} catch (Throwable $e) {
    error_log('MADAPT ALERT TEST ERROR: '.$e->getMessage());
    alert_json(['ok'=>false,'error'=>'Could not send test alert.'],500);
}
