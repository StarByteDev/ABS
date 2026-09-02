<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PulseAlert;
use App\Models\PulseAuditLog;
use App\Models\PulsePair;
use App\Models\PulsePlan;
use App\Models\PulseSignal;
use App\Models\PulseStrategy;
use App\Models\PulseSystemSetting;
use App\Models\PulseTrade;
use App\Models\User;
use App\Models\UserServiceAccess;
use App\Services\BinanceFuturesService;
use App\Services\PulseAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminPulseController extends Controller
{
    public function dashboard()
    {
        return response()->json(['data' => [
            'active_users' => UserServiceAccess::query()->where('service', 'pulse')->where('status', 'active')->count(),
            'active_signals' => PulseSignal::query()->where('status', 'active')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
            'open_trades' => PulseTrade::query()->whereIn('status', ['submitting', 'pending', 'open', 'closing', 'protection_failed'])->count(),
            'protection_review' => PulseTrade::query()->whereIn('status', ['open', 'protection_failed'])->where('protection_status', '!=', 'confirmed')->count(),
            'plans' => PulsePlan::query()->with('strategies:id,name,slug')->orderBy('sort_order')->get(),
        ]]);
    }

    public function access(Request $request)
    {
        $query = User::query()->with(['pulseAccess.plan'])->orderBy('name');
        if ($request->filled('q')) {
            $value = '%'.trim((string) $request->string('q')).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $value)->orWhere('email', 'like', $value));
        }
        if ($request->filled('status')) {
            $query->whereHas('pulseAccess', fn ($q) => $q->where('status', $request->string('status')));
        }
        return response()->json($query->paginate($this->perPage($request, 50)));
    }

    public function updateAccess(Request $request, User $user, PulseAuditService $audit)
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,active,suspended,expired,revoked'],
            'pulse_plan_id' => ['nullable', 'exists:pulse_plans,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $permissions = collect($data['permissions'] ?? [])
            ->filter(fn ($value, $key) => array_key_exists((string) $key, PulsePlan::CAPABILITIES) && $value === false)
            ->all();

        $access = UserServiceAccess::updateOrCreate(
            ['user_id' => $user->id, 'service' => 'pulse'],
            [
                'status' => $data['status'],
                'pulse_plan_id' => $data['pulse_plan_id'] ?? null,
                'approved_by' => $request->user()->id,
                'starts_at' => $data['starts_at'] ?? now(),
                'ends_at' => $data['ends_at'] ?? null,
                'permissions' => $permissions,
                'notes' => $data['notes'] ?? null,
            ],
        );

        $audit->record('api.admin.access_updated', $request->user(), 'UserServiceAccess', $access->id, null, [
            'target_user_id' => $user->id,
            'status' => $access->status,
            'plan_id' => $access->pulse_plan_id,
        ], $request);

        return response()->json(['message' => 'Pulse access updated.', 'data' => $access->load('plan')]);
    }

    public function plans()
    {
        return response()->json(['data' => PulsePlan::query()->with(['strategies','pairs'])->orderBy('sort_order')->get()]);
    }

    public function storePlan(Request $request, PulseAuditService $audit)
    {
        $data = $this->validatedPlan($request);
        $strategyIds = $data['strategy_ids'] ?? [];
        $pairIds = $data['pair_ids'] ?? [];
        unset($data['strategy_ids'], $data['pair_ids']);
        $data = $this->planBooleans($request, $data);
        $plan = PulsePlan::create($data);
        $this->syncStrategies($plan, $strategyIds);
        $this->syncPairsForPlan($plan, $pairIds);
        $audit->record('api.admin.plan_created', $request->user(), 'PulsePlan', $plan->id, null, ['slug' => $plan->slug], $request);
        return response()->json(['message' => 'Pulse plan created.', 'data' => $plan->load(['strategies','pairs'])], 201);
    }

    public function updatePlan(Request $request, PulsePlan $plan, PulseAuditService $audit)
    {
        $data = $this->validatedPlan($request, $plan);
        $strategyIds = $data['strategy_ids'] ?? [];
        $pairIds = $data['pair_ids'] ?? [];
        unset($data['strategy_ids'], $data['pair_ids']);
        $plan->update($this->planBooleans($request, $data));
        $this->syncStrategies($plan, $strategyIds);
        $this->syncPairsForPlan($plan, $pairIds);
        $audit->record('api.admin.plan_updated', $request->user(), 'PulsePlan', $plan->id, null, ['slug' => $plan->slug], $request);
        return response()->json(['message' => 'Pulse plan updated.', 'data' => $plan->fresh(['strategies','pairs'])]);
    }

    public function deletePlan(Request $request, PulsePlan $plan, PulseAuditService $audit)
    {
        if ($plan->accesses()->exists()) {
            return response()->json(['message' => 'This plan is assigned to users. Disable it instead of deleting it.'], 422);
        }
        $id = $plan->id;
        $plan->strategies()->detach();
        $plan->pairs()->detach();
        $plan->delete();
        $audit->record('api.admin.plan_deleted', $request->user(), 'PulsePlan', $id, null, [], $request);
        return response()->json(status: 204);
    }

    public function strategies()
    {
        return response()->json(['data' => PulseStrategy::query()->withCount('plans')->orderBy('sort_order')->get()]);
    }

    public function storeStrategy(Request $request, PulseAuditService $audit)
    {
        $data = $this->validatedStrategy($request);
        $strategy = PulseStrategy::create($data);
        PulsePlan::query()->where('is_active', true)->each(fn (PulsePlan $plan) => $plan->strategies()->syncWithoutDetaching([$strategy->id => ['is_enabled' => true]]));
        $audit->record('api.admin.strategy_created', $request->user(), 'PulseStrategy', $strategy->id, null, ['slug' => $strategy->slug], $request);
        return response()->json(['message' => 'Pulse strategy created.', 'data' => $strategy], 201);
    }

    public function updateStrategy(Request $request, PulseStrategy $strategy, PulseAuditService $audit)
    {
        $strategy->update($this->validatedStrategy($request, $strategy));
        $audit->record('api.admin.strategy_updated', $request->user(), 'PulseStrategy', $strategy->id, null, ['slug' => $strategy->slug], $request);
        return response()->json(['message' => 'Pulse strategy updated.', 'data' => $strategy->fresh()]);
    }

    public function deleteStrategy(Request $request, PulseStrategy $strategy, PulseAuditService $audit)
    {
        $id = $strategy->id;
        $strategy->plans()->detach();
        $strategy->delete();
        $audit->record('api.admin.strategy_deleted', $request->user(), 'PulseStrategy', $id, null, [], $request);
        return response()->json(status: 204);
    }

    public function pairs(Request $request)
    {
        return response()->json(PulsePair::query()->orderBy('sort_order')->orderBy('symbol')->paginate($this->perPage($request, 100)));
    }

    public function storePair(Request $request, PulseAuditService $audit)
    {
        $pair = PulsePair::create($this->validatedPair($request));
        $audit->record('api.admin.pair_created', $request->user(), 'PulsePair', $pair->id, null, ['symbol' => $pair->symbol], $request);
        return response()->json(['message' => 'Pulse pair created.', 'data' => $pair], 201);
    }

    public function updatePair(Request $request, PulsePair $pair, PulseAuditService $audit)
    {
        $pair->update($this->validatedPair($request, $pair));
        $audit->record('api.admin.pair_updated', $request->user(), 'PulsePair', $pair->id, null, ['symbol' => $pair->symbol], $request);
        return response()->json(['message' => 'Pulse pair updated.', 'data' => $pair->fresh()]);
    }

    public function syncPairs(Request $request, BinanceFuturesService $binance, PulseAuditService $audit)
    {
        try {
            $count = $binance->syncPairsFromExchange('live');
            $audit->record('api.admin.pairs_synchronized', $request->user(), null, null, 'live', ['count' => $count], $request);
            return response()->json(['message' => 'Pair filters synchronized.', 'updated' => $count]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function signals(Request $request)
    {
        $query = PulseSignal::query()->with('user:id,name,email')->latest('generated_at');
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('symbol')) $query->where('symbol', strtoupper((string) $request->string('symbol')));
        if ($request->filled('direction')) $query->where('direction', $request->string('direction'));
        return response()->json($query->paginate($this->perPage($request, 50)));
    }

    public function updateSignal(Request $request, PulseSignal $signal, PulseAuditService $audit)
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,executed,expired,rejected'],
            'expires_at' => ['nullable', 'date'],
        ]);
        $signal->update($data);
        $audit->record('api.admin.signal_updated', $request->user(), 'PulseSignal', $signal->id, null, $data, $request);
        return response()->json(['message' => 'Signal updated.', 'data' => $signal->fresh()]);
    }

    public function trades(Request $request)
    {
        $query = PulseTrade::query()->with('user:id,name,email')->latest();
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('environment')) $query->where('environment', $request->string('environment'));
        if ($request->filled('symbol')) $query->where('symbol', strtoupper((string) $request->string('symbol')));
        return response()->json($query->paginate($this->perPage($request, 50)));
    }

    public function settings()
    {
        return response()->json(['data' => PulseSystemSetting::query()->orderBy('group')->orderBy('key')->get()->groupBy('group')]);
    }

    public function updateSettings(Request $request, PulseAuditService $audit)
    {
        $data = $request->validate(['settings' => ['required', 'array'], 'settings.*' => ['nullable', 'string', 'max:10000']]);
        foreach ($data['settings'] as $key => $value) {
            $setting = PulseSystemSetting::query()->where('key', $key)->first();
            if ($setting) {
                $setting->update(['value' => $this->normalizeSettingValue($setting->type, $value)]);
            }
        }
        $audit->record('api.admin.system_settings_updated', $request->user(), null, null, null, ['keys' => array_keys($data['settings'])], $request);
        return response()->json(['message' => 'Pulse system settings updated.']);
    }

    public function broadcastAlert(Request $request, PulseAuditService $audit)
    {
        $data = $request->validate([
            'audience' => ['required', 'in:all,active,plan'],
            'pulse_plan_id' => ['nullable', 'required_if:audience,plan', 'exists:pulse_plans,id'],
            'type' => ['required', 'in:system,market,risk,trade,plan'],
            'title' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:3000'],
            'severity' => ['required', 'in:info,success,warning,danger'],
            'action_url' => ['nullable', 'string', 'max:255'],
        ]);

        $userIds = User::query()->whereHas('pulseAccess', function ($q) use ($data): void {
            $q->where('service', 'pulse');
            if ($data['audience'] === 'active') $q->where('status', 'active');
            if ($data['audience'] === 'plan') $q->where('status', 'active')->where('pulse_plan_id', $data['pulse_plan_id']);
        })->pluck('id');

        foreach ($userIds->chunk(500) as $chunk) {
            PulseAlert::query()->insert($chunk->map(fn ($id) => [
                'user_id' => $id,
                'type' => $data['type'],
                'title' => $data['title'],
                'message' => $data['message'],
                'severity' => $data['severity'],
                'is_read' => false,
                'action_url' => $data['action_url'] ?? null,
                'data' => json_encode(['admin_broadcast' => true]),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        }

        $audit->record('api.admin.alert_broadcast', $request->user(), null, null, null, ['recipients' => $userIds->count(), 'title' => $data['title'], 'audience' => $data['audience'], 'plan_id' => $data['pulse_plan_id'] ?? null], $request);
        return response()->json(['message' => 'Pulse alert broadcast completed.', 'recipients' => $userIds->count()]);
    }

    public function logs(Request $request)
    {
        $query = PulseAuditLog::query()->with('user:id,name,email')->latest('created_at');
        if ($request->filled('action')) $query->where('action', 'like', '%'.trim((string) $request->string('action')).'%');
        if ($request->filled('environment')) $query->where('environment', $request->string('environment'));
        return response()->json($query->paginate($this->perPage($request, 100)));
    }

    private function validatedPlan(Request $request, ?PulsePlan $plan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:3000'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:8'],
            'scanner_runs_per_day' => ['required', 'integer', 'min:0'],
            'signals_per_day' => ['required', 'integer', 'min:0'],
            'minimum_signal_score' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'manual_trades_per_day' => ['required', 'integer', 'min:0'],
            'auto_trades_per_day' => ['required', 'integer', 'min:0'],
            'max_open_trades' => ['required', 'integer', 'min:0'],
            'max_selected_pairs' => ['required', 'integer', 'min:1'],
            'pair_access_mode' => ['nullable', Rule::in(['all','selected'])],
            'pair_ids' => ['nullable', 'array'],
            'pair_ids.*' => ['integer', 'exists:pulse_pairs,id'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'strategy_ids' => ['nullable', 'array'],
            'strategy_ids.*' => ['integer', 'exists:pulse_strategies,id'],
        ]);
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $data['pair_access_mode'] = $data['pair_access_mode'] ?? $plan?->pair_access_mode ?? 'all';
        validator(['slug' => $data['slug']], ['slug' => [Rule::unique('pulse_plans', 'slug')->ignore($plan?->id)]])->validate();
        return $data;
    }

    private function planBooleans(Request $request, array $data): array
    {
        $submitted = is_array($request->input('capabilities')) ? $request->input('capabilities') : [];
        $matrix = [];
        foreach (PulsePlan::CAPABILITIES as $key => $label) {
            $matrix[$key] = filter_var($submitted[$key] ?? false, FILTER_VALIDATE_BOOL);
        }

        $data['capabilities'] = $matrix;
        $data['allow_testnet_trading'] = $matrix['testnet_trading'] ?? false;
        $data['allow_manual_trading'] = $matrix['manual_trading'] ?? false;
        $data['allow_live_trading'] = $matrix['live_trading'] ?? false;
        $data['allow_auto_trading'] = $matrix['auto_trading'] ?? false;
        $data['allow_mobile_api'] = $matrix['mobile_api'] ?? false;
        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }

    private function syncStrategies(PulsePlan $plan, array $strategyIds): void
    {
        $plan->strategies()->sync(collect($strategyIds)->mapWithKeys(fn ($id) => [(int) $id => ['is_enabled' => true]])->all());
    }

    private function syncPairsForPlan(PulsePlan $plan, array $pairIds): void
    {
        if (($plan->pair_access_mode ?: 'all') === 'all') {
            $plan->pairs()->detach();
            return;
        }
        $plan->pairs()->sync(collect($pairIds)->mapWithKeys(fn ($id) => [(int) $id => ['is_enabled' => true]])->all());
    }

    private function validatedStrategy(Request $request, ?PulseStrategy $strategy = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:3000'],
            'timeframe' => ['required', 'in:5m,15m,30m,1h,4h,1d'],
            'weight' => ['required', 'numeric', 'min:0', 'max:10'],
            'minimum_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'settings' => ['nullable', 'array'],
            'is_enabled' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ]);
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        validator(['slug' => $data['slug']], ['slug' => [Rule::unique('pulse_strategies', 'slug')->ignore($strategy?->id)]])->validate();
        $data['settings'] = $data['settings'] ?? [];
        $data['is_enabled'] = $request->boolean('is_enabled');
        return $data;
    }

    private function validatedPair(Request $request, ?PulsePair $pair = null): array
    {
        $data = $request->validate([
            'symbol' => ['required', 'string', 'max:30'],
            'base_asset' => ['required', 'string', 'max:20'],
            'quote_asset' => ['required', 'string', 'max:20'],
            'is_enabled' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'price_precision' => ['required', 'integer', 'min:0', 'max:12'],
            'quantity_precision' => ['required', 'integer', 'min:0', 'max:12'],
            'tick_size' => ['nullable', 'numeric', 'gte:0'],
            'step_size' => ['nullable', 'numeric', 'gte:0'],
            'minimum_quantity' => ['nullable', 'numeric', 'gte:0'],
            'minimum_notional' => ['nullable', 'numeric', 'gte:0'],
        ]);
        $data['symbol'] = strtoupper($data['symbol']);
        validator(['symbol' => $data['symbol']], ['symbol' => [Rule::unique('pulse_pairs', 'symbol')->ignore($pair?->id)]])->validate();
        $data['base_asset'] = strtoupper($data['base_asset']);
        $data['quote_asset'] = strtoupper($data['quote_asset']);
        $data['is_enabled'] = $request->boolean('is_enabled');
        return $data;
    }

    private function normalizeSettingValue(string $type, mixed $value): string
    {
        return match ($type) {
            'boolean' => in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true) ? 'true' : 'false',
            'integer' => (string) (int) $value,
            'float' => (string) (float) $value,
            'json' => json_encode(is_array($value) ? $value : (json_decode((string) $value, true) ?: []), JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };
    }

    private function perPage(Request $request, int $default): int
    {
        return min(100, max(1, (int) $request->integer('per_page', $default)));
    }
}
