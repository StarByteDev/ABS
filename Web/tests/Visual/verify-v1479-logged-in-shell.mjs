import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '../..');
const read = rel => fs.readFileSync(path.join(root, rel), 'utf8');
const failures = [];
const must = (rel, token, label = token) => { if (!read(rel).includes(token)) failures.push(`${rel}: missing ${label}`); };

must('BUILD_VERSION.txt', 'ABS V14.7.9', 'V14.7.9 build identity');
must('docs/openapi.yaml', 'version: 14.7.9', 'V14.7.9 OpenAPI metadata');
must('app/Http/Controllers/AuthController.php', "return redirect()->route('pulse.dashboard');", 'direct Pulse login landing');
must('app/Http/Controllers/AuthController.php', "return redirect()->route('pulse.access');", 'Pulse-shell fallback for inactive access');
must('app/Http/Controllers/DashboardController.php', "return redirect()->route('pulse.dashboard');", 'active /dashboard redirect');
must('app/Http/Controllers/DashboardController.php', "return redirect()->route('pulse.access');", 'inactive /dashboard redirect');
must('resources/views/dashboard.blade.php', "@extends('pulse.layout')", 'legacy dashboard premium-shell fallback');
must('resources/views/pulse/membership/index.blade.php', "@extends('pulse.layout')", 'membership inside Pulse shell');
must('resources/views/pulse/membership/checkout.blade.php', "@extends('pulse.layout')", 'checkout inside Pulse shell');
must('resources/views/pulse/layout.blade.php', "'Dashboard'", 'Dashboard navigation');
must('resources/views/pulse/layout.blade.php', "'Market Scanner'", 'Market Scanner navigation');
must('resources/views/pulse/layout.blade.php', "'Signals'", 'Signals navigation');
must('resources/views/pulse/layout.blade.php', "'Strategies'", 'Strategies navigation');
must('resources/views/pulse/layout.blade.php', "'Trade Execution'", 'Trade Execution navigation');
must('resources/views/pulse/layout.blade.php', "'Open Positions'", 'Open Positions navigation');
must('resources/views/pulse/layout.blade.php', "'Trade History'", 'Trade History navigation');
must('resources/views/pulse/layout.blade.php', "'Risk Controls'", 'Risk Controls navigation');
must('resources/views/pulse/layout.blade.php', "'Alerts & Watchlists'", 'Alerts navigation');
must('resources/views/pulse/layout.blade.php', "'Reports & P&L'", 'Reports navigation');
must('resources/views/pulse/layout.blade.php', "'Binance Connection'", 'Binance navigation');
must('resources/views/pulse/layout.blade.php', "'Settings'", 'Settings navigation');
must('resources/views/pulse/layout.blade.php', 'pulse-nav-lock', 'locked capability treatment');
must('public/assets/css/pulse-premium.css', 'ABS V14.7.9 — finalized authenticated Pulse shell enforcement.', 'V14.7.9 shell CSS');

const auth = read('app/Http/Controllers/AuthController.php');
const defaultBlock = auth.slice(auth.indexOf("if ($request->user()->isAdmin())"), auth.indexOf('public function showRegister'));
if (defaultBlock.includes("redirect()->intended")) failures.push('AuthController.php: default post-login flow still uses redirect()->intended');

const dashboard = read('resources/views/dashboard.blade.php');
if (dashboard.includes("@extends('layouts.app')")) failures.push('dashboard.blade.php: legacy public/member layout still present');

if (failures.length) {
  console.error('ABS V14.7.9 finalized logged-in Pulse navigation verification: FAIL');
  failures.forEach(f => console.error(`- ${f}`));
  process.exit(1);
}
console.log('ABS V14.7.9 finalized logged-in Pulse navigation verification: PASS');
console.log('- Legacy dashboard rendering removed from normal signed-in flow');
console.log('- Finalized 12-item Pulse navigation remains stable across plan states');
console.log('- Authenticated membership/checkout remain inside the Pulse shell');
