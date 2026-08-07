<?php
declare(strict_types=1);

/* MADAPT ULDs Professional - production configuration. */

/*
 * Never commit the production database password to GitHub.
 * On Hostinger set MADAPT_DB_PASS in the PHP environment, or create
 * config.local.php from config.local.example.php and keep that file out
 * of Git. The fallback intentionally remains invalid until configured.
 */
define('DB_HOST', getenv('MADAPT_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('MADAPT_DB_NAME') ?: 'u619448402_uldspro');
define('DB_USER', getenv('MADAPT_DB_USER') ?: 'u619448402_uldspro');
define('DB_PASS', getenv('MADAPT_DB_PASS') ?: 'CHANGE_ME');

define('APP_NAME', 'MADAPT ULDs Professional');
define('APP_VERSION', '2.0.0');

date_default_timezone_set('Europe/Madrid');

/* Secure session cookie settings before starting the session. */
if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    if (DB_PASS === 'CHANGE_ME' || DB_PASS === '') {
        throw new RuntimeException('Database password is not configured on the server.');
    }

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
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
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
