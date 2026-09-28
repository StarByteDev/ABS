import fs from 'node:fs';
import crypto from 'node:crypto';
const must=(cond,msg)=>{if(!cond){console.error('FAIL:',msg);process.exit(1)}};
const read=p=>fs.readFileSync(p,'utf8');
const sha=p=>crypto.createHash('sha256').update(fs.readFileSync(p)).digest('hex');

const newsController=read('app/Http/Controllers/NewsController.php');
const liveNews=read('app/Services/LiveNewsService.php');
const marketBrief=read('app/Services/NewsMarketBriefService.php');
const liveShow=read('resources/views/news/live-show.blade.php');
const newsIndex=read('resources/views/news/index.blade.php');
const home=read('resources/views/home.blade.php');
const appJs=read('public/assets/js/abs-app.js');
const calendar=read('app/Services/EconomicCalendarService.php');
const services=read('config/services.php');
const about=read('resources/views/pages/about.blade.php');
const signalView=read('resources/views/pulse/signals/index.blade.php');
const version=read('VERSION.txt').trim();
const buildVersion=read('BUILD_VERSION.txt').trim();

must(version === '15.6.1','VERSION.txt identifies V15.6.1');
must(buildVersion.includes('ABS V15.6.1'),'BUILD_VERSION.txt identifies V15.6.1');
must(newsController.includes("collect($liveNews->latest(12))"),'ABS News performs a live/cached headline refresh instead of reading cache-only');
must(newsController.includes('NewsMarketBriefService $briefs') && newsController.includes("'marketBrief' => $briefs->build($headline)"),'internal live headline page receives Pulse market context');
must(newsController.includes('bootstrap-economic-calendar-v1561') && newsController.includes('$calendarStale'),'calendar bootstrap refreshes stale or missing relevant windows');
must(liveNews.includes("'summary' => Str::limit($description, 1200") && liveNews.includes("'detail' => Str::limit($description, 1600"),'headline feed detail is retained for a fuller internal brief');
must(marketBrief.includes('class NewsMarketBriefService') && marketBrief.includes('MARKET') === false,'market brief service is present and uses structured trading context');
must(liveShow.includes('MARKET BRIEF') && liveShow.includes('PULSE CONTEXT') && liveShow.includes('MARKET WATCH'),'headline detail contains professional market brief, context and watch sections');
must(liveShow.includes('RISK READ') && liveShow.includes('ASSETS IN FOCUS'),'headline detail includes risk read and assets in focus');
must(!liveShow.includes('publisher-supplied') && !liveShow.includes('inside the platform') && !liveShow.includes('redirecting'),'customer-facing development/meta wording removed');
must(newsIndex.includes('Digital Asset News') && newsIndex.includes('Open Market Brief →'),'ABS News uses custom market-intelligence terminology');
must(home.includes('Loading market intelligence…') && !home.includes('verified-source'),'homepage loading copy is ABS branded and not implementation-oriented');
must(appJs.includes("source.textContent = 'Open Market Brief →'") && !appJs.includes('Read inside ABS →'),'dynamic headline rendering uses the same market brief wording');
must(signalView.includes('Pulse Insight') && !signalView.includes('AI Explain') && !signalView.includes('AI explanation is optional'),'visible AI implementation wording removed from member signal explanation');

must(services.includes("'finance_calendar' => [") && services.includes("'xoomar_calendar' => ["),'keyless macro-calendar fallback sources are configured');
must(!services.includes("env('TRADING_ECONOMICS_API_KEY', 'guest:guest')"),'discontinued guest Trading Economics credentials are not a default dependency');
must(calendar.includes('fetchFinanceCalendarRows') && calendar.includes('persistFinanceCalendarRows'),'Finance Calendar source is fully integrated');
must(calendar.includes('fetchXoomarRows') && calendar.includes('persistXoomarRows'),'official-source macro fallback is fully integrated');
must(calendar.includes('fetchTradingEconomicsRows') && calendar.includes('tradingEconomicsEnabled'),'Trading Economics remains optional when credentials are explicitly configured');
must(newsIndex.includes('Calendar active') && newsIndex.includes("$macroCounts['all']"),'calendar shows health/freshness and counts all displayed windows');
must(about.includes('FinanceCalendar.com'),'required calendar-source attribution is present in the About/data-source area');

must(sha('app/Services/PulseScannerService.php') === '81251d2dd99829127e251c5b217b4d499773ec9f4f4f1de58183cb40012cb5ad','core PulseScannerService remains unchanged');
must(sha('public/assets/brand/abs-logo-master.png') === 'f2c53ad570c0be3ddcc3683b5cddbfad3c18dbe2530dab1084b391f424b3cc05','ABS master logo remains unchanged');
must(!fs.existsSync('database/migrations/2026_09_18_000001_v1561.php'),'no V15.6.1 schema migration is required');

console.log('PASS: ABS V15.6.1 market intelligence and calendar contract');
