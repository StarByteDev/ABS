<?php

namespace App\Http\Controllers;

use App\Models\PulsePlan;
use App\Models\PulseUserSetting;
use App\Models\User;
use App\Models\UserServiceAccess;
use App\Models\PulseSystemSetting;
use App\Services\BrandedMailService;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        $service = in_array($request->query('service'), ['pulse', 'private'], true) ? $request->query('service') : null;

        $requestedPlan = $request->query('plan');
        $plan = is_string($requestedPlan) ? mb_substr($requestedPlan, 0, 120) : null;
        return view('auth.login', compact('service', 'plan'));
    }

    public function login(Request $request)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The supplied credentials are not valid.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        if ($request->user()->status !== 'active') {
            $pendingActivation = $request->user()->status === 'pending' && ! $request->user()->hasVerifiedEmail();
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors(['email' => $pendingActivation
                ? 'Activate your ABS account using the secure link sent to your email before signing in.'
                : 'This account is not currently active. Contact Alpha Block Solutions if you need assistance.']);
        }
        $request->user()->update(['last_login_at' => now()]);

        // Self-heal first-login Pulse Trial access for a newly activated standard user.
        // This is intentionally limited to accounts that have never had a Pulse
        // entitlement, so an expired or revoked trial can never be re-issued by login.
        if ($request->user()->role === 'user'
            && $request->user()->hasVerifiedEmail()
            && ! $request->user()->pulseAccess()->exists()) {
            $this->activateConfiguredTrial($request->user());
            $request->user()->unsetRelation('pulseAccess');
        }

        if ($request->input('service') === 'pulse') {
            if ($request->filled('plan')) {
                $plan = PulsePlan::query()->where('slug', $request->input('plan'))->where('is_active', true)->where('is_public', true)->where('is_trial', false)->first();
                if ($plan) return redirect()->route('pulse.membership.checkout', $plan);
            }
            if ($request->user()->hasPulseAccess()) {
                return redirect()->route('pulse.dashboard')->with('success', 'Welcome back. Your Pulse dashboard is ready.');
            }

            return redirect()->route('pulse.access')->with('warning', 'Your ABS account is active. Review Pulse plans and access to continue.');
        }

        if ($request->input('service') === 'private') {
            if ($request->user()->isPrivateMember()) {
                return redirect()->route('private.index');
            }

            return redirect()->route('dashboard')->with('warning', 'Private Member Portal access has not been assigned to this account.');
        }

        if ($request->user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        // V14.7.9: the finalized authenticated user experience is Pulse-first.
        // Never let a stale intended URL or the legacy generic member dashboard
        // replace the approved Pulse workspace after a successful user login.
        // A user who also has Private Member access can still open that service
        // from Profile, but Pulse remains the primary signed-in workspace.
        if ($request->user()->hasPulseAccess()) {
            return redirect()->route('pulse.dashboard');
        }

        // Private Member reporting remains available from the account profile or
        // the explicit ?service=private sign-in path. The normal signed-in entry
        // point stays in the finalized Pulse application family for every
        // non-administrator account.
        return redirect()->route('pulse.access');
    }

    public function showRegister(Request $request)
    {
        $service = $request->query('service') === 'pulse' ? 'pulse' : null;

        $requestedPlan = $request->query('plan');
        $plan = is_string($requestedPlan) ? mb_substr($requestedPlan, 0, 120) : null;
        return view('auth.register', compact('service', 'plan'));
    }

    public function showForgotPassword(Request $request)
    {
        $recoveryMode = $request->query('mode') === 'identifier' ? 'identifier' : 'password';

        return view('auth.forgot-password', compact('recoveryMode'));
    }

    public function sendPasswordResetLink(Request $request, BrandedMailService $mail)
    {
        $identifier = strtolower(trim((string) $request->input('recovery_identifier')));
        $request->merge(['recovery_identifier' => $identifier, 'recovery_mode' => 'password']);
        $request->validate(['recovery_identifier' => ['required', 'string', 'max:255']]);

        // Always return the same customer-facing response so this endpoint does
        // not disclose whether an email address or username belongs to an ABS account.
        if ($email = $this->resolveRecoveryEmail($identifier)) {
            $user = User::query()->where('email', $email)->first();
            if ($user) {
                $token = PasswordBroker::createToken($user);
                $mail->passwordReset($user, route('password.reset', ['token' => $token, 'email' => $user->email]));
            }
        }

        return back()->withInput(['recovery_mode' => 'password'])->with(
            'status',
            'If an ABS account matches those details, secure recovery instructions have been sent.',
        );
    }

    public function sendAccountIdentifierReminder(Request $request, BrandedMailService $mail)
    {
        $countryCode = preg_replace('/\s+/', '', trim((string) $request->input('country_code')));
        $phone = trim((string) $request->input('phone'));
        $request->merge([
            'country_code' => $countryCode,
            'phone' => $phone,
            'recovery_mode' => 'identifier',
        ]);
        $request->validate([
            'country_code' => ['required', 'string', 'max:8', 'regex:/^\+[0-9]{1,6}$/'],
            'phone' => ['required', 'string', 'min:6', 'max:24', 'regex:/^[0-9\s().-]+$/'],
        ]);

        $phoneDigits = preg_replace('/\D+/', '', $phone);
        $matches = [];
        foreach (User::query()->where('country_code', $countryCode)->select(['id', 'name', 'email', 'country_code', 'phone'])->cursor() as $candidate) {
            if (preg_replace('/\D+/', '', (string) $candidate->phone) !== $phoneDigits) continue;
            $matches[] = $candidate;
            if (count($matches) > 1) break;
        }
        $user = count($matches) === 1 ? $matches[0] : null;

        if ($user) $mail->accountIdentifierReminder($user);

        return back()->withInput([
            'recovery_mode' => 'identifier',
            'country_code' => $countryCode,
            'phone' => $phone,
        ])->with(
            'status',
            'If an ABS account matches those details, account recovery instructions have been sent.',
        );
    }

    public function showResetPassword(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request, BrandedMailService $mail)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $status = PasswordBroker::reset(
            $credentials,
            function (User $user, string $password) use ($mail): void {
                $user->forceFill(['password' => Hash::make($password)]);
                $user->setRememberToken(Str::random(60));
                $user->save();

                event(new PasswordReset($user));
                $mail->passwordChanged($user);
            },
        );

        if ($status === PasswordBroker::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Your password has been updated. You can now sign in securely.');
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }

    public function register(Request $request, BrandedMailService $mail)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
            'country_code' => preg_replace('/\s+/', '', trim((string) $request->input('country_code'))),
            'phone' => trim((string) $request->input('phone')),
            'country' => trim((string) $request->input('country')),
        ]);
        $countryNames = array_column(config('countries', []), 'name');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'country_code' => ['required', 'string', 'max:8', 'regex:/^\+[0-9]{1,6}$/'],
            'phone' => ['required', 'string', 'min:6', 'max:24', 'regex:/^[0-9\s().-]+$/'],
            'country' => ['required', 'string', 'max:80', Rule::in($countryNames)],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'terms' => ['accepted'],
            'service' => ['nullable', 'in:pulse'],
            'plan' => ['nullable', 'string', 'max:120'],
        ]);
        $user = User::create([
            'name' => trim($data['name']),
            'email' => $data['email'],
            'country_code' => $data['country_code'],
            'phone' => $data['phone'],
            'country' => $data['country'],
            'password' => Hash::make($data['password']),
            'role' => 'user',
            'status' => 'pending',
        ]);

        $routeContext = array_filter([
            'service' => ($data['service'] ?? null) === 'pulse' ? 'pulse' : null,
            'plan' => $data['plan'] ?? null,
        ]);
        $activationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            array_merge([
                'user' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ], $routeContext),
        );
        $mail->accountActivation($user, $activationUrl);
        $mail->adminNewRegistration($user, 'web');

        return redirect()->route('login', $routeContext)->with(
            'status',
            'Your ABS account has been created. Check your email and activate the account before signing in.',
        );
    }

    public function verifyEmail(Request $request, User $user, string $hash, BrandedMailService $mail)
    {
        abort_unless(hash_equals($hash, sha1($user->getEmailForVerification())), 403);

        $trialActive = false;
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            $user->forceFill(['status' => 'active'])->save();
            $trialActive = $this->activateConfiguredTrial($user);
            $mail->welcome($user, $trialActive);
        }

        $routeContext = array_filter([
            // When activation successfully provisions the standard Pulse Trial,
            // return the user through the Pulse-aware sign-in path so the first
            // authenticated screen is the premium Pulse workspace rather than a
            // generic account landing page.
            'service' => ($request->query('service') === 'pulse' || $trialActive) ? 'pulse' : null,
            'plan' => $request->query('plan'),
        ]);

        return redirect()->route('login', $routeContext)->with(
            'status',
            $trialActive
                ? 'Your ABS account is active and your Pulse Trial is ready. Sign in to continue.'
                : 'Your ABS account is active. Sign in to continue.',
        );
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }

    private function activateConfiguredTrial(User $user): bool
    {
        // Trial auto-assignment is for standard end-user accounts only. Admin and
        // Private Member roles have their own access models and must not be silently
        // converted into a consumer trial.
        if ($user->role !== 'user') return false;
        if (! (bool) PulseSystemSetting::value('trial_auto_assign_enabled', true)) return false;

        $trial = PulsePlan::query()
            ->where('is_active', true)
            ->where('is_trial', true)
            ->orderBy('sort_order')
            ->first();
        if (! $trial) return false;

        $trialDays = max(1, (int) PulseSystemSetting::value('trial_duration_days', 7));
        UserServiceAccess::updateOrCreate(
            ['user_id' => $user->id, 'service' => 'pulse'],
            [
                'status' => 'active',
                'pulse_plan_id' => $trial->id,
                'starts_at' => now(),
                'ends_at' => now()->addDays($trialDays),
                'trial_used_at' => now(),
                'permissions' => [],
                'notes' => null,
            ],
        );

        PulseUserSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'environment' => 'testnet',
                'execution_mode' => 'signal_only',
                'auto_trade_enabled' => false,
                'emergency_stop' => false,
                'default_leverage' => 3,
                'margin_type' => 'ISOLATED',
                'position_mode' => 'BOTH',
                'risk_per_trade_percent' => 1,
                'sizing_mode' => 'fixed_notional',
                'fixed_notional' => 25,
                'minimum_signal_score' => (float) ($trial->minimum_signal_score ?? 70),
                'default_order_type' => 'MARKET',
                'take_profit_percent' => 2,
                'stop_loss_percent' => 1,
                'daily_loss_limit' => 0,
                'max_open_positions' => min(2, max(1, (int) $trial->max_open_trades)),
                'selected_pairs' => ['BTCUSDT', 'ETHUSDT', 'SOLUSDT'],
                'notification_preferences' => ['signals' => true, 'trades' => true, 'risk' => true, 'market' => true, 'plan_expiry' => true, 'daily_brief' => false, 'system' => true],
            ],
        );

        return true;
    }

    private function resolveRecoveryEmail(string $identifier): ?string
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) return $identifier;
        if (! preg_match('/^[a-z0-9][a-z0-9._-]{1,63}$/i', $identifier)) return null;

        // A username is the portion before @ in the registered sign-in email.
        // Only a unique match is accepted, and the public response stays generic.
        $matches = User::query()
            ->select('email')
            ->where('email', 'like', $identifier.'@%')
            ->pluck('email')
            ->filter(fn (string $email): bool => strtolower(Str::before($email, '@')) === $identifier)
            ->values();

        return $matches->count() === 1 ? strtolower((string) $matches->first()) : null;
    }
}
