<?php

namespace App\Services;

use Illuminate\Support\Str;

class NewsMarketBriefService
{
    public function build(array $headline): array
    {
        $title = trim((string) ($headline['title'] ?? ''));
        $summary = trim((string) (($headline['summary'] ?? null) ?: ($headline['excerpt'] ?? '')));
        $text = Str::lower($title.' '.$summary);

        $theme = $this->theme($text);
        $assets = $this->assets($text);
        $bias = $this->bias($text, $theme);
        $impact = $this->impact($text, $theme);

        return [
            'theme' => $theme,
            'assets' => $assets,
            'bias' => $bias,
            'impact' => $impact,
            'significance' => $this->significance($theme, $bias),
            'watch' => $this->watchItems($theme, $assets),
        ];
    }

    private function theme(string $text): string
    {
        $map = [
            'Macro & Monetary Policy' => ['fomc','federal reserve','fed ','interest rate','rate cut','rate hike','cpi','inflation','pce','jobs report','nonfarm','payroll','gdp','treasury yield','dollar index'],
            'Regulation & Policy' => ['sec ','cftc','regulat','lawmakers','congress','senate','court','lawsuit','policy framework','licens'],
            'ETF & Institutional Flows' => ['etf','blackrock','fidelity','institutional','fund flow','inflow','outflow','asset manager','spot fund'],
            'Exchange & Market Structure' => ['exchange','binance','coinbase','kraken','liquidation','open interest','funding rate','derivatives','futures','options'],
            'Security & Operational Risk' => ['hack','exploit','breach','stolen','attack','security incident','phishing','drain','vulnerability'],
            'Stablecoins & Liquidity' => ['stablecoin','usdt','usdc','tether','circle','liquidity','reserve','depeg'],
            'DeFi & Protocols' => ['defi','protocol','staking','layer 2','layer-2','rollup','validator','yield','smart contract'],
            'Corporate & Treasury' => ['treasury strategy','corporate treasury','microstrategy','strategy inc','miner','mining','balance sheet','capital raise'],
            'Digital Asset Market' => ['bitcoin','btc','ethereum','eth','solana','xrp','crypto','token','altcoin'],
        ];

        foreach ($map as $label => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) return $label;
            }
        }
        return 'Market Development';
    }

    private function assets(string $text): array
    {
        $assets = [];
        $map = [
            'BTC' => ['bitcoin',' btc '],
            'ETH' => ['ethereum',' ether ',' eth '],
            'SOL' => ['solana',' sol '],
            'XRP' => ['xrp','ripple'],
            'BNB' => ['bnb','binance coin'],
            'DOGE' => ['dogecoin','doge'],
            'USDT' => ['tether','usdt'],
            'USDC' => ['usd coin','usdc'],
        ];
        $padded = ' '.$text.' ';
        foreach ($map as $label => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($padded, $needle)) { $assets[] = $label; break; }
            }
        }

        if (str_contains($text, 'crypto') || str_contains($text, 'digital asset')) $assets[] = 'Broad Crypto';
        if (str_contains($text, 'dollar') || str_contains($text, 'dxy')) $assets[] = 'USD';
        if (str_contains($text, 'treasury') || str_contains($text, 'yield')) $assets[] = 'US Yields';

        $assets = array_values(array_unique($assets));
        return $assets ?: ['Broad Crypto'];
    }

    private function bias(string $text, string $theme): array
    {
        $positive = ['approval','approved','inflow','record high','adoption','partnership','launch','surge','rally','gain','buying','accumulat','easing','rate cut','lower inflation','settlement','clarity'];
        $negative = ['hack','exploit','outflow','lawsuit','ban','crackdown','liquidation','sell-off','selloff','decline','drop','plunge','breach','higher inflation','rate hike','rejection','fraud','default'];
        $p = $n = 0;
        foreach ($positive as $needle) if (str_contains($text, $needle)) $p++;
        foreach ($negative as $needle) if (str_contains($text, $needle)) $n++;

        if ($theme === 'Security & Operational Risk') $n += 2;
        if ($p >= $n + 2) return ['label' => 'Supportive', 'class' => 'positive'];
        if ($n >= $p + 2) return ['label' => 'Risk-Off', 'class' => 'negative'];
        if ($p > $n) return ['label' => 'Constructive', 'class' => 'positive'];
        if ($n > $p) return ['label' => 'Cautious', 'class' => 'negative'];
        return ['label' => 'Mixed / Neutral', 'class' => 'neutral'];
    }

    private function impact(string $text, string $theme): string
    {
        foreach (['fomc','interest rate','cpi','sec ','etf','hack','exploit','liquidation','binance','coinbase','stablecoin','treasury yield'] as $needle) {
            if (str_contains($text, $needle)) return 'High';
        }
        return in_array($theme, ['Macro & Monetary Policy','Regulation & Policy','ETF & Institutional Flows','Security & Operational Risk'], true)
            ? 'High'
            : 'Medium';
    }

    private function significance(string $theme, array $bias): string
    {
        $base = match ($theme) {
            'Macro & Monetary Policy' => 'Macro releases and policy expectations can reprice the US dollar, Treasury yields and liquidity conditions, which often changes risk appetite across digital assets.',
            'Regulation & Policy' => 'Regulatory developments can change access, compliance costs, product availability and institutional participation, often creating sharp sector-specific repricing.',
            'ETF & Institutional Flows' => 'Fund flows provide a direct view of institutional demand and can influence spot liquidity, positioning and broader market confidence.',
            'Exchange & Market Structure' => 'Changes in exchange activity, derivatives positioning or liquidations can alter short-term liquidity and amplify volatility around key price levels.',
            'Security & Operational Risk' => 'Security incidents can trigger immediate risk reduction, liquidity stress and contagion concerns across related assets, protocols or exchanges.',
            'Stablecoins & Liquidity' => 'Stablecoin supply, reserves and settlement activity matter because they influence crypto-market liquidity and the ability of capital to move between venues.',
            'DeFi & Protocols' => 'Protocol changes can affect token demand, locked capital, yield conditions and operational risk across connected DeFi markets.',
            'Corporate & Treasury' => 'Corporate treasury activity can affect spot demand, financing expectations and market perception of institutional conviction.',
            'Digital Asset Market' => 'This development can influence spot demand, positioning and short-term volatility across the broader digital-asset market.',
            default => 'This development may affect liquidity, positioning or risk sentiment and is worth monitoring alongside price and volume confirmation.',
        };
        return $base.' Current ABS market read: '.$bias['label'].'.';
    }

    private function watchItems(string $theme, array $assets): array
    {
        $assetText = implode(', ', array_slice($assets, 0, 4));
        return match ($theme) {
            'Macro & Monetary Policy' => [
                'Watch DXY and US Treasury yields for confirmation of the macro reaction.',
                'Compare BTC and ETH reaction with broader risk assets rather than relying on the headline alone.',
                'Monitor whether volatility expands after the initial move or quickly mean-reverts.',
            ],
            'Regulation & Policy' => [
                'Separate the direct impact on named assets, exchanges or products from broader market sentiment.',
                'Watch spot volume and institutional-flow data for evidence that positioning is changing.',
                'Track follow-up statements, implementation dates and legal scope before treating the first headline as final.',
            ],
            'ETF & Institutional Flows' => [
                'Watch net flow direction and whether demand persists across multiple sessions.',
                'Compare spot price response with exchange volume and derivatives positioning.',
                'Monitor '.$assetText.' for confirmation that capital flows are translating into market demand.',
            ],
            'Exchange & Market Structure' => [
                'Watch open interest, funding and liquidation clusters for signs of crowded positioning.',
                'Confirm whether spot volume supports the derivatives move.',
                'Monitor '.$assetText.' around major support, resistance and liquidity levels.',
            ],
            'Security & Operational Risk' => [
                'Track the confirmed loss amount, affected wallets or venues and whether withdrawals remain operational.',
                'Watch for contagion into related tokens, stablecoins or counterparties.',
                'Treat early estimates cautiously until the incident scope is confirmed.',
            ],
            default => [
                'Watch '.$assetText.' for price and volume confirmation.',
                'Compare the move with BTC dominance, market breadth and derivatives positioning.',
                'Reassess if follow-up information materially changes the original market interpretation.',
            ],
        };
    }
}
