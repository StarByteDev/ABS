<?php

namespace App\Services;

use App\Models\PulseAchievement;
use App\Models\PulseMission;
use App\Models\PulseRewardClaim;
use App\Models\PulseRewardedAdReceipt;
use App\Models\PulseSignal;
use App\Models\PulseSystemSetting;
use App\Models\PulseUserAchievement;
use App\Models\PulseUserMission;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PulseGamificationService
{
    public function __construct(private readonly PulsePointService $points) {}

    public function checkIn(User $user): array
    {
        $today = today()->toDateString();
        $key = 'checkin:'.$today;
        $existing = PulseRewardClaim::query()->where('user_id', $user->id)->where('reward_type', 'daily_checkin')->where('reward_key', $key)->first();
        if ($existing) {
            return ['claim' => $existing, 'already_claimed' => true, 'user' => $user->fresh()];
        }

        $pp = max(0, (int) PulseSystemSetting::value('daily_checkin_points', 5));
        $xp = max(0, (int) PulseSystemSetting::value('daily_checkin_xp', 10));

        $claim = DB::transaction(function () use ($user, $today, $key, $pp, $xp): PulseRewardClaim {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $last = $locked->pulse_last_checkin_date ? Carbon::parse($locked->pulse_last_checkin_date)->startOfDay() : null;
            $todayDate = today();

            if ($last && $last->equalTo($todayDate)) {
                $claim = PulseRewardClaim::query()->where('user_id', $locked->id)->where('reward_type', 'daily_checkin')->where('reward_key', $key)->first();
                if ($claim) return $claim;
            }

            $streak = ($last && $last->equalTo($todayDate->copy()->subDay())) ? ((int) $locked->pulse_streak_days + 1) : 1;
            $locked->pulse_streak_days = $streak;
            $locked->pulse_longest_streak = max((int) $locked->pulse_longest_streak, $streak);
            $locked->pulse_last_checkin_date = $today;
            $this->addXpToUser($locked, $xp);
            $locked->save();

            $claim = PulseRewardClaim::create([
                'user_id' => $locked->id,
                'reward_type' => 'daily_checkin',
                'reward_key' => $key,
                'points' => $pp,
                'xp' => $xp,
                'meta' => ['streak_days' => $streak],
                'claimed_at' => now(),
            ]);

            if ($pp > 0) {
                $this->points->credit($locked, $pp, 'daily_checkin', 'reward:daily_checkin:'.$locked->id.':'.$today, 'Daily Pulse check-in reward.', 'PulseRewardClaim', $claim->id, ['streak_days' => $streak]);
            }

            return $claim;
        }, 5);

        $fresh = $user->fresh();
        $this->recordEvent($fresh, 'daily_checkin', 1, ['claim_id' => $claim->id]);
        $this->evaluateAchievements($fresh);

        return ['claim' => $claim, 'already_claimed' => false, 'user' => $fresh->fresh()];
    }

    public function recordBestSignal(User $user, PulseSignal $signal): void
    {
        $xp = 15;
        $user->refresh();
        $this->addXp($user, $xp);
        $this->recordEvent($user->fresh(), 'best_signal_unlocked', 1, ['signal_id' => $signal->id]);
        $this->evaluateAchievements($user->fresh());
    }

    public function rewardSocialShare(User $user, PulseSignal $signal, ?string $channel = null): PulseRewardClaim
    {
        if ((int) $signal->user_id !== (int) $user->id) {
            throw new RuntimeException('This signal does not belong to the current account.');
        }

        $key = 'signal:'.$signal->id;
        $existing = PulseRewardClaim::query()->where('user_id', $user->id)->where('reward_type', 'signal_share')->where('reward_key', $key)->first();
        if ($existing) return $existing;

        $pp = max(0, (int) PulseSystemSetting::value('social_share_points', 3));
        $xp = max(0, (int) PulseSystemSetting::value('social_share_xp', 5));

        $claim = DB::transaction(function () use ($user, $signal, $key, $channel, $pp, $xp): PulseRewardClaim {
            $claim = PulseRewardClaim::query()->firstOrCreate(
                ['user_id' => $user->id, 'reward_type' => 'signal_share', 'reward_key' => $key],
                ['points' => $pp, 'xp' => $xp, 'meta' => ['signal_id' => $signal->id, 'channel' => $channel], 'claimed_at' => now()],
            );

            if (! $claim->wasRecentlyCreated) return $claim;

            if ($pp > 0) {
                $this->points->credit($user, $pp, 'signal_share', 'reward:signal_share:'.$user->id.':'.$signal->id, 'Best Signal social-share reward.', 'PulseSignal', $signal->id, ['channel' => $channel]);
            }
            if ($xp > 0) $this->addXp($user, $xp);
            $signal->increment('share_count');
            return $claim;
        }, 5);

        $this->recordEvent($user->fresh(), 'signal_shared', 1, ['signal_id' => $signal->id, 'channel' => $channel]);
        $this->evaluateAchievements($user->fresh());
        return $claim;
    }

    public function rewardVerifiedAd(User $user, string $providerReference, array $meta = []): PulseRewardClaim
    {
        if (! (bool) PulseSystemSetting::value('rewarded_ads_enabled', false)) {
            throw new RuntimeException('Rewarded-ad Pulse Sparks are not enabled.');
        }
        if (! $this->verifyRewardedAd($user, $providerReference, $meta)) {
            throw new RuntimeException('The rewarded-ad completion could not be verified by the server.');
        }

        $providerReference = trim($providerReference);
        $key = 'verified:'.$providerReference;
        $pp = max(0, (int) PulseSystemSetting::value('rewarded_ad_points', 5));
        $xp = max(0, (int) PulseSystemSetting::value('rewarded_ad_xp', 5));

        $claim = DB::transaction(function () use ($user, $providerReference, $key, $pp, $xp, $meta): PulseRewardClaim {
            $claim = PulseRewardClaim::query()->firstOrCreate(
                ['user_id' => $user->id, 'reward_type' => 'rewarded_ad', 'reward_key' => $key],
                ['points' => $pp, 'xp' => $xp, 'provider_reference' => $providerReference, 'meta' => $meta, 'claimed_at' => now()],
            );
            if (! $claim->wasRecentlyCreated) return $claim;
            if ($pp > 0) $this->points->credit($user, $pp, 'rewarded_ad', 'reward:ad:'.$providerReference, 'Verified rewarded-ad completion.', 'PulseRewardClaim', $claim->id, $meta);
            if ($xp > 0) $this->addXp($user, $xp);
            return $claim;
        }, 5);

        if ($claim->wasRecentlyCreated) {
            $this->recordEvent($user->fresh(), 'rewarded_ad', 1, ['provider_reference' => $providerReference]);
            $this->evaluateAchievements($user->fresh());
        }
        return $claim;
    }

    public function recordEvent(User $user, string $eventKey, int $count = 1, array $meta = []): void
    {
        if ($count <= 0) return;
        $missions = PulseMission::query()->where('is_active', true)->where('event_key', $eventKey)->orderBy('sort_order')->get();
        foreach ($missions as $mission) {
            $periodKey = $this->periodKey((string) $mission->period);
            DB::transaction(function () use ($user, $mission, $periodKey, $count, $meta): void {
                $progress = PulseUserMission::query()->firstOrCreate(
                    ['user_id' => $user->id, 'pulse_mission_id' => $mission->id, 'period_key' => $periodKey],
                    ['progress' => 0],
                );
                $progress = PulseUserMission::query()->whereKey($progress->id)->lockForUpdate()->firstOrFail();
                if ($progress->completed_at) return;

                $progress->progress = min((int) $mission->target_count, (int) $progress->progress + $count);
                if ($progress->progress >= (int) $mission->target_count) {
                    $progress->completed_at = now();
                    $progress->claimed_at = now();
                }
                $progress->save();

                if ($progress->completed_at && $progress->claimed_at) {
                    $idempotency = 'mission:'.$user->id.':'.$mission->id.':'.$periodKey;
                    if ((int) $mission->reward_points > 0) {
                        $this->points->credit($user, (int) $mission->reward_points, 'mission', $idempotency, 'Mission completed: '.$mission->name, 'PulseUserMission', $progress->id, $meta);
                    }
                    if ((int) $mission->reward_xp > 0) {
                        $this->addXp($user, (int) $mission->reward_xp, 'mission-xp:'.$progress->id);
                    }
                }
            }, 5);
        }
    }

    public function evaluateAchievements(User $user): void
    {
        $metrics = [
            'best_signals' => PulseSignal::query()->where('user_id', $user->id)->whereNotNull('unlocked_at')->count(),
            'streak_days' => (int) $user->pulse_streak_days,
            'lifetime_points' => (int) $this->points->wallet($user)->lifetime_earned,
        ];

        $achievements = PulseAchievement::query()->where('is_active', true)->orderBy('sort_order')->get();
        foreach ($achievements as $achievement) {
            if (! array_key_exists($achievement->metric_key, $metrics)) continue;
            if ((int) $metrics[$achievement->metric_key] < (int) $achievement->target_value) continue;

            DB::transaction(function () use ($user, $achievement): void {
                $unlock = PulseUserAchievement::query()->firstOrCreate(
                    ['user_id' => $user->id, 'pulse_achievement_id' => $achievement->id],
                    ['unlocked_at' => now(), 'claimed_at' => now()],
                );
                if (! $unlock->wasRecentlyCreated) return;

                if ((int) $achievement->reward_points > 0) {
                    $this->points->credit($user, (int) $achievement->reward_points, 'achievement', 'achievement:'.$user->id.':'.$achievement->id, 'Achievement unlocked: '.$achievement->name, 'PulseUserAchievement', $unlock->id);
                }
                if ((int) $achievement->reward_xp > 0) {
                    $this->addXp($user, (int) $achievement->reward_xp, 'achievement-xp:'.$unlock->id);
                }
            }, 5);
        }
    }

    public function addXp(User $user, int $xp, ?string $idempotencyKey = null): void
    {
        if ($xp <= 0) return;
        DB::transaction(function () use ($user, $xp): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->addXpToUser($locked, $xp);
            $locked->save();
        }, 5);
    }

    public function levelForXp(int $xp): int
    {
        // Smooth progression: every 250 XP adds a level. This affects only
        // gamification display and never strategy scoring, confidence or trading logic.
        return max(1, intdiv(max(0, $xp), 250) + 1);
    }

    public function periodKey(string $period): string
    {
        return match ($period) {
            'weekly' => now()->format('o-\\WW'),
            'monthly' => now()->format('Y-m'),
            'once' => 'once',
            default => today()->toDateString(),
        };
    }

    private function addXpToUser(User $user, int $xp): void
    {
        $user->pulse_xp = max(0, (int) $user->pulse_xp + max(0, $xp));
        $user->pulse_level = $this->levelForXp((int) $user->pulse_xp);
    }

    private function verifyRewardedAd(User $user, string $providerReference, array $meta): bool
    {
        // V15.0.8 accepts only server-trusted provider receipts or the legacy
        // HMAC adapter. Browser/mobile clients never receive a server signing secret.
        $trustedProvider = trim((string) ($meta['trusted_provider'] ?? ''));
        $receiptId = (int) ($meta['receipt_id'] ?? 0);
        if (in_array($trustedProvider, ['google_ad_manager', 'admob'], true) && $receiptId > 0) {
            $receipt = PulseRewardedAdReceipt::query()->find($receiptId);
            if (! $receipt || (int) $receipt->user_id !== (int) $user->id || $receipt->provider !== $trustedProvider) return false;
            if (! in_array($receipt->status, ['verified', 'rewarded'], true) || ! $receipt->verified_at) return false;
            $expectedReference = $trustedProvider === 'admob' ? 'admob:'.$receipt->provider_reference : $receipt->provider_reference;
            return hash_equals($expectedReference, $providerReference);
        }

        // Backward-compatible provider-neutral HMAC verification for existing
        // integrations that already use PULSE_REWARDED_AD_SECRET.
        $secret = trim((string) config('services.rewarded_ads.secret', ''));
        $signature = trim((string) ($meta['signature'] ?? ''));
        if ($secret === '' || $providerReference === '' || $signature === '') return false;

        $expected = hash_hmac('sha256', $providerReference, $secret);
        return hash_equals($expected, $signature);
    }
}
