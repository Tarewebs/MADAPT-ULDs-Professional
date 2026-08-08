<?php
declare(strict_types=1);

$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) require_once $localConfig;

define('DB_HOST', defined('MADAPT_DB_HOST') ? MADAPT_DB_HOST : (getenv('MADAPT_DB_HOST') ?: 'localhost'));
define('DB_NAME', defined('MADAPT_DB_NAME') ? MADAPT_DB_NAME : (getenv('MADAPT_DB_NAME') ?: 'u619448402_uldspro'));
define('DB_USER', defined('MADAPT_DB_USER') ? MADAPT_DB_USER : (getenv('MADAPT_DB_USER') ?: ''));
define('DB_PASS', defined('MADAPT_DB_PASS') ? MADAPT_DB_PASS : (getenv('MADAPT_DB_PASS') ?: ''));
define('APP_NAME', 'MADAPT ULDs Professional');
define('APP_VERSION', '2.0.0');
date_default_timezone_set('Europe/Madrid');

if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    if (DB_USER === '' || DB_PASS === '') throw new RuntimeException('Faltan las credenciales MySQL de Hostinger.');
    try {
        $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,
        ]);
        return $pdo;
    } catch (Throwable $e) { throw new RuntimeException('No se pudo conectar con MySQL. Comprueba usuario, contraseña, host y base de datos.'); }
}
function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function out(array $data, int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function require_login(): void { if (empty($_SESSION['user'])) { header('Location: login.php'); exit; } }
function require_admin(): void { require_login(); if (strtoupper((string)($_SESSION['user']['role']??''))!=='ADMIN') { http_response_code(403); exit('Acceso restringido.'); } }
function flash(?string $message=null, string $type='success'): ?array { if ($message!==null) { $_SESSION['flash']=['message'=>$message,'type'=>$type]; return null; } $x=$_SESSION['flash']??null; unset($_SESSION['flash']); return $x; }
function page_start(string $title): void {
    $u=$_SESSION['user']??[]; echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' · '.APP_NAME.'</title><link rel="stylesheet" href="assets/css/app.css"></head><body><header><div class="brand">MADAPT <span>ULDs Professional</span></div><div>'.e($u['full_name']??'').'<a class="logout" href="logout.php">Salir</a></div></header><div class="shell"><aside><a href="dashboard.php">Dashboard</a><a href="inventory.php">Inventario</a><a href="movements.php">Movimientos</a><a href="history.php">Historial</a>'.(strtoupper((string)($u['role']??''))==='ADMIN'?'<a href="users.php">Usuarios</a><a href="settings.php">Configuración</a>':'').'</aside><main><h1>'.e($title).'</h1>';
    if($f=flash()) echo '<div class="flash '.e($f['type']).'">'.e($f['message']).'</div>';
}
function page_end(): void { echo '</main></div><footer>MADAPT ULDs Professional · v'.APP_VERSION.'</footer></body></html>'; }
function setup_page(Throwable $e): never { http_response_code(503); echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MADAPT · Configuración</title><link rel="stylesheet" href="assets/css/app.css"></head><body><div class="setup"><div class="card"><h1>MADAPT ULDs Professional</h1><h2>Falta configurar la conexión MySQL</h2><p>'.e($e->getMessage()).'</p><pre>public_html/config.local.php</pre><p>La base de datos <strong>u619448402_uldspro</strong> ya está creada. Añade las credenciales de MySQL de Hostinger en ese archivo privado y vuelve a cargar.</p></div></div></body></html>'; exit; }

function login_required(): void { if (empty($_SESSION['user'])) out(['ok'=>false,'error'=>'Login required'],401); }
function admin_required(): void { login_required(); if (strtoupper((string)($_SESSION['user']['role']??''))!=='ADMIN') out(['ok'=>false,'error'=>'Administrator access required'],403); }

/* The current SPA is index.php. Inject the mobile controls, editable footer and
   animated aircraft while keeping the existing application markup untouched. */
if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'index.php') {
    ob_start(static function (string $html): string {
        if (stripos($html, '</body>') === false || stripos($html, 'id="madapt-mobilebar"') !== false) return $html;
        $user = $_SESSION['user'] ?? [];
        $name = e((string)($user['full_name'] ?? $user['username'] ?? ''));
        $s=[];
        try { foreach (db()->query('SELECT setting_key,setting_value FROM madapt_settings')->fetchAll(PDO::FETCH_ASSOC) as $r) $s[$r['setting_key']]=$r['setting_value']; } catch (Throwable $e) {}
        $contactName = e((string)($s['footer_contact_name'] ?? 'Tarekegne Asnake'));
        $contactEmail = e((string)($s['footer_contact_email'] ?? 'tarekegnea@airlinesairmat.com'));
        $contactPhone = e((string)($s['footer_contact_phone'] ?? '+34622510121'));
        $footerText = e((string)($s['footer_text'] ?? 'Secure operational inventory'));
        $aircraft = trim((string)($s['aircraft_path'] ?? ''));
        $aircraftHtml = $aircraft ? '<div class="header-aircraft" aria-hidden="true"><img src="'.e($aircraft).'" alt=""></div>' : '<div class="header-aircraft header-aircraft-placeholder" aria-hidden="true"></div>';
        $footer = '<footer id="madapt-footer" class="madapt-footer"><div class="footer-brand"><strong>MADAPT ULDs</strong><span>Inventory Management System</span></div><div class="footer-contact"><strong>Contacto</strong><span>'. $contactName .'</span><a href="mailto:'. $contactEmail .'">'. $contactEmail .'</a><a href="tel:'. preg_replace('/[^0-9+]/','',$contactPhone) .'">'. $contactPhone .'</a></div><div class="footer-meta"><span>Ethiopian Airlines · MADAPT ULDs</span><span>© '.date('Y').' MADAPT ULDs</span><span>'. $footerText .'</span></div></footer>';
        $mobile = '<div id="madapt-mobilebar" class="madapt-mobilebar"><button id="madapt-menu-toggle" type="button" aria-label="Open menu" aria-expanded="false">☰</button><strong>MADAPT ULDs</strong><span>'. $name .'</span></div>';
        $style = '<style id="madapt-shell-style">.header-aircraft{position:relative;flex:1;min-width:120px;height:72px;margin:0 28px;display:flex;align-items:center;justify-content:center;overflow:hidden;pointer-events:none}.header-aircraft img{display:block;max-width:min(320px,70%);max-height:62px;width:auto;height:auto;object-fit:contain;animation:madaptAircraftFly 10s linear infinite}.header-aircraft-placeholder{min-width:0}.madapt-footer{background:#fff;border-top:1px solid #dfe9e5;padding:16px 30px;display:grid;grid-template-columns:minmax(220px,1fr) auto minmax(300px,1.3fr);align-items:center;gap:24px;color:#5f746c;font-size:12px;line-height:1.45;flex:0 0 auto}.footer-brand{display:flex;align-items:center;gap:12px;min-width:0}.footer-brand strong{color:var(--primary);font-size:14px}.footer-brand span{padding-left:12px;border-left:1px solid #d6e1dc}.footer-contact{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:center}.footer-contact strong{color:var(--primary)}.footer-contact a{color:#486a5c;text-decoration:none}.footer-contact a:hover{text-decoration:underline}.footer-meta{display:flex;gap:18px;flex-wrap:wrap;justify-content:flex-end;text-align:right}.footer-meta span+span{border-left:1px solid #d6e1dc;padding-left:18px}.settings-footer-fields{width:100%;flex-basis:100%;display:grid;grid-template-columns:repeat(2,minmax(180px,1fr));gap:10px;padding-top:8px}.settings-footer-fields hr{grid-column:1/-1;width:100%;border:0;border-top:1px solid #e2ebe7}.settings-footer-fields h3,.settings-footer-fields p{grid-column:1/-1;margin:0}.settings-footer-fields input{width:100%;min-width:0}.settings-footer-fields input:last-child{grid-column:1/-1}@keyframes madaptAircraftFly{0%{transform:translateX(-125%)}45%{transform:translateX(0)}55%{transform:translateX(0)}100%{transform:translateX(125%)}}@media(max-width:700px){.header-aircraft{display:none}.madapt-footer{display:block;padding:18px 15px;text-align:center}.footer-brand{justify-content:center;flex-wrap:wrap}.footer-brand span{border:0;padding:0}.footer-contact{margin-top:10px;justify-content:center}.footer-meta{justify-content:center;margin-top:10px;gap:8px;text-align:center}.footer-meta span{display:block}.footer-meta span+span{border-left:0;padding-left:0}.footer-meta span+span:before{content:"·";padding:0 7px;color:#b0beb9}.settings-footer-fields{grid-template-columns:1fr}.settings-footer-fields input:last-child{grid-column:auto}}@media(min-width:701px){.madapt-mobilebar{display:none!important}}@media(prefers-reduced-motion:reduce){.header-aircraft img{animation:none!important}} </style>';
        $script = <<<'JS'
<script>
(function(){
  const side=document.getElementById('sidebar'), toggle=document.getElementById('madapt-menu-toggle');
  if(side&&toggle){
    function close(){side.classList.remove('open');toggle.setAttribute('aria-expanded','false');}
    toggle.addEventListener('click',function(){const open=side.classList.toggle('open');toggle.setAttribute('aria-expanded',open?'true':'false');});
    side.addEventListener('click',function(e){if(e.target.closest('[data-page]'))close();});
    document.addEventListener('click',function(e){if(window.innerWidth<=700&&side.classList.contains('open')&&!side.contains(e.target)&&!toggle.contains(e.target))close();});
    window.addEventListener('resize',function(){if(window.innerWidth>700)close();});
  }
  function addFooterFields(){
    const form=document.getElementById('sf');
    if(!form||form.querySelector('[name="footer_contact_name"]'))return;
    const wrap=document.createElement('div');wrap.className='settings-footer-fields';
    wrap.innerHTML='<hr><h3>Footer & Contact</h3><p class="muted">These details appear in the application footer on PC and mobile.</p><input name="footer_contact_name" placeholder="Contact name"><input name="footer_contact_email" type="email" placeholder="Contact email"><input name="footer_contact_phone" placeholder="Contact phone"><input name="footer_text" placeholder="Footer text">';
    form.appendChild(wrap);
    const footer=document.getElementById('madapt-footer');
    const c=footer?.querySelector('.footer-contact');
    const spans=c?.querySelectorAll('span,a')||[];
    wrap.querySelector('[name="footer_contact_name"]').value=spans[0]?.textContent?.trim()||'Tarekegne Asnake';
    wrap.querySelector('[name="footer_contact_email"]').value=spans[1]?.textContent?.trim()||'tarekegnea@airlinesairmat.com';
    wrap.querySelector('[name="footer_contact_phone"]').value=spans[2]?.textContent?.trim()||'+34622510121';
    wrap.querySelector('[name="footer_text"]').value=footer?.querySelector('.footer-meta span:last-child')?.textContent?.trim()||'Secure operational inventory';
  }
  const observer=new MutationObserver(addFooterFields);observer.observe(document.body,{childList:true,subtree:true});addFooterFields();
})();
</script>
JS;
        if (stripos($html, '</head>') !== false) $html = str_ireplace('</head>', $style.'</head>', $html);
        if (stripos($html, '<button class="profile-btn"') !== false) $html = str_ireplace('<button class="profile-btn"', $aircraftHtml.'<button class="profile-btn"', $html);
        elseif (stripos($html, '</header>') !== false) $html = str_ireplace('</header>', $aircraftHtml.'</header>', $html);
        if (stripos($html, '</main>') !== false) $html = str_ireplace('</main>', $footer.'</main>', $html);
        return str_ireplace('</body>', $mobile.$script.'</body>', $html);
    });
}
