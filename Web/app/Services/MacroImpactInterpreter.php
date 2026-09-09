<?php

namespace App\Services;

use Illuminate\Support\Str;

class MacroImpactInterpreter
{
    public function interpret(string $title, ?string $actual = null, ?string $forecast = null, ?string $previous = null): array
    {
        $name = Str::lower(trim($title));
        $actualNumber = $this->number($actual);
        $forecastNumber = $this->number($forecast);
        $surprise = ($actualNumber !== null && $forecastNumber !== null) ? $actualNumber <=> $forecastNumber : null;

        $category = $this->category($name);
        $explanation = $this->easyExplanation($category);
        [$bias, $impact] = $this->cryptoImpact($category, $name, $surprise, $actualNumber, $forecastNumber);

        return [
            'category' => $category,
            'crypto_impact' => $bias,
            'easy_explanation' => $explanation,
            'crypto_impact_summary' => $impact,
            'surprise' => $surprise,
            'is_crypto_relevant' => $category !== 'other',
        ];
    }

    public function classifyImportance(string $title, ?string $providerImpact = null): string
    {
        $provider = Str::lower(trim((string) $providerImpact));
        if (in_array($provider, ['high', '3', 'red'], true)) return 'high';
        if (in_array($provider, ['medium', 'moderate', '2', 'orange'], true)) return 'medium';
        if (in_array($provider, ['low', '1', 'yellow'], true)) return 'low';

        $name = Str::lower($title);
        foreach (['fomc', 'federal funds', 'interest rate decision', 'cpi', 'consumer price', 'pce', 'nonfarm', 'non-farm', 'payroll', 'gdp', 'unemployment rate'] as $needle) {
            if (str_contains($name, $needle)) return 'high';
        }
        foreach ([
            'ppi', 'producer price', 'retail sales', 'ism', 'pmi', 'jobless claims', 'jolts', 'adp',
            'consumer confidence', 'michigan', 'fed chair', 'powell', 'durable goods', 'industrial production',
            'factory orders', 'trade balance', 'housing starts', 'building permits', 'existing home sales',
            'new home sales', 'pending home sales', 'construction spending', 'beige book', 'federal reserve',
            'treasury', 'bond auction', 'inventory', 'inventories', 'personal income', 'personal spending',
        ] as $needle) {
            if (str_contains($name, $needle)) return 'medium';
        }
        return 'low';
    }

    private function category(string $name): string
    {
        if ($this->has($name, ['fomc', 'federal funds', 'interest rate decision', 'fed rate', 'central bank rate'])) return 'rates';
        if ($this->has($name, ['cpi', 'consumer price', 'ppi', 'producer price', 'pce', 'inflation', 'price index'])) return 'inflation';
        if ($this->has($name, ['nonfarm', 'non-farm', 'payroll', 'adp employment', 'unemployment rate', 'jobless claims', 'jolts', 'employment change'])) return 'labor';
        if ($this->has($name, ['gdp', 'gross domestic product'])) return 'growth';
        if ($this->has($name, ['retail sales', 'consumer spending'])) return 'retail';
        if ($this->has($name, ['ism', 'pmi', 'purchasing managers'])) return 'pmi';
        if ($this->has($name, ['consumer confidence', 'michigan sentiment', 'consumer sentiment'])) return 'sentiment';
        if ($this->has($name, ['fomc minutes', 'beige book', 'fed chair', 'powell', 'fed speech', 'federal reserve', 'central bank speech'])) return 'fed_communication';
        if ($this->has($name, ['treasury', 'bond auction', 'yield'])) return 'rates_market';
        if ($this->has($name, ['durable goods', 'industrial production', 'factory orders', 'capacity utilization', 'business inventories', 'wholesale inventories'])) return 'activity';
        if ($this->has($name, ['housing starts', 'building permits', 'existing home sales', 'new home sales', 'pending home sales', 'construction spending'])) return 'housing';
        if ($this->has($name, ['trade balance', 'current account', 'imports', 'exports'])) return 'external';
        if ($this->has($name, ['personal income', 'personal spending'])) return 'consumer';
        return 'other';
    }

    private function easyExplanation(string $category): string
    {
        return match ($category) {
            'inflation' => 'Measures price pressure in the economy. Markets compare the actual number with the forecast because hotter inflation can keep interest rates higher for longer.',
            'rates' => 'Shows the central bank interest-rate decision. Rate changes and the policy message can quickly move the US dollar, bond yields, stocks and crypto.',
            'labor' => 'Shows how strong or weak the jobs market is. Very strong employment can keep rate expectations high, while weaker jobs can increase expectations for easier policy.',
            'growth' => 'Measures how fast the economy is growing. A large surprise can change expectations for interest rates, recession risk and overall risk appetite.',
            'retail' => 'Shows how strongly consumers are spending. Strong spending can support growth but may also keep inflation and rate expectations elevated.',
            'pmi' => 'A survey-based snapshot of business activity. Readings above 50 usually indicate expansion and below 50 usually indicate contraction.',
            'sentiment' => 'Shows how confident consumers feel about the economy. Large surprises can affect growth expectations and risk appetite.',
            'fed_communication' => 'Comments or minutes can reveal whether policymakers are leaning toward tighter or easier monetary policy, which can move yields and crypto quickly.',
            'rates_market' => 'Bond-market events can change Treasury yields and financial conditions. Rising yields can pressure risk assets while falling yields can be supportive.',
            'activity' => 'Shows changes in business production, orders or inventories. Large surprises can shift expectations for economic growth, inflation and interest rates.',
            'housing' => 'Shows activity in the housing market. Housing can influence growth, inflation expectations and the interest-rate outlook, especially when results differ sharply from forecasts.',
            'external' => 'Tracks trade or cross-border flows. Large surprises can affect growth expectations and the US dollar, which can indirectly influence crypto.',
            'consumer' => 'Shows changes in household income or spending. Stronger spending can support growth but may also keep inflation and interest-rate expectations elevated.',
            default => 'A scheduled macroeconomic release that may affect the US dollar, interest-rate expectations, liquidity and risk appetite.',
        };
    }

