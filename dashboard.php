<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
$user = $_SESSION['user'];
$settings = [];
try {
    foreach (db()->query("SELECT setting_key, setting_value FROM madapt_settings")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Throwable $e) {}
$company = $settings['company_name'] ?? 'MADAPT ULDs';
$logo = $settings['logo_path'] ?? '';
$role = strtoupper((string)($user['role'] ?? 'OPERATOR'));
$isAdmin = $role === 'ADMIN';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($company)?> — Dashboard</title>
<link rel="stylesheet" href="assets/app.css">
<style>
:root{--primary:<?=htmlspecialchars($settings['primary']??'#087a46')?>;--accent:<?=htmlspecialchars($settings['accent']??'#1b8b5b')?>;--sidebar:<?=htmlspecialchars($settings['sidebar']??'#075b38')?>}
</style>
</head>
<body>
<div id="app">
<aside id="sidebar"><div class="brand"><div class="brand-text">MADAPT<small>ULDs PROFESSIONAL</small></div><strong>MADAPT ULDs</strong></div>
<nav><button class="active">⌂ <span>Dashboard</span></button><button onclick="page('move')">↔ <span>In / Out</span></button><button onclick="page('inv')">▣ <span>Inventory</span></button><button onclick="page('hist')">◷ <span>History</span></button><button onclick="page('report')">▥ <span>Reports</span></button><button onclick="page('portals')">🌐 <span>Portals</span></button><?php if($isAdmin):?><button onclick="page('users')">♙ <span>Users</span></button><button onclick="page('settings')">⚙ <span>Settings</span></button><?php endif;?><button onclick="logout()">⇥ <span>Logout</span></button></nav></aside>
<main><header><div><h1>MADAPT ULDs</h1><p>Inventory Management System</p></div><span>👤 <?=htmlspecialchars($user['full_name']??$user['username']??'')?></span></header>
<section id="content"><div class="box"><b>Loading live inventory…</b></div></section></main></div>
<script>
const IS_ADMIN=<?=json_encode($isAdmin)?>;
let stock=[],historyRows=[];
async function api(action,data={}){const r=await fetch('api.php?action='+encodeURIComponent(action),{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(data)});const j=await r.json();if(r.status===401){location.href='login.php';return}if(!j.ok)throw Error(j.error||'Request failed');return j}
const esc=v=>String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const type=x=>x.uld_type??x.uld??'';const mov=x=>x.movement_type??x.type??'';const qty=x=>Number(x.quantity??x.qty??0);const state=x=>{let c=+x.current_stock,m=+x.minimum_level;return c<=m?'low':c<=m+Math.max(3,Math.ceil(m*.25))?'warning':'ok'};
async function load(){const [s,h]=await Promise.all([api('stock'),api('history')]);stock=s.stock||[];historyRows=h.history||h.movements||[]}
function cards(){return '<div class="uld-grid">'+stock.map(x=>`<article class="uld-card ${state(x)}"><div class="uld-head"><b>${esc(type(x))}</b><span>${state(x)==='low'?'LOW STOCK':state(x)==='warning'?'NEAR MINIMUM':'OK'}</span></div><strong class="uld-number">${+x.current_stock}</strong><small>Minimum ${+x.minimum_level}</small><div class="uld-bar"><i style="width:${Math.min(100,+x.current_stock/Math.max(+x.minimum_level*2,1)*100)}%"></i></div></article>`).join('')+'</div>'}
function table(rows){return `<div class="table-wrap"><table><thead><tr><th>Time</th><th>Type</th><th>ULD</th><th>Qty</th><th>Flight</th><th>User</th></tr></thead><tbody>${rows.map(x=>`<tr><td>${esc(x.created_at)}</td><td class="${mov(x)==='IN'?'in':'out'}">${esc(mov(x))}</td><td><b>${esc(type(x))}</b></td><td>${mov(x)==='IN'?'+':'-'}${qty(x)}</td><td>${esc(x.flight_number??x.flight??'')}</td><td>${esc(x.user_name??x.username??'')}</td></tr>`).join('')}</tbody></table></div>`}
async function page(p){const c=document.getElementById('content');c.innerHTML='<div class="box"><b>Loading…</b></div>';try{if(['dash','move','inv','hist','report'].includes(p))await load();if(p==='dash')c.innerHTML='<h2>Current Stock Overview</h2>'+alert()+cards()+'<div class="two"><div class="box"><h3>Recent Activity</h3>'+table(historyRows.slice(0,8))+'</div><div class="box"><h3>Quick Actions</h3><div class="quick-actions"><button onclick="page(\'move\')">＋ Stock IN</button><button onclick="page(\'move\')">－ Stock OUT</button><button onclick="page(\'report\')">▥ Reports</button><button onclick="page(\'portals\')">🌐 Portals</button></div><hr><h3>Stock Status</h3>'+stock.map(x=>`<p><b>${esc(type(x))}</b> — ${+x.current_stock} / ${+x.minimum_level}</p>`).join('')+'</div></div>';
if(p==='move')c.innerHTML='<h2>In / Out — Stock Movement</h2><div class="box"><form id="movement"><select name="movement_type"><option>IN</option><option>OUT</option></select><select name="uld_type"><option>AKE</option><option>PAJ</option><option>PMC</option><option>PAG</option></select><input name="quantity" type="number" min="1" required placeholder="Quantity"><input name="reference" placeholder="Reference"><input name="flight_number" placeholder="Flight Number"><input name="remarks" placeholder="Remarks"><button>Submit Movement</button></form><p id="msg"></p></div>';document.getElementById('movement')?.addEventListener('submit',async e=>{e.preventDefault();let d=Object.fromEntries(new FormData(e.target));d.quantity=+d.quantity;try{await api('movement',d);page('dash')}catch(x){document.getElementById('msg').textContent=x.message}});
if(p==='inv')c.innerHTML='<h2>Inventory</h2>'+alert()+'<div class="box">'+table(historyRows.filter(()=>false))+'<div class="table-wrap"><table><tr><th>ULD</th><th>Current</th><th>Minimum</th><th>Status</th></tr>'+stock.map(x=>`<tr><td><b>${esc(type(x))}</b></td><td>${+x.current_stock}</td><td>${+x.minimum_level}</td><td>${state(x).toUpperCase()}</td></tr>`).join('')+'</table></div></div>';
if(p==='hist')c.innerHTML='<h2>History</h2><div class="box">'+table(historyRows)+'</div>';
if(p==='report')c.innerHTML='<h2>Stock Summary</h2><div class="box print-area"><h2><?=htmlspecialchars($company)?></h2><p>MADAPT ULDs — Stock Summary</p>'+cards()+'<button onclick="window.print()">Print Report</button></div>';
if(p==='portals'){let r=await api('portals');c.innerHTML='<h2>Portals</h2><div class="portal-grid">'+(r.portals||[]).map(x=>`<div class="portal-card"><h3>${esc(x.name)}</h3><p>${esc(x.description)}</p><a href="${esc(x.url)}" target="_blank" rel="noopener">Open Portal ↗</a></div>`).join('')+'</div>'}
if(p==='users'){let r=await api('users');c.innerHTML='<h2>User Approval</h2><div class="box"><div class="table-wrap"><table><tr><th>User</th><th>Name</th><th>Role</th><th>Status</th></tr>'+(r.users||[]).map(x=>`<tr><td>${esc(x.username)}</td><td>${esc(x.full_name)}</td><td>${esc(x.role)}</td><td>${+x.active?'Approved':'Pending'}</td></tr>`).join('')+'</table></div></div>'}
if(p==='settings')c.innerHTML='<div class="box"><h2>Settings</h2><p>Administrator settings are available from the production configuration.</p></div>'}catch(e){c.innerHTML='<div class="box error"><b>Error</b><p>'+esc(e.message)+'</p></div>'}}
function alert(){let l=stock.filter(x=>state(x)==='low');return l.length?'<div class="stock-alert danger-alert"><b>● LOW STOCK ALERT</b> '+l.map(x=>esc(type(x))+' — '+x.current_stock+' / '+x.minimum_level).join(' • ')+'</div>':'<div class="stock-alert good-alert">✓ All ULD stock levels are above the minimum.</div>'}
async function logout(){try{await api('logout')}finally{location.href='login.php'}}
page('dash');
</script></body></html>
