<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PulsePointLedger;
use App\Models\PulsePointPack;
use App\Models\PulsePointPurchase;
use App\Models\PulseRewardedAdReceipt;
use App\Models\PulseSystemSetting;
use App\Models\User;
use App\Services\PulseAuditService;
use App\Services\PulsePointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class AdminPulsePointsController extends Controller
{
    public function index()
    {
        return view('admin.pulse.points', [
            'packs' => PulsePointPack::query()->orderBy('sort_order')->get(),
            'purchases' => PulsePointPurchase::query()->with(['user', 'pack', 'reviewer'])->latest()->limit(100)->get(),
            'ledger' => PulsePointLedger::query()->with('user')->latest('id')->limit(100)->get(),
            'giftUsers' => User::query()->select(['id', 'name', 'email'])->orderByDesc('created_at')->limit(500)->get(),
            'settings' => PulseSystemSetting::query()
                ->whereIn('group', ['points', 'gamification', 'ai'])
                ->where('key', 'not like', 'rewarded_%')
                ->orderBy('group')->orderBy('key')->get()->groupBy('group'),
            'rewardedAds' => [
                'enabled' => (bool) PulseSystemSetting::value('rewarded_ads_enabled', false),
                'provider' => (string) PulseSystemSetting::value('rewarded_ads_provider', 'google_ad_manager'),
                'points' => (int) PulseSystemSetting::value('rewarded_ad_points', 5),
                'xp' => (int) PulseSystemSetting::value('rewarded_ad_xp', 5),
                'daily_limit' => (int) PulseSystemSetting::value('rewarded_ads_daily_limit', 10),
                'cooldown_seconds' => (int) PulseSystemSetting::value('rewarded_ads_cooldown_seconds', 120),
                'web_ad_unit_path' => (string) PulseSystemSetting::value('rewarded_web_ad_unit_path', ''),
                'web_test_mode' => (bool) PulseSystemSetting::value('rewarded_web_test_mode', true),
                'mobile_test_mode' => (bool) PulseSystemSetting::value('rewarded_mobile_test_mode', true),
                'android_ad_unit_id' => (string) PulseSystemSetting::value('rewarded_admob_android_ad_unit_id', ''),
                'ios_ad_unit_id' => (string) PulseSystemSetting::value('rewarded_admob_ios_ad_unit_id', ''),
                'ssv_enabled' => (bool) PulseSystemSetting::value('rewarded_admob_ssv_enabled', true),
                'max_callback_age' => (int) PulseSystemSetting::value('rewarded_admob_max_callback_age_seconds', 3600),
                'ssv_callback_url' => url('/api/v1/pulse/rewarded-ad/admob/ssv'),
                'rewarded_today' => PulseRewardedAdReceipt::query()->where('status', 'rewarded')->whereDate('rewarded_at', today())->count(),
                'rewarded_total' => PulseRewardedAdReceipt::query()->where('status', 'rewarded')->count(),
                'recent' => PulseRewardedAdReceipt::query()->with('user')->latest('id')->limit(10)->get(),
            ],
            'commerce' => [
                'enabled' => (bool) PulseSystemSetting::value('point_purchases_enabled', true),
                'wallet_address' => trim((string) PulseSystemSetting::value('usdt_wallet_address', '')),
                'network' => trim((string) PulseSystemSetting::value('usdt_network', '')),
                'instructions' => trim((string) PulseSystemSetting::value('usdt_payment_instructions', '')),
                'proof_required' => (bool) PulseSystemSetting::value('payment_proof_required', false),
            ],
            'stats' => [
                'pending' => PulsePointPurchase::query()->whereIn('status', ['submitted', 'under_review'])->count(),
                'credited_points' => (int) PulsePointLedger::query()->where('amount', '>', 0)->sum('amount'),
                'spent_points' => abs((int) PulsePointLedger::query()->where('amount', '<', 0)->sum('amount')),
                'purchase_value' => (float) PulsePointPurchase::query()->where('status', 'approved')->sum('amount_usdt'),
            ],
        ]);
    }

    public function storePack(Request $request, PulseAuditService $audit)
    {
        $data = $this->packData($request);
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        validator(['slug' => $data['slug']], ['slug' => [Rule::unique('pulse_point_packs', 'slug')]])->validate();
        $data['is_active'] = $request->boolean('is_active');
        $pack = PulsePointPack::create($data);
        $audit->record('admin.points_pack_created', $request->user(), 'PulsePointPack', $pack->id, null, ['points' => $pack->totalPoints(), 'price_usdt' => (float) $pack->price_usdt], $request);
        return back()->with('success', 'Pulse Sparks bundle created.');
    }

    public function updatePack(Request $request, PulsePointPack $pack, PulseAuditService $audit)
    {
        $data = $this->packData($request);
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        validator(['slug' => $data['slug']], ['slug' => [Rule::unique('pulse_point_packs', 'slug')->ignore($pack->id)]])->validate();
        $data['is_active'] = $request->boolean('is_active');
        $pack->update($data);
        $audit->record('admin.points_pack_updated', $request->user(), 'PulsePointPack', $pack->id, null, ['points' => $pack->totalPoints(), 'price_usdt' => (float) $pack->price_usdt], $request);
        return back()->with('success', 'Pulse Sparks bundle updated.');
    }

    public function approve(Request $request, PulsePointPurchase $purchase, PulsePointService $points, PulseAuditService $audit)
    {
        $data = $request->validate(['admin_notes' => ['required', 'string', 'max:2000']]);
        if (! $purchase->isOpen()) return back()->withErrors(['points' => 'This purchase is no longer awaiting review.']);
        try {
            DB::transaction(function () use ($request, $purchase, $points, $data): void {
                $locked = PulsePointPurchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
                if (! $locked->isOpen()) throw new RuntimeException('This purchase has already been processed.');
                $locked->update(['status' => 'under_review']);
                $points->credit($locked->user, $locked->totalPoints(), 'point_purchase', 'point-purchase:'.$locked->id, 'Admin-verified USDT Pulse Sparks purchase.', 'PulsePointPurchase', $locked->id, ['amount_usdt' => (float) $locked->amount_usdt, 'payment_reference' => $locked->payment_reference]);
                $locked->update(['status' => 'approved', 'admin_notes' => $data['admin_notes'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'credited_at' => now()]);
            }, 5);
        } catch (RuntimeException $e) { return back()->withErrors(['points' => $e->getMessage()]); }
        $audit->record('admin.points_purchase_approved', $request->user(), 'PulsePointPurchase', $purchase->id, null, ['target_user_id' => $purchase->user_id, 'points' => $purchase->totalPoints()], $request);
        return back()->with('success', number_format($purchase->totalPoints()).' Sparks credited exactly once.');
    }

    public function reject(Request $request, PulsePointPurchase $purchase, PulseAuditService $audit)
    {
        $data = $request->validate(['admin_notes' => ['required', 'string', 'max:2000']]);
        try {
            DB::transaction(function () use ($request, $purchase, $data): void {
                $locked = PulsePointPurchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
                if (! $locked->isOpen()) throw new RuntimeException('This purchase has already been processed.');
                $locked->update(['status' => 'rejected', 'admin_notes' => $data['admin_notes'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            }, 5);
        } catch (RuntimeException $e) {
            return back()->withErrors(['points' => $e->getMessage()]);
        }
        $audit->record('admin.points_purchase_rejected', $request->user(), 'PulsePointPurchase', $purchase->id, null, ['target_user_id' => $purchase->user_id], $request);
        return back()->with('success', 'Pulse Sparks purchase rejected.');
    }

    public function proof(PulsePointPurchase $purchase)
    {
        abort_unless($purchase->payment_proof_path && Storage::disk('local')->exists($purchase->payment_proof_path), 404);
        return Storage::disk('local')->download($purchase->payment_proof_path);
    }

    public function adjust(Request $request, PulsePointService $points, PulseAuditService $audit)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'amount' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'], 'reason' => ['required', 'string', 'max:500']]);
        $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower($data['email'])])->first();
        if (! $user) return back()->withErrors(['email' => 'No ABS user account matches this email address.']);
        try { $entry = $points->adminAdjust($user, (int) $data['amount'], 'admin-adjustment:'.Str::uuid(), $data['reason'], ['admin_user_id' => $request->user()->id]); }
        catch (RuntimeException $e) { return back()->withErrors(['amount' => $e->getMessage()]); }
        $audit->record('admin.points_adjusted', $request->user(), 'User', $user->id, null, ['amount' => (int) $data['amount'], 'ledger_id' => $entry->id, 'reason' => $data['reason']], $request);
        return back()->with('success', 'Pulse Sparks balance adjusted.');
    }

    public function gift(Request $request, PulsePointService $points, PulseAuditService $audit)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower($data['email'])])->first();
        if (! $user) return back()->withErrors(['email' => 'No registered ABS user matches this email address.']);

        try {
            $entry = $points->credit(
                $user,
                (int) $data['amount'],
                'admin_gift',
                'admin-spark-gift:'.Str::uuid(),
                'Pulse Sparks gift from ABS Admin: '.$data['reason'],
                'User',
                $user->id,
                ['admin_user_id' => $request->user()->id, 'gift_reason' => $data['reason']],
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        $audit->record('admin.sparks_gifted', $request->user(), 'User', $user->id, null, [
            'amount' => (int) $data['amount'], 'ledger_id' => $entry->id, 'reason' => $data['reason'],
        ], $request);

        return back()->with('success', number_format((int) $data['amount']).' Sparks gifted to '.$user->email.'.');
    }

    public function updateCommerce(Request $request, PulseAuditService $audit)
    {
        $data = $request->validate([
            'point_purchases_enabled' => ['required', 'in:true,false'],
            'usdt_wallet_address' => ['nullable', 'string', 'max:500'],
            'usdt_network' => ['nullable', 'string', 'max:80'],
            'usdt_payment_instructions' => ['nullable', 'string', 'max:2000'],
            'payment_proof_required' => ['required', 'in:true,false'],
        ]);

        $definitions = [
            'point_purchases_enabled' => ['boolean', 'points', 'Allow members to submit USDT purchases for Admin-verified Pulse Sparks bundles.'],
            'usdt_wallet_address' => ['string', 'membership', 'USDT receiving wallet used only for Pulse Sparks bundle purchases.'],
            'usdt_network' => ['string', 'membership', 'Blockchain network required for USDT Pulse Sparks bundle transfers.'],
            'usdt_payment_instructions' => ['string', 'membership', 'Customer-facing instructions for USDT Pulse Sparks bundle purchases.'],
            'payment_proof_required' => ['boolean', 'membership', 'Require proof in addition to the transaction reference for Spark purchases.'],
        ];

        foreach ($definitions as $key => [$type, $group, $description]) {
            $value = in_array($key, ['point_purchases_enabled', 'payment_proof_required'], true)
                ? ($data[$key] === 'true' ? '1' : '0')
                : trim((string) ($data[$key] ?? ''));
            PulseSystemSetting::updateOrCreate(['key' => $key], [
                'value' => $value,
                'type' => $type,
                'group' => $group,
                'description' => $description,
            ]);
        }

        // Enforce the V15 commerce boundary even if legacy settings existed.
        PulseSystemSetting::updateOrCreate(['key' => 'membership_requests_enabled'], [
            'value' => '0', 'type' => 'boolean', 'group' => 'membership',
            'description' => 'Legacy direct membership requests are disabled in ABS V15.',
        ]);
        PulseSystemSetting::updateOrCreate(['key' => 'promotion_codes_enabled'], [
            'value' => '0', 'type' => 'boolean', 'group' => 'membership',
            'description' => 'Legacy direct membership promotions are disabled in the V15 Pulse Sparks commerce flow.',
        ]);

        $audit->record('admin.points_commerce_updated', $request->user(), null, null, null, ['keys' => array_keys($definitions)], $request);
        return back()->with('success', 'USDT → Pulse Sparks commerce settings updated.');
    }

    public function updateSettings(Request $request, PulseAuditService $audit)
    {
        $data = $request->validate(['settings' => ['required', 'array'], 'settings.*' => ['nullable', 'string', 'max:10000']]);
        $allowed = PulseSystemSetting::query()->whereIn('group', ['points', 'gamification', 'ai'])->get()->keyBy('key');
        foreach ($data['settings'] as $key => $value) {
            $setting = $allowed->get($key); if (! $setting) continue;
            $normalized = match ($setting->type) { 'boolean' => filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0', 'integer' => (string) max(0, (int) $value), 'float' => (string) (float) $value, default => trim((string) $value) };
            $setting->update(['value' => $normalized]);
        }
        $audit->record('admin.points_settings_updated', $request->user(), null, null, null, ['keys' => array_keys($data['settings'])], $request);
        return back()->with('success', 'Pulse Sparks, rewards and AI settings updated.');
    }

    private function packData(Request $request): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:120'], 'slug' => ['nullable', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:2000'], 'points' => ['required', 'integer', 'min:1', 'max:10000000'], 'bonus_points' => ['required', 'integer', 'min:0', 'max:10000000'], 'price_usdt' => ['required', 'numeric', 'min:0.01', 'max:1000000'], 'sort_order' => ['required', 'integer', 'min:0', 'max:100000']]);
    }
}
