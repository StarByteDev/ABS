@extends('admin.layout')
@section('title','System Updates & Rollback | ABS Admin')
@section('heading','System Updates & Rollback')
@section('content')
<div class="enterprise-command-bar release-command-bar">
    <div>
        <span class="release-eyebrow">PRODUCTION RELEASE CONTROL</span>
        <h2>Safe application upgrades with one-click rollback</h2>
        <p>Upload a complete ABS release package from this page. Before installation ABS automatically captures the current application code, MySQL database and uploaded files so you can return to the previous working release if needed.</p>
    </div>
    <div class="release-version-chip"><small>CURRENT BUILD</small><strong>{{ $currentVersion }}</strong><span>Environment files stay protected</span></div>
</div>

<div class="enterprise-kpi-grid release-kpis">
    <div class="enterprise-kpi"><small>Current Build</small><strong style="font-size:18px">{{ $currentVersion }}</strong></div>
    <div class="enterprise-kpi"><small>Staged Packages</small><strong>{{ count($packages) }}</strong></div>
    <div class="enterprise-kpi"><small>Restore Points</small><strong>{{ count($restorePoints) }}</strong></div>
    <div class="enterprise-kpi"><small>Database Backups</small><strong>{{ count($databaseBackups) }}</strong></div>
</div>

<div class="release-flow">
    <section class="enterprise-surface release-step-card">
        <div class="release-step-number">01</div>
        <div class="enterprise-section-head"><div><h2>Upload New ABS Package</h2><p>Stage and validate the ZIP first. Uploading does not change the live site.</p></div><span class="release-state safe">SAFE STAGING</span></div>
        <div class="release-protection-list">
            <span><b>Validated structure</b><small>ABS verifies application folders and BUILD_VERSION.txt before accepting the package.</small></span>
            <span><b>Environment protected</b><small><code>.env</code>, storage, vendor and server-specific runtime data cannot be overwritten by a release ZIP.</small></span>
            <span><b>Private storage</b><small>Staged packages are stored under protected Laravel storage, not the public web root.</small></span>
        </div>
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.enterprise.updates.upload') }}" class="release-upload-form">@csrf
            <label class="release-dropzone"><input type="file" name="release_package" accept=".zip,application/zip" required><b>Select ABS release ZIP</b><span>Maximum upload {{ $uploadMax }} · POST limit {{ $postMax }}</span></label>
            <button class="button button-primary">Validate & Stage Package</button>
        </form>
    </section>

    <section class="enterprise-surface release-step-card">
        <div class="release-step-number">02</div>
        <div class="enterprise-section-head"><div><h2>Create Restore Point</h2><p>Use this anytime. Installation also creates one automatically before touching production files.</p></div><span class="release-state">CODE + DB + FILES</span></div>
        <div class="release-restore-visual"><strong>FULL SNAPSHOT</strong><div><span>Application Code</span><i>+</i><span>MySQL Database</span><i>+</i><span>Uploads</span></div><small>APP_KEY and live .env remain on the server and are never copied into an update package.</small></div>
        <form method="POST" action="{{ route('admin.enterprise.updates.restore-point') }}">@csrf<button class="button button-primary">Create Full Restore Point Now</button></form>
        <a class="button button-ghost" href="{{ route('admin.enterprise.backups') }}">Open Database-Only Backups</a>
    </section>
</div>

