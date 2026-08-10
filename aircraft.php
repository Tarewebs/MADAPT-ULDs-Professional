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

    // The Settings page stores uploaded branding images as relative local paths.
    // Only serve files from the application's upload directories.
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

    // Keep the uploaded PNG/JPEG/WebP as the aircraft artwork, but wrap it in
    // an animated SVG so the existing header background can show a subtle,
    // smooth flying/banking motion without changing the uploaded asset.
    $encoded = base64_encode((string)file_get_contents($file));
    $dataUri = 'data:' . $mime . ';base64,' . $encoded;
    $href = htmlspecialchars($dataUri, ENT_QUOTES | ENT_XML1, 'UTF-8');

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="120" viewBox="0 0 320 120" preserveAspectRatio="xMidYMid meet">'
         . '<title>MADAPT aircraft</title>'
         . '<g transform="translate(160 60)">'
         . '<image href="' . $href . '" x="-145" y="-45" width="290" height="90" preserveAspectRatio="xMidYMid meet">'
         . '<animateTransform attributeName="transform" attributeType="XML" type="rotate" values="0;1.2;0;-1.2;0" dur="3.6s" repeatCount="indefinite" calcMode="spline" keySplines=".42 0 .58 1;.42 0 .58 1;.42 0 .58 1;.42 0 .58 1"/>'
         . '</image>'
         . '<animateTransform attributeName="transform" attributeType="XML" type="translate" values="0 0;0 -2;0 0;0 2;0 0" dur="3.6s" repeatCount="indefinite" calcMode="spline" keySplines=".42 0 .58 1;.42 0 .58 1;.42 0 .58 1;.42 0 .58 1"/>'
         . '</g>'
         . '</svg>';

    header('Content-Type: image/svg+xml; charset=UTF-8');
    header('Cache-Control: public, max-age=300, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    echo $svg;
} catch (Throwable $e) {
    http_response_code(404);
}
