@extends('admin.layout')
@section('title','Website & Mobile Settings — ABS Admin')
@section('heading','Website & Mobile Settings')
@section('content')
<section class="panel admin-page-intro"><div><span class="admin-kicker">APPLICATION CONFIGURATION</span><h2>Public website and mobile-app settings</h2><p>Manage safe presentation and mobile configuration values. Secrets, exchange credentials and server environment values stay outside this CMS.</p></div></section>
<form method="POST" action="{{ route('admin.enterprise.settings.update') }}">@csrf @method('PUT')
    @forelse($settings as $group => $items)
        <section class="panel admin-form"><h2>{{ ucwords(str_replace('_',' ',$group)) }}</h2><div class="admin-form-grid">
            @foreach($items as $setting)
                <label class="{{ $setting->type==='json' ? 'full' : '' }}">{{ ucwords(str_replace('_',' ',$setting->key)) }}<small>Type: {{ $setting->type }}</small>
                    @if($setting->type==='boolean')
                        <select name="settings[{{ $setting->key }}]"><option value="1" @selected(filter_var($setting->value,FILTER_VALIDATE_BOOL))>Enabled</option><option value="0" @selected(!filter_var($setting->value,FILTER_VALIDATE_BOOL))>Disabled</option></select>
                    @elseif($setting->type==='json')
                        <textarea name="settings[{{ $setting->key }}]" rows="5">{{ $setting->value }}</textarea>
                    @else
                        <input name="settings[{{ $setting->key }}]" value="{{ $setting->value }}">
                    @endif
                </label>
            @endforeach
        </div></section>
    @empty
        <section class="panel"><div class="empty-state"><h2>No site settings are stored yet.</h2><p>Seed the reviewed V14.7.0 configuration with <code>php artisan abs:repair --seed</code>.</p></div></section>
    @endforelse
    <div class="admin-page-actions"><button class="button button-primary">Save Settings</button></div>
</form>
@endsection
