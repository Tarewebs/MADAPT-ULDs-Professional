<?php
declare(strict_types=1);
if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'index.php') {
    ob_start(static function (string $html): string {
        if (stripos($html, '</body>') === false) return $html;
        if (preg_match('/<footer[^>]*id=["\']madapt-footer["\'][^>]*>.*?<\/footer>/is', $html, $m)) {
            $footer = $m[0];
            $html = str_replace($footer, '', $html);
            $html = str_ireplace('</body>', $footer . '</body>', $html);
        }
        if (stripos($html, 'href="operations.php"') === false && preg_match('/(<nav[^>]*>)(.*?)(<\/nav>)/is', $html, $nav)) {
            $link = '<a class="madapt-operations-link" href="operations.php" aria-label="OPERACIONES">✈ <span>OPERACIONES</span></a>';
            $html = str_replace($nav[0], $nav[1] . $link . $nav[2] . $nav[3], $html);
        }
        $style = '<style id="madapt-operations-style">.madapt-operations-link{display:flex;align-items:center;gap:10px;width:100%;padding:11px 14px;margin:2px 0;border:0;border-radius:8px;background:transparent;color:inherit;text-decoration:none;font:inherit;font-weight:700;cursor:pointer}.madapt-operations-link:hover{background:rgba(255,255,255,.12)}@media(max-width:700px){.madapt-operations-link{padding:12px 14px}}</style>';
        if (stripos($html, 'id="madapt-operations-style"') === false && stripos($html, '</head>') !== false) $html = str_ireplace('</head>', $style . '</head>', $html);
        return $html;
    });
}
