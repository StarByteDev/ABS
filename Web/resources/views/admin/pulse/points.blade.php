@extends('admin.layout')
@section('title','Pulse Sparks')
@section('heading','Pulse Sparks & Rewards')
@section('content')
<section class="enterprise-command-bar compact">
    <div><h2>Pulse Sparks economy control center</h2><p>Manage Spark bundles, gifts, USDT verification, reward settings and the protected wallet ledger. Pulse access packages are activated only with Sparks.</p></div>
    <div class="enterprise-command-actions">
        <a class="button button-ghost" href="{{ route('admin.pulse.plans') }}">Access Packages</a>
        <a class="button button-ghost" href="{{ route('admin.pulse.intelligence') }}">Strategy Intelligence</a>
    </div>
</section>
<section class="enterprise-stat-grid">
    <article><span>Pending verification</span><b>{{ number_format($stats['pending']) }}</b></article>
    <article><span>Total Sparks credited</span><b>{{ number_format($stats['credited_points']) }}</b></article>
    <article><span>Total Sparks used</span><b>{{ number_format($stats['spent_points']) }}</b></article>
    <article><span>Approved USDT</span><b>{{ number_format($stats['purchase_value'],2) }}</b></article>
</section>

<section class="enterprise-surface admin-spark-gift">
    <div class="enterprise-section-head"><div><h2>Gift Sparks to a registered user</h2><p>Find the member by email, enter the gift amount and record a clear reason. The credit is applied once and permanently logged.</p></div><span>ADMIN GIFT</span></div>
    <form method="POST" action="{{ route('admin.pulse.points.gift') }}" class="admin-form-grid">@csrf
        <label>Registered user
            <input type="email" name="email" list="spark-user-emails" required autocomplete="off" placeholder="member@example.com">
            <datalist id="spark-user-emails">@foreach($giftUsers as $giftUser)<option value="{{ $giftUser->email }}">{{ $giftUser->name }}</option>@endforeach</datalist>
            <small>Start typing a name or registered email address.</small>
        </label>
        <label>Sparks to gift<input type="number" name="amount" min="1" max="1000000" value="25" required><small>Credits only; use Balance Correction below for a debit.</small></label>
        <label class="full">Gift reason<input name="reason" required maxlength="500" placeholder="e.g. Welcome reward, service recovery, promotion"></label>
        <div class="full"><button class="button button-primary">Gift Sparks</button></div>
    </form>
</section>

<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>USDT verification queue</h2><p>Approving a request credits the selected Spark bundle exactly once through the immutable ledger.</p></div><span>{{ number_format($stats['pending']) }} WAITING</span></div>
    <div class="enterprise-table-wrap"><table class="enterprise-table">
        <thead><tr><th>User</th><th>Bundle</th><th>Value</th><th>TXID</th><th>Status</th><th>Review</th></tr></thead>
        <tbody>@forelse($purchases as $purchase)
        <tr>
            <td><b>{{ $purchase->user?->name }}</b><small>{{ $purchase->user?->email }}</small></td>
            <td>{{ $purchase->pack?->name ?? 'Spark bundle' }}<small>{{ number_format($purchase->totalPoints()) }} Sparks</small></td>
            <td>{{ number_format((float)$purchase->amount_usdt,2) }} USDT</td>
            <td style="max-width:180px;word-break:break-all">{{ $purchase->payment_reference }}@if($purchase->payment_proof_path)<br><a href="{{ route('admin.pulse.points.purchases.proof',$purchase) }}">View proof</a>@endif</td>
            <td><span class="admin-status {{ $purchase->status==='approved'?'good':($purchase->status==='rejected'?'danger':'warn') }}">{{ ucfirst(str_replace('_',' ',$purchase->status)) }}</span></td>
            <td>@if($purchase->isOpen())
                <form method="POST" action="{{ route('admin.pulse.points.purchases.approve',$purchase) }}" class="admin-inline-review">@csrf<input name="admin_notes" required placeholder="Verification notes"><button class="button button-primary">Approve + Credit</button></form>
                <form method="POST" action="{{ route('admin.pulse.points.purchases.reject',$purchase) }}" class="admin-inline-review secondary">@csrf<input name="admin_notes" required placeholder="Rejection reason"><button class="button button-ghost">Reject</button></form>
            @else<small>{{ $purchase->reviewed_at?->format('d M Y H:i') }}</small>@endif</td>
        </tr>
        @empty<tr><td colspan="6">No Pulse Sparks purchases yet.</td></tr>@endforelse</tbody>
    </table></div>
