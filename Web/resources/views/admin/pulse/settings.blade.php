@extends('admin.layout')
@section('title','Pulse System Settings')
@section('heading','Pulse System Gates & Alerts')
@section('description','Control Pulse safety gates, operational defaults and targeted customer communications with server protections clearly separated.')
@section('content')
<section class="enterprise-command-bar compact admin-report-command">
    <div>
        <span class="admin-report-eyebrow">PULSE GOVERNANCE · SAFETY CONTROLS</span><h2>Safety-first control layers</h2>
        <p>Database settings can disable features immediately, but cannot enable live or automatic execution while the corresponding server <code>.env</code> gate is false.</p>
    </div>
    <div class="enterprise-command-actions"><a class="button button-primary" href="{{ route('admin.market-data') }}">Market Feed Health</a><a class="button button-ghost" href="{{ route('admin.pulse.logs') }}">Audit Trail</a></div>
</section>

<section class="enterprise-surface admin-insight-band"><div><span class="insight-dot good"></span><p><b>Database control</b><small>Operational features can be disabled immediately from this page.</small></p></div><div><span class="insight-dot warn"></span><p><b>Server authority</b><small>Live and automatic execution still require protected environment gates.</small></p></div><div><span class="insight-dot info"></span><p><b>Audit coverage</b><small>Every settings save and administrator broadcast is recorded.</small></p></div></section>

<form method="POST" action="{{ route('admin.pulse.settings.update') }}">
    @csrf
    @method('PUT')
    @foreach($settings as $group => $items)
        <details class="enterprise-surface enterprise-plan-editor setting-group" @if($loop->first) open @endif>
            <summary><div><h2>{{ ucfirst(str_replace('_',' ',$group)) }}</h2><p>{{ $items->count() }} controlled settings in this operational group.</p></div><span>Review controls</span></summary>
            <div class="admin-form-grid">
                @foreach($items as $setting)
                    <label class="{{ $setting->type === 'json' ? 'full' : '' }}">
                        {{ ucfirst(str_replace('_',' ',$setting->key)) }}
                        @if($setting->description)<small>{{ $setting->description }}</small>@endif
                        @if($setting->type === 'boolean')
                            <select name="settings[{{ $setting->key }}]">
                                <option value="true" @selected(filter_var($setting->value,FILTER_VALIDATE_BOOL))>Enabled</option>
                                <option value="false" @selected(!filter_var($setting->value,FILTER_VALIDATE_BOOL))>Disabled</option>
                            </select>
                        @elseif($setting->type === 'json')
                            <textarea name="settings[{{ $setting->key }}]" rows="4">{{ $setting->value }}</textarea>
                        @else
                            <input name="settings[{{ $setting->key }}]" value="{{ $setting->value }}">
                        @endif
                    </label>
                @endforeach
            </div>
        </details>
    @endforeach
    <div class="admin-page-actions"><button class="button button-primary">Save System Settings</button></div>
</form>

<section class="enterprise-surface admin-form broadcast-control-card">
    <div class="enterprise-section-head"><div><h2>Broadcast Pulse alert</h2><p>Send an account notification to all Pulse users, active users, or one active package.</p></div><span class="report-period-chip">CUSTOMER COMMS</span></div>
    <form method="POST" action="{{ route('admin.pulse.alerts.broadcast') }}" class="admin-form-grid" data-plan-audience-form>
        @csrf
        <label>Audience<select name="audience" data-plan-audience>
            <option value="active">Active Pulse users</option>
            <option value="plan">Users on one active plan</option>
            <option value="all">All users with a Pulse access record</option>
        </select></label>
        <label data-plan-picker hidden>Plan<select name="pulse_plan_id">
            <option value="">Choose plan</option>
            @foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach
        </select></label>
        <label>Type<select name="type">@foreach(['system','market','risk','trade','plan'] as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach</select></label>
        <label>Severity<select name="severity">@foreach(['info','success','warning','danger'] as $severity)<option value="{{ $severity }}">{{ ucfirst($severity) }}</option>@endforeach</select></label>
        <label>Action URL<input name="action_url" placeholder="/pulse/alerts"></label>
        <label class="full">Title<input name="title" required maxlength="160"></label>
        <label class="full">Message<textarea name="message" rows="4" required></textarea></label>
        <label class="full check-label"><input type="checkbox" name="send_email" value="1"><span>Also send this alert by email when Pulse alert email delivery is enabled.</span></label>
        <div class="full admin-page-actions"><button class="button button-primary">Send Alert</button></div>
    </form>
</section>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-plan-audience-form]');
    if (!form) return;
    const audience = form.querySelector('[data-plan-audience]');
    const picker = form.querySelector('[data-plan-picker]');
    const select = picker?.querySelector('select');
    const update = () => {
        const show = audience?.value === 'plan';
        if (picker) picker.hidden = !show;
        if (select) select.required = show;
    };
    audience?.addEventListener('change', update);
    update();
});
</script>
@endpush
@endsection
