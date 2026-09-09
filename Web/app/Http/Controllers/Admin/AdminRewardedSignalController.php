<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PulsePublicSignalUnlock;
use App\Models\PulseSystemSetting;
use App\Services\PulseAuditService;
use App\Services\PulsePublicRewardedSignalService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminRewardedSignalController extends Controller
{
    public function index(PulsePublicRewardedSignalService $rewards)
    {
        $settings = $rewards->settings();
        $base = PulsePublicSignalUnlock::query();

        return view('admin.pulse.rewarded-signals', [
            'settings' => $settings,
            'stats' => [
                'today' => (clone $base)->where('claimed_at', '>=', today())->count(),
                'last30' => (clone $base)->where('claimed_at', '>=', now()->subDays(30))->count(),
                'unique30' => (clone $base)->where('claimed_at', '>=', now()->subDays(30))->distinct()->count('visitor_hash'),
                'signals30' => (clone $base)->where('claimed_at', '>=', now()->subDays(30))->whereNotNull('signal_id')->count(),
            ],
            'unlocks' => PulsePublicSignalUnlock::query()->with('signal')->latest('claimed_at')->paginate(50),
        ]);
    }

    public function update(Request $request, PulseAuditService $audit, PulsePublicRewardedSignalService $rewards)
    {
        $data = $request->validate([
            'enabled' => ['required', 'in:true,false'],
            'cooldown_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'web_test_mode' => ['required', 'in:true,false'],
            'web_ad_unit_input' => ['nullable', 'string', 'max:5000'],
            'badge' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:140'],
            'description' => ['required', 'string', 'max:320'],
            'cta' => ['required', 'string', 'max:60'],
        ]);

        $input = trim((string) ($data['web_ad_unit_input'] ?? ''));
        $resolvedPath = $rewards->resolveAdUnitPath($input);
        $testMode = $data['web_test_mode'] === 'true';
        if (! $testMode && $resolvedPath === '') {
            throw ValidationException::withMessages([
                'web_ad_unit_input' => 'Production mode requires a Google Ad Manager rewarded ad-unit path such as /1234567/abs_rewarded_signal, or a copied GPT snippet containing that path.',
            ]);
        }

        $definitions = [
            'public_rewarded_signals_enabled' => [$data['enabled'] === 'true' ? '1' : '0', 'boolean', 'Allow anonymous website visitors to reveal one qualified public signal or, when none qualifies, the highest-scoring Entry Watch after a rewarded ad.'],
            'public_rewarded_signal_cooldown_minutes' => [(string) (int) $data['cooldown_minutes'], 'integer', 'Cooldown between anonymous rewarded Free Signal / Entry Watch reveals.'],
            'public_rewarded_signal_min_claim_seconds' => ['5', 'integer', 'Minimum time between issuing a web reward session and accepting its browser claim.'],
            'rewarded_web_test_mode' => [$testMode ? '1' : '0', 'boolean', 'Use Google rewarded-web test inventory.'],
            'rewarded_web_ad_unit_input' => [$input, 'text', 'Google Ad Manager rewarded ad-unit path or copied GPT snippet. Stored as configuration only and never executed.'],
            'rewarded_web_ad_unit_path' => [$resolvedPath, 'string', 'Resolved Google Ad Manager rewarded-web ad unit path.'],
            'public_rewarded_signal_badge' => [trim($data['badge']), 'string', 'Public rewarded-signal badge.'],
            'public_rewarded_signal_title' => [trim($data['title']), 'string', 'Public rewarded-signal headline.'],
            'public_rewarded_signal_description' => [trim($data['description']), 'text', 'Public rewarded-signal description.'],
            'public_rewarded_signal_cta' => [trim($data['cta']), 'string', 'Public rewarded-signal action label.'],
        ];

        foreach ($definitions as $key => [$value, $type, $description]) {
            PulseSystemSetting::updateOrCreate(['key' => $key], [
                'value' => $value,
                'type' => $type,
                'group' => 'public_signal',
                'description' => $description,
            ]);
        }

        $audit->record('admin.public_rewarded_signal_settings_updated', $request->user(), null, null, null, [
            'enabled' => $data['enabled'],
            'cooldown_minutes' => (int) $data['cooldown_minutes'],
            'web_test_mode' => $data['web_test_mode'],
            'resolved_ad_unit' => $resolvedPath,
        ], $request);

        return back()->with('success', 'Rewarded Signal settings updated.');
    }
}