</section>

<div class="enterprise-section-grid equal">
<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>USDT → Sparks settings</h2><p>These details appear only when a member buys a Spark bundle. USDT never activates a Pulse package directly.</p></div></div>
    <form method="POST" action="{{ route('admin.pulse.points.commerce') }}" class="admin-form-grid">@csrf @method('PUT')
        <label>Spark purchases<select name="point_purchases_enabled"><option value="true" @selected($commerce['enabled'])>Enabled</option><option value="false" @selected(!$commerce['enabled'])>Paused</option></select></label>
        <label>USDT network<input name="usdt_network" value="{{ $commerce['network'] }}" placeholder="e.g. TRC20"></label>
        <label class="full">USDT receiving wallet<input name="usdt_wallet_address" value="{{ $commerce['wallet_address'] }}" autocomplete="off"></label>
        <label>Payment proof<select name="payment_proof_required"><option value="true" @selected($commerce['proof_required'])>Required</option><option value="false" @selected(!$commerce['proof_required'])>Optional</option></select></label>
        <label class="full">Customer instructions<textarea name="usdt_payment_instructions">{{ $commerce['instructions'] }}</textarea></label>
        <div class="full"><button class="button button-primary">Save Spark Commerce</button></div>
    </form>
</section>

<section class="enterprise-surface abs-rewarded-admin">
    <div class="enterprise-section-head"><div><h2>Rewarded Ads Control Center</h2><p>Web rewards use Google Publisher Tag reward events. Mobile rewards use AdMob server-side verification before Sparks are credited.</p></div><span>{{ number_format($rewardedAds['rewarded_today']) }} TODAY</span></div>
    <div class="admin-rewarded-summary">
        <article><span>Rewarded today</span><b>{{ number_format($rewardedAds['rewarded_today']) }}</b><small>verified receipts</small></article>
        <article><span>Rewarded lifetime</span><b>{{ number_format($rewardedAds['rewarded_total']) }}</b><small>protected receipts</small></article>
        <article><span>Reward per ad</span><b>{{ number_format($rewardedAds['points']) }} Sparks</b><small>+{{ number_format($rewardedAds['xp']) }} XP</small></article>
        <article><span>Daily cap</span><b>{{ number_format($rewardedAds['daily_limit']) }}</b><small>per member</small></article>
    </div>
    <form method="POST" action="{{ route('admin.pulse.points.settings') }}" class="admin-form-grid admin-rewarded-form">@csrf @method('PUT')
        <label>Rewarded ads
            <select name="settings[rewarded_ads_enabled]"><option value="1" @selected($rewardedAds['enabled'])>Enabled</option><option value="0" @selected(!$rewardedAds['enabled'])>Paused</option></select>
            <small>Keep paused until consent and production inventory are ready. Test mode can be used first.</small>
        </label>
        <label>Web provider<input name="settings[rewarded_ads_provider]" value="{{ $rewardedAds['provider'] }}" readonly><small>Google Ad Manager / Publisher Tag</small></label>
        <label>Sparks per verified ad<input type="number" min="0" max="100000" name="settings[rewarded_ad_points]" value="{{ $rewardedAds['points'] }}"></label>
        <label>XP per verified ad<input type="number" min="0" max="100000" name="settings[rewarded_ad_xp]" value="{{ $rewardedAds['xp'] }}"></label>
        <label>Daily rewarded-ad limit<input type="number" min="1" max="100" name="settings[rewarded_ads_daily_limit]" value="{{ $rewardedAds['daily_limit'] }}"></label>
        <label>Cooldown between rewards (seconds)<input type="number" min="0" max="86400" name="settings[rewarded_ads_cooldown_seconds]" value="{{ $rewardedAds['cooldown_seconds'] }}"></label>
        <label>Web test mode<select name="settings[rewarded_web_test_mode]"><option value="1" @selected($rewardedAds['web_test_mode'])>Google demo inventory</option><option value="0" @selected(!$rewardedAds['web_test_mode'])>Production ad unit</option></select><small>Use test mode during development.</small></label>
        <label>Mobile test mode<select name="settings[rewarded_mobile_test_mode]"><option value="1" @selected($rewardedAds['mobile_test_mode'])>Google test ad units</option><option value="0" @selected(!$rewardedAds['mobile_test_mode'])>Production ad units</option></select></label>
        <label class="full">Google Ad Manager rewarded web ad unit path<input name="settings[rewarded_web_ad_unit_path]" value="{{ $rewardedAds['web_ad_unit_path'] }}" placeholder="/123456789/abs_rewarded_web"><small>Not required while Web Test Mode is enabled.</small></label>
        <label class="full">Android AdMob rewarded ad unit ID<input name="settings[rewarded_admob_android_ad_unit_id]" value="{{ $rewardedAds['android_ad_unit_id'] }}" placeholder="ca-app-pub-xxxxxxxxxxxxxxxx/yyyyyyyyyy"></label>
        <label class="full">iOS AdMob rewarded ad unit ID<input name="settings[rewarded_admob_ios_ad_unit_id]" value="{{ $rewardedAds['ios_ad_unit_id'] }}" placeholder="ca-app-pub-xxxxxxxxxxxxxxxx/yyyyyyyyyy"></label>
        <label>AdMob SSV verification<select name="settings[rewarded_admob_ssv_enabled]"><option value="1" @selected($rewardedAds['ssv_enabled'])>Required</option><option value="0" @selected(!$rewardedAds['ssv_enabled'])>Disabled</option></select></label>
        <label>Max SSV callback age (seconds)<input type="number" min="60" max="86400" name="settings[rewarded_admob_max_callback_age_seconds]" value="{{ $rewardedAds['max_callback_age'] }}"></label>
        <label class="full">AdMob SSV callback URL<input value="{{ $rewardedAds['ssv_callback_url'] }}" readonly onclick="this.select()"><small>Paste this URL into the server-side verification settings for your rewarded AdMob unit.</small></label>
        <div class="full"><button class="button button-primary">Save Rewarded Ads Configuration</button></div>
    </form>
    <div class="admin-rewarded-receipts">
        <h3>Latest rewarded-ad receipts</h3>
        <div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>User</th><th>Provider</th><th>Verification</th><th>Status</th><th>Reference</th><th>Rewarded</th></tr></thead><tbody>
        @forelse($rewardedAds['recent'] as $receipt)<tr><td>{{ $receipt->user?->email ?? 'Unknown' }}</td><td>{{ strtoupper(str_replace('_',' ',$receipt->provider)) }}</td><td>{{ str_replace('_',' ',$receipt->verification_mode ?? '—') }}</td><td><span class="admin-status {{ $receipt->status==='rewarded'?'good':'warn' }}">{{ ucfirst($receipt->status) }}</span></td><td style="max-width:180px;word-break:break-all">{{ $receipt->provider_reference }}</td><td>{{ $receipt->rewarded_at?->format('d M Y H:i') ?? '—' }}</td></tr>
        @empty<tr><td colspan="6">No rewarded-ad receipts yet.</td></tr>@endforelse
        </tbody></table></div>
    </div>
