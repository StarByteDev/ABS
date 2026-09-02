(() => {
    'use strict';

    const API = '/api/v1';
    const BINANCE_HOSTS = [
        'https://data-api.binance.vision',
        'https://api.binance.com',
        'https://api1.binance.com',
    ];
    const CORE_SYMBOLS = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'BNBUSDT', 'XRPUSDT', 'ADAUSDT'];
    const COINGECKO_API = 'https://api.coingecko.com/api/v3';
    const FEAR_GREED_API = 'https://api.alternative.me';
    const LIQUIDATION_API = 'https://xoomar.com';
    const OKX_API = 'https://www.okx.com';
    const EXCLUDED_MOVER_SYMBOLS = new Set(['USDCUSDT', 'FDUSDUSDT', 'TUSDUSDT', 'USDPUSDT', 'DAIUSDT', 'EURUSDT']);

    const numeric = (value) => value !== null && value !== undefined && value !== '' && value !== false && Number.isFinite(Number(value));
    const money = (value, decimals = 2) => numeric(value)
        ? new Intl.NumberFormat('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(Number(value))
        : '—';

    const compactUsd = (value) => {
        if (!numeric(value)) return '—';
        const amount = Number(value);
        const abs = Math.abs(amount);
        if (abs >= 1e12) return `$${money(amount / 1e12)}T`;
        if (abs >= 1e9) return `$${money(amount / 1e9)}B`;
        if (abs >= 1e6) return `$${money(amount / 1e6, 1)}M`;
        return `$${money(amount, 0)}`;
    };

    const fetchJson = async (url, options = {}, timeout = 10000) => {
        const controller = new AbortController();
        const timer = window.setTimeout(() => controller.abort(), timeout);
        try {
            const response = await fetch(url, {
                ...options,
                headers: { Accept: 'application/json', ...(options.headers || {}) },
                signal: controller.signal,
                cache: 'no-store',
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return await response.json();
        } finally {
            window.clearTimeout(timer);
        }
    };

    const setSource = (element, source, unavailable = false) => {
        if (!element) return;
        const label = unavailable ? 'Unavailable' : (source || 'Live data');
        element.textContent = label;
        element.classList.toggle('unavailable', unavailable);
        element.title = unavailable
            ? 'Live market data could not be reached. No synthetic prices are shown.'
            : `Live market source: ${source}`;
    };

    const menuButton = document.querySelector('[data-menu-button]');
    const mainNav = document.querySelector('[data-main-nav]');
    menuButton?.addEventListener('click', () => {
        const isOpen = mainNav?.classList.toggle('open') || false;
        menuButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
    mainNav?.addEventListener('click', (event) => {
        if (!event.target.closest('a')) return;
        mainNav.classList.remove('open');
        menuButton?.setAttribute('aria-expanded', 'false');
    });

    const normalizeTicker = (row) => {
        const symbol = String(row.symbol || '');
        const base = symbol.endsWith('USDT') ? symbol.slice(0, -4) : symbol;
        return {
            symbol,
            base,
            pair: `${base}/USDT`,
            price: numeric(row.lastPrice ?? row.price) ? Number(row.lastPrice ?? row.price) : null,
            change_percent: numeric(row.priceChangePercent ?? row.change_percent) ? Number(row.priceChangePercent ?? row.change_percent) : null,
            volume: numeric(row.quoteVolume ?? row.volume) ? Number(row.quoteVolume ?? row.volume) : null,
            high: numeric(row.highPrice ?? row.high) ? Number(row.highPrice ?? row.high) : null,
            low: numeric(row.lowPrice ?? row.low) ? Number(row.lowPrice ?? row.low) : null,
        };
    };

    const directBinanceJson = async (path) => {
        const errors = [];
        for (const host of BINANCE_HOSTS) {
            try {
                return await fetchJson(`${host}${path}`, {}, 10000);
            } catch (error) {
                errors.push(`${host}: ${error.message}`);
            }
        }
        throw new Error(`All browser Binance market hosts failed. ${errors.join(' | ')}`);
    };

    const directCoreOverview = async () => {
        const symbols = encodeURIComponent(JSON.stringify(CORE_SYMBOLS));
        const rows = await directBinanceJson(`/api/v3/ticker/24hr?symbols=${symbols}`);
        return Array.isArray(rows) ? rows.map(normalizeTicker) : [];
    };

    const isExcludedMover = (symbol) => EXCLUDED_MOVER_SYMBOLS.has(symbol)
        || ['UPUSDT', 'DOWNUSDT', 'BULLUSDT', 'BEARUSDT'].some((suffix) => symbol.endsWith(suffix));

    const directMovers = async (limit = 5) => {
        const rows = await directBinanceJson('/api/v3/ticker/24hr');
        if (!Array.isArray(rows)) return { gainers: [], losers: [] };
        const normalized = rows
            .filter((row) => String(row.symbol || '').endsWith('USDT'))
            .filter((row) => !isExcludedMover(String(row.symbol || '')))
            .filter((row) => Number(row.quoteVolume || 0) >= 5000000)
            .map(normalizeTicker)
            .filter((row) => numeric(row.change_percent) && numeric(row.price));
        return {
            gainers: normalized.filter((row) => Number(row.change_percent) > 0).sort((a, b) => b.change_percent - a.change_percent).slice(0, limit),
            losers: normalized.filter((row) => Number(row.change_percent) < 0).sort((a, b) => a.change_percent - b.change_percent).slice(0, limit),
        };
    };


    const directCoinGeckoMovers = async (limit = 5) => {
        const rows = await fetchJson(`${COINGECKO_API}/coins/markets?vs_currency=usd&order=market_cap_desc&per_page=100&page=1&sparkline=false&price_change_percentage=24h`, {}, 9000);
        if (!Array.isArray(rows)) return { gainers: [], losers: [] };
        const normalized = rows
            .filter((row) => numeric(row.current_price) && numeric(row.price_change_percentage_24h) && Number(row.total_volume || 0) >= 5000000)
            .map((row) => ({
                symbol: `${String(row.symbol || '').toUpperCase()}USDT`,
                base: String(row.symbol || '').toUpperCase(),
                pair: `${String(row.symbol || '').toUpperCase()}/USDT`,
                price: Number(row.current_price),
                change_percent: Number(row.price_change_percentage_24h),
                volume: numeric(row.total_volume) ? Number(row.total_volume) : null,
                high: numeric(row.high_24h) ? Number(row.high_24h) : null,
                low: numeric(row.low_24h) ? Number(row.low_24h) : null,
            }));
        return {
            gainers: normalized.filter((row) => row.change_percent > 0).sort((a, b) => b.change_percent - a.change_percent).slice(0, limit),
            losers: normalized.filter((row) => row.change_percent < 0).sort((a, b) => a.change_percent - b.change_percent).slice(0, limit),
        };
    };

    const deriveSentiment = (core) => {
        const changes = (Array.isArray(core) ? core : []).map((coin) => Number(coin.change_percent)).filter(Number.isFinite);
        if (!changes.length) return null;
        const average = changes.reduce((sum, value) => sum + value, 0) / changes.length;
        const score = Math.round(Math.min(95, Math.max(5, 50 + (average * 4))));
        let bullish; let bearish; let neutral;
        if (score >= 50) {
            bullish = score;
            bearish = Math.round((100 - score) * 0.35);
            neutral = Math.max(0, 100 - bullish - bearish);
        } else {
            bearish = 100 - score;
            bullish = Math.round(score * 0.35);
            neutral = Math.max(0, 100 - bullish - bearish);
        }
        return { score, label: score >= 65 ? 'Bullish' : (score <= 35 ? 'Defensive' : 'Neutral'), bullish, neutral, bearish };
    };

    const derivePulse = (core, global = {}, sentiment = null) => {
        const changes = (Array.isArray(core) ? core : []).map((coin) => Number(coin.change_percent)).filter(Number.isFinite);
        if (!changes.length && !numeric(sentiment?.score)) return null;
        const sentimentScore = numeric(sentiment?.score) ? Number(sentiment.score) : 50;
        const breadthScore = changes.length ? (changes.filter((value) => value > 0).length / changes.length) * 100 : 50;
        const btc = (Array.isArray(core) ? core : []).find((coin) => coin.symbol === 'BTCUSDT');
        const btcMomentum = numeric(btc?.change_percent) ? Math.min(100, Math.max(0, 50 + (Number(btc.change_percent) * 5))) : 50;
        const marketMomentum = numeric(global?.market_cap_change_24h) ? Math.min(100, Math.max(0, 50 + (Number(global.market_cap_change_24h) * 7))) : 50;
        const score = Math.round(Math.min(95, Math.max(5, (sentimentScore * 0.35) + (breadthScore * 0.30) + (marketMomentum * 0.20) + (btcMomentum * 0.15))));
        return { score, label: score >= 65 ? 'Bullish' : (score <= 35 ? 'Defensive' : 'Neutral') };
    };

    const directGlobalSnapshot = async () => {
        const payload = await fetchJson(`${COINGECKO_API}/global`, {}, 8500);
        const data = payload?.data || {};
        return {
            total_market_cap: numeric(data?.total_market_cap?.usd) ? Number(data.total_market_cap.usd) : null,
            total_volume: numeric(data?.total_volume?.usd) ? Number(data.total_volume.usd) : null,
            btc_dominance: numeric(data?.market_cap_percentage?.btc) ? Number(data.market_cap_percentage.btc) : null,
            market_cap_change_24h: numeric(data?.market_cap_change_percentage_24h_usd) ? Number(data.market_cap_change_percentage_24h_usd) : null,
        };
    };

    const directFearGreed = async () => {
        const payload = await fetchJson(`${FEAR_GREED_API}/fng/?limit=1&format=json`, {}, 7500);
        const row = payload?.data?.[0];
        if (!row || !numeric(row.value)) throw new Error('Fear & Greed response unavailable');
        return { fear_greed_score: Number(row.value), fear_greed_label: row.value_classification || 'Unknown' };
    };

    const directLiquidations = async () => {
        const payload = await fetchJson(`${LIQUIDATION_API}/api/markets/liquidations`, {}, 8000);
        const data = payload?.data || {};
        if (!numeric(data.longUsd) || !numeric(data.shortUsd)) throw new Error('Liquidation response unavailable');
        const long = Number(data.longUsd); const short = Number(data.shortUsd); const total = long + short;
        return {
            liquidation_long_24h_usd: long,
            liquidation_short_24h_usd: short,
            liquidation_24h_usd: numeric(data.totalUsd) ? Number(data.totalUsd) : total,
            liquidation_long_share_24h: total > 0 ? (long / total) * 100 : null,
            liquidation_short_share_24h: total > 0 ? (short / total) * 100 : null,
        };
    };

    const directOkxFutures = async () => {
        const results = await Promise.allSettled([
            fetchJson(`${OKX_API}/api/v5/public/open-interest?instType=SWAP&instId=BTC-USDT-SWAP`, {}, 8000),
            fetchJson(`${OKX_API}/api/v5/public/funding-rate?instId=BTC-USDT-SWAP`, {}, 8000),
            fetchJson(`${OKX_API}/api/v5/public/mark-price?instType=SWAP&instId=BTC-USDT-SWAP`, {}, 8000),
            fetchJson(`${OKX_API}/api/v5/rubik/stat/contracts/long-short-account-ratio?ccy=BTC&period=5m`, {}, 8000),
        ]);
        const value = (result) => result.status === 'fulfilled' ? result.value : null;
        const [openPayload, fundingPayload, markPayload, ratioPayload] = results.map(value);
        const open = openPayload?.data?.[0] || {};
        const funding = fundingPayload?.data?.[0] || {};
        const mark = markPayload?.data?.[0] || {};
        if (!openPayload && !fundingPayload && !markPayload && !ratioPayload) throw new Error('OKX futures endpoints unavailable');
        const markPrice = numeric(mark.markPx) ? Number(mark.markPx) : null;
        const openUsd = numeric(open.oiUsd) ? Number(open.oiUsd)
            : (numeric(open.oiCcy) && numeric(markPrice) ? Number(open.oiCcy) * Number(markPrice)
                : (numeric(open.oi) && numeric(markPrice) ? Number(open.oi) * Number(markPrice) : null));
        let ratio = null;
        const ratioRow = ratioPayload?.data?.[0];
        if (Array.isArray(ratioRow)) {
            for (let i = ratioRow.length - 1; i >= 0; i -= 1) {
                if (numeric(ratioRow[i])) { ratio = Number(ratioRow[i]); break; }
            }
        } else if (numeric(ratioRow?.ratio)) ratio = Number(ratioRow.ratio);
        if (!numeric(ratio) || Number(ratio) <= 0 || Number(ratio) > 100) ratio = null;
        return {
            open_interest_usd: numeric(openUsd) ? Number(openUsd) : null,
            funding_rate: numeric(funding.fundingRate) ? Number(funding.fundingRate) * 100 : null,
            long_short_ratio: ratio,
            perp_premium_basis: numeric(funding.premium) ? Number(funding.premium) * 100 : null,
        };
    };

    const directIndustries = async () => {
        const rows = await fetchJson(`${COINGECKO_API}/coins/categories?order=market_cap_desc`, {}, 10000);
        if (!Array.isArray(rows)) return [];
        const definitions = [
            ['defi', 'DeFi', ['decentralized-finance-defi', 'defi']],
            ['layer1', 'Layer 1', ['layer-1', 'layer 1']],
            ['infrastructure', 'Infrastructure', ['infrastructure', 'blockchain-infrastructure']],
            ['gaming', 'Gaming', ['gaming']],
            ['ai', 'AI & Big Data', ['artificial-intelligence', 'ai big data', 'artificial intelligence']],
            ['nft', 'NFT', ['non-fungible-tokens-nft', 'nft']],
            ['payments', 'Payments', ['payments', 'payment-solutions']],
            ['metaverse', 'Metaverse', ['metaverse']],
        ];
        return definitions.map(([key, label, patterns]) => {
            const row = rows.find((item) => {
                const haystack = `${String(item?.id || '').toLowerCase()} ${String(item?.name || '').toLowerCase()}`;
                return patterns.some((pattern) => haystack.includes(pattern));
            });
            return row ? { key, label, market_cap: numeric(row.market_cap) ? Number(row.market_cap) : null, change_24h: numeric(row.market_cap_change_24h) ? Number(row.market_cap_change_24h) : null } : null;
        }).filter(Boolean);
    };

    const renderMarketStrip = (coins) => {
        const strip = document.querySelector('[data-market-strip]');
        if (!strip || !Array.isArray(coins) || coins.length === 0) return;
        const order = new Map(CORE_SYMBOLS.map((symbol, index) => [symbol, index]));
        const ordered = [...coins].sort((a, b) => (order.get(a.symbol) ?? 99) - (order.get(b.symbol) ?? 99));
        const marketLink = strip.dataset.marketLink || '/markets';
        const stripCoins = ordered.filter((coin) => coin.symbol !== 'BTCUSDT').slice(0, 5);
        strip.innerHTML = stripCoins.map((coin) => {
            const decimals = Number(coin.price) < 1 ? 4 : 2;
            const positive = Number(coin.change_percent) >= 0;
            return `<div class="mini-market" data-market-symbol="${coin.symbol}">
                <span class="coin-icon coin-${coin.base.toLowerCase()}">${coin.base.slice(0, 1)}</span>
                <div><small>${coin.pair}</small><strong>${money(coin.price, decimals)}</strong><em class="${positive ? 'positive' : 'negative'}">${positive ? '+' : ''}${money(coin.change_percent)}%</em></div>
            </div>`;
        }).join('');

        const btc = ordered.find((coin) => coin.symbol === 'BTCUSDT');
        if (btc) {
            const decimals = Number(btc.price) < 1 ? 4 : 2;
            const setText = (selector, value) => {
                const node = document.querySelector(selector);
                if (node) node.textContent = value;
            };
            setText('[data-hero-market-price]', money(btc.price, decimals));
            setText('[data-hero-market-high]', money(btc.high, decimals));
            setText('[data-hero-market-low]', money(btc.low, decimals));
            setText('[data-hero-market-volume]', numeric(btc.volume) ? `${money(Number(btc.volume) / 1e9)}B USDT` : '—');
            const change = document.querySelector('[data-hero-market-change]');
            if (change) {
                change.textContent = `${btc.change_percent >= 0 ? '+' : ''}${money(btc.change_percent)}% (24h)`;
                change.className = btc.change_percent >= 0 ? 'positive' : 'negative';
            }
        }
    };

    const updatePulseMarketRow = (coins) => {
        const row = document.querySelector('[data-pulse-market-row]');
        if (!row || !Array.isArray(coins) || !coins.length) return;
        const order = new Map(CORE_SYMBOLS.map((symbol, index) => [symbol, index]));
        const ordered = [...coins].sort((a, b) => (order.get(a.symbol) ?? 99) - (order.get(b.symbol) ?? 99));
        row.innerHTML = ordered.slice(0, 5).map((coin) => {
            const decimals = Number(coin.price) < 1 ? 4 : 2;
            const change = Number(coin.change_percent);
            const positive = change >= 0;
            return `<div><small>${coin.symbol}</small><b>$${money(coin.price, decimals)}</b><em class="${positive ? 'positive' : 'negative'}">${positive ? '+' : ''}${money(change)}%</em></div>`;
        }).join('');
    };

    const updateCoreMarketTable = (coins) => {
        if (!Array.isArray(coins)) return;
        coins.forEach((coin) => {
            const row = document.querySelector(`[data-core-market-row="${coin.symbol}"]`);
            if (row) {
                const decimals = Number(coin.price) < 1 ? 4 : 2;
                const change = row.querySelector('[data-core-change]');
                const set = (selector, value) => { const node = row.querySelector(selector); if (node) node.textContent = value; };
                set('[data-core-price]', `$${money(coin.price, decimals)}`);
                set('[data-core-high]', `$${money(coin.high, decimals)}`);
                set('[data-core-low]', `$${money(coin.low, decimals)}`);
                set('[data-core-volume]', `$${money(Number(coin.volume || 0) / 1e6, 1)}M`);
                if (change) {
                    change.textContent = `${coin.change_percent >= 0 ? '+' : ''}${money(coin.change_percent)}%`;
                    change.className = coin.change_percent >= 0 ? 'positive' : 'negative';
                }
            }

            const performance = document.querySelector(`[data-performance-symbol="${coin.symbol}"]`);
            if (performance && numeric(coin.change_percent)) {
                const value = Number(coin.change_percent);
                const bar = performance.querySelector('i b');
                const label = performance.querySelector('em');
                if (bar) {
                    bar.style.width = `${Math.min(100, Math.max(12, Math.abs(value) * 9))}%`;
                    bar.className = value < 0 ? 'down' : '';
                }
                if (label) {
                    label.textContent = `${value >= 0 ? '+' : ''}${money(value)}%`;
                    label.className = value >= 0 ? 'positive' : 'negative';
                }
            }
        });
    };

    const updateIndustries = (industries) => {
        if (!Array.isArray(industries)) return;
        industries.forEach((industry) => {
            const card = document.querySelector(`[data-industry-key="${industry.key}"]`);
            if (!card) return;
            const cap = card.querySelector('[data-industry-cap]');
            const change = card.querySelector('[data-industry-change]');
            if (cap && numeric(industry.market_cap)) cap.textContent = compactUsd(industry.market_cap);
            if (change && numeric(industry.change_24h)) {
                const value = Number(industry.change_24h);
                change.textContent = `${value >= 0 ? '+' : ''}${money(value)}%`;
                change.className = value >= 0 ? 'positive' : 'negative';
            }
        });
    };

    const updateDailyInsights = (insights) => {
        if (!insights || typeof insights !== 'object') return;
        const set = (selector, value) => {
            if (value === undefined || value === null || value === '') return;
            document.querySelectorAll(selector).forEach((node) => { node.textContent = value; });
        };
        set('[data-market-bias]', insights.market_bias);
        set('[data-market-bias-detail]', insights.market_bias_detail);
        if (numeric(insights.stablecoin_flow_24h_usd)) {
            const amount = Number(insights.stablecoin_flow_24h_usd);
            set('[data-stablecoin-flow]', `${amount >= 0 ? '+' : '-'}${compactUsd(Math.abs(amount))}`);
        }
        if (numeric(insights.volatility_score)) set('[data-volatility-score]', money(insights.volatility_score, 1));
        set('[data-volatility-label]', insights.volatility_label);
        set('[data-key-trend]', insights.key_trend);
        set('[data-key-trend-detail]', insights.key_trend_detail);
        set('[data-daily-insight]', insights.daily_insight);
    };

    const updateGlobalMetrics = (global, sentiment, pulse = null, industries = null, insights = null) => {
        const setText = (selector, value) => {
            document.querySelectorAll(selector).forEach((node) => { node.textContent = value; });
        };
        const setChange = (selector, value, fallback = 'Live source') => {
            const nodes = document.querySelectorAll(selector);
            if (!nodes.length) return;
            if (!numeric(value)) {
                nodes.forEach((node) => { node.textContent = fallback; node.className = 'muted-value'; });
                return;
            }
            const number = Number(value);
            nodes.forEach((node) => {
                node.textContent = `${number >= 0 ? '+' : ''}${money(number)}%`;
                node.className = number >= 0 ? 'positive' : 'negative';
            });
        };

        if (numeric(global?.total_market_cap)) {
            const formatted = `$${money(global.total_market_cap / 1e12)}T`;
            setText('[data-market-cap]', formatted);
            setText('[data-market-cap-trend]', formatted);
        }
        if (numeric(global?.total_volume)) setText('[data-market-volume]', `$${money(global.total_volume / 1e9)}B`);
        setChange('[data-market-cap-change]', global?.market_cap_change_24h, 'Connecting live source');

        if (numeric(global?.btc_dominance)) setText('[data-btc-dominance]', `${money(global.btc_dominance, 1)}%`);
        const dominanceChange = document.querySelector('[data-btc-dominance-change]');
        if (dominanceChange) {
            if (numeric(global?.btc_dominance_change_24h)) {
                const value = Number(global.btc_dominance_change_24h);
                dominanceChange.textContent = `${value >= 0 ? '+' : ''}${money(value, 2)}% (24h)`;
                dominanceChange.className = value >= 0 ? 'positive' : 'negative';
            } else {
                dominanceChange.textContent = 'Live global share';
                dominanceChange.className = 'muted-value';
            }
        }

        if (numeric(global?.stablecoin_market_cap)) setText('[data-stablecoin-cap]', compactUsd(global.stablecoin_market_cap));
        setChange('[data-stablecoin-change]', global?.stablecoin_market_cap_change_24h, 'CoinGecko stablecoins');

        if (numeric(global?.liquidation_long_24h_usd)) setText('[data-liquidation-long]', compactUsd(global.liquidation_long_24h_usd));
        if (numeric(global?.liquidation_short_24h_usd)) setText('[data-liquidation-short]', compactUsd(global.liquidation_short_24h_usd));
        setText('[data-liquidation-long-share]', numeric(global?.liquidation_long_share_24h) ? `${money(global.liquidation_long_share_24h, 1)}% of 24H` : 'Aggregated live');
        setText('[data-liquidation-short-share]', numeric(global?.liquidation_short_share_24h) ? `${money(global.liquidation_short_share_24h, 1)}% of 24H` : 'Aggregated live');
        if (numeric(global?.open_interest_usd)) setText('[data-open-interest]', compactUsd(global.open_interest_usd));
        if (numeric(global?.funding_rate)) {
            const funding = Number(global.funding_rate);
            setText('[data-funding-rate]', `${money(funding, 3)}%`);
            setText('[data-funding-label]', funding >= 0 ? 'Positive' : 'Negative');
        }
        if (numeric(global?.long_short_ratio) && Number(global.long_short_ratio) > 0 && Number(global.long_short_ratio) <= 100) {
            const ratio = Number(global.long_short_ratio);
            setText('[data-long-short-ratio]', money(ratio, 2));
            setText('[data-long-short-label]', ratio >= 1 ? 'Long-heavy' : 'Short-heavy');
        }
        if (numeric(global?.perp_premium_basis)) {
            const basis = Number(global.perp_premium_basis);
            setText('[data-perp-premium-basis]', `${basis >= 0 ? '+' : ''}${money(basis, 3)}%`);
            setText('[data-perp-premium-label]', basis >= 0 ? 'Above index' : 'Below index');
        }
        if (numeric(global?.fear_greed_score)) {
            const score = Math.round(Number(global.fear_greed_score));
            setText('[data-fear-greed-score]', String(score));
            document.querySelectorAll('.fear-greed-meter i').forEach((node) => node.style.setProperty('--fear-score', String(score)));
        }
        if (global?.fear_greed_label) setText('[data-fear-greed-label]', global.fear_greed_label);

        if (numeric(sentiment?.score)) {
            const score = Number(sentiment.score);
            document.querySelectorAll('[data-sentiment-score]').forEach((node) => {
                node.textContent = String(score);
                node.closest('.gauge')?.classList.remove('gauge-unavailable');
                node.closest('.gauge')?.style.setProperty('--score', score);
            });
            document.querySelectorAll('[data-sentiment-label]').forEach((node) => { node.textContent = sentiment.label || 'Neutral'; });
            document.querySelectorAll('[data-sentiment-badge]').forEach((node) => { node.textContent = `${sentiment.label || 'Neutral'} Market`; });
            if (numeric(sentiment.bullish)) setText('[data-sentiment-bullish]', String(Math.round(Number(sentiment.bullish))));
            if (numeric(sentiment.neutral)) setText('[data-sentiment-neutral]', String(Math.round(Number(sentiment.neutral))));
            if (numeric(sentiment.bearish)) setText('[data-sentiment-bearish]', String(Math.round(Number(sentiment.bearish))));
        }

        let pulseScore = numeric(pulse?.score) ? Number(pulse.score) : null;
        let pulseLabel = pulse?.label || null;
        if (pulseScore === null && numeric(sentiment?.score)) {
            const marketMomentum = numeric(global?.market_cap_change_24h)
                ? Math.min(100, Math.max(0, 50 + (Number(global.market_cap_change_24h) * 7)))
                : 50;
            pulseScore = Math.round((Number(sentiment.score) * 0.7) + (marketMomentum * 0.3));
            pulseLabel = pulseScore >= 65 ? 'Bullish' : (pulseScore <= 35 ? 'Defensive' : 'Neutral');
        }
        if (numeric(pulseScore)) {
            setText('[data-pulse-score]', String(Math.round(Number(pulseScore))));
            setText('[data-pulse-label]', pulseLabel || 'Neutral');
        }
        updateIndustries(industries);
        updateDailyInsights(insights);
    };

    const loadOverview = async () => {
        const sourceBadge = document.querySelector('[data-market-source]');
        let liveCore = [];
        let liveGlobal = {};
        let liveSentiment = null;

        const serverPromise = fetchJson(`${API}/market/overview`, {}, 9000).then((payload) => payload.data || payload);
        const directCorePromise = directCoreOverview();

        const [serverResult, directCoreResult] = await Promise.allSettled([serverPromise, directCorePromise]);

        if (directCoreResult.status === 'fulfilled' && directCoreResult.value.length) {
            liveCore = directCoreResult.value;
        } else if (serverResult.status === 'fulfilled' && Array.isArray(serverResult.value.core)) {
            liveCore = serverResult.value.core.filter((coin) => numeric(coin.price));
        }

        if (liveCore.length) {
            renderMarketStrip(liveCore);
            updatePulseMarketRow(liveCore);
            updateCoreMarketTable(liveCore);
            liveSentiment = deriveSentiment(liveCore);
            updateGlobalMetrics({}, liveSentiment, derivePulse(liveCore, {}, liveSentiment));
            setSource(sourceBadge, directCoreResult.status === 'fulfilled' ? 'Binance live' : (serverResult.value?.source || 'Live market'), false);
        }

        if (serverResult.status === 'fulfilled') {
            const data = serverResult.value || {};
            liveGlobal = data.global || {};
            if (Array.isArray(data.core) && data.core.some((coin) => numeric(coin.price)) && !liveCore.length) {
                liveCore = data.core;
                renderMarketStrip(liveCore);
                updatePulseMarketRow(liveCore);
                updateCoreMarketTable(liveCore);
            }
            liveSentiment = data.sentiment && numeric(data.sentiment.score) ? data.sentiment : (liveSentiment || deriveSentiment(liveCore));
            updateGlobalMetrics(liveGlobal, liveSentiment, data.pulse || derivePulse(liveCore, liveGlobal, liveSentiment), data.industries, data.insights);
            if (data.source && data.source !== 'unavailable') setSource(sourceBadge, data.source, false);
        }

        // Browser-side public fallbacks are deliberately independent. HostGator may
        // reach CoinGecko while blocking an exchange host (or vice versa), so each
        // metric is allowed to recover without waiting for every provider.
        const supplemental = await Promise.allSettled([
            directGlobalSnapshot(),
            directFearGreed(),
            directLiquidations(),
            directOkxFutures(),
            directIndustries(),
        ]);
        const [globalResult, fearResult, liquidationResult, futuresResult, industriesResult] = supplemental;
        if (globalResult.status === 'fulfilled') liveGlobal = { ...liveGlobal, ...globalResult.value };
        if (fearResult.status === 'fulfilled') liveGlobal = { ...liveGlobal, ...fearResult.value };
        if (liquidationResult.status === 'fulfilled') liveGlobal = { ...liveGlobal, ...liquidationResult.value };
        if (futuresResult.status === 'fulfilled') liveGlobal = { ...liveGlobal, ...futuresResult.value };
        const directIndustryRows = industriesResult.status === 'fulfilled' ? industriesResult.value : null;
        liveSentiment = liveSentiment || deriveSentiment(liveCore);
        updateGlobalMetrics(liveGlobal, liveSentiment, derivePulse(liveCore, liveGlobal, liveSentiment), directIndustryRows, null);

        const hasAnyLive = liveCore.length || Object.values(liveGlobal).some((value) => numeric(value));
        if (!hasAnyLive) {
            setSource(sourceBadge, 'Unavailable', true);
            console.error('ABS live market overview unavailable', { serverResult, directCoreResult, supplemental });
        }
    };

    const directKlines = async (symbol, interval, limit) => {
        const rows = await directBinanceJson(`/api/v3/klines?symbol=${encodeURIComponent(symbol)}&interval=${encodeURIComponent(interval)}&limit=${limit}`);
        if (!Array.isArray(rows)) return [];
        return rows.map((row) => ({
            time: Math.floor(Number(row[0]) / 1000),
            open: Number(row[1]), high: Number(row[2]), low: Number(row[3]), close: Number(row[4]), volume: Number(row[5]),
        }));
    };

    const chartConfig = {
        '1h': { interval: '5m', limit: 12 },
        '1d': { interval: '15m', limit: 96 },
        '7d': { interval: '1h', limit: 168 },
        '1m': { interval: '4h', limit: 180 },
        '1y': { interval: '1d', limit: 365 },
        'all': { interval: '1d', limit: 500 },
    };

    const renderCandles = (element, candles) => {
        if (!element || !Array.isArray(candles) || candles.length < 2) {
            if (element) element.innerHTML = '<div class="chart-error">Live candlestick data is unavailable.</div>';
            return;
        }

        const width = Math.max(element.clientWidth || 480, 320);
        const height = Math.max(element.clientHeight || 220, 160);
        const pad = { top: 12, right: 12, bottom: 10, left: 12 };
        const plotW = width - pad.left - pad.right;
        const plotH = height - pad.top - pad.bottom;
        const lows = candles.map((c) => Number(c.low));
        const highs = candles.map((c) => Number(c.high));
        let min = Math.min(...lows);
        let max = Math.max(...highs);
        const range = Math.max(max - min, max * 0.001, 1);
        min -= range * 0.05;
        max += range * 0.05;
        const y = (value) => pad.top + ((max - Number(value)) / (max - min)) * plotH;
        const step = plotW / candles.length;
        const bodyW = Math.max(1, Math.min(7, step * 0.64));
        const x = (index) => pad.left + (index + 0.5) * step;
        const grid = [0.25, 0.5, 0.75].map((fraction) => `<line class="chart-grid-line" x1="${pad.left}" y1="${pad.top + plotH * fraction}" x2="${width - pad.right}" y2="${pad.top + plotH * fraction}"/>`).join('');
        if (element.classList.contains('final-live-chart')) {
            const points = candles.map((candle, index) => `${x(index)},${y(candle.close)}`).join(' ');
            const area = `${pad.left},${height - pad.bottom} ${points} ${width - pad.right},${height - pad.bottom}`;
            const last = candles[candles.length - 1];
            const lastY = y(last.close);
            const decimals = last.close < 1 ? 4 : 2;
            element.innerHTML = `<svg viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" role="img" aria-label="Live BTC USDT market trend"><defs><linearGradient id="finalLiveArea" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#18d8ff" stop-opacity=".30"/><stop offset="1" stop-color="#18d8ff" stop-opacity="0"/></linearGradient></defs>${grid}<polygon points="${area}" fill="url(#finalLiveArea)"/><polyline points="${points}" fill="none" stroke="#18d8ff" stroke-width="2" vector-effect="non-scaling-stroke"/><line class="chart-price-line" x1="${pad.left}" y1="${lastY}" x2="${width - pad.right}" y2="${lastY}"/><text class="chart-price-label" x="${width - pad.right - 2}" y="${Math.max(13, lastY - 5)}" text-anchor="end">${money(last.close, decimals)}</text></svg>`;
            return;
        }
        const bars = candles.map((candle, index) => {
            const up = candle.close >= candle.open;
            const cls = up ? 'chart-up' : 'chart-down';
            const cx = x(index);
            const top = Math.min(y(candle.open), y(candle.close));
            const bottom = Math.max(y(candle.open), y(candle.close));
            const bodyH = Math.max(1.4, bottom - top);
            return `<g data-candle-index="${index}"><line class="chart-wick ${cls}" x1="${cx}" y1="${y(candle.high)}" x2="${cx}" y2="${y(candle.low)}"/><rect class="${cls}" x="${cx - bodyW / 2}" y="${top}" width="${bodyW}" height="${bodyH}" rx=".5"/></g>`;
        }).join('');
        const last = candles[candles.length - 1];
        const lastY = y(last.close);
        const decimals = last.close < 1 ? 4 : 2;
        element.innerHTML = `<svg viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" role="img" aria-label="Live BTC USDT candlestick chart">${grid}${bars}<line class="chart-price-line" x1="${pad.left}" y1="${lastY}" x2="${width - pad.right}" y2="${lastY}"/><text class="chart-price-label" x="${width - pad.right - 2}" y="${Math.max(13, lastY - 4)}" text-anchor="end">${money(last.close, decimals)}</text></svg><div class="chart-tooltip"></div>`;

        const svg = element.querySelector('svg');
        const tooltip = element.querySelector('.chart-tooltip');
        svg?.addEventListener('mousemove', (event) => {
            const rect = svg.getBoundingClientRect();
            const logicalX = ((event.clientX - rect.left) / rect.width) * width;
            const index = Math.max(0, Math.min(candles.length - 1, Math.floor((logicalX - pad.left) / step)));
            const candle = candles[index];
            if (!candle || !tooltip) return;
            const date = new Date(candle.time * 1000);
            tooltip.innerHTML = `${date.toLocaleString()}<br>O ${money(candle.open, decimals)} · H ${money(candle.high, decimals)} · L ${money(candle.low, decimals)} · C ${money(candle.close, decimals)}`;
            tooltip.style.left = `${Math.min(event.clientX - rect.left + 12, rect.width - 230)}px`;
            tooltip.style.top = `${Math.max(4, event.clientY - rect.top - 42)}px`;
            tooltip.classList.add('visible');
        });
        svg?.addEventListener('mouseleave', () => tooltip?.classList.remove('visible'));
    };

    let currentChartRange = '1d';
    let chartRequestId = 0;
    const loadMarketChart = async (range = currentChartRange) => {
        const element = document.querySelector('#market-chart');
        if (!element) return;
        currentChartRange = range;
        const requestId = ++chartRequestId;
        const symbol = element.dataset.chartSymbol || 'BTCUSDT';
        const config = chartConfig[range] || chartConfig['1d'];
        const sourceBadge = document.querySelector('[data-chart-source]');
        element.innerHTML = '<div class="chart-loading">Loading live candles…</div>';

        const serverPromise = fetchJson(`${API}/market/chart/${symbol}?interval=${config.interval}&limit=${config.limit}`, {}, 5500).then((payload) => {
            const data = payload.data || payload;
            if (!data.is_live || !Array.isArray(data.candles) || data.candles.length < 2) throw new Error('Server candles unavailable');
            return { candles: data.candles, source: data.source || 'Binance live' };
        });
        const directPromise = directKlines(symbol, config.interval, config.limit).then((candles) => {
            if (candles.length < 2) throw new Error('Direct candles unavailable');
            return { candles, source: 'Binance live' };
        });

        try {
            const result = await Promise.any([serverPromise, directPromise]);
            if (requestId !== chartRequestId) return;
            renderCandles(element, result.candles);
            setSource(sourceBadge, result.source, false);
        } catch (error) {
            if (requestId !== chartRequestId) return;
            element.innerHTML = '<div class="chart-error">Live chart is unavailable. Check internet access and local PHP network settings.</div>';
            setSource(sourceBadge, 'Unavailable', true);
            console.error('ABS live chart unavailable', error);
        }
    };

    document.querySelectorAll('[data-chart-range]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('[data-chart-range]').forEach((item) => item.classList.remove('active'));
            button.classList.add('active');
            loadMarketChart(button.dataset.chartRange || '1d');
        });
    });

    const moverRowHtml = (coin, losing = false) => {
        const value = Number(coin.change_percent || 0);
        const width = Math.min(100, Math.max(20, Math.abs(value) * 8));
        return `<div class="final-mover-row"><span class="mover-dot ${losing ? 'lose' : 'gain'}">${String(coin.base || '•').slice(0, 1)}</span><b>${coin.base || '—'}</b><i><u style="width:${width}%"></u></i><em class="${losing ? 'negative' : 'positive'}">${value >= 0 ? '+' : ''}${money(value)}%</em></div>`;
    };

    const renderMovers = (data) => {
        if (!data || typeof data !== 'object') return;
        const gainers = document.querySelector('[data-gainers-list]');
        const losers = document.querySelector('[data-losers-list]');
        if (gainers && Array.isArray(data.gainers) && data.gainers.length) {
            gainers.innerHTML = data.gainers.slice(0, 5).map((coin) => moverRowHtml(coin, false)).join('');
        }
        if (losers && Array.isArray(data.losers) && data.losers.length) {
            losers.innerHTML = data.losers.slice(0, 5).map((coin) => moverRowHtml(coin, true)).join('');
        }
    };

    const loadMovers = async () => {
        const serverPromise = fetchJson(`${API}/market/movers?limit=5`, {}, 5500).then((payload) => {
            const data = payload.data || payload;
            if (!data.is_live || !Array.isArray(data.gainers) || !data.gainers.length || !Array.isArray(data.losers) || !data.losers.length) throw new Error('Server movers unavailable');
            return data;
        });
        const directPromise = directMovers(5).then((data) => {
            if (!data.gainers.length || !data.losers.length) throw new Error('Direct Binance movers unavailable');
            return data;
        });
        const coinGeckoPromise = directCoinGeckoMovers(5).then((data) => {
            if (!data.gainers.length || !data.losers.length) throw new Error('Direct CoinGecko movers unavailable');
            return data;
        });
        try {
            renderMovers(await Promise.any([serverPromise, directPromise, coinGeckoPromise]));
        } catch (error) {
            const empty = document.querySelector('[data-movers-empty]');
            if (empty) empty.textContent = 'Live movers are unavailable.';
            console.error('ABS live movers unavailable', error);
        }
    };

    const safeExternalUrl = (value) => {
        try {
            const url = new URL(String(value || ''), window.location.origin);
            return ['http:', 'https:'].includes(url.protocol) ? url.href : '#';
        } catch (_) {
            return '#';
        }
    };

    const relativePublishedTime = (value) => {
        const timestamp = Date.parse(value || '');
        if (!Number.isFinite(timestamp)) return 'Recently published';
        const seconds = Math.max(0, Math.floor((Date.now() - timestamp) / 1000));
        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)} min ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)} hr ago`;
        const days = Math.floor(seconds / 86400);
        return `${days} day${days === 1 ? '' : 's'} ago`;
    };

    const createHomeHeadline = (item, index) => {
        const link = document.createElement('a');
        link.className = 'final-headline-card';
        link.dataset.liveNewsItem = '1';
        link.dataset.external = '1';
        link.href = safeExternalUrl(item.url);
        link.target = '_blank';
        link.rel = 'noopener noreferrer';

        const image = document.createElement('img');
        const fallback = `/assets/images/home/news-${(index % 4) + 1}.png`;
        image.src = safeExternalUrl(item.image_url) === '#' ? fallback : safeExternalUrl(item.image_url);
        image.alt = '';
        image.loading = 'lazy';
        image.addEventListener('error', () => { image.src = fallback; }, { once: true });

        const content = document.createElement('div');
        const title = document.createElement('h3');
        title.textContent = item.title || 'Market update';
        const meta = document.createElement('small');
        meta.innerHTML = `${relativePublishedTime(item.published_at)} <i>•</i> <span>${String(item.source_name || item.category || 'MARKETS').toUpperCase()}</span>`;
        const excerpt = document.createElement('p');
        excerpt.textContent = item.excerpt || 'Open the verified report for the complete market context and source details.';
        content.append(meta, title, excerpt);
        link.append(image, content);
        return link;
    };

    const createNewsPageHeadline = (item) => {
        const link = document.createElement('a');
        link.className = 'panel live-headline-card';
        link.dataset.liveNewsItem = '1';
        link.dataset.external = '1';
        link.href = safeExternalUrl(item.url);
        link.target = '_blank';
        link.rel = 'noopener noreferrer';

        const meta = document.createElement('div');
        meta.className = 'headline-source';
        const publisher = document.createElement('span');
        publisher.textContent = item.source_name || item.category || 'Publisher';
        const time = document.createElement('time');
        time.textContent = relativePublishedTime(item.published_at);
        meta.append(publisher, time);

        const title = document.createElement('h2');
        title.textContent = item.title || 'Market update';
        link.append(meta, title);
        if (item.excerpt) {
            const excerpt = document.createElement('p');
            excerpt.textContent = item.excerpt;
            link.append(excerpt);
        }
        const source = document.createElement('span');
        source.className = 'source-link';
        source.textContent = 'Open original article ↗';
        link.append(source);
        return link;
    };

    const loadLiveNews = async (force = false) => {
        const lists = [...document.querySelectorAll('[data-live-news-list]')];
        if (!lists.length) return;
        const maxLimit = Math.max(...lists.map((list) => Number(list.dataset.liveNewsLimit || 5)));
        try {
            const payload = await fetchJson(`${API}/news/live?limit=${Math.min(40, maxLimit + 6)}${force ? '&refresh=1' : ''}`, {}, 7000);
            const items = Array.isArray(payload.data) ? payload.data : [];
            if (!items.length) return;

            lists.forEach((list) => {
                const limit = Math.max(1, Number(list.dataset.liveNewsLimit || 5));
                const mode = list.dataset.liveNewsMode || 'home';
                list.querySelector('[data-live-news-empty]')?.remove();

                if (mode === 'home') {
                    list.querySelectorAll('[data-live-news-item][data-external="1"]').forEach((node) => node.remove());
                    const existingEditorial = list.querySelectorAll('[data-live-news-item][data-external="0"]').length;
                    items.slice(0, Math.max(0, limit - existingEditorial)).forEach((item, index) => list.append(createHomeHeadline(item, index)));
                } else {
                    list.innerHTML = '';
                    items.slice(0, limit).forEach((item) => list.append(createNewsPageHeadline(item)));
                }
            });
        } catch (error) {
            console.warn('ABS live headlines unavailable', error);
        }
    };

    document.querySelector('[data-refresh-live-news]')?.addEventListener('click', (event) => {
        const button = event.currentTarget;
        button.disabled = true;
        button.textContent = 'Refreshing…';
        loadLiveNews(true).finally(() => {
            button.disabled = false;
            button.textContent = 'Refresh Headlines';
        });
    });

    const value = (root, field) => {
        const raw = root.querySelector(`[data-field="${field}"]`)?.value;
        return raw === '' || raw == null ? null : Number(raw);
    };

    const validPositive = (...values) => values.every((item) => Number.isFinite(item) && item > 0);
    const signedMoney = (amount) => `${amount >= 0 ? '' : '-'}$${money(Math.abs(amount))}`;
    const percent = (amount) => `${amount >= 0 ? '+' : ''}${money(amount)}%`;

    const renderResult = (result, rows, note = '') => {
        result.classList.remove('result-error');
        result.innerHTML = `<dl class="result-breakdown">${rows.map(([label, display, className = '']) => `<div><dt>${label}</dt><dd class="${className}">${display}</dd></div>`).join('')}</dl>${note ? `<p class="result-note">${note}</p>` : ''}`;
    };

    const renderCalculatorError = (result, message) => {
        result.classList.add('result-error');
        result.innerHTML = `<b>Check the inputs</b><span>${message}</span>`;
    };

    document.querySelectorAll('[data-calculator]').forEach((calculator) => {
        const result = calculator.querySelector('[data-result]');
        const originalResult = result?.innerHTML || '';

        calculator.querySelector('[data-reset]')?.addEventListener('click', () => {
            calculator.querySelectorAll('input').forEach((input) => { input.value = ''; });
            calculator.querySelectorAll('select').forEach((select) => { select.selectedIndex = 0; });
            if (result) {
                result.classList.remove('result-error');
                result.innerHTML = originalResult;
            }
        });

        calculator.querySelector('[data-calculate]')?.addEventListener('click', () => {
            if (!result) return;
            const type = calculator.dataset.calculator;

            if (type === 'profit') {
                const direction = calculator.querySelector('[data-field="direction"]')?.value || 'long';
                const entry = value(calculator, 'entry');
                const exit = value(calculator, 'exit');
                const capital = value(calculator, 'capital');
                const leverage = value(calculator, 'leverage');
                const entryFeePercent = value(calculator, 'entryFee') ?? 0;
                const exitFeePercent = value(calculator, 'exitFee') ?? 0;
                const funding = value(calculator, 'funding') ?? 0;

                if (!validPositive(entry, exit, capital, leverage)) {
                    renderCalculatorError(result, 'Entry price, exit price, margin and leverage must all be greater than zero.');
                    return;
                }
                if ([entryFeePercent, exitFeePercent, funding].some((item) => !Number.isFinite(item) || item < 0)) {
                    renderCalculatorError(result, 'Fees and funding costs cannot be negative.');
                    return;
                }

                const notional = capital * leverage;
                const quantity = notional / entry;
                const priceMove = direction === 'long' ? exit - entry : entry - exit;
                const gross = priceMove * quantity;
                const entryFee = notional * (entryFeePercent / 100);
                const exitNotional = quantity * exit;
                const exitFee = exitNotional * (exitFeePercent / 100);
                const totalCosts = entryFee + exitFee + funding;
                const net = gross - totalCosts;
                const roi = (net / capital) * 100;

                renderResult(result, [
                    ['Position notional', `$${money(notional)}`],
                    ['Asset quantity', money(quantity, 8)],
                    ['Gross P/L', signedMoney(gross), gross >= 0 ? 'positive' : 'negative'],
                    ['Entry fee', `$${money(entryFee)}`],
                    ['Exit fee', `$${money(exitFee)}`],
                    ['Funding / other cost', `$${money(funding)}`],
                    ['Estimated net P/L', signedMoney(net), net >= 0 ? 'positive' : 'negative'],
                    ['Return on margin', percent(roi), roi >= 0 ? 'positive' : 'negative'],
                ], 'The estimate excludes slippage, partial fills, changing fee tiers and exchange-specific contract rules.');
            }

            if (type === 'position') {
                const balance = value(calculator, 'balance');
                const riskPercent = value(calculator, 'risk');
                const entry = value(calculator, 'entry');
                const stop = value(calculator, 'stop');
                const leverage = value(calculator, 'leverage') ?? 1;

                if (!validPositive(balance, riskPercent, entry, stop, leverage)) {
                    renderCalculatorError(result, 'Balance, risk percentage, entry, stop and leverage must be greater than zero.');
                    return;
                }
                if (riskPercent > 100) {
                    renderCalculatorError(result, 'Risk per trade cannot exceed 100% of the account balance.');
                    return;
                }

                const distance = Math.abs(entry - stop);
                if (distance === 0) {
                    renderCalculatorError(result, 'Entry and stop price must be different.');
                    return;
                }

                const riskAmount = balance * (riskPercent / 100);
                const stopDistancePercent = (distance / entry) * 100;
                const quantity = riskAmount / distance;
                const notional = quantity * entry;
                const margin = notional / leverage;

                renderResult(result, [
                    ['Maximum planned loss', `$${money(riskAmount)}`],
                    ['Stop distance', `${money(distance, entry < 1 ? 6 : 2)} (${money(stopDistancePercent, 3)}%)`],
                    ['Asset quantity', money(quantity, 8)],
                    ['Position notional', `$${money(notional)}`],
                    ['Estimated margin at '+money(leverage, 2)+'×', `$${money(margin)}`],
                ], 'Fees and slippage can increase the final loss beyond the planned risk amount.');
            }

            if (type === 'liquidation') {
                const direction = calculator.querySelector('[data-field="direction"]')?.value || 'long';
                const entry = value(calculator, 'entry');
                const leverage = value(calculator, 'leverage');
                const maintenancePercent = value(calculator, 'maintenance') ?? 0;

                if (!validPositive(entry, leverage)) {
                    renderCalculatorError(result, 'Entry price and leverage must be greater than zero.');
                    return;
                }
                if (!Number.isFinite(maintenancePercent) || maintenancePercent < 0 || maintenancePercent >= 100) {
                    renderCalculatorError(result, 'Maintenance margin must be between 0% and 100%.');
                    return;
                }

                const initialMarginRate = 1 / leverage;
                const maintenanceRate = maintenancePercent / 100;
                const effectiveMove = Math.max(initialMarginRate - maintenanceRate, 0);
                const estimated = direction === 'long'
                    ? entry * (1 - effectiveMove)
                    : entry * (1 + effectiveMove);
                const distance = Math.abs(entry - estimated);
                const distancePercent = (distance / entry) * 100;

                renderResult(result, [
                    ['Estimated liquidation price', `$${money(estimated, estimated < 1 ? 6 : 2)}`],
                    ['Distance from entry', `$${money(distance, distance < 1 ? 6 : 2)}`],
                    ['Approximate distance', `${money(distancePercent, 3)}%`],
                    ['Initial margin rate', `${money(initialMarginRate * 100, 3)}%`],
                    ['Maintenance margin', `${money(maintenancePercent, 3)}%`],
                ], 'This simplified estimate does not include exchange risk tiers, wallet balance, funding, fees, cross margin or liquidation charges.');
            }
        });
    });

    const newsEditor = document.querySelector('[data-news-editor]');
    if (newsEditor) {
        const bindPreview = (inputSelector, outputSelector, fallback) => {
            const input = newsEditor.querySelector(inputSelector);
            const output = newsEditor.querySelector(outputSelector);
            if (!input || !output) return;
            const update = () => { output.textContent = input.value.trim() || fallback; };
            input.addEventListener('input', update);
            input.addEventListener('change', update);
        };
        bindPreview('[data-preview-title]', '[data-preview-title-output]', 'Your market headline will appear here');
        bindPreview('[data-preview-excerpt]', '[data-preview-excerpt-output]', 'A concise summary helps readers understand the development before opening the full article.');
        bindPreview('[data-preview-category]', '[data-preview-category-output]', 'Market News');

        const imageInput = newsEditor.querySelector('[data-preview-image]');
        const imageOutput = newsEditor.querySelector('[data-preview-image-output]');
        imageInput?.addEventListener('input', () => {
            const fallback = '/assets/images/home/news-1.png';
            imageOutput.src = imageInput.value.trim() || fallback;
        });
        imageOutput?.addEventListener('error', () => { imageOutput.src = '/assets/images/home/news-1.png'; });
    }

    const hasMarketStrip = Boolean(document.querySelector('[data-market-strip]'));
    const hasCoreTable = Boolean(document.querySelector('[data-core-market-row]'));
    const hasPulseMarketRow = Boolean(document.querySelector('[data-pulse-market-row]'));
    const hasChart = Boolean(document.querySelector('#market-chart'));
    const hasMovers = Boolean(document.querySelector('[data-gainers-list], [data-losers-list]'));

    if (document.querySelector('[data-live-news-list]')) {
        loadLiveNews(false);
    }

    if (hasMarketStrip || hasCoreTable || hasPulseMarketRow) {
        loadOverview();
        window.setInterval(loadOverview, 30000);
    }
    if (hasMovers) {
        loadMovers();
        window.setInterval(loadMovers, 60000);
    }
    if (hasChart) {
        loadMarketChart('1d');
        window.setInterval(() => loadMarketChart(currentChartRange), 60000);
    }
})();

// ABS V14.8.3 — Premium market selectors, pair cooldown and Binance environment cards.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-market-selector]').forEach((selector) => {
        const limit = Math.max(1, Number(selector.dataset.limit || 1));
        const selectionLocked = selector.dataset.locked === '1';
        const search = selector.querySelector('[data-market-search]');
        const feedback = selector.querySelector('[data-market-feedback]');
        const count = selector.querySelector('[data-market-selected-count]');
        const options = [...selector.querySelectorAll('[data-market-option]')];
        const quoteButtons = [...selector.querySelectorAll('[data-quote]')];
        let quote = 'all';

        const checked = () => options.filter((option) => option.querySelector('input')?.checked);
        const updateState = () => {
            const selected = checked();
            if (count) count.textContent = String(selected.length);
            options.forEach((option) => {
                const input = option.querySelector('input');
                option.classList.toggle('is-disabled', selectionLocked || (selected.length >= limit && !input.checked));
            });
            if (feedback && !selectionLocked) {
                feedback.classList.toggle('limit', selected.length >= limit);
                feedback.textContent = selected.length >= limit
                    ? `Plan selection limit reached: ${selected.length} of ${limit} markets.`
                    : `Choose ${limit - selected.length} more market${limit - selected.length === 1 ? '' : 's'} (maximum ${limit}). Saving a changed selection starts the 50-hour cooldown.`;
            }
        };
        const filter = () => {
            const term = (search?.value || '').trim().toUpperCase();
            options.forEach((option) => {
                const matchesQuote = quote === 'all' || option.dataset.quote === quote;
                const haystack = `${option.dataset.symbol || ''} ${option.dataset.base || ''} ${option.dataset.quote || ''}`;
                option.classList.toggle('is-hidden', !matchesQuote || (term && !haystack.includes(term)));
            });
        };
        options.forEach((option) => option.querySelector('input')?.addEventListener('change', (event) => {
            if (selectionLocked) { event.target.checked = !event.target.checked; return; }
            if (checked().length > limit) event.target.checked = false;
            updateState();
        }));
        search?.addEventListener('input', filter);
        quoteButtons.forEach((button) => button.addEventListener('click', () => {
            quote = button.dataset.quote || 'all';
            quoteButtons.forEach((item) => item.classList.toggle('active', item === button));
            filter();
        }));
        selector.querySelector('[data-market-select-all]')?.addEventListener('click', () => {
            if (selectionLocked) return;
            options.forEach((option, index) => { const input = option.querySelector('input'); if (input) input.checked = index < limit; });
            updateState();
            if (feedback) {
                const chosen = checked().length;
                feedback.classList.toggle('limit', chosen >= limit && options.length > limit);
                feedback.textContent = options.length <= limit
                    ? `All ${chosen} package-eligible markets selected for execution/watch.`
                    : `Selected the maximum ${chosen} markets allowed by this plan. Run Market Scan evaluates exactly the markets saved here.`;
            }
        });
        selector.querySelector('[data-market-clear]')?.addEventListener('click', () => {
            if (selectionLocked) return;
            options.forEach((option) => { const input = option.querySelector('input'); if (input) input.checked = false; });
            updateState();
        });
        selector.querySelector('[data-market-popular]')?.addEventListener('click', () => {
            if (selectionLocked) return;
            const popular = ['BTCUSDT','ETHUSDT','BNBUSDT','SOLUSDT','XRPUSDT','ADAUSDT','DOGEUSDT','AVAXUSDT','LINKUSDT','SUIUSDT'];
            options.forEach((option) => { const input = option.querySelector('input'); if (input) input.checked = false; });
            popular.slice(0, limit).forEach((symbol) => options.find((option) => option.dataset.symbol === symbol)?.querySelector('input')?.click());
            updateState();
        });
        updateState(); filter();
    });

    document.querySelectorAll('[data-plan-pair-scope]').forEach((scope) => {
        const radios = [...scope.querySelectorAll('input[name="pair_access_mode"]')];
        const panel = scope.querySelector('[data-plan-pair-selected-panel]');
        const search = scope.querySelector('[data-plan-pair-search]');
        const options = [...scope.querySelectorAll('[data-plan-pair-option]')];
        const count = scope.querySelector('[data-plan-pair-count]');
        const refresh = () => {
            const selectedMode = radios.find((radio) => radio.checked)?.value || 'all';
            if (panel) panel.hidden = selectedMode !== 'selected';
            scope.querySelectorAll('.admin-market-mode-card').forEach((card) => card.classList.toggle('active', card.querySelector('input')?.checked));
            if (count) count.textContent = String(options.filter((option) => option.querySelector('input')?.checked).length);
        };
        radios.forEach((radio) => radio.addEventListener('change', refresh));
        options.forEach((option) => option.querySelector('input')?.addEventListener('change', refresh));
        scope.querySelector('[data-plan-pair-select-all]')?.addEventListener('click', () => {
            options.forEach((option) => { const input = option.querySelector('input'); if (input) input.checked = true; });
            refresh();
        });
        scope.querySelector('[data-plan-pair-clear-all]')?.addEventListener('click', () => {
            options.forEach((option) => { const input = option.querySelector('input'); if (input) input.checked = false; });
            refresh();
        });
        search?.addEventListener('input', () => {
            const term = search.value.trim().toUpperCase();
            options.forEach((option) => option.classList.toggle('is-hidden', term && !(option.dataset.search || '').includes(term)));
        });
        refresh();
    });

    const form = document.querySelector('[data-binance-connection-form]');
    if (form) {
        const setMode = (mode) => {
            const radio = form.querySelector(`input[name="environment"][value="${mode}"]`);
            if (!radio || radio.disabled) return;
            radio.checked = true;
            form.querySelectorAll('[data-binance-form-mode]').forEach((label) => label.classList.toggle('active', label.dataset.binanceFormMode === mode));
            form.scrollIntoView({behavior: 'smooth', block: 'center'});
        };
        document.querySelectorAll('[data-binance-mode-select]').forEach((button) => button.addEventListener('click', () => setMode(button.dataset.binanceModeSelect)));
        form.querySelectorAll('input[name="environment"]').forEach((radio) => radio.addEventListener('change', () => setMode(radio.value)));
    }
});
