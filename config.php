<?php
declare(strict_types=1);

/*
 * MADAPT ULDs Professional
 * Environment-aware configuration for Hostinger / local development.
 *
 * IMPORTANT: never commit real database passwords to GitHub.
 * Set DB_PASS in the hosting environment or replace CHANGE_ME on the server.
 */
define('DB_HOST', getenv('MADAPT_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('MADAPT_DB_NAME') ?: 'u619448402_uldspro');
define('DB_USER', getenv('MADAPT_DB_USER') ?: 'u619448402_uldspro');
define('DB_PASS', getenv('MADAPT_DB_PASS') ?: 'CHANGE_ME');

define('APP_NAME', 'MADAPT ULDs Professional');
define('APP_VERSION', '2.0.0');
define('APP_TIMEZONE', getenv('MADAPT_TIMEZONE') ?: 'Europe/Madrid');

date_default_timezone_set(APP_TIMEZONE);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
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

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* Backwards-compatible alias used by the existing API. */
function out(array $data, int $status = 200): never
{
    json_out($data, $status);
}

function login_required(): void
{
    if (empty($_SESSION['user'])) {
        json_out(['ok' => false, 'error' => 'Login required'], 401);
    }
}

function admin_required(): void
{
    login_required();

    if (strtoupper((string)($_SESSION['user']['role'] ?? '')) !== 'ADMIN') {
        json_out(['ok' => false, 'error' => 'Administrator access required'], 403);
    }
}

function app_setting(PDO $pdo, string $key, string $default = ''): string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM madapt_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    return $value === false ? $default : (string)$value;
}

function save_app_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO madapt_settings (setting_key, setting_value) VALUES (?, ?) '
        . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}