    private function cryptoImpact(string $category, string $name, ?int $surprise, ?float $actual, ?float $forecast): array
    {
        if ($surprise === null) {
            return match ($category) {
                'inflation' => ['volatile', 'Crypto can become volatile around this release. A higher-than-forecast inflation reading is often a headwind for risk assets; a lower reading can be supportive if it increases expectations for easier monetary policy.'],
                'rates' => ['volatile', 'A more hawkish rate decision or message can pressure crypto through higher yields and a stronger dollar. A more dovish outcome can be supportive, but the market reaction can reverse quickly.'],
                'labor' => ['volatile', 'A major jobs surprise can move rate expectations. Stronger-than-expected labor data can pressure crypto if yields rise; weaker data can support rate-cut expectations but may also increase recession concerns.'],
                'growth', 'retail', 'pmi', 'sentiment', 'activity', 'housing', 'external', 'consumer' => ['mixed', 'The crypto effect is usually indirect through the dollar, bond yields and risk appetite. A large surprise in either direction can increase volatility.'],
                'fed_communication', 'rates_market' => ['volatile', 'Watch the US dollar and Treasury yields. Hawkish language or rising yields can pressure crypto; dovish language or falling yields can be supportive.'],
                default => ['mixed', 'This event may affect crypto indirectly through liquidity, the US dollar, bond yields or broader risk sentiment.'],
            };
        }

        if ($category === 'inflation') {
            return $surprise > 0
                ? ['pressure', 'Actual inflation is above forecast. That can strengthen higher-for-longer rate expectations and may pressure crypto and other risk assets.']
                : ($surprise < 0
                    ? ['supportive', 'Actual inflation is below forecast. That can reduce rate pressure and may support crypto if yields and the US dollar ease.']
                    : ['mixed', 'Actual inflation matched forecast. The market may focus more on the details, revisions and central-bank reaction than the headline number.']);
        }

        if ($category === 'rates') {
            return $surprise > 0
                ? ['pressure', 'The rate outcome is above forecast, which is relatively hawkish and can pressure crypto through higher yields and tighter financial conditions.']
                : ($surprise < 0
                    ? ['supportive', 'The rate outcome is below forecast, which is relatively dovish and can support crypto if liquidity expectations improve.']
                    : ['volatile', 'The rate decision matched forecast. The statement, projections and press conference may matter more than the headline rate.']);
        }

        if ($category === 'labor') {
            $inverse = $this->has($name, ['unemployment rate', 'jobless claims', 'initial claims', 'continuing claims']);
            $stronger = $inverse ? $surprise < 0 : $surprise > 0;
            $weaker = $inverse ? $surprise > 0 : $surprise < 0;
            if ($stronger) return ['mixed', 'The labour market reading is stronger than forecast. That can support economic confidence but may also keep yields and rate expectations higher, which can pressure crypto.'];
            if ($weaker) return ['mixed', 'The labour market reading is weaker than forecast. That can increase expectations for easier policy, but a very weak result can also increase recession concerns and risk aversion.'];
            return ['mixed', 'The labour reading matched forecast. Crypto may react more to revisions, wage details, unemployment and the broader market tone.'];
        }

        if (in_array($category, ['growth', 'retail', 'pmi', 'sentiment', 'activity', 'housing', 'external', 'consumer'], true)) {
            return $surprise > 0
                ? ['mixed', 'The release is stronger than forecast. That can improve risk sentiment, but it can also lift yields and reduce expectations for rate cuts.']
                : ($surprise < 0
                    ? ['mixed', 'The release is weaker than forecast. That can support easier-policy expectations, but it may also weaken risk appetite if growth concerns rise.']
                    : ['mixed', 'The release matched forecast. The crypto effect may be limited unless revisions or underlying details surprise the market.']);
        }

        return ['mixed', 'The release differs from expectations. Watch the US dollar, Treasury yields and equity-market reaction for confirmation of the broader crypto impact.'];
    }

    private function has(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) if (str_contains($haystack, $needle)) return true;
        return false;
    }

    private function number(?string $value): ?float
    {
        if ($value === null) return null;
        $raw = trim($value);
        if ($raw === '' || in_array(Str::lower($raw), ['n/a', 'na', '-', '—'], true)) return null;
        $negative = str_starts_with($raw, '(') && str_ends_with($raw, ')');
        $multiplier = 1.0;
        if (preg_match('/([KMBT])\s*%?$/i', $raw, $m)) {
            $multiplier = match (strtoupper($m[1])) { 'K' => 1_000, 'M' => 1_000_000, 'B' => 1_000_000_000, 'T' => 1_000_000_000_000, default => 1 };
        }
        $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $raw));
        if ($clean === '' || $clean === '-' || ! is_numeric($clean)) return null;
        $number = (float) $clean * $multiplier;
        return $negative ? -abs($number) : $number;
    }
}
