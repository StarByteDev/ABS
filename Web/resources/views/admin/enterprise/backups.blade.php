@extends('admin.layout')
@section('title','Backup & Restore | ABS Admin')
@section('heading','Backup & Restore')
@section('content')
<div class="enterprise-command-bar">
    <div>
        <h2>Production Backup & Migration Center</h2>
        <p>Create a portable ABS backup before updates or server migration. The archive preserves the complete MySQL database, users, memberships, CMS content, Pulse settings and application settings stored in the database, with an option to include uploaded files.</p>
    </div>
</div>

<div class="enterprise-kpi-grid">
    <div class="enterprise-kpi"><small>Stored Backups</small><strong>{{ count($backups) }}</strong></div>
    <div class="enterprise-kpi"><small>Backup Format</small><strong>ABS ZIP</strong></div>
    <div class="enterprise-kpi"><small>Upload Limit</small><strong style="font-size:20px">{{ $uploadMax }}</strong></div>
    <div class="enterprise-kpi"><small>POST Limit</small><strong style="font-size:20px">{{ $postMax }}</strong></div>
</div>

<div class="enterprise-section-grid equal">
    <section class="enterprise-surface backup-action-card">
        <div class="enterprise-section-head">
            <div><h2>Create Backup</h2><p>Recommended before every deployment, database change or HostGator migration.</p></div>
            <span class="backup-icon">⇩</span>
        </div>
        <div class="backup-detail-list">
            <div><b>Database</b><span>All users, subscriptions, plans, settings, CMS, news, alerts, reports and operational records.</span></div>
            <div><b>Safe configuration snapshot</b><span>Runtime configuration reference without passwords, APP_KEY, API secrets or exchange credentials.</span></div>
            <div><b>Uploads</b><span>Optional storage/app/public files such as proof documents and CMS uploads.</span></div>
        </div>
        <form method="POST" action="{{ route('admin.enterprise.backups.create') }}" class="backup-create-form">@csrf
            <label class="backup-checkbox"><input type="checkbox" name="include_uploads" value="1" checked> Include uploaded/public storage files</label>
            <button class="button button-primary">Create New Backup</button>
        </form>
    </section>

    <section class="enterprise-surface backup-action-card backup-restore-card">
        <div class="enterprise-section-head">
            <div><h2>Restore / Move to HostGator</h2><p>Upload an ABS backup created on your existing server. The HostGator <code>.env</code> remains untouched so its database/SMTP/API credentials stay safe.</p></div>
            <span class="backup-icon">↺</span>
        </div>
        <div class="backup-key-warning"><b>Important: preserve your current APP_KEY</b><span>Encrypted database values can only be read with the same Laravel APP_KEY. Set the source production APP_KEY in HostGator before restore. ABS validates a non-secret key fingerprint and will stop the restore if the keys do not match.</span></div>
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.enterprise.backups.restore') }}" class="enterprise-form-grid">@csrf
            <label class="full">ABS backup ZIP<input type="file" name="backup_file" accept=".zip,application/zip" required><small>Current server limits: upload {{ $uploadMax }}, POST {{ $postMax }}. Increase PHP limits in HostGator if your backup is larger.</small></label>
            <label class="backup-checkbox full"><input type="checkbox" name="restore_uploads" value="1" checked> Restore uploaded/public storage files</label>
            <label class="backup-checkbox full"><input type="checkbox" name="run_migrations" value="1" checked> Run pending Laravel migrations after database restore</label>
            <label class="full">Safety confirmation<input type="text" name="confirmation" placeholder="Type RESTORE" autocomplete="off" required><small>This operation replaces the current database with the contents of the backup.</small></label>
            <div class="full"><button class="button button-primary backup-danger-button" onclick="return confirm('Restore this backup now? The current database will be replaced.')">Validate & Restore Backup</button></div>
        </form>
    </section>
</div>

<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Available Backups</h2><p>Backups are private under storage/app/abs-backups and are only downloadable through authenticated Admin routes.</p></div></div>
    @if(!$backups)
        <div class="backup-empty">No backups have been created yet.</div>
    @else
    <div class="enterprise-table-wrap"><table class="enterprise-table">
        <thead><tr><th>Backup</th><th>Created</th><th>Size</th><th>SHA-256</th><th>Actions</th></tr></thead>
        <tbody>
        @foreach($backups as $backup)
            <tr>
                <td><b>{{ $backup['name'] }}</b><small>Portable ABS database/configuration archive</small></td>
                <td>{{ $backup['created_at'] }}</td>
                <td>{{ number_format($backup['size']/1024/1024,2) }} MB</td>
                <td><code class="backup-hash">{{ $backup['sha256'] }}</code></td>
                <td><div class="backup-row-actions"><a class="button button-ghost" href="{{ route('admin.enterprise.backups.download',$backup['name']) }}">Download</a><form method="POST" action="{{ route('admin.enterprise.backups.destroy',$backup['name']) }}" onsubmit="return confirm('Delete this backup file?')">@csrf @method('DELETE')<button class="button button-ghost">Delete</button></form></div></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</section>

<section class="enterprise-surface backup-hostgator-guide">
    <div class="enterprise-section-head"><div><h2>HostGator Migration — Safe Order</h2><p>The backup system is intentionally environment-safe: your live HostGator credentials are configured separately and are never overwritten by restore.</p></div></div>
    <ol>
        <li>On the current ABS server, open <b>Admin → Backup & Restore</b> and create a full backup with uploads.</li>
        <li>Download the generated ZIP to your computer.</li>
        <li>Deploy the same/newer ABS application code to HostGator and configure its production <code>.env</code> with the HostGator MySQL, APP_URL, SMTP and API credentials.</li>
        <li>Open the HostGator ABS Admin → Backup & Restore page, upload the ZIP, type <b>RESTORE</b> and restore it.</li>
        <li>Keep “Run pending migrations” enabled. After restore, verify login, users, plans, CMS, Pulse access, emails and live market feeds.</li>
    </ol>
</section>
@endsection
