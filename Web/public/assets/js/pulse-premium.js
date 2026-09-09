'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const sidebarToggle = document.querySelector('[data-pulse-sidebar-toggle]');
    const storageKey = 'abs.pulse.sidebar.collapsed.v1';

    const setSidebarCollapsed = (collapsed, persist = true) => {
        body.classList.toggle('pulse-sidebar-collapsed', collapsed);
        if (sidebarToggle) {
            sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            sidebarToggle.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
        }
        if (persist) {
            try { window.localStorage.setItem(storageKey, collapsed ? '1' : '0'); } catch (error) { /* Storage may be unavailable. */ }
        }
    };

    try {
        setSidebarCollapsed(window.localStorage.getItem(storageKey) === '1', false);
    } catch (error) {
        setSidebarCollapsed(false, false);
    }

    sidebarToggle?.addEventListener('click', () => {
        setSidebarCollapsed(!body.classList.contains('pulse-sidebar-collapsed'));
    });

    const bindSavedScanControls = () => {
        document.querySelectorAll('[data-scan-save]:not([data-scan-bound])').forEach(button => {
            button.dataset.scanBound = '1';
            button.addEventListener('click', () => {
                const form = document.querySelector('[data-scan-filters]');
                if (!form) return;
                const values = Object.fromEntries(new FormData(form).entries());
                try {
                    window.localStorage.setItem('abs.pulse.saved.scan.v1', JSON.stringify(values));
                    button.textContent = 'Saved';
                    window.setTimeout(() => { button.textContent = 'Save Current'; }, 1400);
                } catch (error) {
                    button.textContent = 'Unable to Save';
                }
            });
        });

        document.querySelectorAll('[data-scan-load]:not([data-scan-bound])').forEach(button => {
            button.dataset.scanBound = '1';
            button.addEventListener('click', () => {
                const form = document.querySelector('[data-scan-filters]');
                if (!form) return;
                try {
                    const values = JSON.parse(window.localStorage.getItem('abs.pulse.saved.scan.v1') || '{}');
                    Object.entries(values).forEach(([name, value]) => {
                        const field = form.elements.namedItem(name);
                        if (field) field.value = value;
                    });
                    form.requestSubmit();
                } catch (error) {
                    button.textContent = 'No Saved View';
                }
            });
        });
    };
    bindSavedScanControls();

    const replaceScanFragment = (name, html) => {
        const current = document.querySelector(`[data-scan-fragment="${name}"]`);
        if (!current || !html) return;
        const template = document.createElement('template');
        template.innerHTML = html.trim();
        const next = template.content.firstElementChild;
        if (next) current.replaceWith(next);
    };

    const scanRunForm = document.querySelector('[data-run-market-scan]');

    // V14.8.15: keep scanner action stages aligned with a fresh Binance ticker
    // snapshot without consuming another scan. This refreshes the rendered
    // action state only; it does not rerun strategies or alter package access.
    let liveScannerRefreshBusy = false;
    const refreshScannerLiveState = async () => {
        if (!scanRunForm?.dataset.refreshUrl || liveScannerRefreshBusy || document.hidden) return;
        liveScannerRefreshBusy = true;
        try {
            const refreshUrl = new URL(scanRunForm.dataset.refreshUrl, window.location.origin);
            const currentQuery = new URLSearchParams(window.location.search);
            currentQuery.forEach((value, key) => refreshUrl.searchParams.set(key, value));
            const response = await fetch(refreshUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            const refreshed = await response.json().catch(() => ({}));
            if (response.ok && refreshed.results_html) {
                replaceScanFragment('results', refreshed.results_html);
                document.querySelectorAll('[data-scanner-last-scan]').forEach(node => { node.textContent = refreshed.last_scan || node.textContent; });
            }
        } catch (error) {
            // Live-state refresh is best-effort; the last rendered scan remains usable.
        } finally {
            liveScannerRefreshBusy = false;
        }
    };
    if (scanRunForm) window.setInterval(refreshScannerLiveState, 20000);

    scanRunForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = scanRunForm.querySelector('[data-scan-run-button]');
        const label = scanRunForm.querySelector('[data-scan-run-label]');
        const status = document.querySelector('[data-scan-action-message]');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const originalLabel = label?.textContent || 'Run Market Scan';
        if (button?.disabled) return;

        // V14.8.16: the scan follows the timeframe currently selected in the
        // scanner filter. "All" is one protected multi-timeframe run (15M+4H).
        const runTimeframe = scanRunForm.querySelector('[data-scan-run-timeframe]');
        const selectedTimeframe = document.querySelector('[data-scan-filters] select[name="timeframe"]');
        if (runTimeframe && selectedTimeframe) runTimeframe.value = selectedTimeframe.value || 'all';

        if (button) button.disabled = true;
        if (label) label.textContent = 'Scanning Markets…';
        if (status) { status.hidden = false; status.className = 'pp-async-status working'; status.textContent = 'Pulse is scanning your selected markets. You can stay on this page.'; }

        try {
            const response = await fetch(scanRunForm.action, {
                method: 'POST',
                body: new FormData(scanRunForm),
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                credentials: 'same-origin',
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.message || 'The market scan could not be completed.');

            const refreshUrl = new URL(scanRunForm.dataset.refreshUrl, window.location.origin);
            const currentQuery = new URLSearchParams(window.location.search);
            currentQuery.forEach((value, key) => refreshUrl.searchParams.set(key, value));
            const refreshResponse = await fetch(refreshUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            const refreshed = await refreshResponse.json().catch(() => ({}));
            if (!refreshResponse.ok) throw new Error(refreshed.message || 'The scan completed, but the results panel could not refresh.');

            replaceScanFragment('metrics', refreshed.metrics_html);
            replaceScanFragment('results', refreshed.results_html);
            replaceScanFragment('bottom', refreshed.bottom_html);
            bindSavedScanControls();
            document.querySelectorAll('[data-scanner-last-scan]').forEach(node => { node.textContent = refreshed.last_scan || 'just now'; });
            if (status) { status.className = 'pp-async-status success'; status.textContent = payload.message || 'Market scan completed and results updated.'; }
        } catch (error) {
            if (status) { status.hidden = false; status.className = 'pp-async-status error'; status.textContent = error.message || 'The market scan could not be completed.'; }
        } finally {
            if (button) button.disabled = false;
            if (label) label.textContent = originalLabel;
        }
    });

    document.querySelectorAll('[data-copy-value]').forEach(button => {
        button.addEventListener('click', async () => {
            const value = button.getAttribute('data-copy-value') || '';
            if (!value) return;
            const original = button.textContent;
            try {
                await navigator.clipboard.writeText(value);
                button.textContent = 'Copied';
                window.setTimeout(() => { button.textContent = original; }, 1400);
            } catch (error) {
                button.textContent = 'Copy manually';
                window.setTimeout(() => { button.textContent = original; }, 1600);
            }
        });
    });

    const ticket = document.querySelector('[data-order-ticket]');
    if (ticket) {
        const number = name => Number(ticket.elements.namedItem(name)?.value || 0);
        const currency = value => new Intl.NumberFormat('en-US', {style: 'currency', currency: 'USD'}).format(Number.isFinite(value) ? value : 0);
        const setText = (name, value) => {
            const element = document.querySelector(`[data-calculation="${name}"]`);
            if (element) element.textContent = value;
        };
        const calculate = () => {
            const price = number('price');
            const quantity = number('quantity');
            const leverage = Math.max(1, number('leverage'));
            const stop = number('stop_loss');
            const target = number('take_profit');
            const equity = Number(ticket.dataset.accountEquity || 0);
            const notional = price * quantity;
            const margin = notional / leverage;
            const risk = Math.abs(price - stop) * quantity;
            const reward = Math.abs(target - price) * quantity;
            const ratio = risk > 0 ? reward / risk : 0;
            setText('notional', currency(notional));
            setText('margin', currency(margin));
            setText('risk', currency(risk));
            setText('reward', currency(reward));
            setText('ratio', `1:${ratio.toFixed(2)}`);
            setText('risk-percent', `${equity > 0 ? ((risk / equity) * 100).toFixed(2) : '0.00'}%`);
        };
        ticket.querySelector('[data-ticket-calculate]')?.addEventListener('click', calculate);
        ticket.querySelectorAll('input,select').forEach(field => field.addEventListener('change', calculate));
        ticket.querySelector('[data-ticket-reset]')?.addEventListener('click', () => {
            ticket.reset();
            window.setTimeout(calculate, 0);
        });
        document.querySelector(`[data-ticket-draft][form="${ticket.id}"]`)?.addEventListener('click', () => {
            try {
                const draft = Object.fromEntries(new FormData(ticket).entries());
                delete draft._token;
                window.localStorage.setItem('abs.pulse.order.draft.v1', JSON.stringify(draft));
                const note = document.querySelector('[data-draft-status]');
                if (note) note.textContent = 'Draft saved in this browser. No order has been sent to Binance.';
            } catch (error) {
                const note = document.querySelector('[data-draft-status]');
                if (note) note.textContent = 'The browser could not save this draft.';
            }
        });
    }
});
