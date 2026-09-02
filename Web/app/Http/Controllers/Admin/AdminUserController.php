<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PulseMembershipRequest;
use App\Models\PulsePlan;
use App\Models\PulseScannerRun;
use App\Models\PulseSignal;
use App\Models\PulseTrade;
use App\Models\User;
use App\Models\UserServiceAccess;
use App\Services\BrandedMailService;
use App\Services\PulseAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->with(['pulseAccess.plan', 'portfolioAccount']);
        if ($request->string('deleted')->value() === 'only') $query->onlyTrashed();
        elseif ($request->string('deleted')->value() === 'with') $query->withTrashed();
        $query->latest();

        if ($request->filled('q')) {
            $term = '%'.trim((string) $request->string('q')).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
        }
        if ($request->filled('role')) $query->where('role', $request->string('role'));
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('plan')) $query->whereHas('pulseAccess', fn ($q) => $q->where('pulse_plan_id', (int) $request->input('plan')));
        if ($request->filled('pulse_status')) $query->whereHas('pulseAccess', fn ($q) => $q->where('status', $request->string('pulse_status')));
        if ($request->string('expiry')->value() === '7') {
            $query->whereHas('pulseAccess', fn ($q) => $q->where('status', 'active')->whereNotNull('ends_at')->whereBetween('ends_at', [now(), now()->addDays(7)]));
        } elseif ($request->string('expiry')->value() === '30') {
            $query->whereHas('pulseAccess', fn ($q) => $q->where('status', 'active')->whereNotNull('ends_at')->whereBetween('ends_at', [now(), now()->addDays(30)]));
        } elseif ($request->string('expiry')->value() === 'expired') {
            $query->whereHas('pulseAccess', fn ($q) => $q->whereNotNull('ends_at')->where('ends_at', '<=', now()));
        } elseif ($request->string('expiry')->value() === 'unassigned') {
            $query->whereDoesntHave('pulseAccess');
        }

        $now = now();
        return view('admin.users', [
            'users' => $query->paginate(35)->withQueryString(),
            'plans' => PulsePlan::query()->orderBy('sort_order')->orderBy('name')->get(),
            'summary' => [
                'all' => User::count(),
                'active' => User::where('status', 'active')->count(),
                'private' => User::where('role', 'private_member')->where('status', 'active')->count(),
                'pulse' => UserServiceAccess::query()->where('service', 'pulse')->where('status', 'active')->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))->count(),
                'pending' => User::where('status', 'pending')->count(),
                'suspended' => User::where('status', 'suspended')->count(),
                'deleted' => User::onlyTrashed()->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.user-create', [
            'plans' => PulsePlan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'capabilityLabels' => PulsePlan::CAPABILITIES,
        ]);
    }

    public function store(Request $request, PulseAuditService $audit, BrandedMailService $mail)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'country_code' => ['nullable', 'string', 'max:8', 'regex:/^\+[0-9]{1,6}$/'],
            'phone' => ['nullable', 'string', 'max:24', 'regex:/^[0-9\s().-]+$/'],
            'country' => ['nullable', 'string', 'max:80'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'role' => ['required', 'in:user,private_member,admin'],
            'status' => ['required', 'in:active,pending,suspended'],
            'pulse_plan_id' => ['nullable', 'exists:pulse_plans,id'],
            'pulse_status' => ['nullable', 'in:pending,active,suspended,expired,revoked'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'access_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $assignPulse = $request->boolean('assign_pulse');
        if ($assignPulse && empty($data['pulse_plan_id'])) {
            return back()->withErrors(['pulse_plan_id' => 'Choose a Pulse plan when Pulse access is enabled.'])->withInput();
        }

        [$user, $access] = DB::transaction(function () use ($request, $data, $assignPulse): array {
            $user = User::create([
                'name' => trim($data['name']),
                'email' => $data['email'],
                'country_code' => $data['country_code'] ?? null,
                'phone' => $data['phone'] ?? null,
                'country' => $data['country'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'status' => $data['status'],
                'email_verified_at' => $request->boolean('email_verified') ? now() : null,
                'private_member_approved_at' => $data['role'] === 'private_member' && $data['status'] === 'active' ? now() : null,
            ]);

            $access = null;
            if ($assignPulse) {
                $permissions = $this->restrictionPayload($request);
                $plan = PulsePlan::query()->findOrFail((int) $data['pulse_plan_id']);
                $access = UserServiceAccess::create([
                    'user_id' => $user->id,
                    'service' => 'pulse',
                    'status' => $data['pulse_status'] ?? 'active',
                    'pulse_plan_id' => $plan->id,
                    'approved_by' => $request->user()->id,
                    'starts_at' => $data['starts_at'] ?? now(),
                    'ends_at' => $data['ends_at'] ?? null,
                    'trial_used_at' => $plan->is_trial ? now() : null,
                    'permissions' => $permissions,
                    'notes' => $data['access_notes'] ?? null,
                ]);
                $access->load('plan');
            }

            return [$user, $access];
        });

        $audit->record('admin.user_created', $request->user(), 'User', $user->id, null, [
            'target_user_id' => $user->id,
            'role' => $user->role,
            'status' => $user->status,
            'pulse_plan_id' => $access?->pulse_plan_id,
            'pulse_status' => $access?->status,
            'pulse_starts_at' => $access?->starts_at?->toIso8601String(),
            'pulse_ends_at' => $access?->ends_at?->toIso8601String(),
        ], $request);

        if ($request->boolean('send_welcome_email')) {
            if (! $user->hasVerifiedEmail()) {
                $activationUrl = URL::temporarySignedRoute(
                    'verification.verify',
                    now()->addHours(24),
                    [
                        'user' => $user->getKey(),
                        'hash' => sha1($user->getEmailForVerification()),
                    ],
                );
                $mail->accountActivation($user, $activationUrl);
            } else {
                $mail->accountCreatedByAdmin($user, $access);
            }
        }

        return redirect()->route('admin.users.show', $user)->with(
            'success',
            $user->hasVerifiedEmail()
                ? 'User created. Account role and service access are ready.'
                : 'User created. A secure activation email was sent and the account will become usable after verification.'
        );
    }

    public function show(User $user)
    {
        $user->load(['pulseAccess.plan.strategies', 'portfolioAccount', 'binanceConnections']);
        $today = today();
        $access = $user->pulseAccess;

        return view('admin.user-show', [
            'user' => $user,
            'access' => $access,
            'plans' => PulsePlan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'usage' => [
                'Scans today' => PulseScannerRun::query()->where('user_id', $user->id)->whereDate('created_at', $today)->count(),
                'Signals today' => PulseSignal::query()->where('user_id', $user->id)->whereDate('generated_at', $today)->count(),
                'Trades today' => PulseTrade::query()->where('user_id', $user->id)->whereDate('created_at', $today)->count(),
                'Open trades' => PulseTrade::query()->where('user_id', $user->id)->whereIn('status', ['submitting','pending','open','closing','protection_failed'])->count(),
            ],
            'tradeSummary' => [
                'total' => PulseTrade::query()->where('user_id', $user->id)->count(),
                'realized_pnl' => (float) PulseTrade::query()->where('user_id', $user->id)->sum('realized_pnl'),
                'fees' => (float) PulseTrade::query()->where('user_id', $user->id)->sum('fees'),
                'last_trade' => PulseTrade::query()->where('user_id', $user->id)->latest()->first(),
            ],
            'recentSignals' => PulseSignal::query()->where('user_id', $user->id)->latest('generated_at')->limit(6)->get(),
            'recentTrades' => PulseTrade::query()->where('user_id', $user->id)->latest()->limit(6)->get(),
            'membershipRequests' => PulseMembershipRequest::query()->where('user_id',$user->id)->with(['plan','promotion','reviewer'])->latest()->limit(8)->get(),
            'isLastActiveAdmin' => $user->role === 'admin' && $user->status === 'active' && User::query()->where('role','admin')->where('status','active')->count() <= 1,
        ]);
    }

    public function update(Request $request, User $user, PulseAuditService $audit)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email', $user->email)))]);
        $data = $request->validate([
            'name' => ['required','string','max:100'],
            'email' => ['required','email','max:255', Rule::unique('users','email')->ignore($user->id)],
            'country_code' => ['nullable','string','max:8','regex:/^\+[0-9]{1,6}$/'],
            'phone' => ['nullable','string','max:24','regex:/^[0-9\s().-]+$/'],
            'country' => ['nullable','string','max:80'],
            'role' => ['required','in:user,private_member,admin'],
            'status' => ['required','in:active,suspended,pending'],
        ]);

        $removingAdmin = $user->role === 'admin' && ($data['role'] !== 'admin' || $data['status'] !== 'active');
        if ($removingAdmin && User::query()->where('role','admin')->where('status','active')->count() <= 1) {
            return back()->withErrors(['role' => 'At least one active administrator must remain.']);
        }
        if ($request->user()->is($user) && ($data['role'] !== 'admin' || $data['status'] !== 'active')) {
            return back()->withErrors(['role' => 'You cannot remove your own active administrator access from this session.']);
        }

        $before = $user->only(['name','email','role','status','email_verified_at','private_member_approved_at']);
        $user->update([
            'name' => trim($data['name']),
            'email' => $data['email'],
            'country_code' => $data['country_code'] ?? null,
            'phone' => $data['phone'] ?? null,
            'country' => $data['country'] ?? null,
            'role' => $data['role'],
            'status' => $data['status'],
            'email_verified_at' => $request->boolean('email_verified') ? ($user->email_verified_at ?: now()) : null,
            'private_member_approved_at' => $data['role'] === 'private_member' && $data['status'] === 'active'
                ? ($user->private_member_approved_at ?: now())
                : null,
        ]);

        $audit->record('admin.user_updated', $request->user(), 'User', $user->id, null, [
            'target_user_id' => $user->id,
            'before' => $before,
            'after' => $user->fresh()->only(['name','email','role','status','email_verified_at','private_member_approved_at']),
        ], $request);

        return back()->with('success', 'User identity, role and account status updated.');
    }

    public function resetPassword(Request $request, User $user, PulseAuditService $audit, BrandedMailService $mail)
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);
        $user->tokens()->delete();
        if (! $request->user()->is($user) && Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        $audit->record('admin.user_password_reset', $request->user(), 'User', $user->id, null, [
            'target_user_id' => $user->id,
            'sessions_revoked' => ! $request->user()->is($user),
        ], $request);
        $mail->passwordChangedByAdmin($user);

        return back()->with('success', 'Password updated. Existing mobile tokens and other active sessions were revoked.');
    }


    public function deactivate(Request $request, User $user, PulseAuditService $audit)
    {
        if ($request->user()->is($user)) {
            return back()->withErrors(['user' => 'You cannot deactivate your own administrator session.']);
        }
        if ($user->role === 'admin' && $user->status === 'active' && User::query()->where('role', 'admin')->where('status', 'active')->count() <= 1) {
            return back()->withErrors(['user' => 'At least one active administrator must remain.']);
        }

        DB::transaction(function () use ($user): void {
            $user->update(['status' => 'suspended']);
            UserServiceAccess::query()->where('user_id', $user->id)->where('status', 'active')->update(['status' => 'suspended']);
            $user->tokens()->delete();
            if (Schema::hasTable('sessions')) DB::table('sessions')->where('user_id', $user->id)->delete();
        });

        $audit->record('admin.user_deactivated', $request->user(), 'User', $user->id, null, ['target_user_id' => $user->id], $request);
        return back()->with('success', 'User deactivated. Sign-in sessions, mobile tokens and active service access were suspended.');
    }

    public function destroy(Request $request, User $user, PulseAuditService $audit)
    {
        if ($request->user()->is($user)) return back()->withErrors(['user' => 'You cannot delete your own administrator account.']);
        if ($user->role === 'admin' && User::query()->where('role', 'admin')->where('status', 'active')->count() <= 1) {
            return back()->withErrors(['user' => 'The last active administrator cannot be deleted.']);
        }

        DB::transaction(function () use ($user): void {
            $user->update(['status' => 'suspended']);
            UserServiceAccess::query()->where('user_id', $user->id)->whereIn('status', ['pending','active'])->update(['status' => 'revoked']);
            $user->tokens()->delete();
            if (Schema::hasTable('sessions')) DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->delete();
        });

        $audit->record('admin.user_soft_deleted', $request->user(), 'User', $user->id, null, ['target_user_id' => $user->id], $request);
        return redirect()->route('admin.users', ['deleted' => 'with'])->with('success', 'User moved to Deleted Users. Trading and historical records were preserved.');
    }

    public function restore(Request $request, int $userId, PulseAuditService $audit)
    {
        $user = User::onlyTrashed()->findOrFail($userId);
        $user->restore();
        $user->update(['status' => 'suspended']);
        $audit->record('admin.user_restored', $request->user(), 'User', $user->id, null, ['target_user_id' => $user->id], $request);
        return redirect()->route('admin.users.show', $user)->with('success', 'User restored in suspended state. Review access and activate only when ready.');
    }

    public function forceDelete(Request $request, int $userId, PulseAuditService $audit)
    {
        $user = User::onlyTrashed()->findOrFail($userId);
        $openTrades = PulseTrade::query()->where('user_id', $user->id)->whereIn('status', ['submitting','pending','open','closing','protection_failed'])->count();
        if ($openTrades > 0) return back()->withErrors(['user' => 'Permanent deletion is blocked because this user still has an active/pending trade record.']);

        $audit->record('admin.user_permanent_delete_requested', $request->user(), 'User', $user->id, null, ['target_user_id' => $user->id], $request);
        $user->forceDelete();
        return redirect()->route('admin.users', ['deleted' => 'only'])->with('success', 'User permanently deleted. Use this only when legal/data-retention requirements allow it.');
    }

    private function restrictionPayload(Request $request): array
    {
        $permissions = [];
        foreach (PulsePlan::CAPABILITIES as $key => $label) {
            if ($request->boolean('restriction_'.$key)) $permissions[$key] = false;
        }
        return $permissions;
    }
}