</section>

<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Missions, rewards & AI settings</h2><p>Configure non-ad Spark earnings, missions and optional AI costs. Rewarded-ad controls are managed separately above.</p></div></div>
    <form method="POST" action="{{ route('admin.pulse.points.settings') }}" class="admin-form-grid">@csrf @method('PUT')
        @foreach($settings as $group => $items)
            <div class="full"><h3>{{ ucfirst($group === 'points' ? 'Sparks' : $group) }}</h3></div>
            @foreach($items as $setting)
                <label>{{ ucwords(str_replace(['points','_'],['Sparks',' '],$setting->key)) }}<input name="settings[{{ $setting->key }}]" value="{{ $setting->value }}"><small>{{ str_replace(['Pulse Points','points','PP'],['Pulse Sparks','Sparks','Sparks'],$setting->description) }}</small></label>
            @endforeach
        @endforeach
        <div class="full"><button class="button button-primary">Save Reward Settings</button></div>
    </form>
</section>
</div>

<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Pulse Sparks bundles</h2><p>Create and maintain the USDT top-up bundles shown in the member wallet.</p></div></div>
    <form method="POST" action="{{ route('admin.pulse.points.packs.store') }}" class="admin-form-grid">@csrf
        <label>Name<input name="name" required placeholder="e.g. Spark Starter"></label><label>Slug<input name="slug"></label>
        <label>Base Sparks<input type="number" name="points" min="1" value="100" required></label><label>Bonus Sparks<input type="number" name="bonus_points" min="0" value="0" required></label>
        <label>USDT price<input type="number" name="price_usdt" min="0.01" step="0.01" required></label><label>Display order<input type="number" name="sort_order" min="0" value="10" required></label>
        <label class="admin-check"><input type="checkbox" name="is_active" value="1" checked><span>Published</span></label>
        <label class="full">Description<textarea name="description" placeholder="Short customer-facing description"></textarea></label>
        <div class="full"><button class="button button-primary">Create Spark Bundle</button></div>
    </form>
    @foreach($packs as $pack)
    <details class="enterprise-plan-editor"><summary><div><h3>{{ $pack->name }}</h3><p>{{ number_format($pack->totalPoints()) }} Sparks · {{ number_format((float)$pack->price_usdt,2) }} USDT</p></div><span>Edit bundle</span></summary>
        <form method="POST" action="{{ route('admin.pulse.points.packs.update',$pack) }}" class="admin-form-grid">@csrf @method('PUT')
            <label>Name<input name="name" value="{{ $pack->name }}" required></label><label>Slug<input name="slug" value="{{ $pack->slug }}"></label>
            <label>Base Sparks<input type="number" name="points" value="{{ $pack->points }}" required></label><label>Bonus Sparks<input type="number" name="bonus_points" value="{{ $pack->bonus_points }}" required></label>
            <label>USDT price<input type="number" step="0.01" name="price_usdt" value="{{ $pack->price_usdt }}" required></label><label>Display order<input type="number" name="sort_order" value="{{ $pack->sort_order }}" required></label>
            <label class="admin-check"><input type="checkbox" name="is_active" value="1" @checked($pack->is_active)><span>Published</span></label>
            <label class="full">Description<textarea name="description">{{ $pack->description }}</textarea></label>
            <div class="full"><button class="button button-primary">Save Bundle</button></div>
        </form>
    </details>
    @endforeach
