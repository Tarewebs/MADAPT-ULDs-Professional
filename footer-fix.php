<?php
declare(strict_types=1);

/* Keep the SPA footer outside <main> so navigation does not remove it.
   This file is loaded automatically through .user.ini. */
if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'index.php') {
    ob_start(static function (string $html): string {
        $marker = 'id="madapt-footer"';
        if (stripos($html, $marker) === false || stripos($html, '</body>') === false) {
            return $html;
        }

        if (preg_match('/<footer[^>]*id=["\']madapt-footer["\'][^>]*>.*?<\/footer>/is', $html, $m)) {
            $footer = $m[0];
            $html = str_replace($footer, '', $html);
            $html = str_ireplace('</body>', $footer . '</body>', $html);
        }

        return $html;
    });
}
