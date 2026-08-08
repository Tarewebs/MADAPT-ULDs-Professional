<?php
declare(strict_types=1);
session_start();
require_once __DIR__.'/config.php';
if(!empty($_SESSION['user'])){header('Location:index.php');exit;}
$settings=[];
try{$q=db()->query("SELECT setting_key,setting_value FROM madapt_settings");foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$settings[$r['setting_key']]=$r['setting_value'];}catch(Throwable $e){}
$company=$settings['company_name']??'MADAPT ULDs';
$logo=$settings['logo_path']??'';
$aircraft=$settings['aircraft_path']??'';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=htmlspecialchars($company)?> — Login</title>
<style>
*{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:Arial,sans-serif}.login-page{min-height:100vh;position:relative;overflow:hidden;display:grid;place-items:center;padding:20px;background:linear-gradient(135deg,#004f2d,#006b3c 50%,#087a46)}.login-card{position:relative;z-index:10;width:min(540px,100%);background:#fff;border-radius:22px;padding:42px;text-align:center;box-shadow:0 25px 70px #0005}.logo-wrap{min-height:80px;display:grid;place-items:center}.logo{max-width:230px;max-height:90px;object-fit:contain}.fallback{font:700 28px Georgia;color:#c5222b}.fallback small{display:block;font:700 12px Arial;letter-spacing:2px}.login-card h1{color:#087a46;font-size:38px;margin:12px 0 5px}.subtitle{color:#687b75;margin:0 0 25px}.login-form{display:grid;gap:12px}.login-form input{width:100%;padding:15px;border:1px solid #cbd9d4;border-radius:9px}.login-form button{padding:15px;border:0;border-radius:9px;background:#087a46;color:#fff;font-weight:bold;cursor:pointer}.message{min-height:20px;color:#bd2330;font-weight:bold}.links{display:flex;gap:10px;justify-content:center;margin-top:16px;flex-wrap:wrap}.links button{padding:9px 12px;border:1px solid #cbd9d4;background:#fff;color:#087a46;border-radius:8px;cursor:pointer}.aircraft{position:absolute;z-index:3;left:-38vw;bottom:5%;width:min(600px,45vw);max-height:250px;object-fit:contain;filter:drop-shadow(0 10px 10px #0005);animation:flyAcross 16s linear infinite;pointer-events:none}@keyframes flyAcross{0%{transform:translate3d(0,10px,0)}20%{transform:translate3d(35vw,-5px,0)}40%{transform:translate3d(75vw,7px,0)}60%{transform:translate3d(115vw,-5px,0)}80%{transform:translate3d(155vw,7px,0)}100%{transform:translate3d(195vw,0,0)}}@media(max-width:600px){.login-card{padding:28px 20px}.login-card h1{font-size:30px}.aircraft{width:330px;max-width:72vw;bottom:3%;animation-duration:13s}}
</style></head><body><div class="login-page">
<?php if($aircraft):?><img class="aircraft" src="<?=htmlspecialchars($aircraft)?>" alt=""><?php endif;?>
<div class="login-card"><div class="logo-wrap"><?php if($logo):?><img class="logo" src="<?=htmlspecialchars($logo)?>" alt="Logo"><?php else:?><div class="fallback">Ethiopian<small>AIRLINES</small></div><?php endif;?></div>
<h1><?=htmlspecialchars($company)?></h1><p class="subtitle">Secure Inventory Management</p>
<form id="lf" class="login-form"><input id="lu" placeholder="Username" autocomplete="username" required><input id="lp" type="password" placeholder="Password" autocomplete="current-password" required><button id="lb">Sign in</button><div id="lm" class="message"></div></form>
<div class="links"><button type="button" onclick="register()">Create account</button><button type="button" onclick="forgot()">Forgot password?</button></div></div></div>
<script>
async function A(a,d={}){const r=await fetch('api.php?action='+encodeURIComponent(a),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});let j;try{j=await r.json()}catch(e){throw Error('Invalid server response (HTTP '+r.status+')')}if(!j.ok)throw Error(j.error||'Request failed');return j}
lf.onsubmit=async e=>{e.preventDefault();lm.textContent='Signing in...';lb.disabled=true;try{await A('login',{username:lu.value.trim(),password:lp.value});location.href='index.php'}catch(x){lm.textContent=x.message;lb.disabled=false}};
function register(){const u=prompt('Username');if(!u)return;const n=prompt('Full name');if(!n)return;const email=prompt('Email address');if(email===null)return;const p=prompt('Password (minimum 8 characters)');if(!p)return;A('register',{username:u.trim(),full_name:n.trim(),email:email.trim(),password:p}).then(r=>alert(r.message||'Account request sent.')).catch(e=>alert(e.message))}
function forgot(){const u=prompt('Enter your username');if(u)A('forgot',{username:u.trim()}).then(()=>alert('Reset request sent to the administrator.')).catch(e=>alert(e.message))}
</script></body></html>