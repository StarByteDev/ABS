/* ABS V15.8.7: one shared accessible theme state for public, member and admin. */
(()=>{'use strict';
 const key='abs.theme', root=document.documentElement;
 const normalize=(value)=>value==='light'?'light':'dark';
 const read=()=>{try{return normalize(localStorage.getItem(key))}catch(_){return normalize(root.dataset.absTheme)}};
 function apply(value){
   const mode=normalize(value), next=mode==='dark'?'Light':'Dark';
   root.dataset.absTheme=mode;
   root.style.colorScheme=mode;
   document.querySelectorAll('[data-abs-theme-toggle]').forEach(button=>{
     button.setAttribute('aria-label','Switch to '+next+' theme');
     button.setAttribute('title','Switch to '+next+' theme');
     button.setAttribute('aria-pressed',mode==='dark'?'true':'false');
     const label=button.querySelector('[data-abs-theme-label]');
     if(label)label.textContent='Switch to '+next;
   });
   const meta=document.querySelector('meta[name="theme-color"]');
   if(meta)meta.setAttribute('content',mode==='dark'?'#07121d':'#f3f7fa');
 }
 apply(read());
 document.addEventListener('click',event=>{
   const trigger=event.target.closest('[data-abs-theme-toggle]');
   if(!trigger || trigger.disabled)return;
   const next=normalize(root.dataset.absTheme)==='dark'?'light':'dark';
   try{localStorage.setItem(key,next)}catch(_){}
   apply(next);
 });
 window.addEventListener('storage',event=>{if(event.key===key)apply(read())});
 document.addEventListener('DOMContentLoaded',()=>apply(read()),{once:true});
})();
