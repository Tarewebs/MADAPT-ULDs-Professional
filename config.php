<?php

declare(strict_types=1);

$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) require_once $localConfig;

define('DB_HOST', defined('MADAPT_DB_HOST') ? MADAPT_DB_HOST : (getenv('MADAPT_DB_HOST') ?: 'localhost'));
define('DB_NAME', defined('MADAPT_DB_NAME') ? MADAPT_DB_NAME : (getenv('MADAPT_DB_NAME') ?: 'u619448402_uldspro'));
define('DB_USER', defined('MADAPT_DB_USER') ? MADAPT_DB_USER : (getenv('MADAPT_DB_USER') ?: ''));
define('DB_PASS', defined('MADAPT_DB_PASS') ? MADAPT_DB_PASS : (getenv('MADAPT_DB_PASS') ?: ''));
define('MADAPT_SMTP_HOST', defined('MADAPT_SMTP_HOST') ? MADAPT_SMTP_HOST : (getenv('MADAPT_SMTP_HOST') ?: 'smtp.hostinger.com'));
define('MADAPT_SMTP_PORT', defined('MADAPT_SMTP_PORT') ? MADAPT_SMTP_PORT : (getenv('MADAPT_SMTP_PORT') ?: 465));
define('MADAPT_SMTP_USER', defined('MADAPT_SMTP_USER') ? MADAPT_SMTP_USER : (getenv('MADAPT_SMTP_USER') ?: 'ulds@madapt.es'));
define('MADAPT_SMTP_PASS', defined('MADAPT_SMTP_PASS') ? MADAPT_SMTP_PASS : (getenv('MADAPT_SMTP_PASS') ?: ''));
define('MADAPT_SMTP_FROM', defined('MADAPT_SMTP_FROM') ? MADAPT_SMTP_FROM : (getenv('MADAPT_SMTP_FROM') ?: 'ulds@madapt.es'));
define('MADAPT_SMTP_FROM_NAME', defined('MADAPT_SMTP_FROM_NAME') ? MADAPT_SMTP_FROM_NAME : (getenv('MADAPT_SMTP_FROM_NAME') ?: 'MADAPT ULDs'));
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
function out(array $data,int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function requireLogin(): array { if(empty($_SESSION['user'])) out(['ok'=>false,'error'=>'Login required'],401); return $_SESSION['user']; }
function requireAdmin(): array { $u=requireLogin(); if(strtoupper((string)($u['role']??''))!=='ADMIN') out(['ok'=>false,'error'=>'Administrator access required'],403); return $u; }
function validSettingKey(string $key): bool { return preg_match('/^[a-zA-Z0-9_]+$/',$key)===1; }

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
        $footerText = e((string)($s['footer_text'] ?? ''));
        $aircraft = trim((string)($s['aircraft_path'] ?? ''));
        $aircraftHtml = $aircraft ? '<div class="header-aircraft" aria-hidden="true"><img src="'.e($aircraft).'" alt=""></div>' : '<div class="header-aircraft header-aircraft-placeholder" aria-hidden="true"></div>';
        $footer = '<footer id="madapt-footer" class="madapt-footer"><div class="footer-brand"><strong>MADAPT ULDs</strong><span>Inventory Management System</span></div><div class="footer-contact"><strong>Contacto</strong><span>'. $contactName .'</span><a href="mailto:'. $contactEmail .'">'. $contactEmail .'</a><a href="tel:'. preg_replace('/[^0-9+]/','',$contactPhone) .'">'. $contactPhone .'</a></div><div class="footer-meta"><span>Ethiopian Airlines · MADAPT ULDs</span><span>© '.date('Y').' MADAPT ULDs</span>'.($footerText!==''?'<span>'. $footerText .'</span>':'').'</div></footer>';
        $mobile = '<div id="madapt-mobilebar" class="madapt-mobilebar"><button id="madapt-menu-toggle" type="button" aria-label="Open menu" aria-expanded="false">☰</button><strong>MADAPT ULDs</strong><span>'. $name .'</span></div>';
        $style = '<style id="madapt-shell-style">.header-aircraft{position:relative;flex:1;min-width:120px;height:72px;margin:0 28px;display:flex;align-items:center;justify-content:center;overflow:hidden;pointer-events:none}.header-aircraft img{display:block;max-width:min(320px,70%);max-height:62px;width:auto;height:auto;object-fit:contain;animation:madaptAircraftFly 10s linear infinite}.header-aircraft-placeholder{min-width:0}.madapt-footer{background:#fff;border-top:1px solid #dfe9e5;padding:16px 30px;display:grid;grid-template-columns:minmax(220px,1fr) auto minmax(300px,1.3fr);align-items:center;gap:24px;color:#5f746c;font-size:12px;line-height:1.45;flex:0 0 auto}.footer-brand{display:flex;align-items:center;gap:12px;min-width:0}.footer-brand strong{color:var(--primary);font-size:14px}.footer-brand span{padding-left:12px;border-left:1px solid #d6e1dc}.footer-contact{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:center}.footer-contact strong{color:var(--primary)}.footer-contact a{color:#486a5c;text-decoration:none}.footer-contact a:hover{text-decoration:underline}.footer-meta{display:flex;gap:18px;flex-wrap:wrap;justify-content:flex-end;text-align:right}.footer-meta span+span{border-left:1px solid #d6e1dc;padding-left:18px}.settings-footer-fields{width:100%;flex-basis:100%;display:grid;grid-template-columns:repeat(2,minmax(180px,1fr));gap:10px;padding-top:8px}.settings-footer-fields hr{grid-column:1/-1;width:100%;border:0;border-top:1px solid #e2ebe7}.settings-footer-fields h3,.settings-footer-fields p{grid-column:1/-1;margin:0}.settings-footer-fields input{width:100%;min-width:0}.settings-footer-fields input:last-child{grid-column:1/-1}.alert-test-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px}.alert-test-btn{background:#087a46;color:#fff;border:0;border-radius:8px;padding:10px 15px;font-weight:700;cursor:pointer}.alert-test-btn:disabled{opacity:.6;cursor:wait}.alert-test-msg{margin:8px 0 0;font-weight:600}@keyframes madaptAircraftFly{0%{transform:translateX(-125%)}45%{transform:translateX(0)}55%{transform:translateX(0)}100%{transform:translateX(125%)}}@media(max-width:700px){.header-aircraft{display:none}.madapt-footer{display:block;padding:18px 15px;text-align:center}.footer-brand{justify-content:center;flex-wrap:wrap}.footer-brand span{border:0;padding:0}.footer-contact{margin-top:10px;justify-content:center}.footer-meta{justify-content:center;margin-top:10px;gap:8px;text-align:center}.footer-meta span{display:block}.footer-meta span+span{border-left:0;padding-left:0}.footer-meta span+span:before{content:"·";padding:0 7px;color:#b0beb9}.settings-footer-fields{grid-template-columns:1fr}}@media(min-width:701px){.madapt-mobilebar{display:none!important}}@media(prefers-reduced-motion:reduce){.header-aircraft img{animation:none!important}}</style>';
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
    wrap.querySelector('[name="footer_text"]').value=footer?.querySelector('.footer-meta span:last-child')?.textContent?.trim()||'';
  }
  function addOperationsModule(){
    const nav=document.querySelector('#sidebar nav');
    if(!nav||nav.querySelector('[data-page="ops"]'))return;
    const b=document.createElement('button'); b.type='button'; b.dataset.page='ops';
    b.innerHTML='✈ <span>OPERACIONES</span>';
    const anchor=nav.querySelector('[data-page="portals"]');
    if(anchor)anchor.insertAdjacentElement('afterend',b);else nav.insertBefore(b,nav.querySelector('[data-page="profile"]'));
    b.onclick=()=>window.show('ops');
  }
  async function operationsPage(c){
    c.innerHTML='<div class="box"><b>Loading operations…</b></div>';
    try{
      const r=await fetch('operations.php?action=data',{cache:'no-store'});const j=await r.json();
      if(!j.ok)throw new Error(j.error||'Could not load operations');
      const lang=localStorage.getItem('madapt_lang')||'en', es=lang==='es';
      const esc2=v=>String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
      const statusLabel=s=>s==='Completed'?(es?'COMPLETADA':'COMPLETED'):s==='Started'?(es?'INICIADA':'STARTED'):(es?'PENDIENTE':'PENDING');
      const groups={}; (j.operations||[]).forEach(x=>(groups[x.op_group]??=[]).push(x));
      const block=j.blockIn?new Date(j.blockIn.replace(' ','T')).getTime():null;
      const summary=j.summary||{};
      let html='<h2>'+(es?'OPERACIONES':'OPERATIONS')+' — B787-900</h2><div class="box operations-head"><div><h3>'+(es?'Control de turnaround de 70 minutos':'70-minute turnaround control')+'</h3><p class="muted">'+(es?'Control operativo en tiempo real':'Live operational control')+'</p></div><div class="operations-controls"><label>Block-In</label><input id="opBlockIn" type="datetime-local" value="'+(block?new Date(block-new Date().getTimezoneOffset()*60000).toISOString().slice(0,16):'')+'"><button type="button" id="opSaveBlock">'+(es?'Guardar hora':'Set time')+'</button></div></div>';
      html+='<div class="two"><div class="box"><b>'+(es?'Total operaciones':'Total operations')+'</b><div class="uld-number">'+summary.total+'</div></div><div class="box"><b>'+(es?'Iniciadas':'Started')+'</b><div class="uld-number">'+summary.started+'</div></div><div class="box"><b>'+(es?'Completadas':'Completed')+'</b><div class="uld-number">'+summary.completed+'</div></div><div class="box"><b>'+(es?'Pendientes':'Pending')+'</b><div class="uld-number">'+summary.pending+'</div></div></div>';
      Object.keys(groups).forEach(g=>{html+='<div class="box"><h3>'+esc2(g)+'</h3><div class="table-wrap"><table><thead><tr><th>No.</th><th>'+(es?'Operación':'Operation')+'</th><th>'+(es?'Plan':'Plan')+'</th><th>'+(es?'Asignado':'Allocated')+'</th><th>'+(es?'Estado':'Status')+'</th><th>'+(es?'Operador':'Operator')+'</th><th>'+(es?'Acción':'Action')+'</th></tr></thead><tbody>';groups[g].forEach(x=>{const st=x.status||'';html+='<tr><td>'+x.operation_no+'</td><td><b>'+esc2(x.operation_name)+'</b></td><td>'+esc2(x.planned_start)+(x.planned_end?' – '+esc2(x.planned_end):'')+'</td><td>'+esc2(x.allocated)+'</td><td><span class="movement '+(st==='Completed'?'in':st==='Started'?'warning':'')+'">'+statusLabel(st)+'</span></td><td>'+esc2(x.operator_name||'')+'</td><td>';if(st!=='Completed')html+=st==='Started'?'<button type="button" onclick="opComplete('+x.id+')">'+(es?'COMPLETAR':'COMPLETE')+'</button>':'<button type="button" onclick="opStart('+x.id+')">'+(es?'INICIAR':'START')+'</button>';else html+='<span>✓</span>';html+='</td></tr>';});html+='</tbody></table></div></div>';});
      if(j.can_manage)html+='<div class="box"><button type="button" class="danger-btn" onclick="opReset()">'+(es?'Restablecer todas las operaciones':'Reset all operations')+'</button></div>';
      c.innerHTML=html;
      document.getElementById('opSaveBlock').onclick=async()=>{const v=document.getElementById('opBlockIn').value;if(!v)return;await opAction('block_in',{iso:new Date(v).toISOString()});};
      window.opStart=id=>opAction('update',{id,operation_action:'Started'});window.opComplete=id=>opAction('update',{id,operation_action:'Completed'});window.opReset=()=>{if(confirm(es?'¿Restablecer todas las operaciones?':'Reset all operations?'))opAction('reset',{});};
    }catch(e){c.innerHTML='<div class="box error"><b>Could not load operations</b><p>'+esc(e.message)+'</p></div>';}
  }
  async function opAction(action,data){
    const r=await fetch('operations.php?action='+encodeURIComponent(action),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data),cache:'no-store'});const j=await r.json();if(!j.ok)throw new Error(j.error||'Operation request failed');await operationsPage(document.getElementById('content'));
  }
  function hookOperationsShow(){
    if(typeof window.show!=='function'||window.__madaptOpsHook)return;
    window.__madaptOpsHook=true;const originalShow=window.show;
    window.show=async function(page){if(page==='ops'){document.body.dataset.page='ops';return operationsPage(document.getElementById('content'));}return originalShow.apply(this,arguments);};
  }
  function addAlertTestButton(){
    const form=document.getElementById('ef');
    if(!form||document.getElementById('sendAlertTest'))return;
    const row=document.createElement('div');row.className='alert-test-row';
    row.innerHTML='<button id="sendAlertTest" type="button" class="alert-test-btn">📧 Send Test Alert</button><span id="alertTestMsg" class="alert-test-msg"></span>';
    form.parentElement.appendChild(row);
    document.getElementById('sendAlertTest').onclick=async function(){
      const btn=this,msg=document.getElementById('alertTestMsg');
      btn.disabled=true;msg.textContent='Sending…';
      try{
        const r=await fetch('alert-test.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'}});
        const j=await r.json();
        if(!j.ok)throw new Error(j.error||j.message||'Test alert failed');
        msg.textContent='✓ '+j.message;
      }catch(e){msg.textContent='✕ '+e.message}
      finally{btn.disabled=false;}
    };
  }
  const observer=new MutationObserver(function(){addFooterFields();addAlertTestButton();addOperationsModule();hookOperationsShow();});observer.observe(document.body,{childList:true,subtree:true});
  addFooterFields();addAlertTestButton();addOperationsModule();hookOperationsShow();
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
