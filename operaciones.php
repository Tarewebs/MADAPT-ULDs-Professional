<?php
// OPERACIONES - isolated module
// Turnaround control for B787-900 (70 minutes).
declare(strict_types=1);
require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user'])) { http_response_code(401); exit('Unauthorized'); }

header('Content-Type: text/html; charset=UTF-8');
?>
<section class="box operaciones-module" id="operaciones-module">
  <div class="operaciones-head">
    <div><h2>OPERACIONES</h2><p class="muted">B787-900 · Turnaround 70 min</p></div>
    <div class="operaciones-clock" id="opsClock">--:--</div>
  </div>
  <div class="ops-grid" id="opsGrid"></div>
</section>
<script>
(function(){
  const root=document.getElementById('operaciones-module'); if(!root)return;
  const key='madapt_operaciones_state_v1';
  const ops=[
    ['PS','Passenger Services','Boarding / disembarkation'],
    ['BAG','Bag / CGO Handling','Baggage and cargo handling'],
    ['AC','A/C Servicing','Cleaning, catering and technical servicing']
  ];
  let state=JSON.parse(localStorage.getItem(key)||'{}');
  function save(){localStorage.setItem(key,JSON.stringify(state));}
  function render(){
    document.getElementById('opsGrid').innerHTML=ops.map(([id,title,desc])=>{
      const x=state[id]||{}; const started=x.started?'Started':'Not started';
      return `<div class="box ops-card"><div class="ops-title"><strong>${title}</strong><span class="ops-status ${x.completed?'done':x.started?'started':''}">${x.completed?'Completed':started}</span></div><p class="muted">${desc}</p><div class="ops-times">${x.startedAt?'Start: '+new Date(x.startedAt).toLocaleTimeString():'Ready'}${x.completedAt?' · Complete: '+new Date(x.completedAt).toLocaleTimeString():''}</div><div class="ops-actions"><button data-start="${id}" ${x.started||x.completed?'disabled':''}>START</button><button data-complete="${id}" ${!x.started||x.completed?'disabled':''}>COMPLETE</button><button class="danger-btn" data-reset="${id}" ${!x.started?'disabled':''}>RESET</button></div></div>`;
    }).join('');
    root.querySelectorAll('[data-start]').forEach(b=>b.onclick=()=>{const id=b.dataset.start;state[id]={started:true,startedAt:new Date().toISOString()};save();render();});
    root.querySelectorAll('[data-complete]').forEach(b=>b.onclick=()=>{const id=b.dataset.complete;state[id].completed=true;state[id].completedAt=new Date().toISOString();save();render();});
    root.querySelectorAll('[data-reset]').forEach(b=>b.onclick=()=>{delete state[b.dataset.reset];save();render();});
  }
  function clock(){document.getElementById('opsClock').textContent=new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit',second:'2-digit'});}
  render();clock();setInterval(clock,1000);
})();
</script>
<style>
.operaciones-head{display:flex;align-items:center;justify-content:space-between;gap:16px}.operaciones-head h2{margin:0}.operaciones-clock{font-size:1.15rem;font-weight:700}.ops-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:16px}.ops-card{margin:0}.ops-title{display:flex;align-items:center;justify-content:space-between;gap:10px}.ops-status{padding:4px 8px;border-radius:999px;background:#eef2f0;font-size:.78rem}.ops-status.started{background:#fff3cd}.ops-status.done{background:#d1e7dd}.ops-times{font-size:.82rem;margin:10px 0;color:#66736f}.ops-actions{display:flex;gap:8px;flex-wrap:wrap}.ops-actions button{cursor:pointer}@media(max-width:700px){.ops-grid{grid-template-columns:1fr}.operaciones-head{align-items:flex-start}}
</style>
