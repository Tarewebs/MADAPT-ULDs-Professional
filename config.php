<?php
declare(strict_types=1);
session_start();

/* Hostinger database configuration for MADAPT ULDs Professional. */
define('DB_HOST', 'localhost');
define('DB_NAME', 'u619448402_uldspro');
define('DB_USER', 'u619448402_uldspro');
define('DB_PASS', 'CHANGE_ME');

define('APP_NAME', 'MADAPT ULDs Professional');
define('APP_VERSION', '2.0.0');

date_default_timezone_set('Europe/Madrid');

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return $pdo;
}

function out(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function login_required(): void {
    if (empty($_SESSION['user'])) out(['ok' => false, 'error' => 'Login required'], 401);
}

function admin_required(): void {
    login_required();
    if (strtoupper((string)($_SESSION['user']['role'] ?? '')) !== 'ADMIN') {
        out(['ok' => false, 'error' => 'Administrator access required'], 403);
    }
}
