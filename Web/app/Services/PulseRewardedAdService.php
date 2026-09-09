<?php

namespace App\Services;

use App\Models\PulseRewardClaim;
use App\Models\PulseRewardedAdReceipt;
use App\Models\PulseSystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PulseRewardedAdService
{
    public function __construct(private readonly PulseGamificationService $gamification) {}

    public function settings(): array
    {
        return [
            'enabled' => (bool) PulseSystemSetting::value('rewarded_ads_enabled', false),
            'provider' => trim((string) PulseSystemSetting::value('rewarded_ads_provider', 'google_ad_manager')) ?: 'google_ad_manager',
            'points' => max(0, (int) PulseSystemSetting::value('rewarded_ad_points', 5)),
            'xp' => max(0, (int) PulseSystemSetting::value('rewarded_ad_xp', 5)),
            'daily_limit' => max(1, (int) PulseSystemSetting::value('rewarded_ads_daily_limit', 10)),
            'cooldown_seconds' => max(0, (int) PulseSystemSetting::value('rewarded_ads_cooldown_seconds', 120)),
            'web_ad_unit_path' => trim((string) PulseSystemSetting::value('rewarded_web_ad_unit_path', '')),
            'web_test_mode' => (bool) PulseSystemSetting::value('rewarded_web_test_mode', true),
            'mobile_test_mode' => (bool) PulseSystemSetting::value('rewarded_mobile_test_mode', true),
            'android_ad_unit_id' => trim((string) PulseSystemSetting::value('rewarded_admob_android_ad_unit_id', '')),
            'ios_ad_unit_id' => trim((string) PulseSystemSetting::value('rewarded_admob_ios_ad_unit_id', '')),
            'admob_ssv_enabled' => (bool) PulseSystemSetting::value('rewarded_admob_ssv_enabled', true),
            'admob_max_callback_age_seconds' => max(60, (int) PulseSystemSetting::value('rewarded_admob_max_callback_age_seconds', 3600)),
        ];
    }

    public function userStatus(User $user): array
    {
        $settings = $this->settings();
        $watchedToday = PulseRewardClaim::query()
            ->where('user_id', $user->id)
            ->where('reward_type', 'rewarded_ad')
            ->whereDate('claimed_at', today())
            ->count();
        $lastClaim = PulseRewardClaim::query()
            ->where('user_id', $user->id)
            ->where('reward_type', 'rewarded_ad')
            ->latest('claimed_at')
            ->first();
        $cooldownRemaining = 0;
        if ($lastClaim?->claimed_at && $settings['cooldown_seconds'] > 0) {
            $cooldownRemaining = max(0, $settings['cooldown_seconds'] - $lastClaim->claimed_at->diffInSeconds(now()));
        }
        return $settings + [
            'watched_today' => $watchedToday,
            'remaining_today' => max(0, $settings['daily_limit'] - $watchedToday),
            'cooldown_remaining' => $cooldownRemaining,
            'web_ready' => $settings['enabled'] && $settings['provider'] === 'google_ad_manager' && ($settings['web_test_mode'] || $settings['web_ad_unit_path'] !== ''),
            'mobile_ready' => $settings['enabled'] && ($settings['mobile_test_mode'] || $settings['android_ad_unit_id'] !== '' || $settings['ios_ad_unit_id'] !== ''),
        ];
    }

    public function createWebSession(User $user): array
    {
        $status = $this->userStatus($user);
        if (! $status['enabled']) throw new RuntimeException('Rewarded ads are currently paused.');
        if (! $status['web_ready']) throw new RuntimeException('Web rewarded ads are not configured yet.');
        $this->assertEligible($status);

        $reference = 'web-'.Str::uuid();
        $expires = now()->addMinutes(10)->timestamp;
        $payload = [
            'v' => 1,
            'uid' => (int) $user->id,
            'ref' => $reference,
            'exp' => $expires,
            'nonce' => Str::random(24),
            'provider' => 'google_ad_manager',
        ];
        $token = $this->signPayload($payload);

        return [
            'provider_reference' => $reference,
            'claim_token' => $token,
            'ad_unit_path' => $status['web_test_mode'] ? '/22639388115/rewarded_web_example' : $status['web_ad_unit_path'],
            'test_mode' => (bool) $status['web_test_mode'],
            'reward_points' => (int) $status['points'],
            'reward_xp' => (int) $status['xp'],
            'expires_at' => $expires,
        ];
    }

    public function claimWebReward(User $user, string $claimToken, array $providerPayload = []): PulseRewardClaim
    {
        $payload = $this->verifySignedPayload($claimToken);
        if ((int) ($payload['uid'] ?? 0) !== (int) $user->id) throw new RuntimeException('Reward session does not belong to this account.');
        if (($payload['provider'] ?? '') !== 'google_ad_manager') throw new RuntimeException('Reward provider is invalid.');

        $status = $this->userStatus($user);
        if (! $status['enabled']) throw new RuntimeException('Rewarded ads are currently paused.');

        $reference = trim((string) ($payload['ref'] ?? ''));
        if ($reference === '') throw new RuntimeException('Reward session reference is invalid.');
        $knownReceipt = PulseRewardedAdReceipt::query()
            ->where('provider', 'google_ad_manager')
            ->where('provider_reference', $reference)
            ->first();
        if ($knownReceipt && (int) $knownReceipt->user_id !== (int) $user->id) {
            throw new RuntimeException('Reward receipt belongs to another account.');
        }
        if (! $knownReceipt) $this->assertEligible($status);

        $receipt = DB::transaction(function () use ($user, $reference, $providerPayload): PulseRewardedAdReceipt {
            $existing = PulseRewardedAdReceipt::query()->where('provider', 'google_ad_manager')->where('provider_reference', $reference)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->user_id !== (int) $user->id) throw new RuntimeException('Reward receipt belongs to another account.');
                return $existing;
            }
            return PulseRewardedAdReceipt::query()->create([
                'user_id' => $user->id,
                'provider' => 'google_ad_manager',
                'provider_reference' => $reference,
                'ad_unit' => $this->settings()['web_test_mode'] ? '/22639388115/rewarded_web_example' : $this->settings()['web_ad_unit_path'],
                'reward_amount' => (int) ($providerPayload['amount'] ?? 0),
                'reward_item' => trim((string) ($providerPayload['type'] ?? 'reward')) ?: 'reward',
                'status' => 'verified',
                'verification_mode' => 'gpt_reward_granted',
                'meta' => ['provider_payload' => $providerPayload],
                'verified_at' => now(),
            ]);
        }, 5);

        $claim = $this->gamification->rewardVerifiedAd($user, $reference, [
            'trusted_provider' => 'google_ad_manager',
            'receipt_id' => $receipt->id,
            'verification_mode' => 'gpt_reward_granted',
            'provider_payload' => $providerPayload,
        ]);
        $receipt->update(['status' => 'rewarded', 'rewarded_at' => now()]);
        return $claim;
    }

    public function mobileConfig(User $user, string $platform): array
    {
        $status = $this->userStatus($user);
        if (! $status['enabled']) return ['enabled' => false, 'reason' => 'Rewarded ads are paused.'];
        $this->assertEligible($status);
        $platform = strtolower($platform) === 'ios' ? 'ios' : 'android';
        $productionAdUnit = $platform === 'ios' ? $status['ios_ad_unit_id'] : $status['android_ad_unit_id'];
        $testAdUnit = $platform === 'android' ? 'ca-app-pub-3940256099942544/5224354917' : 'ca-app-pub-3940256099942544/1712485313';
        $useTestAds = app()->environment('local', 'testing') || (bool) $status['mobile_test_mode'];
        if (! $useTestAds && $productionAdUnit === '') return ['enabled' => false, 'reason' => strtoupper($platform).' rewarded ad unit is not configured.'];
        $adUnit = $useTestAds ? $testAdUnit : $productionAdUnit;

        $customData = $this->signPayload([
            'v' => 1,
            'uid' => (int) $user->id,
            'exp' => now()->addHour()->timestamp,
            'nonce' => Str::random(24),
            'provider' => 'admob',
        ]);

        return [
            'enabled' => true,
            'provider' => 'admob',
            'platform' => $platform,
            'ad_unit_id' => $adUnit,
            'production_ad_unit_id' => $productionAdUnit,
            'test_ad_unit_id' => $testAdUnit,
            'use_test_ads' => $useTestAds,
            'ssv_custom_data' => $customData,
            'ssv_user_id' => (string) $user->id,
            'reward_points' => (int) $status['points'],
            'reward_xp' => (int) $status['xp'],
            'remaining_today' => (int) $status['remaining_today'],
            'cooldown_remaining' => (int) $status['cooldown_remaining'],
        ];
    }

    public function processAdMobSsv(Request $request): array
    {
        $settings = $this->settings();
        if (! $settings['enabled'] || ! $settings['admob_ssv_enabled']) throw new RuntimeException('AdMob SSV rewards are disabled.');
        $rawQuery = (string) $request->server('QUERY_STRING', '');
        $verified = $this->verifyAdMobSignature($rawQuery);

        $transactionId = trim((string) $request->query('transaction_id', ''));
        $customData = trim((string) $request->query('custom_data', ''));
        $userId = (int) $request->query('user_id', 0);
        $timestampMs = (int) $request->query('timestamp', 0);
        if ($transactionId === '' || $customData === '' || $userId <= 0 || $timestampMs <= 0) throw new RuntimeException('Required AdMob reward parameters are missing.');

        $ageSeconds = abs(now()->timestamp - intdiv($timestampMs, 1000));
        if ($ageSeconds > $settings['admob_max_callback_age_seconds']) throw new RuntimeException('AdMob reward callback is outside the accepted time window.');

        $tokenPayload = $this->verifySignedPayload(rawurldecode($customData));
        if (($tokenPayload['provider'] ?? '') !== 'admob' || (int) ($tokenPayload['uid'] ?? 0) !== $userId) throw new RuntimeException('AdMob custom data does not match the rewarded user.');
        $user = User::query()->findOrFail($userId);

        // AdMob can retry a valid SSV callback. A previously verified transaction must
        // remain idempotently successful even if the member is now inside the cooldown
        // window or has since reached the daily cap. Eligibility applies only to a new
        // transaction; retries complete/return the existing protected reward.
        $knownReceipt = PulseRewardedAdReceipt::query()
            ->where('provider', 'admob')
            ->where('provider_reference', $transactionId)
            ->first();
        if ($knownReceipt && (int) $knownReceipt->user_id !== (int) $user->id) {
            throw new RuntimeException('AdMob transaction belongs to another account.');
        }
        if (! $knownReceipt) $this->assertEligible($this->userStatus($user));

        $receipt = DB::transaction(function () use ($request, $user, $transactionId, $verified): PulseRewardedAdReceipt {
            $existing = PulseRewardedAdReceipt::query()->where('provider', 'admob')->where('provider_reference', $transactionId)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->user_id !== (int) $user->id) throw new RuntimeException('AdMob transaction belongs to another account.');
                return $existing;
            }
            return PulseRewardedAdReceipt::query()->create([
                'user_id' => $user->id,
                'provider' => 'admob',
                'provider_reference' => $transactionId,
                'ad_unit' => trim((string) $request->query('ad_unit', '')),
                'reward_amount' => max(0, (int) $request->query('reward_amount', 0)),
                'reward_item' => trim((string) $request->query('reward_item', '')) ?: 'reward',
                'status' => 'verified',
                'verification_mode' => 'admob_ssv_ecdsa',
                'meta' => ['key_id' => $verified['key_id'], 'ad_network' => $request->query('ad_network'), 'timestamp' => $request->query('timestamp')],
                'verified_at' => now(),
            ]);
        }, 5);

        $claim = $this->gamification->rewardVerifiedAd($user, 'admob:'.$transactionId, [
            'trusted_provider' => 'admob',
            'receipt_id' => $receipt->id,
            'verification_mode' => 'admob_ssv_ecdsa',
            'transaction_id' => $transactionId,
        ]);
        if ($receipt->status !== 'rewarded') $receipt->update(['status' => 'rewarded', 'rewarded_at' => now()]);

        return ['user_id' => $user->id, 'transaction_id' => $transactionId, 'claim_id' => $claim->id, 'points' => (int) $claim->points, 'xp' => (int) $claim->xp];
    }

    private function assertEligible(array $status): void
    {
        if ((int) $status['remaining_today'] <= 0) throw new RuntimeException('Daily rewarded-ad limit reached. Try again tomorrow.');
        if ((int) $status['cooldown_remaining'] > 0) throw new RuntimeException('Please wait '.$status['cooldown_remaining'].' seconds before watching another rewarded ad.');
    }

    private function signPayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $encoded = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $encoded, $this->tokenSecret(), true);
        return $encoded.'.'.rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
    }

    private function verifySignedPayload(string $token): array
    {
        [$encoded, $signature] = array_pad(explode('.', trim($token), 2), 2, '');
        if ($encoded === '' || $signature === '') throw new RuntimeException('Reward token is invalid.');
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $encoded, $this->tokenSecret(), true)), '+/', '-_'), '=');
        if (! hash_equals($expected, $signature)) throw new RuntimeException('Reward token signature is invalid.');
        $json = $this->base64UrlDecode($encoded);
        $payload = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($payload) || (int) ($payload['exp'] ?? 0) < now()->timestamp) throw new RuntimeException('Reward token has expired.');
        return $payload;
    }

    private function base64UrlDecode(string $value): string|false
    {
        $normalized = strtr(trim($value), '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) $normalized .= str_repeat('=', 4 - $padding);
        return base64_decode($normalized, true);
    }

    private function tokenSecret(): string
    {
        $secret = trim((string) config('app.key', ''));
        if ($secret === '') throw new RuntimeException('APP_KEY is required for rewarded-ad tokens.');
        return $secret;
    }

    private function verifyAdMobSignature(string $rawQuery): array
    {
        $signatureMarker = '&signature=';
        $signaturePos = strpos($rawQuery, $signatureMarker);
        if ($signaturePos === false) throw new RuntimeException('AdMob signature parameter is missing.');
        $content = substr($rawQuery, 0, $signaturePos);
        $tail = substr($rawQuery, $signaturePos + 1);
        parse_str($tail, $sigParams);
        $signatureText = (string) ($sigParams['signature'] ?? '');
        $keyId = (string) ($sigParams['key_id'] ?? '');
        if ($signatureText === '' || $keyId === '') throw new RuntimeException('AdMob verification key is missing.');

        $keys = Cache::remember('abs:admob:ssv:keys', now()->addHours(12), function (): array {
            $response = Http::timeout(7)->connectTimeout(3)->get(config('services.rewarded_ads.admob_key_url'));
            if (! $response->successful()) throw new RuntimeException('Unable to retrieve AdMob verification keys.');
            return (array) $response->json('keys', []);
        });
        $key = collect($keys)->first(fn ($item) => (string) ($item['keyId'] ?? '') === $keyId);
        $pem = is_array($key) ? trim((string) ($key['pem'] ?? '')) : '';
        if ($pem === '') throw new RuntimeException('AdMob verification key ID is not trusted.');

        $signature = $this->base64UrlDecode($signatureText);
        if ($signature === false) throw new RuntimeException('AdMob signature encoding is invalid.');
        $publicKey = openssl_pkey_get_public($pem);
        if ($publicKey === false) throw new RuntimeException('AdMob public key could not be loaded.');
        $ok = openssl_verify($content, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) throw new RuntimeException('AdMob SSV signature verification failed.');
        return ['key_id' => $keyId];
    }
}
