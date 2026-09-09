import fs from 'node:fs';
import path from 'node:path';
const root=process.cwd();
const read=f=>fs.readFileSync(path.join(root,f),'utf8');
const exists=f=>fs.existsSync(path.join(root,f));
const must=(c,m)=>{if(!c)throw new Error(m)};
must(['ABS V15.1.2','ABS V15.1.3'].includes(read('VERSION.txt').trim()),'VERSION must be ABS V15.1.2 or ABS V15.1.3');
for(const f of [
  'public/assets/css/admin-communications-v1512.css',
  'public/assets/css/pulse-public-signal-v1513.css',
  'public/assets/css/abs-news-v1512.css',
  'database/migrations/2026_09_09_181200_v1512_premium_usability_polish.php',
  'ABS_V15_1_2_APPLY_USABILITY_FIXES.sql',
]) must(exists(f),`Missing V15.1.2 artifact: ${f}`);

const email=read('resources/views/admin/enterprise/emails.blade.php');
for(const x of ['Plan expiry reminders','Administrator alerts','Customer email categories','Test email delivery','Recent delivery history']) must(email.includes(x),`Email UX missing ${x}`);

const content=read('resources/views/admin/enterprise/content-index.blade.php');
must(content.includes('@forelse($items as $item)')&&content.includes('@endforelse'),'Economic CMS list loop missing');
must(content.includes('Sync Calendar Now'),'Economic calendar sync action missing');

const scanner=read('resources/views/pulse/scanner.blade.php');
must(!scanner.includes('Automatic Pulse Intelligence'),'Obsolete scanner block still present');
const pulseLayout=read('resources/views/pulse/layout.blade.php');
must(!pulseLayout.includes('<small>Commerce</small>')&&!pulseLayout.includes('<small>Activation</small>'),'Obsolete commerce/activation strip still present');

const publicSignal=read('resources/views/pulse/public-signal.blade.php');
for(const forbidden of ['GOOGLE REWARDED WEB','OPTIONAL</span><span>NO ACCOUNT REQUIRED','Google controls ad availability']) must(!publicSignal.includes(forbidden),`Technical rewarded-ad copy still public: ${forbidden}`);
for(const required of ['Unlock one qualified','No registration','Watch Ad & Reveal Signal']) must(publicSignal.includes(required),`Premium free-signal UX missing: ${required}`);

const news=read('resources/views/news/index.blade.php');
for(const x of ['Today','Upcoming','Previous Releases','Previous','Forecast','Actual','Possible crypto effect']) must(news.includes(x),`Economic calendar navigation/context missing ${x}`);
const newsController=read('app/Http/Controllers/NewsController.php');
for(const x of ["['today', 'upcoming', 'history', 'all']",'subDays(30)','addDays(45)','bootstrap-economic-calendar']) must(newsController.includes(x),`News history/bootstrap missing ${x}`);

const binance=read('app/Services/BinanceFuturesService.php');
for(const x of ['normalizeLiveOrderParameters','symbolRules','refreshPairRules','Binance -1111:','LOT_SIZE','MARKET_LOT_SIZE']) must(binance.includes(x),`Binance precision guard missing ${x}`);
const trade=read('app/Services/PulseTradeService.php');
must(trade.includes('refreshPairRules($pair, $environment)'),'Trade execution does not refresh current Binance symbol rules');

console.log('ABS V15.1.2 usability + Binance precision + calendar history contract: PASS');
