'use strict';
document.addEventListener('DOMContentLoaded',()=>{
 const sidebar=document.getElementById('pulse-sidebar');
 const setMenu=open=>{sidebar?.classList.toggle('open',open);document.body.classList.toggle('pulse-menu-open',open);document.querySelectorAll('[data-pulse-menu]').forEach(b=>b.setAttribute('aria-expanded',open?'true':'false'));};
 document.querySelectorAll('[data-pulse-menu]').forEach(b=>b.addEventListener('click',()=>setMenu(!sidebar?.classList.contains('open'))));
 sidebar?.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>setMenu(false)));
 const accountMenus=[...document.querySelectorAll('.pulse-account-menu')];
 document.addEventListener('click',event=>{accountMenus.forEach(menu=>{if(menu.open&&!menu.contains(event.target))menu.removeAttribute('open');});});
 document.addEventListener('keydown',event=>{if(event.key==='Escape'){setMenu(false);accountMenus.forEach(menu=>menu.removeAttribute('open'));}});
 document.querySelectorAll('[data-confirm]').forEach(el=>el.addEventListener('click',e=>{if(!window.confirm(el.dataset.confirm||'Confirm this action?'))e.preventDefault();}));
 document.querySelectorAll('[data-secret-toggle]').forEach(btn=>btn.addEventListener('click',()=>{const input=document.getElementById(btn.dataset.secretToggle);if(input){input.type=input.type==='password'?'text':'password';btn.textContent=input.type==='password'?'Show':'Hide';}}));
 const mode=document.querySelector('[name="execution_mode"]');
 const auto=document.querySelector('[name="auto_trade_enabled"]');
 const syncAuto=()=>{if(auto&&mode){auto.checked=mode.value==='automatic';auto.disabled=mode.value!=='automatic';}};
 mode?.addEventListener('change',syncAuto);syncAuto();
 const sizing=document.querySelector('[name="sizing_mode"]');
 const syncSizing=()=>{document.querySelectorAll('[data-sizing]').forEach(el=>el.hidden=el.dataset.sizing!==sizing?.value);};
 sizing?.addEventListener('change',syncSizing);syncSizing();
});
