import fs from 'node:fs';

const read = (file) => fs.readFileSync(file, 'utf8');
const must = (condition, message) => {
  if (!condition) {
    console.error('FAIL:', message);
    process.exitCode = 1;
  } else console.log('PASS:', message);
};

const layout = read('resources/views/pulse/layout.blade.php');
const scanner = read('resources/views/pulse/scanner.blade.php');
const settings = read('resources/views/pulse/settings.blade.php');
const points = read('resources/views/pulse/points/index.blade.php');
const signals = read('resources/views/pulse/signals/index.blade.php');
const admin = read('resources/views/admin/pulse/points.blade.php');

must(layout.includes("'label' => 'Find Best Signal'") && layout.includes("'label' => 'Pulse Points'"), 'Pulse navigation exposes V15 Best Signal and Pulse Points');
must(scanner.includes('Find Best Signal') && scanner.includes('Admin-defined market universe'), 'scanner presents one-click Admin-controlled Best Signal flow');
must(scanner.includes('15M + 4H evaluated automatically') && scanner.includes('0 PP if no signal qualifies'), 'scanner clearly communicates automatic timeframes and no-result PP rule');
must(!scanner.includes('Scanner Filters') && !scanner.includes('Saved Scanner View'), 'legacy user scanner filter controls are absent');
must(settings.includes('Admin controlled') && settings.includes('Strategy selection, weighting and the minimum qualification score are managed by Admin'), 'settings clearly identify signal intelligence as Admin managed');
must(!settings.includes('name="minimum_signal_score"') && !settings.includes('name="selected_pairs'), 'settings expose no user signal-threshold or pair-selection inputs');
must(points.includes('Claim Daily Check-in') && points.includes('Buy Pulse Points with USDT'), 'Pulse Points page exposes daily earning and USDT pack purchase');
must(points.includes('Missions & Achievements') && points.includes('Immutable ledger'), 'Pulse Points page exposes gamification and ledger history');
must(signals.includes('Share Signal') && signals.includes('AI Explain'), 'signal UI exposes V15 social sharing and optional AI explanation');
must(admin.includes('USDT verification queue') && admin.includes('credits PP exactly once'), 'Admin UI communicates manual exactly-once PP verification');
must(admin.includes('Pulse Points packs') && admin.includes('V15 Pulse economy control center'), 'Admin UI exposes PP pack/economy controls');

if (process.exitCode) process.exit(process.exitCode);
console.log('ABS V15.0.0 premium visual/content contract: PASS');
