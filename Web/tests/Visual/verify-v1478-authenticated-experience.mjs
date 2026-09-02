import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '../..');
const read = rel => fs.readFileSync(path.join(root, rel), 'utf8');
const failures = [];
const requireText = (rel, text, label = text) => {
  const source = read(rel);
  if (!source.includes(text)) failures.push(`${rel}: missing ${label}`);
};

requireText('app/Http/Controllers/AuthController.php', "redirect()->route('pulse.dashboard')->with('success'", 'Pulse-specific login landing on premium dashboard');
requireText('app/Http/Controllers/AuthController.php', "return redirect()->route('pulse.dashboard');", 'normal active Pulse login landing directly on premium dashboard');
requireText('app/Http/Controllers/DashboardController.php', "return redirect()->route('pulse.dashboard');", '/dashboard compatibility redirect');
requireText('resources/views/profile.blade.php', "@extends('pulse.layout')", 'Profile using premium Pulse shell');
requireText('resources/views/profile.blade.php', 'Services linked to this account', 'ABS service-access section');
requireText('resources/views/profile.blade.php', 'Trades — 30D', 'authenticated trading summary');
requireText('resources/views/profile.blade.php', 'Binance {{ $environment }}', 'safe exchange readiness');
requireText('resources/views/pulse/layout.blade.php', '<a class="pulse-user-card" href="{{ route(\'profile\') }}"', 'sidebar identity opening Profile');
requireText('public/assets/css/pulse-premium.css', 'ABS V14.7.8 — complete authenticated user-family alignment.', 'V14.7.8 UI alignment section');
requireText('public/assets/css/pulse-premium.css', '.pulse-page-hero{min-height:76px', 'secondary Pulse page harmonization');
requireText('public/assets/css/pulse-premium.css', '.pp-profile-grid{display:grid', 'premium Profile layout');
requireText('BUILD_VERSION.txt', 'ABS V14.7.', 'compatible build version');
requireText('docs/openapi.yaml', 'version: 14.7.', 'OpenAPI build metadata');

const profile = read('resources/views/profile.blade.php');
if (/\b(?:\$1,566\.46|64\.6%|87\s*\/\s*100)\b/.test(profile)) {
  failures.push('resources/views/profile.blade.php: fixture/demo trading values must not be hard-coded');
}

const layout = read('resources/views/pulse/layout.blade.php');
if (layout.includes('pulse-user-card">\n                    <span') && layout.includes('action="{{ route(\'logout\') }}"')) {
  failures.push('resources/views/pulse/layout.blade.php: sidebar user card still acts as direct logout');
}

if (failures.length) {
  console.error('ABS V14.7.8 authenticated experience verification: FAIL');
  failures.forEach(f => console.error(`- ${f}`));
  process.exit(1);
}
console.log('ABS V14.7.8 authenticated experience verification: PASS');
console.log('- Premium Pulse login/dashboard routing aligned');
console.log('- Premium Profile shell/account/service summaries present');
console.log('- Remaining Pulse workspace styling harmonization present');
console.log('- No hard-coded approved-screenshot trading fixtures added to Profile');
