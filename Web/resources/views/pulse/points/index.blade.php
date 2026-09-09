@extends('pulse.layout')
@section('title','Pulse Sparks')
@section('heading','Pulse Sparks')
@section('content')
@php
    $user = auth()->user();
    $balance = (int) $wallet->balance;
    $level = max(1, (int) $user->pulse_level);
    $xp = max(0, (int) $user->pulse_xp);
    $streak = max(0, (int) $user->pulse_streak_days);
    $longestStreak = max(0, (int) $user->pulse_longest_streak);
    $xpProgress = min(100, (int) round(($xp / max(1, (int) $nextLevelXp)) * 100));
    $pendingPurchase = $purchases->first(fn ($purchase) => $purchase->isOpen());
    $unlockedAchievements = $achievements->filter(fn ($item) => (bool) $item['unlock'])->count();
    $completedMissions = $missions->filter(fn ($item) => $item['progress'] && $item['progress']->completed_at)->count();

    $planFeatures = static function ($plan): array {
        $features = [
            'Up to '.number_format(max(1, (int) $plan->max_selected_pairs)).' Admin-selected markets',
            '15M + 4H intelligence across all 15 strategies',
            number_format(max(0, (int) $plan->best_signal_cost_points)).' Sparks only when a new Best Signal unlocks',
        ];
        if ($plan->allows('reports', false)) $features[] = 'Performance reports and signal history';
        if ($plan->allows('testnet_trading', false)) $features[] = 'Protected Binance Practice trading workflow';
        if ($plan->allows('live_trading', false) || $plan->allows('auto_trading', false)) $features[] = 'Live and automatic eligibility behind safety controls';
        return array_slice($features, 0, 5);
    };
@endphp

