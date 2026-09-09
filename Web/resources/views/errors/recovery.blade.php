<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07111f">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>ABS Database Recovery</title>
    <style>
        :root{color-scheme:dark;--bg:#050916;--panel:#0b1226;--line:#26325b;--text:#f7f9ff;--muted:#aeb8d2;--gold:#e8b337;--cyan:#58c8ff;--green:#7ee787;--red:#ff6b74}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(circle at 78% 8%,#15264d 0,transparent 34%),radial-gradient(circle at 12% 85%,#092b55 0,transparent 30%),var(--bg);font-family:Inter,Segoe UI,Arial,sans-serif;color:var(--text);padding:34px}
        .wrap{width:min(1120px,100%);margin:auto}.brand{display:flex;align-items:center;gap:14px;margin-bottom:22px}.mark{width:50px;height:50px;border:1px solid #e8b33788;border-radius:14px;display:grid;place-items:center;font-size:22px;font-weight:900;color:var(--gold);background:#0b1428}.brand b{font-size:20px}.brand small{display:block;color:#80d8ff;letter-spacing:.16em;margin-top:4px}.card{background:linear-gradient(145deg,#0d1530f2,#080d1ef2);border:1px solid var(--line);border-radius:22px;padding:28px;box-shadow:0 30px 90px #0008}.hero{margin-bottom:20px}.tag{display:inline-flex;border:1px solid #e8b33777;color:#f4c85f;border-radius:999px;padding:7px 11px;font-size:12px;font-weight:800}.hero h1{font-size:clamp(30px,4.2vw,50px);margin:16px 0 8px}.hero p{font-size:16px;color:var(--muted);line-height:1.65}.status{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin:20px 0}.box,.action{border:1px solid var(--line);background:#ffffff05;border-radius:16px;padding:18px}.box small{display:block;color:var(--muted);margin-bottom:6px}.actions{display:grid;grid-template-columns:1fr 1fr;gap:18px}.action h2{font-size:21px;margin:0 0 8px}.action p{color:var(--muted);line-height:1.55;min-height:74px}.field{margin:12px 0}.field label{display:block;font-size:13px;font-weight:750;margin-bottom:6px}.field input[type=password],.field input[type=text],.field input[type=file]{width:100%;padding:12px 13px;border-radius:10px;border:1px solid #33446f;background:#040914;color:var(--text)}.check{display:flex;align-items:center;gap:8px;color:var(--muted);font-size:13px;margin:10px 0}.btn{width:100%;border:0;border-radius:10px;padding:13px 16px;font-weight:850;cursor:pointer;background:linear-gradient(135deg,#f3c552,#d79c20);color:#111827}.btn.secondary{background:#14213a;color:#eef4ff;border:1px solid #3c4e7c}.notice{margin:18px 0;padding:15px 17px;border-radius:12px;border:1px solid #31527e;background:#0a1830;color:#cfe8ff;white-space:pre-wrap}.notice.ok{border-color:#2f7245;background:#0a2014;color:#b8f5c7}.notice.bad{border-color:#7b3940;background:#281015;color:#ffc1c6}.warn{border-left:3px solid var(--gold);background:#e8b33710;padding:13px 15px;border-radius:8px;color:#f6d98f;margin:14px 0}.footer{font-size:12px;color:#7785a8;margin-top:18px}.link{color:#71cfff;text-decoration:none}.small{font-size:12px;color:#8491ad}.errors{border:1px solid #8c3b43;background:#2a1117;color:#ffd0d4;padding:12px 15px;border-radius:10px;margin:14px 0}.errors ul{margin:6px 0 0;padding-left:20px}@media(max-width:800px){body{padding:18px}.actions,.status{grid-template-columns:1fr}.action p{min-height:0}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand"><div class="mark">ABS</div><div><b>ALPHA BLOCK SOLUTIONS</b><small>SECURE DATABASE RECOVERY</small></div></div>

    <div class="card hero">
        <span class="tag">V15.0.9 Recovery</span>
        <h1>Repair the current database, initialize an empty database or restore an ABS backup.</h1>
        <p>This recovery page works before user/admin tables exist. It never asks for your MySQL password or APP_KEY in the browser. The database credentials continue to come from your private <code>.env</code>.</p>

        <div class="status">
            <div class="box"><small>Configured connection</small><strong>{{ $diagnosis['connection'] ?: 'Not configured' }}</strong></div>
            <div class="box"><small>Configured database</small><strong>{{ $diagnosis['database'] ?: 'Not configured' }}</strong></div>
        </div>

        @if(!empty($diagnosis['error']))
            <div class="warn"><strong>Database connection problem:</strong> {{ $diagnosis['error'] }}</div>
        @endif

        @if(!$recoveryEnabled)
            <div class="warn"><strong>Recovery is locked.</strong> Add <code>ABS_RECOVERY_KEY=your-own-long-secret</code> to your private <code>.env</code>, then clear Laravel config cache once. Do not share that key.</div>
        @endif

        @if(!empty($validationErrors))
            <div class="errors"><strong>Please correct:</strong><ul>@foreach($validationErrors as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        @if($result)
            <div class="notice {{ $result['ok'] ? 'ok' : 'bad' }}"><strong>{{ $result['title'] }}</strong>\n\n{{ $result['message'] }}</div>
            @if($result['ok'])
                <p><a class="link" href="/">Open Alpha Block Solutions →</a></p>
            @endif
        @endif

        @if(!($diagnosis['ready'] ?? false))
            <section class="action" style="margin-bottom:18px;border-color:#2d7180">
                <h2>Recommended — Fix missing database structure</h2>
                <p>Use this first when your existing ABS database has missing tables or columns. It is non-destructive: existing users, memberships, website content and Pulse data are preserved.</p>
                <form method="post" action="/api/recovery/repair">
                    <div class="field"><label>ABS Recovery Key</label><input type="password" name="recovery_key" autocomplete="off" required></div>
                    <button class="btn" type="submit" {{ $recoveryEnabled ? '' : 'disabled' }}>Fix Missing Tables & Columns</button>
                </form>
            </section>
        @endif

        <div class="actions">
            <section class="action">
                <h2>Option A — Create this empty ABS database</h2>
                <p>Use this only when the selected MySQL database is intentionally empty and you want a clean ABS installation with the reviewed default plans, CMS baseline and configuration.</p>
                <form method="post" action="/api/recovery/initialize">
                    <div class="field"><label>ABS Recovery Key</label><input type="password" name="recovery_key" autocomplete="off" required></div>
                    <button class="btn" type="submit" {{ $recoveryEnabled ? '' : 'disabled' }}>Initialize Empty Database</button>
                </form>
            </section>

            <section class="action">
                <h2>Option B — Restore your existing website backup</h2>
                <p>Use this when you have an <strong>ABS_BACKUP_*.zip</strong> from the old website and want to preserve users, settings, memberships, CMS content and uploaded files.</p>
                <form method="post" action="/api/recovery/restore" enctype="multipart/form-data">
                    <div class="field"><label>ABS Backup ZIP</label><input type="file" name="backup_file" accept=".zip,application/zip" required></div>
                    <div class="field"><label>ABS Recovery Key</label><input type="password" name="recovery_key" autocomplete="off" required></div>
                    <div class="field"><label>Confirmation</label><input type="text" name="confirmation" placeholder="Type RESTORE" required></div>
                    <label class="check"><input type="checkbox" name="restore_uploads" value="1" checked> Restore uploaded CMS/media files into storage/app/public</label>
                    <button class="btn secondary" type="submit" {{ $recoveryEnabled ? '' : 'disabled' }}>Restore Existing ABS Backup</button>
                </form>
                <div class="small">Server limits: upload_max_filesize={{ $uploadMax }}, post_max_size={{ $postMax }}</div>
            </section>
        </div>

        <div class="warn" style="margin-top:20px"><strong>Important:</strong> If restoring an old live ABS backup, keep the same production <code>APP_KEY</code> in the new HostGator <code>.env</code> before restoring. A different APP_KEY can make encrypted exchange credentials unreadable.</div>
        <div class="footer">After successful repair/setup/restore, you may remove <code>ABS_RECOVERY_KEY</code> from <code>.env</code> to lock these recovery actions.</div>
    </div>
</div>
</body>
</html>