</section>

<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Balance correction</h2><p>Use only to correct an account balance. Positive values credit Sparks; negative values debit Sparks. Every change is permanently recorded.</p></div></div>
    <form method="POST" action="{{ route('admin.pulse.points.adjust') }}" class="admin-form-grid">@csrf
        <label>User email<input type="email" name="email" list="spark-user-emails" required></label>
        <label>Amount (+ credit / − debit)<input type="number" name="amount" required></label>
        <label class="full">Correction reason<input name="reason" required maxlength="500"></label>
        <div class="full"><button class="button button-ghost">Apply Correction</button></div>
    </form>
</section>

<section class="enterprise-surface no-pad">
    <div class="enterprise-section-head"><div><h2>Latest protected Spark ledger</h2><p>Most recent wallet-changing events across registered users.</p></div></div>
    <div class="enterprise-table-wrap"><table class="enterprise-table"><thead><tr><th>ID</th><th>User</th><th>Source</th><th>Change</th><th>Balance</th><th>Created</th></tr></thead>
    <tbody>@forelse($ledger as $entry)<tr><td>#{{ $entry->id }}</td><td>{{ $entry->user?->email }}</td><td>{{ ucwords(str_replace('_',' ',$entry->source)) }}</td><td>{{ $entry->amount>0?'+':'' }}{{ number_format($entry->amount) }} Sparks</td><td>{{ number_format($entry->balance_after) }} Sparks</td><td>{{ $entry->created_at?->format('d M Y H:i') }}</td></tr>@empty<tr><td colspan="6">No ledger entries.</td></tr>@endforelse</tbody></table></div>
</section>
@endsection
