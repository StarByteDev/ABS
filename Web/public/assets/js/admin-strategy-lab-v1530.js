(() => {
  const form = document.querySelector('[data-lab-engine-form]');
  if (form) {
    const options = [...form.querySelectorAll('.lab-mode-option')];
    const interval = form.querySelector('[data-lab-interval]');
    const toggle = form.querySelector('[data-lab-enable-toggle]');
    const enabled = form.querySelector('[data-lab-enabled]');
    const sync = () => {
      options.forEach(option => option.classList.toggle('selected', option.querySelector('input')?.checked));
      const mode = form.querySelector('input[name="mode"]:checked')?.value || 'cron';
      if (interval) interval.closest('.lab-field')?.classList.toggle('is-muted', mode === 'manual');
      if (enabled && toggle) enabled.value = toggle.checked ? 'true' : 'false';
    };
    options.forEach(option => option.addEventListener('click', () => setTimeout(sync, 0)));
    toggle?.addEventListener('change', sync);
    sync();
  }

  const canvas = document.getElementById('strategyTrendChart');
  if (!canvas) return;
  const rows = Array.isArray(window.ABS_STRATEGY_TREND) ? window.ABS_STRATEGY_TREND : [];
  const meaningful = rows.filter(row => Number(row.signals || 0) > 0 || Number(row.net_r || 0) !== 0);
  const empty = document.getElementById('strategyTrendEmpty');
  if (!meaningful.length) {
    if (empty) empty.hidden = false;
    canvas.style.opacity = '.2';
  }

  const draw = () => {
    const rect = canvas.getBoundingClientRect();
    if (!rect.width || !rect.height) return;
    const dpr = Math.max(1, window.devicePixelRatio || 1);
    canvas.width = Math.floor(rect.width * dpr);
    canvas.height = Math.floor(rect.height * dpr);
    const ctx = canvas.getContext('2d');
    ctx.setTransform(dpr,0,0,dpr,0,0);
    const w=rect.width,h=rect.height,p={l:38,r:12,t:14,b:24};
    const pw=Math.max(1,w-p.l-p.r),ph=Math.max(1,h-p.t-p.b);
    const values=(rows.length?rows:[{cumulative_r:0}]).map(r=>Number(r.cumulative_r||0));
    let min=Math.min(0,...values),max=Math.max(0,...values); if(min===max){min-=1;max+=1}
    const range=max-min,x=i=>p.l+(rows.length<=1?pw/2:(i/(rows.length-1))*pw),y=v=>p.t+((max-v)/range)*ph;
    ctx.clearRect(0,0,w,h); ctx.font='9px system-ui,sans-serif'; ctx.fillStyle='rgba(122,148,166,.75)';
    [0,.5,1].forEach(step=>{const yy=p.t+step*ph;ctx.strokeStyle='rgba(255,255,255,.055)';ctx.beginPath();ctx.moveTo(p.l,yy);ctx.lineTo(w-p.r,yy);ctx.stroke();ctx.fillText(`${(max-step*range).toFixed(1)}R`,4,yy+3)});
    if(!rows.length)return;
    const zero=y(0);ctx.strokeStyle='rgba(216,166,62,.18)';ctx.beginPath();ctx.moveTo(p.l,zero);ctx.lineTo(w-p.r,zero);ctx.stroke();
    ctx.beginPath();rows.forEach((row,i)=>{const px=x(i),py=y(Number(row.cumulative_r||0));i?ctx.lineTo(px,py):ctx.moveTo(px,py)});ctx.strokeStyle='rgba(240,197,99,.95)';ctx.lineWidth=2;ctx.stroke();
    const last=rows.length-1;ctx.beginPath();ctx.arc(x(last),y(Number(rows[last].cumulative_r||0)),3,0,Math.PI*2);ctx.fillStyle='rgba(240,197,99,1)';ctx.fill();
    const ids=[0,Math.floor(last/2),last].filter((v,i,a)=>a.indexOf(v)===i);ctx.fillStyle='rgba(115,143,162,.8)';ids.forEach(i=>{const label=String(rows[i]?.label||'');const mw=ctx.measureText(label).width;ctx.fillText(label,Math.max(p.l,Math.min(w-p.r-mw,x(i)-mw/2)),h-6)});
  };
  draw(); let timer; window.addEventListener('resize',()=>{clearTimeout(timer);timer=setTimeout(draw,100)});
})();
