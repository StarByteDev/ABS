<?php

namespace App\Services;

use App\Models\PulseSignal;

class PulseShareService
{
    public function payload(PulseSignal $signal): array
    {
        $tp = collect((array) $signal->take_profit_levels)->filter(fn ($v) => is_numeric($v))->values();
        $targets = $tp->isNotEmpty() ? $tp->map(fn ($v, $i) => 'TP'.($i + 1).' '.$v)->implode(' · ') : 'TP '.$signal->take_profit;
        $text = "ABS Pulse Best Signal — {$signal->symbol} {$signal->direction} ({$signal->timeframe})\n".
            "Entry {$signal->entry_price} · SL {$signal->stop_loss} · {$targets}\n".
            "Confidence ".number_format((float) $signal->confidence_score, 0)."/100\n".
            "Market intelligence for decision support — not a guarantee of profit.";

        return [
            'title' => 'ABS Pulse Best Signal · '.$signal->symbol,
            'text' => $text,
            'url' => route('pulse.signals.show', $signal),
        ];
    }
}
