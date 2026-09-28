import fs from 'node:fs';
import crypto from 'node:crypto';
const must=(cond,msg)=>{if(!cond){console.error('FAIL:',msg);process.exit(1)}};
const read=p=>fs.readFileSync(p,'utf8');
const sha=p=>crypto.createHash('sha256').update(fs.readFileSync(p)).digest('hex');

const routes=read('routes/web.php');
const liveNews=read('app/Services/LiveNewsService.php');
const newsController=read('app/Http/Controllers/NewsController.php');
const apiContent=read('app/Http/Controllers/Api/V1/ContentController.php');
const newsIndex=read('resources/views/news/index.blade.php');
const liveShow=read('resources/views/news/live-show.blade.php');
const home=read('resources/views/home.blade.php');
const appJs=read('public/assets/js/abs-app.js');
const scannerView=read('resources/views/pulse/scanner.blade.php');
const scannerJs=read('public/assets/js/pulse-premium.js');
const scannerCss=read('public/assets/css/pulse-premium.css');
const calendar=read('app/Services/EconomicCalendarService.php');
const services=read('config/services.php');
const consoleRoutes=read('routes/console.php');

const internalRoutePos=routes.indexOf("Route::get('/news/live/{id}'");
const editorialRoutePos=routes.indexOf("Route::get('/news/{article:slug}'");
must(internalRoutePos>=0 && editorialRoutePos>=0 && internalRoutePos<editorialRoutePos,'internal live-news route exists before editorial slug route');
must(liveNews.includes('public function findById') && liveNews.includes("'summary' => Str::limit($description,"),'feed items support internal lookup and expanded feed summary');
must(apiContent.includes("$item['abs_url'] = route('news.live.show'"),'live-news API returns ABS internal URL');
must(newsController.includes('public function liveShow') && newsController.includes('withInternalHeadlineUrl'),'NewsController renders live headline internally');
must(newsController.includes("whereBetween('event_at', [$calendarFrom, $calendarTo])") && newsController.includes('$calendarStale'),'calendar bootstrap checks relevant current window and stale refresh state');
must(newsIndex.includes('Open Market Brief →') && !newsIndex.includes('Open original article ↗'),'ABS News live headline cards use the internal Market Brief');
must(newsIndex.includes('Source: {{ $event->source }}') && !newsIndex.includes('href="{{ $event->source_url }}"'),'economic-event source attribution is non-redirecting');
must(home.includes("route('news.live.show',['id'=>$item['id']])"),'home verified headlines use internal ABS route');
must(appJs.includes('safeInternalUrl') && appJs.includes("link.href = safeInternalUrl(item.abs_url)") && appJs.includes('Open Market Brief →'),'dynamically loaded headlines use internal ABS routes');
must(liveShow.includes('MARKET BRIEF') && liveShow.includes('Back to Market Intelligence'),'internal market-intelligence detail page exists');

must(scannerView.includes('data-scan-progress') && scannerView.includes('Finding Best Signal'),'scanner has visible Best Signal progress surface');
must(scannerJs.includes("label.textContent = 'Finding Best Signal…'") && scannerJs.includes('startScanProgress()') && scannerJs.includes('Ranking qualified setups'),'scanner visibly communicates Best Signal search stages');
must(scannerCss.includes('ABS V15.6.0 — visible Find Best Signal progress state'),'scanner progress uses dedicated premium ABS styling');

must(services.includes("'finance_calendar' => [") && services.includes("'xoomar_calendar' => [") && services.includes("'trading_economics' => ["),'calendar fallback configuration exists');
must(calendar.includes('fetchFinanceCalendarRows') && calendar.includes('fetchXoomarRows') && calendar.includes('fetchTradingEconomicsRows') && calendar.includes('fetchFmpRows'),'economic calendar supports primary and resilient fallback providers');
must(calendar.includes("source: 'Financial Modeling Prep'") && calendar.includes("source: 'Finance Calendar'"),'calendar persists provider attribution');
must(consoleRoutes.includes("Economic Calendar synced via "),'scheduled calendar sync reports active provider');

must(sha('app/Services/PulseScannerService.php') === '81251d2dd99829127e251c5b217b4d499773ec9f4f4f1de58183cb40012cb5ad','core PulseScannerService remains unchanged from V15.5.0 base');
must(sha('public/assets/brand/abs-logo-master.png') === 'f2c53ad570c0be3ddcc3683b5cddbfad3c18dbe2530dab1084b391f424b3cc05','ABS master logo remains unchanged');
must(!fs.existsSync('database/migrations/2026_09_18_000000_v1560.php'),'no V15.6 schema migration is required');

console.log('PASS: ABS V15.6.x in-app news, scanner progress and economic calendar regression contract');
