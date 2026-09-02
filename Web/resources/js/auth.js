(() => {
    'use strict';

    const togglePassword = (button) => {
        const input = document.getElementById(button.dataset.passwordToggle || '');
        if (!input) return;

        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.setAttribute('aria-pressed', showing ? 'false' : 'true');
        button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        input.focus({preventScroll: true});
    };

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => togglePassword(button));
    });

    const country = document.getElementById('country');
    const countryCode = document.getElementById('country_code');
    if (country && countryCode) {
        let lastSuggestedCode = '';
        const applyCountryCode = () => {
            const selected = country.options[country.selectedIndex];
            const suggestedCode = selected?.dataset?.dialCode || '';
            if (!suggestedCode) return;
            if (!countryCode.value.trim() || countryCode.value.trim() === lastSuggestedCode) {
                countryCode.value = suggestedCode;
                lastSuggestedCode = suggestedCode;
            }
        };
        country.addEventListener('change', applyCountryCode);
        if (!countryCode.value.trim()) applyCountryCode();
    }

    document.querySelectorAll('.abs-auth-page form').forEach((form) => {
        form.addEventListener('submit', () => {
            const submit = form.querySelector('.auth-submit');
            if (!submit || !form.checkValidity()) return;
            submit.disabled = true;
            submit.setAttribute('aria-busy', 'true');
            const label = submit.querySelector('span') || submit;
            label.textContent = submit.dataset.busyLabel || 'Please wait…';
        });
    });

    const recoveryTabs = Array.from(document.querySelectorAll('[data-recovery-tab]'));
    const recoveryPanels = Array.from(document.querySelectorAll('[data-recovery-panel]'));
    if (recoveryTabs.length && recoveryPanels.length) {
        const activateRecoveryTab = (mode, moveFocus = false) => {
            recoveryTabs.forEach((tab) => {
                const selected = tab.dataset.recoveryTab === mode;
                tab.setAttribute('aria-selected', selected ? 'true' : 'false');
                tab.tabIndex = selected ? 0 : -1;
            });
            recoveryPanels.forEach((panel) => {
                const selected = panel.dataset.recoveryPanel === mode;
                panel.hidden = !selected;
                panel.querySelectorAll('input,button').forEach((control) => {
                    if (selected) control.removeAttribute('tabindex');
                    else control.setAttribute('tabindex', '-1');
                });
                if (selected && moveFocus) panel.querySelector('input:not([type="hidden"])')?.focus();
            });
        };

        recoveryTabs.forEach((tab, index) => {
            tab.addEventListener('click', () => activateRecoveryTab(tab.dataset.recoveryTab || 'password', true));
            tab.addEventListener('keydown', (event) => {
                if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault();
                let targetIndex = index;
                if (event.key === 'ArrowLeft') targetIndex = (index - 1 + recoveryTabs.length) % recoveryTabs.length;
                if (event.key === 'ArrowRight') targetIndex = (index + 1) % recoveryTabs.length;
                if (event.key === 'Home') targetIndex = 0;
                if (event.key === 'End') targetIndex = recoveryTabs.length - 1;
                const target = recoveryTabs[targetIndex];
                activateRecoveryTab(target.dataset.recoveryTab || 'password');
                target.focus();
            });
        });

        const selectedTab = recoveryTabs.find((tab) => tab.getAttribute('aria-selected') === 'true') || recoveryTabs[0];
        activateRecoveryTab(selectedTab.dataset.recoveryTab || 'password');
    }

    const canvas = document.querySelector('[data-auth-market-canvas]');
    if (!canvas) return;

    const context = canvas.getContext('2d');
    if (!context) return;

    const seeded = (seed) => {
        let value = seed >>> 0;
        return () => {
            value = (value * 1664525 + 1013904223) >>> 0;
            return value / 4294967296;
        };
    };

    const draw = () => {
        const rect = canvas.getBoundingClientRect();
        const scale = Math.min(window.devicePixelRatio || 1, 2);
        const width = Math.max(1, Math.round(rect.width));
        const height = Math.max(1, Math.round(rect.height));

        canvas.width = Math.round(width * scale);
        canvas.height = Math.round(height * scale);
        context.setTransform(scale, 0, 0, scale, 0, 0);
        context.clearRect(0, 0, width, height);

        const gradient = context.createLinearGradient(0, 0, width, height);
        gradient.addColorStop(0, '#031022');
        gradient.addColorStop(.55, '#031831');
        gradient.addColorStop(1, '#010813');
        context.fillStyle = gradient;
        context.fillRect(0, 0, width, height);

        context.lineWidth = 1;
        for (let x = 0; x < width; x += 42) {
            context.strokeStyle = x % 168 === 0 ? 'rgba(38,156,220,.16)' : 'rgba(38,135,204,.07)';
            context.beginPath();
            context.moveTo(x + .5, 0);
            context.lineTo(x + .5, height);
            context.stroke();
        }
        for (let y = 0; y < height; y += 42) {
            context.strokeStyle = y % 168 === 0 ? 'rgba(38,156,220,.14)' : 'rgba(38,135,204,.065)';
            context.beginPath();
            context.moveTo(0, y + .5);
            context.lineTo(width, y + .5);
            context.stroke();
        }

        const random = seeded(20260810);
        const clusters = [
            [width * .34, height * .23, width * .21, height * .13],
            [width * .56, height * .32, width * .26, height * .15],
            [width * .45, height * .47, width * .13, height * .18],
            [width * .72, height * .52, width * .18, height * .11],
            [width * .20, height * .42, width * .10, height * .14],
        ];
        context.fillStyle = 'rgba(0,170,255,.48)';
        clusters.forEach(([cx, cy, rx, ry], clusterIndex) => {
            for (let i = 0; i < 180; i += 1) {
                const angle = random() * Math.PI * 2;
                const radius = Math.sqrt(random());
                const drift = Math.sin(angle * (clusterIndex + 2)) * .13;
                const x = cx + Math.cos(angle) * rx * (radius + drift);
                const y = cy + Math.sin(angle) * ry * radius;
                if (x < 0 || y < 0 || x > width || y > height) continue;
                context.globalAlpha = .18 + random() * .65;
                context.fillRect(Math.round(x), Math.round(y), 2, 2);
            }
        });
        context.globalAlpha = 1;

        const points = [
            [0, .80], [.08, .74], [.16, .77], [.24, .64], [.32, .68], [.40, .53], [.48, .58],
            [.56, .42], [.64, .49], [.72, .28], [.79, .39], [.86, .17], [.93, .25], [1, .10],
        ];
        const chartLeft = width * .05;
        const chartTop = height * .30;
        const chartWidth = width * .94;
        const chartHeight = height * .58;

        const glowLine = (color, blur, lineWidth) => {
            context.save();
            context.strokeStyle = color;
            context.lineWidth = lineWidth;
            context.shadowColor = color;
            context.shadowBlur = blur;
            context.beginPath();
            points.forEach(([px, py], index) => {
                const x = chartLeft + px * chartWidth;
                const y = chartTop + py * chartHeight;
                if (index === 0) context.moveTo(x, y);
                else context.lineTo(x, y);
            });
            context.stroke();
            context.restore();
        };
        glowLine('rgba(0,209,255,.25)', 18, 7);
        glowLine('#21d6ff', 8, 1.7);

        const goldPoints = points.map(([px, py], index) => [px, Math.min(.96, py + .07 + Math.sin(index * .9) * .05)]);
        context.save();
        context.strokeStyle = 'rgba(245,181,50,.7)';
        context.lineWidth = 1;
        context.shadowColor = '#f5b532';
        context.shadowBlur = 8;
        context.beginPath();
        goldPoints.forEach(([px, py], index) => {
            const x = chartLeft + px * chartWidth;
            const y = chartTop + py * chartHeight;
            if (index === 0) context.moveTo(x, y);
            else context.lineTo(x, y);
        });
        context.stroke();
        context.restore();

        goldPoints.forEach(([px, py], index) => {
            if (index % 2 !== 0) return;
            const x = chartLeft + px * chartWidth;
            const y = chartTop + py * chartHeight;
            context.fillStyle = '#ffc552';
            context.shadowColor = '#f5b532';
            context.shadowBlur = 11;
            context.beginPath();
            context.arc(x, y, 2.1, 0, Math.PI * 2);
            context.fill();
            context.shadowBlur = 0;
        });

        const candleCount = 23;
        const candleStart = width * .56;
        const candleAreaWidth = width * .40;
        for (let i = 0; i < candleCount; i += 1) {
            const t = i / (candleCount - 1);
            const x = candleStart + t * candleAreaWidth;
            const center = height * (.66 - t * .42 + Math.sin(i * 1.7) * .035);
            const bodyHeight = 15 + random() * 32;
            const rising = random() > .31;
            const color = rising ? '#11d8ff' : '#f6b537';
            context.strokeStyle = color;
            context.fillStyle = rising ? 'rgba(0,188,255,.82)' : 'rgba(245,181,50,.76)';
            context.shadowColor = color;
            context.shadowBlur = 8;
            context.beginPath();
            context.moveTo(x, center - bodyHeight * .95);
            context.lineTo(x, center + bodyHeight * .95);
            context.stroke();
            context.fillRect(x - 3.4, center - bodyHeight / 2, 6.8, bodyHeight);
            context.shadowBlur = 0;
        }

        const marketPanel = (x, y, panelWidth, panelHeight, rows) => {
            const panelGradient = context.createLinearGradient(x, y, x + panelWidth, y + panelHeight);
            panelGradient.addColorStop(0, 'rgba(3,26,50,.82)');
            panelGradient.addColorStop(1, 'rgba(1,12,29,.9)');
            context.fillStyle = panelGradient;
            context.strokeStyle = 'rgba(35,148,213,.28)';
            context.lineWidth = 1;
            context.fillRect(x, y, panelWidth, panelHeight);
            context.strokeRect(x + .5, y + .5, panelWidth - 1, panelHeight - 1);
            context.font = '9px ui-monospace, SFMono-Regular, Menlo, monospace';
            context.fillStyle = 'rgba(65,205,255,.84)';
            context.fillText('GLOBAL MARKETS', x + 10, y + 18);
            rows.forEach(([label, value, positive], rowIndex) => {
                const rowY = y + 42 + rowIndex * 25;
                context.fillStyle = 'rgba(111,202,240,.78)';
                context.fillText(label, x + 10, rowY);
                context.fillStyle = positive ? 'rgba(20,220,255,.9)' : 'rgba(245,181,50,.82)';
                context.fillText(value, x + panelWidth - 54, rowY);
            });
        };
        if (width > 650 && height > 500) {
            marketPanel(width * .71, height * .62, Math.min(130, width * .15), 172, [
                ['NASDAQ', '+1.23%', true],
                ['S&P 500', '+0.65%', true],
                ['DOW JONES', '+0.64%', true],
                ['GOLD', '+0.41%', true],
                ['OIL (WTI)', '-0.12%', false],
            ]);
        }

        context.font = '11px ui-monospace, SFMono-Regular, Menlo, monospace';
        ['+1.23%','+0.57%','+2.34%','-0.89%','+1.01%'].forEach((label, index) => {
            context.fillStyle = index === 3 ? 'rgba(245,181,50,.62)' : 'rgba(0,217,255,.68)';
            context.fillText(label, 18, 100 + index * 35);
        });
    };

    let resizeFrame = null;
    const scheduleDraw = () => {
        if (resizeFrame) cancelAnimationFrame(resizeFrame);
        resizeFrame = requestAnimationFrame(draw);
    };

    draw();
    window.addEventListener('resize', scheduleDraw, {passive: true});
    if ('ResizeObserver' in window) new ResizeObserver(scheduleDraw).observe(canvas);
})();
