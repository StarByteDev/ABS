<?php

namespace App\Http\Controllers\Pulse;

use App\Http\Controllers\Controller;
use App\Models\PulseSignal;
use App\Services\PulseAiExplanationService;
use App\Services\PulseShareService;
use Illuminate\Http\Request;
use RuntimeException;

class SignalUtilityController extends Controller
{
    public function share(Request $request, PulseSignal $signal, PulseShareService $share)
    {
        abort_unless((int) $signal->user_id === (int) $request->user()->id, 403);
        $request->validate(['channel' => ['nullable', 'string', 'max:40']]);
        $payload = $share->payload($signal);
        $signal->increment('share_count');

        return response()->json(['message' => 'Share card ready.', 'data' => $payload]);
    }

    public function explain(Request $request, PulseSignal $signal, PulseAiExplanationService $ai)
    {
        abort_unless((int) $signal->user_id === (int) $request->user()->id, 403);
        try {
            $text = $ai->explain($request->user(), $signal);
        } catch (RuntimeException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withErrors(['ai' => $e->getMessage()]);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'AI explanation ready.', 'data' => ['explanation' => $text]])
            : back()->with('success', 'AI explanation generated.');
    }
}
