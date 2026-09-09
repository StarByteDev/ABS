import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const exists = file => fs.existsSync(path.join(root, file));
const must = (condition, message) => { if (!condition) throw new Error(message); };

must(read('VERSION.txt').trim() === 'ABS V15.1.3', 'VERSION.txt must be ABS V15.1.3');
for (const file of [
  'resources/views/pulse/public-signal.blade.php',
  'public/assets/css/pulse-public-signal-v1513.css',
  'public/assets/js/pulse-public-signal-v1513.js',
  'app/Services/PulsePublicRewardedSignalService.php',
  'app/Http/Controllers/Pulse/PublicRewardedSignalController.php',
  'resources/views/admin/pulse/rewarded-signals.blade.php',
]) must(exists(file), `Missing V15.1.3 artifact: ${file}`);

const view = read('resources/views/pulse/public-signal.blade.php');
for (const fragment of [
  'ABS PULSE · FREE SIGNAL',
  'Keep it visible',
  'until you refresh or leave this page',
  'data-signal-teaser',
  'data-signal-reveal',
  'data-next-free-panel',
  'data-share-network="x"',
  'data-share-network="facebook"',
  'data-share-network="whatsapp"',
  'data-share-network="telegram"',
  'data-share-network="linkedin"',
  'data-share-network="reddit"',
  'data-share-network="more"',
  'data-share-network="copy"',
  'A PRODUCT OF ALPHA BLOCK SOLUTIONS',
]) must(view.includes(fragment), `Free Signal UX missing: ${fragment}`);
must(!view.includes('30-second view'), '30-second signal visibility copy must be removed.');
must(!view.includes('data-view-clock'), 'Reveal countdown must not exist.');
must(!view.includes('assets/brand/abs-logo-512.png') || view.includes('og:image'), 'Free Signal body must not add a replacement/duplicate ABS logo.');

const css = read('public/assets/css/pulse-public-signal-v1513.css');
for (const fragment of ['signal-teaser-blur','filter:blur','pulse-ad-animation','fsWave','signal-share-actions','next-signal-banner']) {
  must(css.includes(fragment), `V15.1.3 premium style missing: ${fragment}`);
}

const js = read('public/assets/js/pulse-public-signal-v1513.js');
for (const fragment of [
  'OutOfPageFormat.REWARDED', 'rewardedSlotReady', 'rewardedSlotGranted', 'makeRewardedVisible',
  'renderSignal', 'renderChart', 'navigator.share', 'navigator.clipboard.writeText',
  'twitter.com/intent/tweet', 'facebook.com/sharer', 'api.whatsapp.com', 't.me/share/url',
  'linkedin.com/sharing/share-offsite', 'reddit.com/submit',
]) must(js.includes(fragment), `V15.1.3 client contract missing: ${fragment}`);
must(!js.includes('viewRemaining'), 'Client must not hide the signal on a view timer.');
must(!js.includes('reveal.hidden=true'), 'Client must not auto-hide a revealed signal.');

const service = read('app/Services/PulsePublicRewardedSignalService.php');
for (const fragment of [
  "'visibility_mode' => 'until_refresh'",
  "'active_signal' => null",
  "'view_expires_at' => null",
  'PulseMarketPrice::query()',
  'PulseMarketCandle::query()',
  "'candles' => $candles",
  "'change_percent_24h'",
]) must(service.includes(fragment), `Server page-session contract missing: ${fragment}`);
must(!service.includes("public_rewarded_signal_view_seconds', 30"), 'Retired reveal-duration setting must not control V15.1.3.');

const admin = read('resources/views/admin/pulse/rewarded-signals.blade.php');
must(admin.includes('Until page refresh / navigation'), 'Admin must explain page-session signal visibility.');
must(!admin.includes('name="view_seconds"'), 'Admin must not expose reveal-seconds control.');

const membership = read('app/Services/PulseMembershipService.php');
must(membership.includes("'commerce_model' => 'direct_usdt_admin_verification'"), 'Direct-USDT Admin verification model must remain active.');
const scannerView = read('resources/views/pulse/scanner.blade.php');
must(!scannerView.includes('Automatic Pulse Intelligence'), 'Removed scanner information block must stay removed.');
const news = read('resources/views/news/index.blade.php');
for (const fragment of ['Today','Upcoming','Previous Releases','Previous','Forecast','Actual','Possible crypto effect']) must(news.includes(fragment), `ABS News regression: ${fragment}`);
const binance = read('app/Services/BinanceFuturesService.php');
for (const fragment of ['normalizeLiveOrderParameters','symbolRules','refreshPairRules','Binance -1111:','LOT_SIZE','MARKET_LOT_SIZE']) must(binance.includes(fragment), `Binance precision guard regression: ${fragment}`);

const activeRoots = ['app','routes','resources/views','config'];
const forbidden = [/Pulse Sparks/i,/Pulse Points/i,/price_points/,/best_signal_cost_points/,/points_charged/,/points_cost/];
const walk = dir => fs.readdirSync(dir,{withFileTypes:true}).flatMap(entry => { const full=path.join(dir,entry.name); return entry.isDirectory()?walk(full):[full]; });
for (const base of activeRoots) for (const file of walk(path.join(root,base))) {
  const src = fs.readFileSync(file,'utf8');
  for (const re of forbidden) must(!re.test(src), `Retired points/Sparks reference ${re} in ${path.relative(root,file)}`);
}

console.log('ABS V15.1.3 branded Free Signal + teaser + social sharing contract: PASS');
