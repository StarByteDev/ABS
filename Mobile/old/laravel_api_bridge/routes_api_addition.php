<?php

/*
|--------------------------------------------------------------------------
| Alpha Block Solutions V11.12 Flutter Mobile API Bridge
|--------------------------------------------------------------------------
|
| Add this to routes/api.php, or adapt into routes/web.php.
| This bridge assumes your existing V11.11 models/services exist.
| For Sanctum token authentication, install Laravel Sanctum.
| If you do not want Sanctum yet, you can replace auth:sanctum with auth.
|
*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\BotSetting;
use App\Models\CryptoSignal;
use App\Models\CryptoTrade;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPayment;
use App\Models\CouponCode;
use App\Services\MarketScannerService;

Route::prefix('mobile')->group(function () {

    Route::post('/login', function (Request $request) {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid login details.'], 422);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Your user account is disabled.'], 403);
        }

        $token = method_exists($user, 'createToken')
            ? $user->createToken('mobile')->plainTextToken
            : base64_encode($user->id.'|'.Str::random(60));

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    });

    Route::post('/register', function (Request $request) {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'referral_code' => 'nullable|string|max:50',
        ]);

        $referrer = null;
        if (!empty($data['referral_code'])) {
            $referrer = User::where('referral_code', strtoupper(trim($data['referral_code'])))->first();
        }

        $isFirstUser = User::count() === 0;

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $isFirstUser ? 'admin' : 'user',
            'is_active' => true,
            'accepted_terms_at' => now(),
            'referral_code' => 'USR'.strtoupper(Str::random(8)),
            'referred_by' => $referrer?->id,
        ]);

        $token = method_exists($user, 'createToken')
            ? $user->createToken('mobile')->plainTextToken
            : base64_encode($user->id.'|'.Str::random(60));

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    });

    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/me', function (Request $request) {
            return response()->json(['user' => $request->user()]);
        });

        Route::post('/logout', function (Request $request) {
            if (method_exists($request->user(), 'currentAccessToken') && $request->user()->currentAccessToken()) {
                $request->user()->currentAccessToken()->delete();
            }
            return response()->json(['success' => true]);
        });

        Route::get('/dashboard', function (Request $request) {
            $userId = $request->user()->id;

            return response()->json([
                'stats' => [
                    'signals' => CryptoSignal::where('user_id', $userId)->whereIn('side', ['BUY','SELL'])->where('created_at', '>=', now()->subHour())->count(),
                    'active' => CryptoSignal::where('user_id', $userId)->where('status', 'NEW')->whereIn('side', ['BUY','SELL'])->where('created_at', '>=', now()->subHour())->count(),
                    'trades' => CryptoTrade::where('user_id', $userId)->count(),
                    'pnl' => (float) CryptoTrade::where('user_id', $userId)->sum('pnl'),
                ],
                'signals' => CryptoSignal::where('user_id', $userId)->whereIn('side', ['BUY','SELL'])->latest()->limit(50)->get(),
            ]);
        });

        Route::post('/scan-next-batch', function (Request $request, MarketScannerService $scanner) {
            $result = $scanner->scanNextBatch($request->user()->id);

            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Batch scan completed.',
                'signals' => collect($result['signals'] ?? [])->map(function ($s) {
                    return [
                        'id' => $s->id,
                        'created_at' => optional($s->created_at)->format('d M H:i'),
                        'symbol' => $s->symbol,
                        'strategy' => $s->strategy,
                        'reason' => $s->reason,
                        'side' => $s->side,
                        'entry_price' => $s->entry_price,
                        'stop_loss' => $s->stop_loss,
                        'take_profit' => $s->take_profit,
                        'confidence' => $s->confidence,
                        'status' => $s->status,
                    ];
                })->values(),
                'scanned_symbols' => $result['scanned_symbols'] ?? [],
                'batch_size' => $result['batch_size'] ?? 0,
                'next_index' => $result['next_index'] ?? 0,
                'total_symbols' => $result['total_symbols'] ?? 0,
                'stats' => [
                    'signals' => CryptoSignal::where('user_id', $request->user()->id)->whereIn('side', ['BUY','SELL'])->where('created_at', '>=', now()->subHour())->count(),
                    'active' => CryptoSignal::where('user_id', $request->user()->id)->where('status', 'NEW')->whereIn('side', ['BUY','SELL'])->where('created_at', '>=', now()->subHour())->count(),
                    'trades' => CryptoTrade::where('user_id', $request->user()->id)->count(),
                    'pnl' => (float) CryptoTrade::where('user_id', $request->user()->id)->sum('pnl'),
                ],
            ]);
        });

        Route::get('/subscription-plans', function () {
            return response()->json([
                'plans' => SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get(),
                'payment_network' => BotSetting::getValue('usdt_payment_network', 'TRC20', null),
                'payment_address' => BotSetting::getValue('usdt_payment_address', '', null),
            ]);
        });

        Route::post('/subscription-payment', function (Request $request) {
            $data = $request->validate([
                'subscription_plan_id' => 'required|exists:subscription_plans,id',
                'txid' => 'required|string|max:255',
                'network' => 'required|string|max:50',
                'coupon_code' => 'nullable|string|max:50',
                'referral_code' => 'nullable|string|max:50',
                'proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            ]);

            $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);

            $originalAmount = (float) $plan->price;
            $finalAmount = $originalAmount;
            $couponCode = null;

            if (!empty($data['coupon_code'])) {
                $coupon = CouponCode::where('code', strtoupper(trim($data['coupon_code'])))->first();
                if ($coupon && $coupon->isValid()) {
                    $finalAmount = $coupon->apply($originalAmount);
                    $couponCode = $coupon->code;
                    $coupon->increment('used_count');
                }
            }

            $proofPath = null;
            if ($request->hasFile('proof')) {
                $proofPath = $request->file('proof')->store('subscription-proofs', 'public');
            }

            SubscriptionPayment::create([
                'user_id' => $request->user()->id,
                'subscription_plan_id' => $plan->id,
                'amount_usdt' => $finalAmount,
                'original_amount_usdt' => $originalAmount,
                'network' => strtoupper($data['network']),
                'payment_address' => BotSetting::getValue('usdt_payment_address', '', null),
                'txid' => trim($data['txid']),
                'coupon_code' => $couponCode,
                'referral_code' => !empty($data['referral_code']) ? strtoupper(trim($data['referral_code'])) : null,
                'proof_path' => $proofPath,
                'status' => 'pending',
                'submitted_at' => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Payment submitted for admin review.']);
        });

        Route::get('/profile', function (Request $request) {
            return response()->json([
                'user' => $request->user(),
                'settings' => BotSetting::forUser($request->user()->id),
            ]);
        });

        Route::post('/profile/settings', function (Request $request) {
            $data = $request->only([
                'minimum_confidence',
                'scan_batch_size',
                'scan_frequency_minutes',
                'telegram_enabled',
                'telegram_bot_token',
                'telegram_chat_id',
                'emergency_stop',
            ]);

            foreach ($data as $key => $value) {
                BotSetting::setValue($key, (string) $value, $request->user()->id);
            }

            return response()->json(['success' => true]);
        });
    });
});
