@extends('admin.layout')
@section('title','Updates & Recovery | ABS Admin')
@section('heading','Updates & Recovery')
@section('description','Apply a new ABS build with one previous-build rollback point while preserving live production data.')
@section('content')
<div class="release62-hero">
    <div>
        <span>ABS PULSE · RELEASE CONTROL</span>
        <h2>Safe live updates. One rollback point. Live data stays in place.</h2>
        <p>ABS stores only the immediately previous application build. Patch rollback restores application files only; the production database is never replaced by this workflow.</p>
    </div>
    <div class="release62-version"><small>CURRENT BUILD</small><strong>{{ $currentVersion }}</strong><em>{{ $recoveryEnabled ? 'Recovery key ready' : 'Recovery key required' }}</em></div>
</div>

<div class="release62-protection">
    <article><i>◆</i><div><b>Database protected</b><span>Users, payments, trades and live records remain untouched during build rollback.</span></div></article>
    <article><i>↶</i><div><b>One previous build</b><span>Only the latest rollback point is retained. Older application checkpoints are removed.</span></div></article>
    <article><i>⌁</i><div><b>Protected restore</b><span>Restore uses the same ABS recovery key as Database Fix.</span></div></article>
</div>

<div class="release62-grid">
    <section class="release62-card">
        <div class="release62-card-head"><div><span>BEFORE A PATCH</span><h3>Back up current build</h3></div><b class="release62-state safe">CODE ONLY</b></div>
        <p>Save the application that is working now. Creating a new snapshot automatically replaces the older rollback point.</p>
        @if($restorePoint)
            <div class="release62-current-backup">
                <small>ROLLBACK POINT</small>
                <strong>{{ $restorePoint['name'] }}</strong>
                <span>{{ $restorePoint['created_at'] }} · {{ number_format($restorePoint['size']/1024/1024,2) }} MB</span>
            </div>
        @else
            <div class="release62-empty">No previous-build backup is stored yet.</div>
        @endif
        <form method="POST" action="{{ route('admin.enterprise.updates.restore-point') }}">@csrf
            <button class="button button-primary">Back Up Current Build</button>
        </form>
    </section>

    <section class="release62-card">
        <div class="release62-card-head"><div><span>NEW RELEASE</span><h3>Upload new patch</h3></div><b class="release62-state">SAFE STAGING</b></div>
        <p>Upload the complete ABS ZIP. ABS validates protected paths and checks pending migrations before the patch can be installed.</p>
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.enterprise.updates.upload') }}" class="release62-upload">@csrf
            <label><input type="file" name="release_package" accept=".zip,application/zip" required><strong>Select ABS build ZIP</strong><span>Maximum {{ $uploadMax }} · POST limit {{ $postMax }}</span></label>
            <button class="button button-primary">Validate Patch</button>
        </form>
    </section>
</div>

<section class="release62-card release62-stage">
    <div class="release62-card-head"><div><span>READY TO INSTALL</span><h3>Staged patch</h3></div><b class="release62-state {{ count($packages) ? 'safe' : '' }}">{{ count($packages) ? 'VALIDATED' : 'NONE STAGED' }}</b></div>
    @if(!$packages)
        <div class="release62-empty">Upload the next ABS build above. Uploading does not change the live site.</div>
    @else
        @foreach($packages as $package)
        <div class="release62-package">
            <div class="release62-package-main"><small>PACKAGE</small><strong>{{ $package['name'] }}</strong><span>{{ $package['created_at'] }} · {{ number_format($package['size']/1024/1024,2) }} MB</span><code>{{ $package['sha256'] }}</code></div>
            <div class="release62-package-actions">
                <a class="button button-ghost" href="{{ route('admin.enterprise.updates.packages.download',$package['name']) }}">Download</a>
                <form method="POST" action="{{ route('admin.enterprise.updates.install',$package['name']) }}" class="release62-install">@csrf
                    <input name="confirmation" placeholder="Type INSTALL" required autocomplete="off">
                    <button class="button button-primary" onclick="return confirm('Install this ABS patch now? The current application build will be saved first and live database records will remain in place.')">Install Patch</button>
                </form>
                <form method="POST" action="{{ route('admin.enterprise.updates.packages.destroy',$package['name']) }}">@csrf @method('DELETE')<button class="button button-ghost">Remove</button></form>
            </div>
        </div>
        @endforeach
    @endif
</section>

<section class="release62-card release62-restore">
    <div class="release62-card-head"><div><span>ROLLBACK</span><h3>Restore previous build</h3></div><b class="release62-state {{ $restorePoint ? 'safe' : '' }}">{{ $restorePoint ? 'READY' : 'NO BACKUP' }}</b></div>
    <p>If the new build does not behave correctly, restore the last working application files. This does <strong>not</strong> roll back the production database.</p>
    @if($restorePoint)
        <div class="release62-restore-row">
            <div><small>AVAILABLE BUILD BACKUP</small><strong>{{ $restorePoint['name'] }}</strong><span>SHA-256 {{ $restorePoint['sha256'] }}</span></div>
            <form method="POST" action="{{ route('admin.enterprise.updates.restore',$restorePoint['name']) }}" class="release62-restore-form">@csrf
                <input type="password" name="recovery_key" placeholder="ABS Recovery Key" required autocomplete="off">
                <label><input type="checkbox" name="confirmation" value="1" required> Restore application files only; keep the live database unchanged.</label>
                <button class="button release-rollback-button" onclick="return confirm('Restore the previous ABS application build? Live database records will stay unchanged.')">Restore Previous Build</button>
            </form>
        </div>
    @else
        <div class="release62-empty">Create a current-build backup before the next patch to enable one-click rollback.</div>
    @endif
    <div class="release62-emergency">If the Admin interface is unavailable after a patch, open <a href="/api/recovery/build">Protected Build Recovery</a> and use the same recovery key.</div>
</section>

<section class="release62-card">
    <div class="release62-card-head"><div><span>RECENT ACTIVITY</span><h3>Release audit</h3></div><a class="button button-ghost" href="{{ route('admin.enterprise.backups') }}">Database Backups</a></div>
    @if(!$auditEntries)
        <div class="release62-empty">No patch activity has been recorded yet.</div>
    @else
        <div class="release62-audit">
            @foreach($auditEntries as $entry)
                <div><time>{{ isset($entry['time']) ? \Illuminate\Support\Carbon::parse($entry['time'])->format('d M Y · H:i:s') : '—' }}</time><strong>{{ ucwords(str_replace('_',' ',(string)($entry['event'] ?? 'release activity'))) }}</strong><span>{{ data_get($entry,'context.version') ?: data_get($entry,'context.restored_version') ?: ($entry['version'] ?? '') }}</span></div>
            @endforeach
        </div>
    @endif
</section>
@endsection
