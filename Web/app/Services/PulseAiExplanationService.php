<?php

namespace App\Services;

use App\Models\PulseSignal;
use App\Models\PulseSystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PulseAiExplanationService
{
    public function explain(User $user, PulseSignal $signal): string
    {
        if ((int) $signal->user_id !== (int) $user->id) {
            throw new RuntimeException('This signal does not belong to the current account.');
        }
        if (! (bool) PulseSystemSetting::value('openai_signal_explanations_enabled', false)) {
            throw new RuntimeException('AI signal explanations are not enabled by the administrator.');
        }
        if ($signal->ai_explanation) return (string) $signal->ai_explanation;

        $apiKey = trim((string) config('services.openai.api_key', ''));
        if ($apiKey === '') throw new RuntimeException('OpenAI API is not configured on this ABS installation.');

        $model = trim((string) config('services.openai.model', 'gpt-5.6-sol')) ?: 'gpt-5.6-sol';
        $payload = [
            'model' => $model,
            'instructions' => 'Explain the supplied ABS Pulse trading signal in clear, concise educational language. Do not invent market data. Do not promise profit. Explain direction, score, confidence, strategy evidence, entry, stop and targets. State that the user must independently assess risk.',
            'input' => json_encode([
                'symbol' => $signal->symbol,
                'timeframe' => $signal->timeframe,
                'direction' => $signal->direction,
                'entry_price' => (string) $signal->entry_price,
                'stop_loss' => (string) $signal->stop_loss,
                'take_profit_levels' => $signal->take_profit_levels,
                'technical_score' => (float) $signal->technical_score,
                'reliability_score' => (float) $signal->reliability_score,
                'confidence_score' => (float) $signal->confidence_score,
                'strategy_snapshot' => $signal->strategy_snapshot,
            ], JSON_UNESCAPED_SLASHES),
            'max_output_tokens' => 500,
            'store' => false,
        ];

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(35)
            ->retry(1, 500, throw: false)
            ->post('https://api.openai.com/v1/responses', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI could not generate the signal explanation at this time.');
        }

        $text = trim((string) data_get($response->json(), 'output_text', ''));
        if ($text === '') {
            $text = collect((array) data_get($response->json(), 'output', []))
                ->flatMap(fn ($item) => (array) ($item['content'] ?? []))
                ->where('type', 'output_text')
                ->pluck('text')
                ->filter()
                ->implode("\n\n");
        }
        if ($text === '') throw new RuntimeException('OpenAI returned an empty signal explanation.');

        $signal->forceFill(['ai_explanation' => $text, 'ai_explained_at' => now()])->save();
        return $text;
    }
}
