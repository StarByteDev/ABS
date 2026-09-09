(() => {
    'use strict';

    const NS = 'http://www.w3.org/2000/svg';
    const svgNode = (name, attrs = {}) => {
        const node = document.createElementNS(NS, name);
        Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, String(value)));
        return node;
    };
    const number = (value) => Number.isFinite(Number(value)) ? Number(value) : 0;
    const compact = (value) => new Intl.NumberFormat('en', { notation: Math.abs(value) >= 1000 ? 'compact' : 'standard', maximumFractionDigits: 1 }).format(value);
    const readSource = (element) => {
        const source = document.querySelector(element.dataset.chartSource || '');
        if (!source) return null;
        try { return JSON.parse(source.textContent); } catch (_) { return null; }
    };

    function emptyChart(host, message = 'No data is available for this reporting period.') {
        host.innerHTML = '';
        const empty = document.createElement('div');
        empty.className = 'admin-chart-empty';
        empty.innerHTML = `<span aria-hidden="true">⌁</span><p>${message}</p>`;
        host.appendChild(empty);
    }

    function lineChart(host, data) {
        const labels = data.labels || [];
        const series = (data.series || []).filter(item => Array.isArray(item.values));
        if (!labels.length || !series.length || !series.some(item => item.values.some(value => number(value) !== 0))) {
            emptyChart(host);
            return;
        }

        const terminalCompact = Boolean(host.closest('.institutional-main-grid'));
        const height = terminalCompact ? 220 : 286;
        const width = terminalCompact ? Math.max(760, Math.round((host.clientWidth || 900) * height / 198)) : 760;
        const left = 48, right = 18, top = 20, bottom = 42;
        const plotWidth = width - left - right, plotHeight = height - top - bottom;
        const all = series.flatMap(item => item.values.map(number));
        const max = Math.max(1, ...all);
        const svg = svgNode('svg', { viewBox: `0 0 ${width} ${height}`, role: 'img', 'aria-label': data.ariaLabel || 'Reporting trend chart', preserveAspectRatio: 'xMidYMid meet' });

        for (let i = 0; i <= 4; i++) {
            const y = top + (plotHeight * i / 4);
            svg.appendChild(svgNode('line', { x1: left, y1: y, x2: width - right, y2: y, class: 'chart-grid-line' }));
            const value = svgNode('text', { x: left - 10, y: y + 4, 'text-anchor': 'end', class: 'chart-axis-label' });
            value.textContent = compact(max * (1 - i / 4));
            svg.appendChild(value);
        }

        const xAt = (index) => left + (labels.length === 1 ? plotWidth / 2 : plotWidth * index / (labels.length - 1));
        const yAt = (value) => top + plotHeight - (number(value) / max) * plotHeight;
        const step = Math.max(1, Math.ceil(labels.length / 6));
        labels.forEach((label, index) => {
            if (index % step !== 0 && index !== labels.length - 1) return;
            const text = svgNode('text', { x: xAt(index), y: height - 15, 'text-anchor': index === 0 ? 'start' : (index === labels.length - 1 ? 'end' : 'middle'), class: 'chart-axis-label' });
            text.textContent = label;
            svg.appendChild(text);
        });

        series.forEach((item, seriesIndex) => {
            const points = labels.map((_, index) => [xAt(index), yAt(item.values[index] || 0)]);
            if (item.render === 'bar') {
                const barWidth = Math.max(4, Math.min(terminalCompact ? 14 : 18, plotWidth / Math.max(1, labels.length) * .54));
                points.forEach(([x, y], index) => {
                    const rect = svgNode('rect', { x: x - barWidth / 2, y, width: barWidth, height: Math.max(0, top + plotHeight - y), rx: 2, fill: item.color || '#3b91ed', opacity: .68, tabindex: 0, class: 'chart-bar' });
                    const title = svgNode('title');
                    title.textContent = `${labels[index]} · ${item.name}: ${compact(number(item.values[index]))}`;
                    rect.appendChild(title);
                    svg.appendChild(rect);
                });
                return;
            }
            const path = points.map(([x, y], index) => `${index ? 'L' : 'M'}${x.toFixed(2)},${y.toFixed(2)}`).join(' ');
            if (seriesIndex === 0 && item.fill !== false) {
                const area = `${path} L${points.at(-1)[0]},${top + plotHeight} L${points[0][0]},${top + plotHeight} Z`;
                svg.appendChild(svgNode('path', { d: area, fill: item.fillColor || `${item.color || '#2d8cff'}18`, class: 'chart-area' }));
            }
            svg.appendChild(svgNode('path', { d: path, fill: 'none', stroke: item.color || '#2d8cff', 'stroke-width': seriesIndex === 0 ? 3 : 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', class: 'chart-line' }));
            points.forEach(([x, y], index) => {
                const point = svgNode('circle', { cx: x, cy: y, r: 4, fill: item.color || '#2d8cff', tabindex: 0, class: 'chart-point' });
                const title = svgNode('title');
                title.textContent = `${labels[index]} · ${item.name}: ${compact(number(item.values[index]))}`;
                point.appendChild(title);
                svg.appendChild(point);
            });
        });
        host.replaceChildren(svg);
    }

    function barChart(host, data) {
        const labels = data.labels || [];
        const series = (data.series || []).filter(item => Array.isArray(item.values));
        if (!labels.length || !series.length || !series.some(item => item.values.some(value => number(value) !== 0))) {
            emptyChart(host);
            return;
        }
        const width = 760, height = 286, left = 48, right = 18, top = 20, bottom = 48;
        const plotWidth = width - left - right, plotHeight = height - top - bottom;
        const totals = labels.map((_, index) => series.reduce((sum, item) => sum + number(item.values[index]), 0));
        const max = Math.max(1, ...totals);
        const svg = svgNode('svg', { viewBox: `0 0 ${width} ${height}`, role: 'img', 'aria-label': data.ariaLabel || 'Reporting bar chart' });
        for (let i = 0; i <= 4; i++) {
            const y = top + (plotHeight * i / 4);
            svg.appendChild(svgNode('line', { x1: left, y1: y, x2: width - right, y2: y, class: 'chart-grid-line' }));
        }
        const slot = plotWidth / labels.length;
        const barWidth = Math.min(42, Math.max(8, slot * .56));
        const labelStep = Math.max(1, Math.ceil(labels.length / 6));
        labels.forEach((label, index) => {
            let offset = 0;
            series.forEach(item => {
                const value = number(item.values[index]);
                const barHeight = value / max * plotHeight;
                const y = top + plotHeight - offset - barHeight;
                const rect = svgNode('rect', { x: left + slot * index + (slot - barWidth) / 2, y, width: barWidth, height: Math.max(0, barHeight), rx: 4, fill: item.color || '#2d8cff', tabindex: 0, class: 'chart-bar' });
                const title = svgNode('title');
                title.textContent = `${label} · ${item.name}: ${compact(value)}`;
                rect.appendChild(title);
                svg.appendChild(rect);
                offset += barHeight;
            });
            if (index % labelStep === 0 || index === labels.length - 1) {
                const text = svgNode('text', { x: left + slot * index + slot / 2, y: height - 18, 'text-anchor': index === 0 ? 'start' : (index === labels.length - 1 ? 'end' : 'middle'), class: 'chart-axis-label' });
                text.textContent = label;
                svg.appendChild(text);
            }
        });
        host.replaceChildren(svg);
    }

    function donutChart(host, data) {
        const labels = data.labels || [];
        const values = (data.values || []).map(number);
        const colors = data.colors || ['#28b882', '#ef5d67', '#f2b84b', '#8292a8', '#4e8cff'];
        const total = values.reduce((sum, value) => sum + value, 0);
        if (!labels.length || total <= 0) {
            emptyChart(host);
            return;
        }
        const wrap = document.createElement('div');
        wrap.className = 'admin-donut-layout';
        const svg = svgNode('svg', { viewBox: '0 0 220 220', role: 'img', 'aria-label': data.ariaLabel || 'Outcome distribution chart' });
        const radius = 72, circumference = 2 * Math.PI * radius;
        let progress = 0;
        values.forEach((value, index) => {
            const circle = svgNode('circle', { cx: 110, cy: 110, r: radius, fill: 'none', stroke: colors[index % colors.length], 'stroke-width': 24, 'stroke-dasharray': `${(value / total) * circumference} ${circumference}`, 'stroke-dashoffset': -progress * circumference, transform: 'rotate(-90 110 110)', class: 'chart-donut-segment', tabindex: 0 });
            const title = svgNode('title');
            title.textContent = `${labels[index]}: ${compact(value)} (${((value / total) * 100).toFixed(1)}%)`;
            circle.appendChild(title);
            svg.appendChild(circle);
            progress += value / total;
        });
        const totalText = svgNode('text', { x: 110, y: 106, 'text-anchor': 'middle', class: 'chart-donut-total' });
        totalText.textContent = data.centerValue ?? compact(total);
        svg.appendChild(totalText);
        const caption = svgNode('text', { x: 110, y: 128, 'text-anchor': 'middle', class: 'chart-donut-caption' });
        caption.textContent = data.centerLabel || 'TOTAL';
        svg.appendChild(caption);

        const legend = document.createElement('div');
        legend.className = 'admin-chart-legend vertical';
        labels.forEach((label, index) => {
            const item = document.createElement('div');
            const percentage = total > 0 ? `${((values[index] / total) * 100).toFixed(1)}%` : '0.0%';
            item.innerHTML = `<i style="--legend:${colors[index % colors.length]}"></i><span>${label}</span><b>${compact(values[index])}</b><small>${percentage}</small>`;
            legend.appendChild(item);
        });
        wrap.append(svg, legend);
        host.replaceChildren(wrap);
    }

    function renderChart(host) {
        const data = readSource(host);
        if (!data) { emptyChart(host); return; }
        const type = host.dataset.chartType || data.type || 'line';
        if (type === 'donut') donutChart(host, data);
        else if (type === 'bar') barChart(host, data);
        else lineChart(host, data);
    }

    function initCharts() {
        document.querySelectorAll('[data-admin-chart]').forEach(renderChart);
    }

    document.addEventListener('DOMContentLoaded', () => {
        initCharts();
        document.querySelectorAll('[data-table-density]').forEach(button => {
            button.addEventListener('click', () => {
                const target = document.querySelector(button.dataset.tableDensity);
                if (!target) return;
                target.classList.toggle('is-compact');
                button.setAttribute('aria-pressed', String(target.classList.contains('is-compact')));
            });
        });
    });
})();