<div class="pp-page pp-points-page pp-sparks-page">
    <section class="pp-wallet-hero">
        <div class="pp-wallet-copy">
            <span class="pp-wallet-eyebrow">ABS PULSE REWARDS</span>
            <h1>Pulse Sparks</h1>
            <p>Earn Sparks, activate the Pulse access that fits your timeframe, and unlock a new Best Signal only when the market produces a qualifying setup.</p>
            <div class="pp-wallet-trust">
                <span>@include('pulse.partials.icon', ['name' => 'check']) No daily scan or signal limits</span>
                <span>@include('pulse.partials.icon', ['name' => 'shield']) Admin-verified Spark purchases</span>
                <span>@include('pulse.partials.icon', ['name' => 'pulse']) Zero Sparks charged when no signal qualifies</span>
            </div>
            <nav class="pp-sparks-nav" aria-label="Pulse Sparks sections">
                <a href="#spark-packages">Access packages</a>
                <a href="#earn-sparks">Earn Sparks</a>
                <a href="#buy-sparks">Buy Sparks</a>
                <a href="#sparks-activity">Activity</a>
            </nav>
        </div>

        <div class="pp-wallet-balance-card">
            <div class="pp-wallet-balance-label">@include('pulse.partials.icon', ['name' => 'spark']) Available Sparks</div>
            <strong><span data-pulse-sparks-balance>{{ number_format($balance) }}</span> <em>Sparks</em></strong>
            <div class="pp-wallet-lifetime">
                <span><small>Lifetime earned</small><b>{{ number_format((int) $wallet->lifetime_earned) }} Sparks</b></span>
                <span><small>Lifetime used</small><b>{{ number_format((int) $wallet->lifetime_spent) }} Sparks</b></span>
            </div>
            <div class="pp-wallet-actions">
                <a class="pp-button primary" href="#spark-packages">@include('pulse.partials.icon', ['name' => 'diamond']) Activate Pulse</a>
                <a class="pp-button" href="#buy-sparks">@include('pulse.partials.icon', ['name' => 'plus']) Add Sparks</a>
            </div>
        </div>
    </section>

    @if(session('success'))<div class="pulse-notice success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="pulse-notice danger">{{ $errors->first() }}</div>@endif

    <section class="pp-momentum-grid">
        <article class="pp-momentum-card pp-momentum-level">
            <div class="pp-momentum-icon">@include('pulse.partials.icon', ['name' => 'trend'])</div>
            <div><small>Pulse level</small><strong>Level {{ $level }}</strong><span>{{ number_format($xp) }} / {{ number_format((int) $nextLevelXp) }} XP</span></div>
            <div class="pp-xp-track" aria-label="XP progress"><i style="width:{{ $xpProgress }}%"></i></div>
        </article>
        <article class="pp-momentum-card">
            <div class="pp-momentum-icon amber">@include('pulse.partials.icon', ['name' => 'calendar'])</div>
            <div><small>Current streak</small><strong>{{ number_format($streak) }} {{ \Illuminate\Support\Str::plural('day', $streak) }}</strong><span>Personal best: {{ number_format($longestStreak) }} days</span></div>
        </article>
        <article class="pp-momentum-card">
            <div class="pp-momentum-icon green">@include('pulse.partials.icon', ['name' => 'target'])</div>
            <div><small>Reward progress</small><strong>{{ $completedMissions }} missions complete</strong><span>{{ $unlockedAchievements }} achievements unlocked</span></div>
        </article>
        <article class="pp-momentum-card pp-checkin-card">
            <div><small>Daily Spark</small><strong>{{ $checkinClaimed ? 'Today’s reward claimed' : 'Your reward is ready' }}</strong><span>{{ $checkinClaimed ? 'Return tomorrow to keep your streak moving.' : 'Check in now to collect today’s Sparks and XP.' }}</span></div>
            <form method="POST" action="{{ route('pulse.points.check-in') }}">@csrf
                <button class="pp-button primary" @disabled($checkinClaimed)>@include('pulse.partials.icon', ['name' => $checkinClaimed ? 'check' : 'spark']) {{ $checkinClaimed ? 'Claimed' : 'Claim reward' }}</button>
            </form>
        </article>
    </section>


    <section class="pp-points-section pp-rewarded-ad-section" id="rewarded-ads" data-rewarded-ad-card
        data-session-url="{{ route('pulse.points.rewarded-ad.session') }}"
        data-claim-url="{{ route('pulse.points.rewarded-ad.claim') }}">
        <div class="pp-points-section-head">
            <div><span class="pp-section-kicker">WATCH & EARN</span><h2>Rewarded Ads</h2><p>Choose to watch a rewarded ad and earn Pulse Sparks after the ad provider confirms the reward event.</p></div>
            <div class="pp-section-assurance">@include('pulse.partials.icon', ['name' => 'shield']) <span><b>Protected rewards</b><small>Daily cap + cooldown + duplicate protection</small></span></div>
        </div>
        <div class="pp-rewarded-ad-grid">
            <div class="pp-rewarded-ad-copy">
                <span class="pp-rewarded-ad-icon">@include('pulse.partials.icon', ['name' => 'spark'])</span>
                <div>
                    <h3>Earn {{ number_format((int)($rewardedAds['points'] ?? 0)) }} Sparks per completed ad</h3>
                    <p>Rewarded ads are optional. Sparks are credited only after the configured provider grants the reward. Closing early does not earn Sparks.</p>
                    <div class="pp-rewarded-ad-meta">
                        <span><b>{{ number_format((int)($rewardedAds['watched_today'] ?? 0)) }}</b> watched today</span>
                        <span><b>{{ number_format((int)($rewardedAds['remaining_today'] ?? 0)) }}</b> remaining today</span>
                        <span><b>{{ number_format((int)($rewardedAds['daily_limit'] ?? 0)) }}</b> daily limit</span>
                        @if((int)($rewardedAds['xp'] ?? 0)>0)<span><b>+{{ number_format((int)$rewardedAds['xp']) }}</b> XP</span>@endif
                    </div>
                </div>
            </div>
            <div class="pp-rewarded-ad-action">
                @if(($rewardedAds['enabled'] ?? false) && ($rewardedAds['web_ready'] ?? false) && (int)($rewardedAds['remaining_today'] ?? 0)>0)
                    <label class="pp-rewarded-ad-consent"><input type="checkbox" data-rewarded-ad-consent> <span>I choose to view this optional rewarded ad.</span></label>
                    <button type="button" class="pp-button primary" data-rewarded-ad-watch @disabled((int)($rewardedAds['cooldown_remaining'] ?? 0)>0)>@include('pulse.partials.icon', ['name' => 'play']) Watch rewarded ad</button>
                    <p class="pp-rewarded-ad-status" data-rewarded-ad-status>{{ (int)($rewardedAds['cooldown_remaining'] ?? 0)>0 ? 'Available again in '.number_format((int)$rewardedAds['cooldown_remaining']).' seconds.' : 'Tick the consent box, then choose Watch rewarded ad.' }}</p>
                @elseif(!($rewardedAds['enabled'] ?? false))
                    <button type="button" class="pp-button muted" disabled>Rewarded ads paused</button>
                    <p class="pp-rewarded-ad-status" data-rewarded-ad-status>ABS Admin has paused rewarded ads.</p>
                @elseif(!($rewardedAds['web_ready'] ?? false))
                    <button type="button" class="pp-button muted" disabled>Web ads not configured</button>
                    <p class="pp-rewarded-ad-status" data-rewarded-ad-status>Rewarded ads will appear after the web ad unit is configured.</p>
                @else
                    <button type="button" class="pp-button muted" disabled>Daily reward limit reached</button>
                    <p class="pp-rewarded-ad-status" data-rewarded-ad-status>Come back tomorrow for more rewarded ads.</p>
                @endif
                <div class="pp-rewarded-ad-note">@include('pulse.partials.icon', ['name' => 'info']) <span>Availability depends on Google ad inventory, device support and consent requirements. Test inventory can be used before production ad units are enabled.</span></div>
            </div>
        </div>
    </section>

    @if($plans->isNotEmpty())
    <section class="pp-points-section pp-access-packages" id="spark-packages">
        <div class="pp-points-section-head">
            <div><span class="pp-section-kicker">CHOOSE YOUR ACCESS</span><h2>Activate Pulse with Sparks</h2><p>Pick the duration and feature level that suits you. Your wallet is charged only after a secure activation succeeds.</p></div>
            <div class="pp-balance-mini">@include('pulse.partials.icon', ['name' => 'spark']) <span><small>Your balance</small><b>{{ number_format($balance) }} Sparks</b></span></div>
        </div>

        <div class="pp-plan-wallet-grid">
            @foreach($plans as $plan)
                @php
                    $planPrice = max(0, (int) $plan->price_points);
                    $needed = max(0, $planPrice - $balance);
                    $isCurrent = $access?->isActive() && (int) $access->pulse_plan_id === (int) $plan->id;
                    $durationLabel = (int) $plan->access_days === 1 ? '24 HOURS' : number_format((int) $plan->access_days).' DAYS';
                @endphp
                <article class="pp-plan-wallet-card {{ $isCurrent ? 'current' : '' }} {{ $plan->is_featured ? 'featured' : '' }}">
                    @if($plan->badge)<span class="pp-plan-badge">{{ $plan->badge }}</span>@endif
                    <div class="pp-plan-wallet-top">
                        <span class="pp-plan-symbol">@include('pulse.partials.icon', ['name' => 'spark'])</span>
                        <div><small>{{ $durationLabel }}</small><h3>{{ $plan->name }}</h3></div>
                        @if($isCurrent)<span class="pp-status-pill active">Active</span>@endif
                    </div>
                    <p>{{ $plan->description }}</p>
                    <div class="pp-plan-wallet-price"><strong>{{ number_format($planPrice) }} <em>Sparks</em></strong><span>{{ number_format((int) ceil($planPrice / max(1, (int) $plan->access_days))) }} / day</span></div>
                    <ul>
                        @foreach($planFeatures($plan) as $feature)
                            <li>@include('pulse.partials.icon', ['name' => 'check']) {{ $feature }}</li>
                        @endforeach
                    </ul>

                    @if($isCurrent)
                        <span class="pp-plan-wallet-action current">@include('pulse.partials.icon', ['name' => 'check']) Active{{ $access?->ends_at ? ' until '.$access->ends_at->format('d M Y') : ' on your account' }}</span>
                    @elseif($needed === 0)
                        <form method="POST" action="{{ route('pulse.points.plans.activate',$plan) }}">@csrf
                            <input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
                            <button class="pp-button primary pp-plan-wallet-action">@include('pulse.partials.icon', ['name' => 'spark']) Activate for {{ number_format($planPrice) }} Sparks</button>
                        </form>
                    @else
                        <a class="pp-button pp-plan-wallet-action" href="#buy-sparks">@include('pulse.partials.icon', ['name' => 'plus']) Add {{ number_format($needed) }} more Sparks</a>
                    @endif
                </article>
            @endforeach
        </div>
        <div class="pp-package-footnote">@include('pulse.partials.icon', ['name' => 'shield']) Package features and market coverage are configured by ABS Admin. Live and automatic execution always remain behind account, server and risk-control gates.</div>
    </section>
    @endif

    <section class="pp-rewards-grid" id="earn-sparks">
        <article class="pp-points-section">
            <div class="pp-points-section-head compact"><div><span class="pp-section-kicker">EARN SPARKS</span><h2>Missions</h2></div><span>{{ $completedMissions }}/{{ $missions->count() }} complete</span></div>
            <div class="pp-reward-list">
                @forelse($missions as $item)
                    @php
                        $m = $item['mission'];
                        $progress = (int) ($item['progress']?->progress ?? 0);
                        $target = max(1, (int) $m->target_count);
                        $missionPercent = min(100, (int) round(($progress / $target) * 100));
                    @endphp
                    <div class="pp-reward-item">
                        <span class="pp-reward-icon">@include('pulse.partials.icon', ['name' => $missionPercent >= 100 ? 'check' : 'target'])</span>
                        <div class="pp-reward-copy"><div><b>{{ $m->name }}</b><strong>+{{ number_format((int)$m->reward_points) }} Sparks</strong></div><small>{{ $m->description }}</small><div class="pp-reward-progress"><i style="width:{{ $missionPercent }}%"></i></div><em>{{ min($progress,$target) }}/{{ $target }}</em></div>
                    </div>
                @empty
                    <div class="pp-rewards-empty">No active missions right now.</div>
                @endforelse
            </div>
        </article>

        <article class="pp-points-section">
            <div class="pp-points-section-head compact"><div><span class="pp-section-kicker">BUILD MOMENTUM</span><h2>Achievements</h2></div><span>{{ $unlockedAchievements }}/{{ $achievements->count() }} unlocked</span></div>
            <div class="pp-reward-list">
                @forelse($achievements as $item)
                    @php($a = $item['achievement'])
                    <div class="pp-reward-item {{ $item['unlock'] ? 'unlocked' : 'locked' }}">
                        <span class="pp-reward-icon">@include('pulse.partials.icon', ['name' => $item['unlock'] ? 'spark' : 'lock'])</span>
                        <div class="pp-reward-copy"><div><b>{{ $a->name }}</b><strong>+{{ number_format((int)$a->reward_points) }} Sparks</strong></div><small>{{ $a->description }}</small><em>{{ $item['unlock'] ? 'Unlocked' : 'In progress' }}</em></div>
                    </div>
                @empty
                    <div class="pp-rewards-empty">No achievements are currently published.</div>
                @endforelse
            </div>
        </article>
    </section>

    @if($pendingPurchase)
        <section class="pp-purchase-status">
            <span class="pp-purchase-status-icon">@include('pulse.partials.icon', ['name' => 'clock'])</span>
            <div><b>USDT payment awaiting Admin verification</b><small>{{ $pendingPurchase->pack?->name ?? 'Pulse Sparks bundle' }} · {{ number_format($pendingPurchase->totalPoints()) }} Sparks · submitted {{ $pendingPurchase->created_at?->diffForHumans() }}</small></div>
            <span class="pp-status-pill pending">{{ ucfirst(str_replace('_',' ', $pendingPurchase->status)) }}</span>
        </section>
    @endif

    <section class="pp-points-section" id="buy-sparks">
        <div class="pp-points-section-head">
            <div><span class="pp-section-kicker">TOP UP YOUR WALLET</span><h2>Buy Pulse Sparks with USDT</h2><p>Select a Spark bundle, send the exact amount to the published ABS wallet, then submit the transaction for Admin verification.</p></div>
            <div class="pp-section-assurance">@include('pulse.partials.icon', ['name' => 'shield']) <span><b>Admin verified</b><small>Credited exactly once after approval</small></span></div>
        </div>

        @if(data_get($commerce,'points_purchase_enabled',true) && $packs->isNotEmpty())
        <form method="POST" enctype="multipart/form-data" action="{{ route('pulse.points.purchase') }}" class="pp-purchase-flow">@csrf
            <div class="pp-flow-step"><span>1</span><div><b>Choose your Spark bundle</b><small>Bonus Sparks are included automatically.</small></div></div>
            <div class="pp-pack-grid">
                @foreach($packs as $pack)
                <label class="pp-pack-card">
                    <input type="radio" name="pulse_point_pack_id" value="{{ $pack->id }}" @checked($loop->first) required>
                    <span class="pp-pack-check">@include('pulse.partials.icon', ['name' => 'check'])</span>
                    <small>{{ $pack->bonus_points > 0 ? number_format((int)$pack->bonus_points).' bonus Sparks included' : 'Simple wallet top-up' }}</small>
                    <h3>{{ $pack->name }}</h3>
                    <strong>{{ number_format($pack->totalPoints()) }} <em>Sparks</em></strong>
                    <div class="pp-pack-price"><b>{{ number_format((float)$pack->price_usdt,2) }} USDT</b><span>{{ $pack->description }}</span></div>
                </label>
                @endforeach
            </div>

            <div class="pp-flow-step second"><span>2</span><div><b>Send USDT and submit the transaction</b><small>Your Sparks appear after Admin verifies the transfer.</small></div></div>
            <div class="pp-payment-panel">
                <div class="pp-payment-guide">
                    <span class="pp-section-kicker">PAYMENT DESTINATION</span>
                    <h3>Use the exact network and wallet</h3>
                    <p>Every approved transfer is protected by an immutable transaction record, so the same purchase cannot be credited twice.</p>
                    <div class="pp-payment-destination">
                        <div><small>Network</small><b>{{ data_get($commerce,'network') ?: 'Not published' }}</b></div>
                        <div class="wallet"><small>ABS wallet</small><b>{{ data_get($commerce,'wallet_address') ?: 'Not published' }}</b>@if(data_get($commerce,'wallet_address'))<button type="button" data-copy-value="{{ data_get($commerce,'wallet_address') }}">Copy</button>@endif</div>
                    </div>
                    @if(data_get($commerce,'payment_instructions'))<div class="pp-payment-note">@include('pulse.partials.icon', ['name' => 'info']) <span>{{ data_get($commerce,'payment_instructions') }}</span></div>@endif
                </div>

                <div class="pp-payment-form">
                    <label><span>USDT transaction / TXID <b>*</b></span><input name="payment_reference" required maxlength="190" autocomplete="off" placeholder="Paste the blockchain transaction ID"></label>
                    <label><span>Payment proof {{ data_get($commerce,'proof_required') ? '*' : '(optional)' }}</span><input type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.pdf" @required(data_get($commerce,'proof_required'))><small>JPG, PNG or PDF · up to 5 MB</small></label>
                    <label><span>Note to Admin <em>(optional)</em></span><textarea name="user_notes" rows="3" maxlength="2000" placeholder="Add a short note only if needed."></textarea></label>
                    <button class="pp-button primary pp-payment-submit">@include('pulse.partials.icon', ['name' => 'send']) Submit for verification</button>
                    <small class="pp-payment-disclaimer">USDT purchases Pulse Sparks only. Pulse access and Best Signal unlocks use Sparks from your wallet.</small>
                </div>
            </div>
        </form>
        @elseif(!data_get($commerce,'points_purchase_enabled',true))
            <div class="pp-points-empty">@include('pulse.partials.icon', ['name' => 'info']) <div><b>Spark purchases are temporarily paused</b><span>Your existing balance and earned rewards remain available.</span></div></div>
        @else
            <div class="pp-points-empty">@include('pulse.partials.icon', ['name' => 'info']) <div><b>No Spark bundles are currently published</b><span>Admin can publish bundles when purchasing is available.</span></div></div>
        @endif
    </section>

    <section class="pp-points-section" id="sparks-activity">
        <div class="pp-points-section-head">
            <div><span class="pp-section-kicker">WALLET HISTORY</span><h2>Spark activity</h2><p>Every Spark earned, purchased, gifted or used is recorded in your protected wallet ledger.</p></div>
            <div class="pp-section-assurance">@include('pulse.partials.icon', ['name' => 'shield']) <span><b>Ledger protected</b><small>Balance changes are idempotent</small></span></div>
        </div>
        <div class="pp-results-scroll pp-ledger-table-wrap">
            <table class="pp-table pp-ledger-table">
                <thead><tr><th>Date</th><th>Activity</th><th>Change</th><th>Balance after</th></tr></thead>
                <tbody>
                    @forelse($ledger as $entry)
                    <tr>
                        <td>{{ $entry->created_at?->format('d M Y, H:i') }}</td>
                        <td><b>{{ ucwords(str_replace('_',' ',$entry->source)) }}</b><small>{{ $entry->description }}</small></td>
                        <td><span class="pp-ledger-change {{ $entry->amount >= 0 ? 'credit':'debit' }}">{{ $entry->amount >= 0 ? '+':'' }}{{ number_format((int)$entry->amount) }} Sparks</span></td>
                        <td><strong>{{ number_format((int)$entry->balance_after) }} Sparks</strong></td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="pp-empty">Your Spark activity will appear here after your first reward, purchase, gift or unlock.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if($purchases->isNotEmpty())
    <section class="pp-points-section pp-purchase-history">
        <div class="pp-points-section-head compact"><div><span class="pp-section-kicker">VERIFICATION HISTORY</span><h2>USDT purchase requests</h2></div><span>USDT → Sparks only</span></div>
        <div class="pp-results-scroll">
            <table class="pp-table pp-ledger-table">
                <thead><tr><th>Bundle</th><th>Pulse Sparks</th><th>USDT</th><th>Status</th><th>Transaction</th><th></th></tr></thead>
                <tbody>
                    @foreach($purchases as $purchase)
                    <tr>
                        <td><b>{{ $purchase->pack?->name ?? 'Pulse Sparks bundle' }}</b><small>{{ $purchase->created_at?->format('d M Y, H:i') }}</small></td>
                        <td>{{ number_format($purchase->totalPoints()) }} Sparks</td>
                        <td>{{ number_format((float)$purchase->amount_usdt,2) }} USDT</td>
                        <td><span class="pp-status-pill {{ $purchase->status === 'approved' ? 'active' : ($purchase->isOpen() ? 'pending' : 'muted') }}">{{ ucfirst(str_replace('_',' ',$purchase->status)) }}</span></td>
                        <td class="pp-txid">{{ $purchase->payment_reference }}</td>
                        <td>@if($purchase->isOpen())<form method="POST" action="{{ route('pulse.points.purchase.cancel',$purchase) }}">@csrf @method('PATCH')<button class="pp-button muted small">Cancel</button></form>@endif</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/pulse-rewarded-ads-v1508.js') }}?v={{ @filemtime(public_path('assets/js/pulse-rewarded-ads-v1508.js')) ?: '15.0.8' }}" defer></script>
@endpush
