<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#03111f">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>ABS Previous Build Recovery</title>
    <style>
        :root{color-scheme:dark;--bg:#030b15;--panel:#071524;--panel2:#0a1c2d;--line:#20384c;--gold:#d6a548;--cyan:#73c8ed;--text:#f3f7fb;--muted:#8ea6b8;--good:#6bd39c;--bad:#ff7d82}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(circle at 86% 8%,#0c2940 0,transparent 35%),linear-gradient(160deg,#020912,#061220 70%,#04101a);font:14px/1.55 Inter,Segoe UI,Arial,sans-serif;color:var(--text);padding:28px}.wrap{width:min(920px,100%);margin:auto}.brand{display:flex;align-items:center;gap:14px;margin:0 0 18px}.logo{width:48px;height:48px;border:1px solid #d6a54880;border-radius:13px;display:grid;place-items:center;color:var(--gold);font-weight:900;background:#071322}.brand strong{font-size:18px;letter-spacing:.02em}.brand small{display:block;color:var(--gold);letter-spacing:.18em;font-size:10px;margin-top:3px}.card{background:linear-gradient(145deg,#081827f2,#06121ff2);border:1px solid #274054;border-radius:20px;padding:28px;box-shadow:0 30px 90px #0008}.eyebrow{color:var(--gold);font-weight:850;letter-spacing:.18em;font-size:11px}.hero h1{font-size:clamp(29px,4vw,44px);line-height:1.1;margin:12px 0 10px}.hero p{color:var(--muted);max-width:720px;font-size:15px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:24px 0}.stat{border:1px solid var(--line);background:#ffffff05;border-radius:14px;padding:17px}.stat small{display:block;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.1em}.stat strong{display:block;margin-top:6px;font-size:16px}.safe{border:1px solid #2d5a49;background:#0b2a1c77;border-radius:14px;padding:15px 17px;color:#c4f5d8;margin:18px 0}.warn{border:1px solid #694c2a;background:#2a1c0b88;color:#f3d7a2;border-radius:14px;padding:15px 17px;margin:18px 0}.errors{border:1px solid #7a3c44;background:#2a1117;color:#ffd0d4;padding:13px 16px;border-radius:12px;margin:18px 0}.result{padding:15px 17px;border-radius:12px;margin:18px 0;white-space:pre-wrap}.result.ok{border:1px solid #2f7245;background:#0a2014;color:#b8f5c7}.result.bad{border:1px solid #7b3940;background:#281015;color:#ffc1c6}.restore{margin-top:20px;border-top:1px solid var(--line);padding-top:22px}.restore h2{font-size:21px;margin:0 0 6px}.restore p{color:var(--muted);margin:0 0 16px}.field{margin:12px 0}.field label{display:block;font-weight:750;margin-bottom:6px}.field input{width:100%;padding:12px 13px;border-radius:10px;border:1px solid #315069;background:#020a12;color:var(--text)}.btn{border:0;border-radius:10px;padding:13px 18px;font-weight:850;background:linear-gradient(135deg,#e7bd64,#bf872e);color:#0c1117;cursor:pointer}.btn:disabled{opacity:.45;cursor:not-allowed}.links{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px}.links a{color:var(--cyan);text-decoration:none}.foot{margin-top:20px;color:#6f879a;font-size:12px}@media(max-width:700px){body{padding:16px}.grid{grid-template-columns:1fr}.card{padding:22px}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand"><div class="logo">ABS</div><div><strong>ALPHA BLOCK SOLUTIONS</strong><small>PROTECTED BUILD RECOVERY</small></div></div>
    <main class="card hero">
        <span class="eyebrow">ABS PULSE · LIVE RELEASE SAFETY</span>
        <h1>Restore the previous application build without touching live data.</h1>
        <p>This recovery control replaces application files only. The production database, users, memberships, payments, trades, content and current records remain in place.</p>

        <div class="grid">
            <div class="stat"><small>Current build</small><strong>{{ $currentVersion }}</strong></div>
            <div class="stat"><small>Previous build available</small><strong>{{ $restorePoint ? 'Yes' : 'No backup stored' }}</strong></div>
        </div>

        @if($restorePoint)
            <div class="safe"><strong>Rollback point ready</strong><br>{{ $restorePoint['name'] }}<br><small>Created {{ $restorePoint['created_at'] }} · {{ number_format($restorePoint['size']/1024/1024,2) }} MB</small></div>
        @else
            <div class="warn"><strong>No previous build is stored.</strong> Create a current-build backup from Admin → Updates & Recovery before applying the next patch.</div>
        @endif

        @if(!$recoveryEnabled)
            <div class="warn"><strong>Recovery key is not configured.</strong> Add <code>ABS_RECOVERY_KEY</code> to the private production <code>.env</code> before using protected restore actions.</div>
        @endif

        @if(!empty($validationErrors))
            <div class="errors"><strong>Please correct:</strong><ul>@foreach($validationErrors as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        @if($result)
            <div class="result {{ $result['ok'] ? 'ok' : 'bad' }}"><strong>{{ $result['title'] }}</strong>\n\n{{ $result['message'] }}</div>
        @endif

        <section class="restore">
            <h2>Restore previous build</h2>
            <p>Use this if the newest patch has a functional problem. The same recovery key used by Database Fix is required.</p>
            <form method="POST" action="/api/recovery/build/restore">
                <div class="field"><label>ABS Recovery Key</label><input type="password" name="recovery_key" autocomplete="off" required></div>
                <div class="field"><label>Confirmation</label><input type="text" name="confirmation" placeholder="Type RESTORE" required></div>
                <button class="btn" type="submit" {{ ($recoveryEnabled && $restorePoint) ? '' : 'disabled' }}>Restore Previous Build</button>
            </form>
        </section>

        <div class="links"><a href="/">ABS Home</a><a href="/api/recovery">Database Recovery</a></div>
        <div class="foot">Only one previous application build is retained. Environment credentials, storage and the live database are outside the application rollback package.</div>
    </main>
</div>
</body>
</html>
