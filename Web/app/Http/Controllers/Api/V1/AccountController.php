<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NewsArticle;
use App\Models\Product;
use App\Models\PulseUserSetting;
use App\Services\BrandedMailService;
use App\Services\MarketDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function dashboard(Request $request, MarketDataService $market)
    {
        return response()->json(['data' => [
            'user' => $request->user(),
            'watchlist' => $request->user()->watchlists()->orderBy('sort_order')->get(),
            'market' => $market->overview(),
            'news' => NewsArticle::where('status', 'published')->latest('published_at')->take(5)->get(),
            'private_member_enabled' => $request->user()->isPrivateMember(),
        ]]);
    }

    public function updateProfile(Request $request, BrandedMailService $mail)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,'.$request->user()->id],
            'country_code' => ['sometimes', 'nullable', 'string', 'max:8', 'regex:/^\+[0-9]{1,6}$/'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:24', 'regex:/^[0-9\s().-]+$/'],
            'country' => ['sometimes', 'nullable', 'string', 'max:80'],
            'current_password' => ['required_with:password', 'string'],
            'password' => ['sometimes', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);
        $user = $request->user();
        $passwordChanged = isset($data['password']);
        if ($passwordChanged) {
            if (! Hash::check((string) $data['current_password'], $user->password)) {
                return response()->json(['message' => 'Current password is not valid.'], 422);
            }
            $data['password'] = Hash::make($data['password']);
        }
        unset($data['current_password'], $data['password_confirmation']);

        $emailChanged = isset($data['email']) && strtolower((string) $data['email']) !== strtolower((string) $user->email);
        if ($emailChanged) {
            $data['email'] = strtolower(trim((string) $data['email']));
            $data['email_verified_at'] = null;
            $data['status'] = 'pending';
        }

        $user->update($data);
        $user = $user->fresh();

        if ($passwordChanged) $mail->passwordChanged($user);
        if ($emailChanged) {
            $mail->accountActivation($user, URL::temporarySignedRoute('verification.verify', now()->addHours(24), [
                'user' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
                'service' => 'pulse',
            ]));
        }

        return response()->json(['data' => $user, 'email_verification_required' => $emailChanged]);
    }

    public function notifications(Request $request)
    {
        return response()->json($request->user()->notifications()->latest()->paginate(20));
    }

    public function readNotification(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        return response()->json(['data' => $notification]);
    }

    public function notificationPreferences(Request $request)
    {
        $settings = PulseUserSetting::firstOrCreate(['user_id' => $request->user()->id], [
            'environment' => 'testnet',
            'notification_preferences' => $this->defaultNotificationPreferences(),
        ]);
        return response()->json(['data' => array_merge($this->defaultNotificationPreferences(), $settings->notification_preferences ?: [])]);
    }

    public function updateNotificationPreferences(Request $request)
    {
        $data = $request->validate([
            'signals' => ['sometimes', 'boolean'],
            'trades' => ['sometimes', 'boolean'],
            'risk' => ['sometimes', 'boolean'],
            'market' => ['sometimes', 'boolean'],
            'plan_expiry' => ['sometimes', 'boolean'],
            'daily_brief' => ['sometimes', 'boolean'],
            'system' => ['sometimes', 'boolean'],
        ]);
        $settings = PulseUserSetting::firstOrCreate(['user_id' => $request->user()->id], ['environment' => 'testnet']);
        $merged = array_merge($this->defaultNotificationPreferences(), $settings->notification_preferences ?: [], $data);
        $settings->update(['notification_preferences' => $merged]);
        return response()->json(['message' => 'Notification preferences updated.', 'data' => $merged]);
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) return response()->json(['data' => []]);
        $data = collect()
            ->concat(NewsArticle::where('status', 'published')
                ->where(fn ($x) => $x->where('title', 'like', "%$q%")->orWhere('excerpt', 'like', "%$q%"))
                ->limit(10)->get()
                ->map(fn ($x) => ['type' => 'news', 'id' => $x->id, 'slug' => $x->slug, 'title' => $x->title, 'summary' => $x->excerpt]))
            ->concat(Product::where('status', 'live')
                ->whereIn('slug', ['pulse-trading-intelligence', 'private-member-portal'])
                ->where(fn ($x) => $x->where('name', 'like', "%$q%")->orWhere('description', 'like', "%$q%"))
                ->get()
                ->map(fn ($x) => ['type' => 'service', 'id' => $x->id, 'slug' => $x->slug, 'title' => $x->name, 'summary' => $x->tagline]))
            ->take(12)->values();
        return response()->json(['data' => $data]);
    }
    private function defaultNotificationPreferences(): array
    {
        return [
            'signals' => true,
            'trades' => true,
            'risk' => true,
            'market' => true,
            'plan_expiry' => true,
            'daily_brief' => false,
            'system' => true,
        ];
    }

}
