<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07111f">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
<title>ABS Database Self-Repair</title>
    <style>
        :root{color-scheme:dark;--bg:#04101a;--panel:#071826;--panel2:#0a1f30;--line:#214055;--line2:#315971;--text:#f5f8fb;--muted:#9fb2c0;--cyan:#12c7de;--gold:#e7a822;--green:#6be675;--red:#ff6f79}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(circle at 85% 0,#0b3846 0,transparent 30%),radial-gradient(circle at 0 80%,#102b3e 0,transparent 32%),var(--bg);font-family:Inter,Segoe UI,Arial,sans-serif;color:var(--text);padding:30px;display:grid;place-items:center}
        .card{width:min(960px,100%);background:linear-gradient(145deg,#081a28f5,#06131ff5);border:1px solid var(--line);border-radius:22px;box-shadow:0 28px 90px #0008;overflow:hidden}.top{padding:30px 32px 22px;border-bottom:1px solid var(--line)}.brand{display:flex;gap:13px;align-items:center}.logo{width:44px;height:44px;border:1px solid #d99d2b88;border-radius:12px;display:grid;place-items:center;color:#f0b63a;font-weight:900}.brand b{font-size:17px}.brand small{display:block;color:#6fd7e5;letter-spacing:.15em;margin-top:3px}.tag{display:inline-flex;margin-top:22px;padding:6px 10px;border:1px solid #d99d2b66;border-radius:999px;color:#efbe5e;background:#d99d2b0d;font-size:11px;font-weight:800;letter-spacing:.08em}.top h1{font-size:clamp(28px,4vw,44px);margin:13px 0 8px;line-height:1.08}.top p{color:var(--muted);line-height:1.65;margin:0;max-width:790px}.body{padding:26px 32px 32px}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px}.stat{border:1px solid var(--line);background:#ffffff04;border-radius:14px;padding:15px}.stat small{display:block;color:var(--muted);margin-bottom:6px}.stat strong{font-size:17px;word-break:break-word}.repair{border:1px solid #21718a;background:linear-gradient(145deg,#082332,#071a27);border-radius:18px;padding:22px}.repair-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start}.repair h2{margin:0 0 7px;font-size:23px}.repair p{margin:0;color:var(--muted);line-height:1.6}.badge{white-space:nowrap;border:1px solid #2f7e50;color:#8ff09a;background:#0d2b19;border-radius:999px;padding:6px 10px;font-size:11px;font-weight:800}.form{display:grid;grid-template-columns:1fr auto;gap:12px;margin-top:20px}.field input{width:100%;height:46px;border-radius:10px;border:1px solid var(--line2);background:#03101a;color:var(--text);padding:0 14px;font-size:14px;outline:none}.field input:focus{border-color:var(--cyan);box-shadow:0 0 0 3px #12c7de15}.btn{height:46px;border:0;border-radius:10px;padding:0 22px;background:linear-gradient(135deg,#12c7de,#1da8cb);color:#041018;font-weight:900;cursor:pointer}.btn:disabled{cursor:not-allowed;opacity:.42}.safe{display:flex;gap:10px;margin-top:14px;color:#a8c9b2;font-size:12px;line-height:1.5}.safe i{color:var(--green);font-style:normal;font-weight:900}.notice,.errors,.warn{margin:0 0 18px;padding:14px 16px;border-radius:12px;white-space:pre-wrap}.notice{border:1px solid #315d74;background:#0a1b29;color:#d6ebf4}.notice.ok{border-color:#34754a;background:#0a2114;color:#c0f6ca}.notice.bad,.errors{border-color:#7e3a43;background:#281016;color:#ffd0d4}.warn{border:1px solid #7c632e;background:#241d0c;color:#f5d991}.errors ul{margin:7px 0 0;padding-left:20px}.details{margin-top:18px;border:1px solid var(--line);border-radius:14px;background:#ffffff03;padding:0 17px}.details summary{cursor:pointer;padding:15px 0;color:#c7d9e4;font-weight:750}.details .inside{padding:0 0 17px}.details h3{font-size:13px;color:#e8b34f;margin:10px 0 7px}.details ul{columns:2;color:var(--muted);margin:0;padding-left:20px;line-height:1.65}.footer{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-top:18px;color:#788e9c;font-size:12px}.footer a{color:#62d7e8;text-decoration:none}.open{display:inline-flex;margin-top:14px;color:#7de0ee;text-decoration:none;font-size:13px;font-weight:750}@media(max-width:780px){body{padding:14px}.top,.body{padding-left:20px;padding-right:20px}.stats{grid-template-columns:1fr 1fr}.form{grid-template-columns:1fr}.btn{width:100%}.repair-head{display:block}.badge{display:inline-flex;margin-top:12px}.details ul{columns:1}.footer{display:block}.footer a{display:inline-block;margin-top:8px}}
    </style>
</head>
<body>
@php
    $missingTables = count((array) ($diagnosis['missing_tables'] ?? []));
    $missingColumns = collect((array) ($diagnosis['missing_columns'] ?? []))->sum(fn($columns) => count((array) $columns));
    $schemaMissing = $missingTables > 0 || $missingColumns > 0;
    $databaseConnected = empty($diagnosis['error']);
@endphp
<div class="card">
    <div class="top">
        <div class="brand"><div class="logo">ABS</div><div><b>ALPHA BLOCK SOLUTIONS</b><small>DATABASE SELF-REPAIR</small></div></div>
        <span class="tag">{{ ($diagnosis['ready'] ?? false) ? 'DATABASE READY' : 'SETUP CHECK REQUIRED' }}</span>
        <h1>@if($diagnosis['ready'] ?? false)Database structure is ready.@elseif($schemaMissing)ABS found missing database structure.@elseif(!$databaseConnected)ABS cannot connect to the configured database.@else Server configuration needs attention.@endif</h1>
        <p>@if($diagnosis['ready'] ?? false)The required ABS and Pulse tables and columns are present. You can return to the website.@elseif($schemaMissing)Your website files are ready. ABS can securely create the missing required tables and columns in the configured MySQL database without deleting existing users, memberships, Pulse data or website content.@elseif(!$databaseConnected)Check the MySQL database name, user, password and host in your private <code>.env</code>. Database repair can start after the connection succeeds.@else The database structure is present, but one or more server/environment checks still need to be corrected before the application can continue.@endif</p>
    </div>

    <div class="body">
        @if(!empty($validationErrors))
            <div class="errors"><strong>Unable to start repair.</strong><ul>@foreach($validationErrors as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        @if($result)
            <div class="notice {{ $result['ok'] ? 'ok' : 'bad' }}"><strong>{{ $result['title'] }}</strong>\n\n{{ $result['message'] }}</div>
            @if($result['ok'])
                <a class="open" href="/">Open Alpha Block Solutions →</a>
            @endif
        @endif

        @if(!empty($diagnosis['error']))
            <div class="warn"><strong>Database connection problem:</strong> {{ $diagnosis['error'] }}</div>
        @endif

        @if(!empty($diagnosis['environment_issues']))
            <div class="warn"><strong>Server configuration also needs attention:</strong> {{ implode(' · ', $diagnosis['environment_issues']) }}</div>
        @endif

        <div class="stats">
            <div class="stat"><small>Connection</small><strong>{{ $diagnosis['connection'] ?: 'Not configured' }}</strong></div>
            <div class="stat"><small>Database</small><strong>{{ $diagnosis['database'] ?: 'Not configured' }}</strong></div>
            <div class="stat"><small>Missing tables</small><strong>{{ $missingTables }}</strong></div>
            <div class="stat"><small>Missing columns</small><strong>{{ $missingColumns }}</strong></div>
        </div>

        @if($schemaMissing && $databaseConnected)
        <section class="repair">
            <div class="repair-head">
                <div>
                    <h2>Fix Missing Tables & Columns</h2>
                    <p>Enter the <strong>ABS_RECOVERY_KEY</strong> already stored in your private <code>.env</code>. ABS will re-check the schema, create only missing required structures, then verify the database again automatically.</p>
                </div>
                <span class="badge">NON-DESTRUCTIVE REPAIR</span>
            </div>

            @if(!$recoveryEnabled)
                <div class="warn" style="margin-top:16px;margin-bottom:0"><strong>Recovery key not detected.</strong> Add <code>ABS_RECOVERY_KEY=your-long-private-key</code> to <code>Laravel_ABS/.env</code>. On shared hosting, ABS V15.0.9 can read this key directly from the private .env even when Laravel configuration was previously cached.</div>
            @endif

            <form class="form" method="post" action="/api/recovery/repair">
                <div class="field"><input type="password" name="recovery_key" autocomplete="off" placeholder="Enter ABS Recovery Key" required></div>
                <button class="btn" type="submit" {{ $recoveryEnabled ? '' : 'disabled' }}>Fix Missing Tables & Columns</button>
            </form>
            <div class="safe"><i>✓</i><span>This action does not drop tables, wipe the database, replace existing records or seed over your business configuration. It only reconciles missing ABS/Pulse tables and required columns.</span></div>
        </section>
        @elseif($diagnosis['ready'] ?? false)
            <div class="notice ok"><strong>Database check passed.</strong> No required ABS tables or columns are missing.</div>
            <a class="open" href="/">Open Alpha Block Solutions →</a>
        @endif

        @if($missingTables || $missingColumns)
            <details class="details">
                <summary>View detected missing database items</summary>
                <div class="inside">
                    @if($missingTables)
                        <h3>Missing tables</h3>
                        <ul>@foreach($diagnosis['missing_tables'] as $table)<li>{{ $table }}</li>@endforeach</ul>
                    @endif
                    @if($missingColumns)
                        <h3>Missing columns</h3>
                        <ul>@foreach($diagnosis['missing_columns'] as $table => $columns)<li>{{ $table }}: {{ implode(', ', $columns) }}</li>@endforeach</ul>
                    @endif
                </div>
            </details>
        @endif

        <div class="footer">
            <span>ABS V15.0.9 protected database self-repair</span>
            <a href="/api/recovery">Advanced recovery / restore backup →</a>
        </div>
    </div>
</div>
</body>
</html>
