import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const exists = file => fs.existsSync(path.join(root, file));
const must = (condition, message) => { if (!condition) throw new Error(message); };

must(read('VERSION.txt').trim() === 'ABS V15.1.5', 'VERSION.txt must be ABS V15.1.5');
for (const file of [
  'app/Services/PulsePublicRewardedSignalService.php',
  'resources/views/pulse/public-signal.blade.php',
  'public/assets/js/pulse-public-signal-v1515.js',
  'public/assets/css/pulse-public-signal-v1515.css',
]) must(exists(file), `Missing V15.1.5 artifact: ${file}`);

const service = read('app/Services/PulsePublicRewardedSignalService.php');
for (const fragment of [
  "'resource_type' => $preparedSignal instanceof PulseSignal ? 'qualified_signal' : 'market_watch'",
  "'watch_run_id'",
  "'watch_summary_index'",
  "private function highestMarketWatchCandidate(PulseScannerRun $run)",
  "private function marketWatchSnapshotFromReservation",
  "'presentation' => 'market_watch'",
  "'is_qualified_signal' => false",
  "'status_label' => 'ENTRY WATCH'",
  "'signal_id' => $signal?->id",
  "'market_watch' => $watch",
  "run(null, $symbols, 'all')",
]) must(service.includes(fragment), `Entry Watch server contract missing: ${fragment}`);

must(service.includes('highest-scoring Entry Watch'), 'Market-watch notice must clearly identify highest score fallback.');
must(service.includes('it is not a qualified Pulse signal'), 'Market-watch fallback must not be described as a qualified signal.');

const js = read('public/assets/js/pulse-public-signal-v1515.js');
for (const fragment of [
  "signal.presentation === 'market_watch'",
  "'ABS PULSE · MARKET WATCH'",
  "'Market Watch Levels'",
  "'ENTRY WATCH'",
  "'SHARE THIS MARKET WATCH'",
  'has not qualified as a Pulse signal',
]) must(js.includes(fragment), `Entry Watch client contract missing: ${fragment}`);

const view = read('resources/views/pulse/public-signal.blade.php');
for (const fragment of [
  'data-signal-eyebrow',
  'data-signal-setup-heading',
  'data-signal-key-copy',
  'data-share-kicker',
  'data-share-title',
  'data-share-copy',
]) must(view.includes(fragment), `Entry Watch view hook missing: ${fragment}`);

const css = read('public/assets/css/pulse-public-signal-v1515.css');
for (const fragment of ['.signal-result.is-market-watch','border-color:#b6842d','.signal-availability-note']) {
  must(css.includes(fragment), `Entry Watch premium style missing: ${fragment}`);
}

const scanner = read('app/Services/PulseScannerService.php');
must(scanner.includes('Central candle buffers could not be evaluated'), 'Core scanner regression: expected existing scanner behavior missing.');
const membership = read('app/Services/PulseMembershipService.php');
must(membership.includes("'commerce_model' => 'direct_usdt_admin_verification'"), 'Direct USDT model must remain active.');

console.log('ABS V15.1.5 Free Signal Entry Watch fallback contract: PASS');
