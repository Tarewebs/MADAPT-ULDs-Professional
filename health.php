<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$result = [
    'ok' => false,
    'application' => 'MADAPT ULDs Professional',
    'status' => 'error',
    'database' => 'disconnected',
    'timestamp' => gmdate('c'),
];

try {
    $pdo = db();
    $pdo->query('SELECT 1');
    $result['database'] = 'connected';
    $result['ok'] = true;
    $result['status'] = 'healthy';
    http_response_code(200);
} catch (Throwable $e) {
    http_response_code(503);
    if (defined('MADAPT_DEBUG') && MADAPT_DEBUG) {
        $result['error'] = $e->getMessage();
    }
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
