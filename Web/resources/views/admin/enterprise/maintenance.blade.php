@extends('admin.layout')
@section('title','Database & System Updates | ABS Admin')
@section('heading','Database & System Updates')
@section('content')
<div class="enterprise-command-bar">
    <div>
        <h2>Safe Database Maintenance Center</h2>
        <p>Keep an existing live ABS database compatible with the currently deployed build. These tools are designed to preserve users, memberships, CMS content, Pulse records and administrator passwords.</p>
    </div>
</div>

<div class="enterprise-kpi-grid">
    <div class="enterprise-kpi"><small>Build</small><strong style="font-size:18px">{{ $buildVersion }}</strong></div>
    <div class="enterprise-kpi"><small>Schema</small><strong class="{{ $diagnosis['ready'] ? 'positive' : 'negative' }}">{{ $diagnosis['ready'] ? 'READY' : 'ATTENTION' }}</strong></div>
    <div class="enterprise-kpi"><small>Database</small><strong style="font-size:18px">{{ $databaseName }}</strong></div>
    <div class="enterprise-kpi"><small>Pending Migrations</small><strong>{{ count($pendingMigrations) }}</strong></div>
</div>

@if(!$diagnosis['ready'])
<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Detected Schema Differences</h2><p>Use “Repair Schema” or “Apply Latest Database Update” below. Existing rows are preserved.</p></div></div>
    @if($diagnosis['error'])<div class="flash flash-error">{{ $diagnosis['error'] }}</div>@endif
    @if($diagnosis['missing_tables'])<p><b>Missing tables:</b> {{ implode(', ', $diagnosis['missing_tables']) }}</p>@endif
    @if($diagnosis['missing_columns'])
        @foreach($diagnosis['missing_columns'] as $table => $columns)<p><b>{{ $table }}:</b> {{ implode(', ', $columns) }}</p>@endforeach
    @endif
</section>
@endif

<div class="enterprise-section-grid equal">
    <section class="enterprise-surface backup-action-card">
        <div class="enterprise-section-head"><div><h2>Apply Latest Database Update</h2><p>Recommended after uploading a newer ABS build. Repairs required tables/columns, baselines satisfied legacy migrations, runs pending safe migrations, synchronizes reviewed baseline configuration and clears caches.</p></div><span class="backup-icon">↻</span></div>
        <div class="backup-key-warning"><b>Preserves live data</b><span>An automatic database-only safety backup is attempted first. Existing administrator passwords are never reset by routine seeding.</span></div>
        <form method="POST" action="{{ route('admin.enterprise.maintenance.run') }}" class="enterprise-form-grid">@csrf
            <input type="hidden" name="action" value="update_latest">
            <label class="full">Safety confirmation<input type="text" name="confirmation" placeholder="Type UPDATE" autocomplete="off" required></label>
            <div class="full"><button class="button button-primary" onclick="return confirm('Apply the latest ABS database update now?')">Backup & Apply Latest Update</button></div>
        </form>
    </section>

    <section class="enterprise-surface backup-action-card">
        <div class="enterprise-section-head"><div><h2>Database Fix — Repair Everything</h2><p>Use this after any upgrade if ABS reports a missing table/column. It creates a safety backup, repairs all required ABS/Pulse schema, applies safe pending migrations, restores missing baseline records, clears caches and verifies the database is READY.</p></div><span class="backup-icon">⚙</span></div>
        <form method="POST" action="{{ route('admin.enterprise.maintenance.run') }}">@csrf<input type="hidden" name="action" value="repair_schema"><button class="button button-primary">Create Backup & Run Database Fix</button></form>
    </section>
</div>

<div class="enterprise-section-grid equal">
    <section class="enterprise-surface">
        <div class="enterprise-section-head"><div><h2>Content & Configuration Sync</h2><p>Synchronize reviewed default plans, settings and baseline CMS configuration. It updates/creates system baseline records without resetting the password of an existing administrator.</p></div></div>
        <form method="POST" action="{{ route('admin.enterprise.maintenance.run') }}">@csrf<input type="hidden" name="action" value="sync_content"><button class="button button-ghost">Backup & Sync Baseline Content</button></form>
    </section>
    <section class="enterprise-surface">
        <div class="enterprise-section-head"><div><h2>Pending Migrations</h2><p>{{ count($pendingMigrations) }} migration file(s) have not been recorded in the database.</p></div></div>
        @if($pendingMigrations)<p style="max-height:120px;overflow:auto"><code>{{ implode(', ', $pendingMigrations) }}</code></p>@endif
        <form method="POST" action="{{ route('admin.enterprise.maintenance.run') }}">@csrf<input type="hidden" name="action" value="run_migrations"><button class="button button-ghost">Backup & Run Pending Migrations</button></form>
    </section>
</div>

<div class="enterprise-section-grid equal">
    <section class="enterprise-surface">
        <div class="enterprise-section-head"><div><h2>Clear Application Caches</h2><p>Use after changing .env, database settings, routes or public configuration.</p></div></div>
        <form method="POST" action="{{ route('admin.enterprise.maintenance.run') }}">@csrf<input type="hidden" name="action" value="clear_cache"><button class="button button-ghost">Clear Laravel Caches</button></form>
    </section>
    <section class="enterprise-surface">
        <div class="enterprise-section-head"><div><h2>Refresh Live Market Data</h2><p>Forces a new market overview and movers request and displays which providers returned usable data.</p></div></div>
        <form method="POST" action="{{ route('admin.enterprise.maintenance.run') }}">@csrf<input type="hidden" name="action" value="refresh_market"><button class="button button-ghost">Refresh & Test Market Providers</button></form>
    </section>
</div>

@if(session('maintenance_output'))
<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Last Maintenance Result</h2><p>Technical output from the most recent action.</p></div></div>
    <pre style="white-space:pre-wrap;max-height:440px;overflow:auto;background:#06101d;border:1px solid #263750;border-radius:12px;padding:16px;color:#c8d5e8">{{ implode("\n\n", session('maintenance_output')) }}</pre>
</section>
@endif

<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Runtime Reference</h2><p>Useful when troubleshooting a HostGator deployment.</p></div></div>
    <div class="backup-detail-list">
        <div><b>PHP</b><span>{{ $phpVersion }}</span></div>
        <div><b>Cache store</b><span>{{ $cacheStore }}</span></div>
        <div><b>Applied migrations</b><span>{{ count($appliedMigrations) }}</span></div>
        <div><b>Database connection</b><span>{{ $diagnosis['connection'] ?? config('database.default') }}</span></div>
    </div>
</section>
@endsection
