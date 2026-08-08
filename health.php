<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo "MADAPT HEALTH CHECK\n";
echo "DB_HOST=" . DB_HOST . "\n";
echo "DB_NAME=" . DB_NAME . "\n";
echo "DB_USER=" . (DB_USER !== '' ? DB_USER : '[EMPTY]') . "\n";
echo "DB_PASS=" . (DB_PASS !== '' ? '[SET]' : '[EMPTY]') . "\n";

try {
    if (DB_USER === '' || DB_PASS === '') {
        throw new RuntimeException('DB_USER o DB_PASS está vacío.');
    }
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]
    );
    $pdo->query('SELECT 1');
    echo "PDO=OK\n";
    foreach (['users','uld_stock','uld_movements','madapt_settings','madapt_portals'] as $table) {
        try {
            $q = $pdo->query("SELECT COUNT(*) FROM `" . $table . "`");
            echo $table . "=OK rows=" . $q->fetchColumn() . "\n";
        } catch (Throwable $e) {
            echo $table . "=ERROR " . $e->getMessage() . "\n";
        }
    }
} catch (Throwable $e) {
    http_response_code(503);
    echo "PDO=ERROR\n";
    echo "ERROR_CLASS=" . get_class($e) . "\n";
    echo "ERROR_CODE=" . $e->getCode() . "\n";
    echo "ERROR=" . $e->getMessage() . "\n";
}
