(() => {
  const app = document.querySelector('[data-free-signal-app]');
  if (!app) return;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const $ = (selector, root = app) => root.querySelector(selector);
  const $$ = (selector, root = app) => [...root.querySelectorAll(selector)];
  const watch = $('[data-watch-ad]');
  const teaserWatch = $('[data-teaser-watch]');
  const consent = $('[data-reward-consent]');
  const message = $('[data-reward-message]');
  const teaser = $('[data-signal-teaser]');
  const reveal = $('[data-signal-reveal]');
  const sideCooldown = $('[data-side-cooldown]');
  const nextPanel = $('[data-next-free-panel]');
  const sideClock = $('[data-cooldown-clock]');
  const mainClock = $('[data-main-cooldown-clock]');
  const stageTitle = $('[data-stage-title]');
  const stageCopy = $('[data-stage-copy]');
  const shareFeedback = $('[data-share-feedback]');

  const defaultCta = app.dataset.cta || 'Watch Ad & Reveal Signal';
  const cooldownMinutes = Number(app.dataset.cooldownMinutes || 30);
  let cooldown = Math.max(0, Number(app.dataset.cooldown || 0));
  let slot = null;
  let session = null;
  let listeners = [];
  let busy = false;
  let currentSignal = null;

  const fmtTime = (seconds) => {
    const s = Math.max(0, Number(seconds || 0));
    return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(Math.floor(s % 60)).padStart(2, '0')}`;
  };

  const formatPrice = (value) => {
    const n = Number(value);
    if (!Number.isFinite(n)) return '—';
    let decimals = 2;
    if (Math.abs(n) < 1) decimals = 6;
    if (Math.abs(n) < 0.01) decimals = 8;
    if (Math.abs(n) >= 100) decimals = 2;
    return n.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: decimals});
  };

  const displaySymbol = (value) => {
    const raw = String(value || '').toUpperCase().replace(/\s+/g, '');
    if (raw.includes('/')) return raw;
    for (const quote of ['USDT','USDC','BUSD','BTC','ETH']) {
      if (raw.endsWith(quote) && raw.length > quote.length) return `${raw.slice(0, -quote.length)}/${quote}`;
    }
    return raw || '—';
  };

  const formatVolume = (value) => {
    const n = Number(value);
    if (!Number.isFinite(n)) return '—';
    if (Math.abs(n) >= 1e9) return `${(n / 1e9).toFixed(2)}B`;
    if (Math.abs(n) >= 1e6) return `${(n / 1e6).toFixed(2)}M`;
    if (Math.abs(n) >= 1e3) return `${(n / 1e3).toFixed(1)}K`;
    return n.toLocaleString(undefined, {maximumFractionDigits: 2});
  };

  const setText = (selector, value) => {
    const element = $(selector);
    if (element) element.textContent = value ?? '—';
  };

  const setMessage = (text, kind = '') => {
    if (!message) return;
    message.textContent = text || '';
    message.dataset.kind = kind;
  };

  const updateCooldownUi = () => {
    const display = fmtTime(cooldown);
    if (sideClock) sideClock.textContent = display;
    if (mainClock) mainClock.textContent = display;

    const cooling = cooldown > 0;
    if (sideCooldown) sideCooldown.hidden = !cooling;
    if (nextPanel) nextPanel.hidden = !cooling && !currentSignal;

    if (watch) {
      watch.disabled = busy || cooling;
      if (busy) {
        watch.innerHTML = '<span class="reward-spinner"></span> Preparing your ad';
      } else if (cooling) {
        watch.innerHTML = `<span>◷</span> Next Free Signal in ${display}`;
      } else {
        watch.innerHTML = `<span>▶</span> ${defaultCta}`;
      }
    }
    if (teaserWatch) {
      teaserWatch.disabled = busy || cooling;
      teaserWatch.textContent = cooling ? `Next Free Signal in ${display}` : '▶ Watch Ad & Reveal Signal';
    }

    if (!cooling && currentSignal && mainClock) mainClock.textContent = 'READY NOW';
  };

  const post = async (url, body = {}) => {
    const response = await fetch(url, {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
      credentials: 'same-origin',
      body: JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({ok: false, message: 'Unexpected server response.'}));
    if (!response.ok || data.ok === false) throw new Error(data.message || 'Request failed.');
    return data;
  };

  const loadGpt = () => new Promise((resolve, reject) => {
    if (window.googletag?.apiReady) return resolve();
    window.googletag = window.googletag || {cmd: []};
    const existing = document.querySelector('script[data-abs-gpt]');
    if (existing) {
      existing.addEventListener('load', resolve, {once: true});
      existing.addEventListener('error', () => reject(new Error('A rewarded ad is not available right now. Please try again later.')), {once: true});
      return;
    }
    const script = document.createElement('script');
    script.async = true;
    script.src = 'https://securepubads.g.doubleclick.net/tag/js/gpt.js';
    script.dataset.absGpt = '1';
    script.onload = resolve;
    script.onerror = () => reject(new Error('A rewarded ad is not available right now. Please try again later.'));
    document.head.appendChild(script);
  });

  const cleanup = () => {
    if (!window.googletag) return;
    window.googletag.cmd.push(() => {
      const pubads = googletag.pubads();
      listeners.forEach(([event, fn]) => pubads.removeEventListener(event, fn));
      listeners = [];
      if (slot) {
        try { googletag.destroySlots([slot]); } catch (_) {}
        slot = null;
      }
    });
  };

  const renderChart = (signal) => {
    const host = $('[data-signal-chart]');
    if (!host) return;
    const candles = Array.isArray(signal?.candles) ? signal.candles.filter(c => [c.open,c.high,c.low,c.close].every(v => Number.isFinite(Number(v)))) : [];
    if (candles.length < 3) {
      host.innerHTML = '<div class="chart-placeholder">Market structure is updating. Trade levels remain available above.</div>';
      return;
    }

    const width = 900;
    const height = 330;
    const pad = {left: 16, right: 80, top: 18, bottom: 50};
    const priceH = 225;
    const volumeTop = 255;
    const volumeH = 48;
    const targetValues = (signal.take_profit_levels || []).map(Number).filter(Number.isFinite).slice(0, 3);
    if (targetValues.length === 0 && Number.isFinite(Number(signal.take_profit))) targetValues.push(Number(signal.take_profit));
    const stop = Number(signal.stop_loss);
    const entry = Number(signal.entry_price);
    const priceValues = candles.flatMap(c => [Number(c.high), Number(c.low)]).concat(targetValues);
    if (Number.isFinite(stop)) priceValues.push(stop);
    if (Number.isFinite(entry)) priceValues.push(entry);
    let min = Math.min(...priceValues);
    let max = Math.max(...priceValues);
    const range = Math.max(max - min, Math.abs(max || 1) * 0.005);
    min -= range * .08;
    max += range * .08;
    const plotW = width - pad.left - pad.right;
    const xStep = plotW / Math.max(candles.length, 1);
    const x = index => pad.left + index * xStep + xStep / 2;
    const y = value => pad.top + ((max - value) / (max - min)) * priceH;
    const maxVol = Math.max(...candles.map(c => Number(c.volume) || 0), 1);
    const bodyW = Math.max(2, Math.min(9, xStep * .58));

    const grid = [0,.25,.5,.75,1].map(step => {
      const yy = pad.top + priceH * step;
      return `<line class="chart-grid" x1="${pad.left}" y1="${yy}" x2="${width-pad.right}" y2="${yy}"/>`;
    }).join('');

    const candleSvg = candles.map((c, index) => {
      const open = Number(c.open), close = Number(c.close), high = Number(c.high), low = Number(c.low);
      const up = close >= open;
      const cx = x(index);
      const top = Math.min(y(open), y(close));
      const bottom = Math.max(y(open), y(close));
      const bodyH = Math.max(1.5, bottom - top);
      const volH = Math.max(1, ((Number(c.volume) || 0) / maxVol) * volumeH);
      return `<g><line class="${up?'chart-wick-up':'chart-wick-down'}" x1="${cx}" y1="${y(high)}" x2="${cx}" y2="${y(low)}"/><rect class="${up?'chart-body-up':'chart-body-down'}" x="${cx-bodyW/2}" y="${top}" width="${bodyW}" height="${bodyH}" rx="1"/><rect class="${up?'chart-volume-up':'chart-volume-down'}" x="${cx-bodyW/2}" y="${volumeTop+volumeH-volH}" width="${bodyW}" height="${volH}" rx="1"/></g>`;
    }).join('');

    const line = (value, cls, label, tagCls) => {
      if (!Number.isFinite(value)) return '';
      const yy = Math.max(pad.top + 4, Math.min(pad.top + priceH - 4, y(value)));
      const text = `${label} ${formatPrice(value)}`;
      const boxW = Math.max(54, 7.2 * text.length);
      return `<line class="${cls}" x1="${pad.left}" y1="${yy}" x2="${width-pad.right}" y2="${yy}"/><rect class="${tagCls}" x="${width-pad.right+7}" y="${yy-10}" width="${boxW}" height="20" rx="4"/><text class="chart-label" x="${width-pad.right+12}" y="${yy+3}">${text}</text>`;
    };

    const targetLines = targetValues.map((value, i) => line(value, 'chart-target', `TP${i+1}`, 'chart-price-tag-up')).join('');
    const entryLine = line(entry, 'chart-entry', 'ENTRY', 'chart-price-tag-entry');
    const stopLine = line(stop, 'chart-stop', 'SL', 'chart-price-tag-stop');
    const last = candles[candles.length - 1];
    const firstDate = new Date((candles[0].time || 0) * 1000);
    const lastDate = new Date((last.time || 0) * 1000);

    host.innerHTML = `<svg viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" role="img" aria-label="${signal.symbol} ${signal.timeframe} market structure">${grid}${candleSvg}${targetLines}${entryLine}${stopLine}<text class="chart-label" x="${pad.left}" y="${height-12}">${firstDate.toLocaleDateString(undefined,{month:'short',day:'numeric'})}</text><text class="chart-label" x="${width-pad.right}" y="${height-12}" text-anchor="end">${lastDate.toLocaleDateString(undefined,{month:'short',day:'numeric'})}</text></svg>`;
  };

  const renderSignal = (signal) => {
    if (!signal || !reveal) return;
    currentSignal = signal;
    if (teaser) teaser.hidden = true;
    reveal.hidden = false;

    const targets = (Array.isArray(signal.take_profit_levels) ? signal.take_profit_levels : []).filter(v => v !== null && v !== '');
    if (targets.length === 0 && signal.take_profit !== null && signal.take_profit !== undefined) targets.push(signal.take_profit);

    const shownSymbol = displaySymbol(signal.symbol);
    setText('[data-signal-symbol]', shownSymbol);
    setText('[data-signal-coin]', shownSymbol.split('/')[0].slice(0, 8));
    setText('[data-signal-direction]', signal.direction || '—');
    setText('[data-signal-timeframe]', signal.timeframe || '—');
    setText('[data-chart-timeframe]', signal.timeframe || '—');
    setText('[data-signal-score]', signal.score != null ? `${signal.score}/100` : '—');
    setText('[data-signal-confidence-label]', signal.confidence_label || 'QUALIFIED');
    setText('[data-signal-entry]', formatPrice(signal.entry_price));
    setText('[data-signal-sl]', formatPrice(signal.stop_loss));
    setText('[data-signal-tp1]', formatPrice(targets[0]));
    setText('[data-signal-tp2]', formatPrice(targets[1]));
    setText('[data-signal-tp3]', formatPrice(targets[2]));
    $$('[data-tp-row]').forEach((row, index) => { row.hidden = targets[index] === undefined; });
    setText('[data-signal-current-price]', formatPrice(signal.current_price ?? signal.entry_price));
    setText('[data-signal-high]', formatPrice(signal.high_24h));
    setText('[data-signal-low]', formatPrice(signal.low_24h));
    setText('[data-signal-volume]', formatVolume(signal.volume_24h));
    setText('[data-signal-key-level]', formatPrice(signal.key_level ?? targets[0] ?? signal.take_profit));
    setText('[data-signal-bias]', signal.market_bias || (signal.direction === 'SHORT' ? 'BEARISH' : 'BULLISH'));
    setText('[data-signal-strategies]', (signal.strategies || []).join(' · ') || 'Pulse strategy engine');
    setText('[data-signal-summary]', signal.setup_summary || 'Review the Pulse entry, targets and stop-loss levels shown above.');

    const change = Number(signal.change_percent_24h);
    const changeEl = $('[data-signal-change]');
    if (changeEl) {
      changeEl.textContent = Number.isFinite(change) ? `${change >= 0 ? '+' : ''}${change.toFixed(2)}% ${change >= 0 ? '▲' : '▼'}` : '24H change unavailable';
      changeEl.style.color = Number.isFinite(change) && change < 0 ? '#ff6670' : '#54e09f';
    }

    const directionEl = $('[data-signal-direction]');
    if (directionEl) directionEl.style.color = String(signal.direction).toUpperCase() === 'SHORT' ? '#ff6670' : '#54e09f';

    renderChart(signal);
    updateCooldownUi();
    reveal.scrollIntoView({behavior: 'smooth', block: 'start'});
  };

  const sharePayload = () => {
    const url = app.dataset.shareUrl || window.location.href.split('#')[0];
    const symbol = displaySymbol(currentSignal?.symbol || 'a crypto market');
    const direction = currentSignal?.direction || 'qualified';
    const timeframe = currentSignal?.timeframe || '';
    const text = `ABS Pulse found a ${direction} ${symbol} ${timeframe} setup. Unlock your own free Pulse signal:`.replace(/\s+/g, ' ').trim();
    return {url, text, title: `ABS Pulse Free Signal · ${symbol}`};
  };

  const openShare = (network) => {
    const {url, text, title} = sharePayload();
    const U = encodeURIComponent(url);
    const T = encodeURIComponent(text);
    const Title = encodeURIComponent(title);
    const targets = {
      x: `https://twitter.com/intent/tweet?text=${T}&url=${U}`,
      facebook: `https://www.facebook.com/sharer/sharer.php?u=${U}`,
      whatsapp: `https://api.whatsapp.com/send?text=${encodeURIComponent(`${text} ${url}`)}`,
      telegram: `https://t.me/share/url?url=${U}&text=${T}`,
      linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${U}`,
      reddit: `https://www.reddit.com/submit?url=${U}&title=${Title}`,
    };
    if (targets[network]) window.open(targets[network], '_blank', 'noopener,noreferrer,width=760,height=620');
  };

  $$('[data-share-network]').forEach(button => button.addEventListener('click', async () => {
    const network = button.dataset.shareNetwork;
    const {url, text, title} = sharePayload();
    try {
      if (network === 'copy') {
        await navigator.clipboard.writeText(url);
        if (shareFeedback) shareFeedback.textContent = 'Free Signal link copied.';
        return;
      }
      if (network === 'more') {
        if (navigator.share) {
          await navigator.share({title, text, url});
          if (shareFeedback) shareFeedback.textContent = 'Shared successfully.';
        } else {
          await navigator.clipboard.writeText(`${text} ${url}`);
          if (shareFeedback) shareFeedback.textContent = 'Share text and link copied — paste it into any social app.';
        }
        return;
      }
      openShare(network);
      if (shareFeedback) shareFeedback.textContent = 'Share window opened.';
    } catch (_) {
      if (shareFeedback) shareFeedback.textContent = 'Sharing was cancelled.';
    }
  }));

  const showRewarded = async () => {
    if (!consent?.checked) {
      consent?.focus();
      throw new Error('Please accept the Risk Disclosure and Market Disclaimer first.');
    }
    if (cooldown > 0) throw new Error(`Your next free signal is available in ${fmtTime(cooldown)}.`);

    busy = true;
    updateCooldownUi();
    setMessage('Preparing your rewarded ad…');
    if (stageTitle) stageTitle.textContent = 'Preparing your rewarded ad…';
    if (stageCopy) stageCopy.textContent = 'Your qualified signal will appear immediately after the reward is granted.';

    session = await post(app.dataset.sessionUrl);
    await loadGpt();

    await new Promise((resolve, reject) => {
      window.googletag.cmd.push(() => {
        cleanup();
        const pubads = googletag.pubads();
        slot = googletag.defineOutOfPageSlot(session.ad_unit_path, googletag.enums.OutOfPageFormat.REWARDED);
        if (!slot) {
          reject(new Error('A rewarded ad is not available right now. Please try again later.'));
          return;
        }
        slot.addService(pubads);
        googletag.enableServices();

        let granted = false;
        let settled = false;
        const readyTimeout = setTimeout(() => finishReject(new Error('A rewarded ad is not available right now. Please try again later.')), 12000);
        const finishReject = error => { if (settled) return; settled = true; clearTimeout(readyTimeout); reject(error); };
        const finishResolve = () => { if (settled) return; settled = true; clearTimeout(readyTimeout); resolve(); };

        const onReady = event => {
          if (event.slot !== slot) return;
          clearTimeout(readyTimeout);
          setMessage('Your ad is ready. Complete it to reveal the signal.');
          if (stageTitle) stageTitle.textContent = 'Your ad is ready.';
          if (stageCopy) stageCopy.textContent = 'Complete the rewarded ad to reveal your qualified Pulse setup.';
          const shown = event.makeRewardedVisible();
          if (shown === false) finishReject(new Error('The rewarded ad could not open. Please try again.'));
        };

        const onVideoCompleted = event => {
          if (event.slot !== slot) return;
          setMessage('Ad completed. Unlocking your signal…');
        };

        const onGranted = async event => {
          if (event.slot !== slot || granted) return;
          granted = true;
          try {
            const out = await post(app.dataset.claimUrl, {
              claim_token: session.claim_token,
              reward_type: event.payload?.type || 'reward',
              reward_amount: event.payload?.amount || 0,
            });
            cooldown = Number(out.cooldown_seconds || session.cooldown_minutes * 60 || cooldownMinutes * 60);
            renderSignal(out.signal);
            setMessage('Signal unlocked. It will stay visible until you refresh or leave this page.', 'success');
            if (stageTitle) stageTitle.textContent = 'Signal unlocked.';
            if (stageCopy) stageCopy.textContent = 'Your current signal remains visible while the next-free-signal timer runs.';
            finishResolve();
          } catch (error) {
            finishReject(error);
          }
        };

        const onClosed = event => {
          if (event.slot !== slot) return;
          if (!granted) finishReject(new Error('The ad closed before the reward was granted, so no signal was unlocked.'));
          cleanup();
        };
        const onRender = event => {
          if (event.slot !== slot) return;
          if (event.isEmpty) finishReject(new Error('A rewarded ad is not available right now. Please try again later.'));
        };

        [['rewardedSlotReady', onReady], ['rewardedSlotVideoCompleted', onVideoCompleted], ['rewardedSlotGranted', onGranted], ['rewardedSlotClosed', onClosed], ['slotRenderEnded', onRender]].forEach(([event, fn]) => {
          pubads.addEventListener(event, fn);
          listeners.push([event, fn]);
        });
        googletag.display(slot);
      });
    }).finally(cleanup);
  };

  const begin = async () => {
    if (busy) return;
    try {
      await showRewarded();
    } catch (error) {
      setMessage(error.message || 'Could not unlock a signal. Please try again later.', 'error');
    } finally {
      busy = false;
      updateCooldownUi();
    }
  };

  watch?.addEventListener('click', begin);
  teaserWatch?.addEventListener('click', begin);

  setInterval(() => {
    if (cooldown > 0) cooldown -= 1;
    updateCooldownUi();
  }, 1000);

  updateCooldownUi();
})();
