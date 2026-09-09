(() => {
  const root = document.querySelector('[data-rewarded-ad-card]');
  if (!root) return;

  const button = root.querySelector('[data-rewarded-ad-watch]');
  const statusEl = root.querySelector('[data-rewarded-ad-status]');
  const consent = root.querySelector('[data-rewarded-ad-consent]');
  const balanceEl = document.querySelector('[data-pulse-sparks-balance]');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const sessionUrl = root.dataset.sessionUrl;
  const claimUrl = root.dataset.claimUrl;

  let busy = false;
  let rewardedSlot = null;
  let rewardGranted = false;
  let claimToken = null;
  let listeners = [];

  const setStatus = (message, tone = '') => {
    if (!statusEl) return;
    statusEl.textContent = message;
    statusEl.dataset.tone = tone;
  };

  const setBusy = (value) => {
    busy = value;
    if (button) {
      button.disabled = value;
      button.setAttribute('aria-busy', value ? 'true' : 'false');
    }
  };

  const jsonPost = async (url, body = {}) => {
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf,
      },
      body: JSON.stringify(body),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'Unable to complete the rewarded-ad request.');
    return payload;
  };

  const loadGpt = () => new Promise((resolve, reject) => {
    window.googletag = window.googletag || {cmd: []};
    if (window.googletag.apiReady) {
      resolve();
      return;
    }
    const existing = document.querySelector('script[data-abs-gpt]');
    if (existing) {
      existing.addEventListener('load', resolve, {once: true});
      existing.addEventListener('error', () => reject(new Error('Google rewarded ad service could not be loaded.')), {once: true});
      return;
    }
    const script = document.createElement('script');
    script.async = true;
    script.src = 'https://securepubads.g.doubleclick.net/tag/js/gpt.js';
    script.crossOrigin = 'anonymous';
    script.dataset.absGpt = '1';
    script.onload = resolve;
    script.onerror = () => reject(new Error('Google rewarded ad service could not be loaded.'));
    document.head.appendChild(script);
  });

  const removeListeners = () => {
    try {
      const pubads = window.googletag?.pubads?.();
      if (pubads?.removeEventListener) {
        listeners.forEach(([type, handler]) => pubads.removeEventListener(type, handler));
      }
    } catch (_) {}
    listeners = [];
  };

  const cleanup = () => {
    removeListeners();
    try {
      if (rewardedSlot && window.googletag?.destroySlots) window.googletag.destroySlots([rewardedSlot]);
    } catch (_) {}
    rewardedSlot = null;
    rewardGranted = false;
    claimToken = null;
    setBusy(false);
  };

  const claimReward = async (payload) => {
    if (!claimToken || rewardGranted) return;
    rewardGranted = true;
    const token = claimToken;
    setStatus('Reward verified. Adding Pulse Sparks…', 'working');
    try {
      const result = await jsonPost(claimUrl, {
        claim_token: token,
        provider_payload: {
          type: payload?.type || 'reward',
          amount: Number(payload?.amount || 0),
        },
      });
      const balance = result?.data?.balance;
      if (balanceEl && Number.isFinite(Number(balance))) balanceEl.textContent = Number(balance).toLocaleString();
      setStatus(result.message || 'Pulse Sparks added to your wallet.', 'success');
      window.dispatchEvent(new CustomEvent('abs:pulse-sparks-updated', {detail: result.data || {}}));
    } catch (error) {
      rewardGranted = false;
      setStatus(error.message || 'The reward could not be credited.', 'error');
    }
  };

  const showRewardedAd = async () => {
    if (busy) return;
    if (consent && !consent.checked) {
      setStatus('Please confirm that you choose to view the optional rewarded ad.', 'error');
      consent.focus();
      return;
    }
    setBusy(true);
    setStatus('Preparing a rewarded ad…', 'working');

    try {
      const session = await jsonPost(sessionUrl);
      const data = session.data || {};
      claimToken = data.claim_token;
      if (!data.ad_unit_path || !claimToken) throw new Error('Rewarded ad configuration is incomplete.');

      await loadGpt();
      window.googletag = window.googletag || {cmd: []};
      window.googletag.cmd.push(() => {
        const slot = window.googletag.defineOutOfPageSlot(
          data.ad_unit_path,
          window.googletag.enums.OutOfPageFormat.REWARDED,
        );
        rewardedSlot = slot;

        if (!slot) {
          setStatus('Rewarded ads are not supported on this device or page.', 'error');
          cleanup();
          return;
        }

        const pubads = window.googletag.pubads();
        slot.addService(pubads);
        const on = (type, handler) => {
          listeners.push([type, handler]);
          pubads.addEventListener(type, handler);
        };
        const slotMatches = (event) => event.slot === slot;

        on('rewardedSlotReady', (event) => {
          if (!slotMatches(event)) return;
          setStatus('Ad ready. Opening now…', 'working');
          event.makeRewardedVisible();
        });

        on('rewardedSlotVideoCompleted', (event) => {
          if (!slotMatches(event)) return;
          setStatus('Ad completed. Waiting for reward confirmation…', 'working');
        });

        on('rewardedSlotGranted', (event) => {
          if (!slotMatches(event)) return;
          claimReward(event.payload || {});
        });

        on('slotRenderEnded', (event) => {
          if (!slotMatches(event) || !event.isEmpty) return;
          setStatus('No rewarded ad is available right now. Please try again later.', 'error');
          cleanup();
        });

        on('rewardedSlotClosed', (event) => {
          if (!slotMatches(event)) return;
          if (!rewardGranted) setStatus('Ad closed before a reward was granted.', 'neutral');
          window.setTimeout(cleanup, 900);
        });

        if (!window.googletag.pubadsReady) window.googletag.enableServices();
        window.googletag.display(slot);
      });
    } catch (error) {
      setStatus(error.message || 'Rewarded ad could not be started.', 'error');
      cleanup();
    }
  };

  button?.addEventListener('click', showRewardedAd);
})();
