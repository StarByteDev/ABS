<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PulsePlan;
use App\Models\PulseSystemSetting;
use App\Models\PulseUserSetting;
use App\Models\User;
use App\Models\UserServiceAccess;
use App\Services\BrandedMailService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request, BrandedMailService $mail)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:users,email'],
            'country_code' => ['nullable', 'string', 'max:8', 'regex:/^\+[0-9]{1,6}$/'],
            'phone' => ['nullable', 'string', 'max:24', 'regex:/^[0-9\s().-]+$/'],
            'country' => ['nullable', 'string', 'max:80'],
            'password' => ['required', Password::min(8)->mixedCase()->numbers()],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::create([
            'name' => trim($data['name']),
            'email' => $data['email'],
            'country_code' => $data['country_code'] ?? null,
            'phone' => $data['phone'] ?? null,
            'country' => $data['country'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => 'user',
            'status' => 'pending',
        ]);

        $mail->accountActivation($user, $this->activationUrl($user));
        $mail->adminNewRegistration($user, 'mobile_api');
        $token = $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken;

        return response()->json(['data' => [
            'user' => $user,
            'token' => $token,
            'activation_required' => true,
            'message' => 'Check your email and activate the account before using authenticated ABS features.',
        ]], 201);
    }

    public function resendActivation(Request $request, BrandedMailService $mail)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::query()->where('email', strtolower(trim($data['email'])))->first();
        if ($user && ! $user->hasVerifiedEmail()) $mail->accountActivation($user, $this->activationUrl($user));
        return response()->json(['message' => 'If activation is still required, a new secure activation email has been sent.']);
    }

    public function login(Request $request)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required'], 'device_name' => ['nullable', 'string', 'max:120']]);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) return response()->json(['message' => 'Invalid credentials.'], 422);
        if ($user->status === 'pending' && ! $user->hasVerifiedEmail()) return response()->json(['message' => 'Activate your ABS account using the secure link sent to your email.', 'activation_required' => true], 403);
        if ($user->status !== 'active') return response()->json(['message' => 'This account is not currently active.'], 403);
        $user->update(['last_login_at' => now()]);
        return response()->json(['data' => ['user' => $user, 'token' => $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken]]);
    }

    public function forgotPassword(Request $request, BrandedMailService $mail)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::query()->where('email', strtolower(trim($data['email'])))->first();
        if ($user) {
            $token = PasswordBroker::createToken($user);
            $mail->passwordReset($user, route('password.reset', ['token' => $token, 'email' => $user->email]));
        }
        return response()->json(['message' => 'If an ABS account matches that email, secure recovery instructions have been sent.']);
    }

    public function resetPassword(Request $request, BrandedMailService $mail)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);
        $status = PasswordBroker::reset($data, function (User $user, string $password) use ($mail): void {
            $user->forceFill(['password' => Hash::make($password)]);
            $user->setRememberToken(Str::random(60));
            $user->save();
            $user->tokens()->delete();
            event(new PasswordReset($user));
            $mail->passwordChanged($user);
        });
        if ($status !== PasswordBroker::PASSWORD_RESET) return response()->json(['message' => __($status)], 422);
        return response()->json(['message' => 'Password updated successfully. Sign in again on your devices.']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['pulseAccess.plan', 'pulseSettings']);
        return response()->json(['data' => $user]);
    }

    public function sessions(Request $request)
    {
        return response()->json(['data' => $request->user()->tokens()->latest()->get()->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'last_used_at' => $token->last_used_at,
            'created_at' => $token->created_at,
            'current' => (int) optional($request->user()->currentAccessToken())->id === (int) $token->id,
        ])]);
    }

    public function revokeSession(Request $request, int $token)
    {
        $item = $request->user()->tokens()->findOrFail($token);
        $item->delete();
        return response()->json(['message' => 'Session revoked.']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'All mobile/API sessions have been revoked.']);
    }

    private function activationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHours(24), [
            'user' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
            'service' => 'pulse',
        ]);
    }
}
