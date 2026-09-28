(() => {
  const canvas = document.getElementById('strategyTrendChart');
  if (!canvas) return;
  const rows = Array.isArray(window.ABS_STRATEGY_TREND) ? window.ABS_STRATEGY_TREND : [];
  const meaningful = rows.filter(row => Number(row.signals || 0) > 0 || Number(row.net_r || 0) !== 0);
  const empty = document.getElementById('strategyTrendEmpty');
  if (!meaningful.length) {
    if (empty) empty.hidden = false;
    canvas.style.opacity = '.18';
  }

  const draw = () => {
    const rect = canvas.getBoundingClientRect();
    const dpr = Math.max(1, window.devicePixelRatio || 1);
    canvas.width = Math.max(1, Math.floor(rect.width * dpr));
    canvas.height = Math.max(1, Math.floor(rect.height * dpr));
    const ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    const w = rect.width, h = rect.height;
    ctx.clearRect(0, 0, w, h);

    const pad = {l: 42, r: 16, t: 18, b: 28};
    const plotW = Math.max(1, w - pad.l - pad.r), plotH = Math.max(1, h - pad.t - pad.b);
    const values = (rows.length ? rows : [{cumulative_r:0}]).map(r => Number(r.cumulative_r || 0));
    let min = Math.min(0, ...values), max = Math.max(0, ...values);
    if (min === max) { min -= 1; max += 1; }
    const range = max - min;
    const x = i => pad.l + (rows.length <= 1 ? plotW / 2 : (i / (rows.length - 1)) * plotW);
    const y = v => pad.t + ((max - v) / range) * plotH;

    ctx.strokeStyle = 'rgba(255,255,255,.07)';
    ctx.lineWidth = 1;
    ctx.font = '10px system-ui, sans-serif';
    ctx.fillStyle = 'rgba(137,151,173,.75)';
    [0,.25,.5,.75,1].forEach(step => {
      const yy = pad.t + step * plotH;
      ctx.beginPath(); ctx.moveTo(pad.l, yy); ctx.lineTo(w-pad.r, yy); ctx.stroke();
      const val = max - step * range;
      ctx.fillText(`${val.toFixed(1)}R`, 6, yy + 3);
    });

    if (!rows.length) return;
    const zeroY = y(0);
    ctx.strokeStyle = 'rgba(242,181,82,.24)';
    ctx.beginPath(); ctx.moveTo(pad.l, zeroY); ctx.lineTo(w-pad.r, zeroY); ctx.stroke();

    ctx.beginPath();
    rows.forEach((row, i) => {
      const px = x(i), py = y(Number(row.cumulative_r || 0));
      if (i === 0) ctx.moveTo(px, py); else ctx.lineTo(px, py);
    });
    ctx.strokeStyle = 'rgba(242,181,82,.95)';
    ctx.lineWidth = 2;
    ctx.stroke();

    const last = rows[rows.length-1];
    const lx = x(rows.length-1), ly = y(Number(last.cumulative_r || 0));
    ctx.beginPath(); ctx.arc(lx, ly, 3.5, 0, Math.PI*2); ctx.fillStyle = 'rgba(242,181,82,1)'; ctx.fill();

    const labelIndexes = [0, Math.floor((rows.length-1)/2), rows.length-1].filter((v,i,a)=>a.indexOf(v)===i);
    ctx.fillStyle = 'rgba(126,141,163,.8)';
    labelIndexes.forEach(i => {
      const label = String(rows[i]?.label || '');
      const width = ctx.measureText(label).width;
      const px = Math.max(pad.l, Math.min(w-pad.r-width, x(i)-width/2));
      ctx.fillText(label, px, h-8);
    });
  };

  draw();
  let timer;
  window.addEventListener('resize', () => { clearTimeout(timer); timer = setTimeout(draw, 120); });
})();
