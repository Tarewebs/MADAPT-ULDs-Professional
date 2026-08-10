<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

try {
    $pdo = db();
    $q = $pdo->prepare("SELECT setting_value FROM madapt_settings WHERE setting_key='aircraft_path' LIMIT 1");
    $q->execute();
    $path = trim((string)($q->fetchColumn() ?: ''));

    if ($path === '') {
        http_response_code(404);
        exit;
    }

    $path = parse_url($path, PHP_URL_PATH) ?: $path;
    $relative = ltrim(str_replace('\\', '/', $path), '/');
    if (!str_starts_with($relative, 'uploads/') && !str_starts_with($relative, 'assets/uploads/')) {
        http_response_code(403);
        exit;
    }

    $file = __DIR__ . '/' . $relative;
    if (!is_file($file) || !is_readable($file)) {
        http_response_code(404);
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($file);
    if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
        http_response_code(415);
        exit;
    }

    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=300, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    readfile($file);
} catch (Throwable $e) {
    http_response_code(404);
    exit;
}
