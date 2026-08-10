<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$favicon = '';
try {
    $q = db()->prepare("SELECT setting_value FROM madapt_settings WHERE setting_key='favicon_path' LIMIT 1");
    $q->execute();
    $favicon = trim((string)($q->fetchColumn() ?: ''));
} catch (Throwable $e) {}

ob_start();
require __DIR__ . '/index.php';
$html = ob_get_clean();

if ($favicon !== '') {
    $href = htmlspecialchars($favicon, ENT_QUOTES, 'UTF-8');
    $html = preg_replace('~</head>~i', '<link rel="icon" type="image/png" href="' . $href . '"><link rel="shortcut icon" type="image/png" href="' . $href . '"></head>', $html, 1) ?? $html;
}

$extra = <<<'HTML'
<style>
.madapt-extra-box{margin-top:16px}.madapt-extra-table{width:100%;border-collapse:collapse}.madapt-extra-table th,.madapt-extra-table td{padding:9px 10px;border-bottom:1px solid #dfe9e5;text-align:left}.madapt-extra-table input[type=number]{width:90px}.madapt-extra-actions{display:flex;gap:6px;flex-wrap:wrap}.madapt-favicon-preview{width:48px;height:48px;object-fit:contain;border:1px solid #dfe9e5;border-radius:8px;background:#fff;padding:4px}.madapt-muted{color:var(--muted,#71817a);font-size:.92rem}.dark-mode .madapt-extra-table th,.dark-mode .madapt-extra-table td{border-color:#2b3b35}.dark-mode .madapt-favicon-preview{background:#101815;border-color:#344740}
</style>
<script>
(function(){
  const originalSettingsPage = window.settingsPage;
  const originalUsers = window.users;

  function faviconLink(path){
    if(!path)return;
    document.querySelectorAll('link[data-madapt-favicon]').forEach(x=>x.remove());
    const a=document.createElement('link');a.rel='icon';a.type='image/png';a.href=path;a.dataset.madaptFavicon='1';document.head.appendChild(a);
    const b=document.createElement('link');b.rel='shortcut icon';b.type='image/png';b.href=path;b.dataset.madaptFavicon='1';document.head.appendChild(b);
  }

  window.settingsPage = async function(c){
    await originalSettingsPage(c);
    try{
      const [sr,stockRes]=await Promise.all([A('settings_get'),A('stock')]);
      const s=sr.settings||{};
      const stockRows=stockRes.stock||[];
      const minBox=document.createElement('div');
      minBox.className='box madapt-extra-box';
      minBox.innerHTML='<h3>Minimum Stock</h3><p class="madapt-muted">Set the minimum stock level for each ULD type. Changes affect the low-stock warning immediately.</p><div class="table-wrap"><table class="madapt-extra-table"><thead><tr><th>ULD</th><th>Current</th><th>Minimum</th><th>Action</th></tr></thead><tbody>'+stockRows.map(x=>'<tr><td><b>'+esc(x.uld_type)+'</b></td><td>'+Number(x.current_stock)+'</td><td><input type="number" min="0" value="'+Number(x.minimum_level)+'" data-min-uld="'+esc(x.uld_type)+'"></td><td><button type="button" data-save-min="'+esc(x.uld_type)+'">Save</button></td></tr>').join('')+'</tbody></table></div><p class="madapt-min-msg"></p>';
      c.appendChild(minBox);
      minBox.querySelectorAll('[data-save-min]').forEach(btn=>btn.addEventListener('click',async()=>{
        const uld=btn.dataset.saveMin;const input=minBox.querySelector('[data-min-uld="'+CSS.escape(uld)+'"]');const msg=minBox.querySelector('.madapt-min-msg');
        try{await A('set_minimum',{uld_type:uld,minimum_level:Number(input.value)});msg.textContent='Minimum stock updated for '+uld+'.';}catch(e){msg.textContent=e.message;}
      }));

      const favBox=document.createElement('div');
      favBox.className='box madapt-extra-box';
      favBox.innerHTML='<h3>Favicon</h3><p class="madapt-muted">Upload a PNG image to use as the browser tab icon.</p><div class="media-preview">'+(s.favicon_path?'<img class="madapt-favicon-preview" src="'+esc(s.favicon_path)+'" alt="Favicon">':'')+'</div><form id="madaptFaviconForm" enctype="multipart/form-data"><div class="upload-row"><input name="favicon" type="file" accept="image/png" required><button type="submit">Upload Favicon</button></div></form><p id="madaptFaviconMsg"></p>';
      c.appendChild(favBox);
      document.getElementById('madaptFaviconForm').onsubmit=async e=>{
        e.preventDefault();const msg=document.getElementById('madaptFaviconMsg');
        try{
          const r=await fetch('favicon_upload.php',{method:'POST',body:new FormData(e.target)});let j;try{j=await r.json()}catch(_){throw new Error('Invalid server response')}
          if(!j.ok)throw new Error(j.error||'Upload failed');faviconLink(j.path);msg.textContent='Favicon uploaded successfully.';show('set');
        }catch(err){msg.textContent=err.message;}
      };
    }catch(e){const x=document.createElement('p');x.className='madapt-muted';x.textContent=e.message;c.appendChild(x)}
  };

  window.users = async function(c){
    try{
      const r=await A('users');
      const es=currentLang==='es';
      c.innerHTML='<h2>User Approval</h2><div class="box"><div class="table-wrap"><table><thead><tr><th>User</th><th>Name</th><th>Photo</th><th>Role</th><th>Status</th><th>Action</th></tr></thead><tbody>'+r.users.map(x=>{
        const active=Number(x.active)===1;
        const status=active?(es?'APROBADO':'APPROVED'):(es?'PENDIENTE':'PENDING');
        const action=active?'<button type="button" onclick="ap('+x.id+',\'pending\')">'+(es?'Desactivar':'Deactivate')+'</button>':'<button type="button" onclick="ap('+x.id+',\'approved\')">'+(es?'Aprobar':'Approve')+'</button>';
        return '<tr><td>'+esc(x.username)+'</td><td>'+esc(x.full_name)+'</td><td>'+(x.profile_photo?'<img src="'+esc(x.profile_photo)+'" style="width:36px;height:36px;border-radius:50%;object-fit:cover">':'')+'</td><td><select onchange="setRole('+x.id+',this.value)"><option '+(x.role==='OPERATOR'?'selected':'')+'>OPERATOR</option><option '+(x.role==='SUPERVISOR'?'selected':'')+'>SUPERVISOR</option><option '+(x.role==='ADMIN'?'selected':'')+'>ADMIN</option></select></td><td><b>'+status+'</b></td><td><div class="madapt-extra-actions">'+action+'<button type="button" class="danger-btn" onclick="deleteUser('+x.id+')">'+(es?'Eliminar':'Delete')+'</button></div></td></tr>';
      }).join('')+'</tbody></table></div></div>';
      translateRoot(c);
    }catch(e){c.innerHTML='<div class="box error">'+esc(e.message)+'</div>'}
  };

  window.deleteUser = async function(id){
    if(!confirm(currentLang==='es'?'¿Eliminar este usuario?':'Delete this user?'))return;
    try{await A('delete_user',{id});show('users')}catch(e){alert(e.message)}
  };
})();
</script>
HTML;

$html = str_replace('</body>', $extra . '</body>', $html);
echo $html;