<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Staged Release Packages</h2><p>Install only after reviewing the package name and SHA-256. Every install creates a pre-upgrade restore point automatically and rolls back automatically if deployment or migrations fail.</p></div></div>
    @if(!$packages)
        <div class="backup-empty">No release package is staged. Upload the next ABS build above.</div>
    @else
    <div class="enterprise-table-wrap"><table class="enterprise-table release-table"><thead><tr><th>Release Package</th><th>Uploaded</th><th>Size</th><th>SHA-256</th><th>Install</th></tr></thead><tbody>
        @foreach($packages as $package)<tr>
            <td><b>{{ $package['name'] }}</b><small>Validated staged ABS package</small></td>
            <td>{{ $package['created_at'] }}</td><td>{{ number_format($package['size']/1024/1024,2) }} MB</td>
            <td><code class="backup-hash">{{ $package['sha256'] }}</code></td>
            <td><div class="release-package-actions"><a class="button button-ghost" href="{{ route('admin.enterprise.updates.packages.download',$package['name']) }}">Download</a><form method="POST" action="{{ route('admin.enterprise.updates.install',$package['name']) }}" class="release-install-form">@csrf<input name="confirmation" placeholder="Type INSTALL" required autocomplete="off"><button class="button button-primary" onclick="return confirm('Install this ABS release now? A full automatic restore point will be created first.')">Install</button></form><form method="POST" action="{{ route('admin.enterprise.updates.packages.destroy',$package['name']) }}">@csrf @method('DELETE')<button class="button button-ghost" onclick="return confirm('Delete this staged package?')">Delete</button></form></div></td>
        </tr>@endforeach
    </tbody></table></div>
    @endif
</section>

<section class="enterprise-surface">
    <div class="enterprise-section-head"><div><h2>Application Restore Points</h2><p>These are complete rollback checkpoints: previous ABS application code + database + uploads. Restoring one also creates a safety point of the current state first.</p></div><span class="release-state safe">ROLLBACK READY</span></div>
    @if(!$restorePoints)
        <div class="backup-empty">No full restore point exists yet. Create one above or install a future update to generate one automatically.</div>
    @else
    <div class="enterprise-table-wrap"><table class="enterprise-table release-table"><thead><tr><th>Restore Point</th><th>Created</th><th>Size</th><th>SHA-256</th><th>Recovery</th></tr></thead><tbody>
        @foreach($restorePoints as $point)<tr>
            <td><b>{{ $point['name'] }}</b><small>Complete application recovery checkpoint</small></td><td>{{ $point['created_at'] }}</td><td>{{ number_format($point['size']/1024/1024,2) }} MB</td><td><code class="backup-hash">{{ $point['sha256'] }}</code></td>
            <td><div class="release-package-actions"><a class="button button-ghost" href="{{ route('admin.enterprise.updates.restore-points.download',$point['name']) }}">Download</a><form method="POST" action="{{ route('admin.enterprise.updates.restore',$point['name']) }}" class="release-install-form">@csrf<input name="confirmation" placeholder="Type ROLLBACK" required autocomplete="off"><button class="button release-rollback-button" onclick="return confirm('Rollback the live application and database to this restore point?')">Rollback</button></form><form method="POST" action="{{ route('admin.enterprise.updates.restore-points.destroy',$point['name']) }}">@csrf @method('DELETE')<button class="button button-ghost" onclick="return confirm('Delete this restore point permanently?')">Delete</button></form></div></td>
        </tr>@endforeach
    </tbody></table></div>
    @endif
</section>

<section class="enterprise-surface release-safety-guide">
    <div class="enterprise-section-head"><div><h2>Recommended Live Upgrade Flow</h2><p>Designed for your existing production website so updates are reversible without manually copying folders or importing SQL.</p></div></div>
    <div class="release-timeline">
        <div><b>1</b><span><strong>Upload</strong><small>Stage the new complete ABS ZIP.</small></span></div><i>→</i>
        <div><b>2</b><span><strong>Validate</strong><small>Structure and protected-path checks run automatically.</small></span></div><i>→</i>
        <div><b>3</b><span><strong>Auto Backup</strong><small>Current code + DB + uploads become a restore point.</small></span></div><i>→</i>
        <div><b>4</b><span><strong>Install</strong><small>Files overlay safely; migrations run; cache clears.</small></span></div><i>→</i>
        <div><b>5</b><span><strong>Rollback</strong><small>If installation fails, ABS automatically restores the previous checkpoint.</small></span></div>
    </div>
</section>
@endsection
