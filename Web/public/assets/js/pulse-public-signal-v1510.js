(() => {
  const app = document.querySelector('[data-free-signal-app]');
  if (!app) return;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const $ = (s) => app.querySelector(s);
  const message = $('[data-reward-message]');
  const watch = $('[data-watch-ad]');
  const consent = $('[data-reward-consent]');
  const reveal = $('[data-signal-reveal]');
  const cooldownClock = $('[data-cooldown-clock]');
  const viewClock = $('[data-view-clock]');
  const accessState = $('[data-access-state]');
  const stage = $('[data-ad-stage]');
  const stageIcon = $('[data-stage-icon]');
  const stageLabel = $('[data-stage-label]');
  const stageTitle = $('[data-stage-title]');
  const stageCopy = $('[data-stage-copy]');
  const defaultCta = app.dataset.cta || 'Watch Ad & Reveal Signal';
  let cooldown = Number(app.dataset.cooldown || 0);
  let viewRemaining = Number(app.dataset.view || 0);
  let slot = null;
  let session = null;
  let listeners = [];
  let busy = false;

  const fmt = (seconds) => `${String(Math.floor(seconds / 60)).padStart(2,'0')}:${String(seconds % 60).padStart(2,'0')}`;
  const setMessage = (text, kind='') => { if(message){ message.textContent=text; message.dataset.kind=kind; } };
  const setStage = (kind, label, title, copy, icon='▶') => {
    if(stage) stage.dataset.adStage = kind;
    if(stageLabel) stageLabel.textContent = label;
    if(stageTitle) stageTitle.textContent = title;
    if(stageCopy) stageCopy.textContent = copy;
    if(stageIcon) stageIcon.textContent = icon;
  };
  const syncButton = () => {
    if (!watch) return;
    const unavailable = cooldown > 0 || busy;
    watch.disabled = unavailable;
    if (cooldown > 0) {
      watch.innerHTML = `<span>◷</span> Next Free Signal in ${fmt(cooldown)}`;
      if(accessState) accessState.textContent='COOLDOWN';
    } else if (busy) {
      watch.innerHTML = '<span class="reward-spinner"></span> Preparing Ad';
    } else {
      watch.innerHTML = `<span>▶</span> ${defaultCta}`;
      if(accessState) accessState.textContent='READY';
    }
  };
  const renderSignal = (signal) => {
    if (!signal || !reveal) return;
    const set = (sel, value) => { const el=$(sel); if(el) el.textContent = value ?? '—'; };
    set('[data-signal-symbol]', signal.symbol); set('[data-signal-direction]', signal.direction);
    set('[data-signal-timeframe]', signal.timeframe); set('[data-signal-entry]', signal.entry_price);
    set('[data-signal-sl]', signal.stop_loss); set('[data-signal-tp]', signal.take_profit);
    set('[data-signal-confidence]', signal.confidence_score != null ? `${signal.confidence_score}%` : '—');
    set('[data-signal-score]', signal.score); set('[data-signal-strategies]', (signal.strategies || []).join(' · ') || 'Pulse strategy engine');
    reveal.hidden = false;
    reveal.scrollIntoView({behavior:'smooth', block:'center'});
  };
  const post = async (url, body={}) => {
    const res = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, credentials:'same-origin', body:JSON.stringify(body)});
    const data = await res.json().catch(()=>({ok:false,message:'Unexpected server response.'}));
    if (!res.ok || data.ok === false) throw new Error(data.message || 'Request failed.');
    return data;
  };
  const loadGpt = () => new Promise((resolve,reject) => {
    if (window.googletag?.apiReady) return resolve();
    window.googletag = window.googletag || {cmd:[]};
    const existing=document.querySelector('script[data-abs-gpt]');
    if(existing){ existing.addEventListener('load', resolve, {once:true}); existing.addEventListener('error',()=>reject(new Error('Google rewarded advertising could not be loaded.')),{once:true}); return; }
    const script=document.createElement('script'); script.async=true; script.src='https://securepubads.g.doubleclick.net/tag/js/gpt.js'; script.dataset.absGpt='1';
    script.onload=resolve; script.onerror=()=>reject(new Error('Google rewarded advertising could not be loaded. Check privacy/ad-blocking settings and try again.')); document.head.appendChild(script);
  });
  const cleanup = () => {
    if (!window.googletag) return;
    window.googletag.cmd.push(() => {
      const pubads=googletag.pubads(); listeners.forEach(([event,fn])=>pubads.removeEventListener(event,fn)); listeners=[];
      if(slot){ try{ googletag.destroySlots([slot]); }catch(e){} slot=null; }
    });
  };
  const showRewarded = async () => {
    if (!consent?.checked) throw new Error('Please accept the risk notice before continuing.');
    if (cooldown > 0) throw new Error('Please wait until the cooldown finishes.');
    busy=true; syncButton();
    setMessage('Preparing your free signal…');
    setStage('loading','PREPARING','Preparing a short ad…','Your signal will unlock after the ad completes.','◌');
    session = await post(app.dataset.sessionUrl);
    await loadGpt();
    await new Promise((resolve,reject) => {
      window.googletag.cmd.push(() => {
        cleanup();
        const pubads=googletag.pubads();
        slot=googletag.defineOutOfPageSlot(session.ad_unit_path, googletag.enums.OutOfPageFormat.REWARDED);
        if(!slot){ reject(new Error('A free-signal ad is not available in this browser right now. Please try another browser or come back later.')); return; }
        slot.addService(pubads); googletag.enableServices();
        let granted=false;
        let settled=false;
        const finishReject=(error)=>{ if(settled)return; settled=true; clearTimeout(readyTimeout); reject(error); };
        const finishResolve=()=>{ if(settled)return; settled=true; clearTimeout(readyTimeout); resolve(); };
        const readyTimeout=setTimeout(()=>finishReject(new Error('Google did not return rewarded inventory in time. No signal was unlocked and no cooldown was applied. Please try again later.')),12000);
        const onReady=(event)=>{
          if(event.slot!==slot)return; clearTimeout(readyTimeout);
          setStage('ready','AD READY','Your ad is ready','Finish the ad to unlock your Pulse signal.','▶');
          setMessage('Opening ad…');
          const shown=event.makeRewardedVisible();
          if(shown===false) finishReject(new Error('The rewarded ad could not be displayed in this browser context.'));
        };
        const onVideoCompleted=(event)=>{ if(event.slot!==slot)return; setStage('complete','AD COMPLETE','Unlocking your signal…','The ad is complete. Your signal is being prepared.','✓'); setMessage('Ad completed. Confirming reward…'); };
        const onGranted=async(event)=>{ if(event.slot!==slot||granted)return; granted=true; try{
          setStage('granted','REWARD GRANTED','Unlocking your Pulse signal…','Your qualified signal is ready.','✓');
          const out=await post(app.dataset.claimUrl,{claim_token:session.claim_token,reward_type:event.payload?.type||'reward',reward_amount:event.payload?.amount||0});
          renderSignal(out.signal); viewRemaining=Number(out.view_seconds||session.view_seconds||30); cooldown=Number(out.cooldown_seconds||session.cooldown_minutes*60||1800);
          setMessage('Signal unlocked successfully.','success');
          setStage('granted','SIGNAL UNLOCKED','Your free Pulse signal is live','The reveal window is running below. Your next free signal becomes available after the cooldown.','✓');
          finishResolve();
        }catch(e){ finishReject(e); } };
        const onClosed=(event)=>{ if(event.slot!==slot)return; if(!granted) finishReject(new Error('The ad ended before completion, so no signal was unlocked. You can try again.')); cleanup(); };
        const onRender=(event)=>{ if(event.slot!==slot)return; if(event.isEmpty) finishReject(new Error('No free-signal ad is available right now. Please try again later.')); };
        [['rewardedSlotReady',onReady],['rewardedSlotVideoCompleted',onVideoCompleted],['rewardedSlotGranted',onGranted],['rewardedSlotClosed',onClosed],['slotRenderEnded',onRender]].forEach(([e,f])=>{pubads.addEventListener(e,f);listeners.push([e,f]);});
        googletag.display(slot);
      });
    }).finally(()=>cleanup());
  };
  watch?.addEventListener('click', async()=>{
    try{ await showRewarded(); }
    catch(e){ setMessage(e.message||'Could not unlock a signal.','error'); setStage('error','NOT AVAILABLE','Free Signal is unavailable','No signal was unlocked. You can try again later.','!'); }
    finally{ busy=false; syncButton(); }
  });
  setInterval(()=>{
    if(cooldown>0){ cooldown--; if(cooldownClock) cooldownClock.textContent=fmt(cooldown); }
    if(viewRemaining>0){ viewRemaining--; if(viewClock) viewClock.textContent=`${viewRemaining}s`; if(viewRemaining<=0&&reveal){ reveal.hidden=true; setMessage('The signal reveal has ended. Your cooldown remains active.'); } }
    syncButton();
  },1000);
  if(cooldownClock) cooldownClock.textContent=fmt(cooldown);
  syncButton();
})();
