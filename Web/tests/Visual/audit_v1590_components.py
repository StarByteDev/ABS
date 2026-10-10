from pathlib import Path
from playwright.sync_api import sync_playwright
from html import escape
import json,re,collections,sys,math
root=Path(__file__).resolve().parents[2]; assets=root/'public/assets/css'
styles={
 'free':['abs-app.css','abs-home-final.css','pulse-public-signal-v1520.css','abs-theme-v1580.css','abs-theme-v1583.css','abs-theme-v1586.css','abs-theme-v1587.css','abs-theme-v1588.css','abs-theme-v1589.css','abs-theme-v1590.css'],
 'news':['abs-app.css','abs-home-final.css','abs-news-v1512.css','abs-theme-v1580.css','abs-theme-v1583.css','abs-theme-v1586.css','abs-theme-v1587.css','abs-theme-v1588.css','abs-theme-v1589.css','abs-theme-v1590.css'],
 'member':['abs-app.css','pulse-app.css','pulse-premium.css','support-v1566.css','private-investor-v1566.css','private-investor-v1569.css','private-investor-v1573.css','abs-theme-v1580.css','abs-theme-v1583.css','abs-theme-v1586.css','abs-theme-v1587.css','abs-theme-v1588.css','abs-theme-v1589.css','abs-theme-v1590.css']
}
blocks={
 'free': '''<section class="free-signal-page"><div class="container free-signal-benefits"><article><span class="benefit-icon">◎</span><div><b>No registration</b><small>Try Pulse before creating an account.</small></div></article><article><span class="benefit-icon">∞</span><div><b>Keep it visible</b><small>Revealed signal stays until refresh.</small></div></article></div><div class="container free-signal-workspace"><aside class="free-signal-ad-rail"><section class="pulse-ad-card"><span class="pulse-ad-kicker">ABS PULSE · REWARDED ACCESS</span><div class="pulse-ad-animation"><div class="pulse-core">▶</div></div><h2>Watch one ad. Unlock one qualified Pulse signal.</h2><p>No registration required.</p><label class="free-signal-consent"><input type="checkbox"><span class="consent-control">✓</span><span class="consent-copy"><strong>Risk acknowledgement</strong><small>Risk disclosure and market disclaimer</small></span></label><button class="free-signal-button">▶ Watch Ad & Reveal Signal</button></section><section class="free-signal-grow-card"><span class="grow-icon">↗</span><div><b>Help ABS Pulse grow</b><p>Found the signal useful?</p></div></section></aside><main class="free-signal-signal-zone"><section class="signal-teaser"><div class="signal-teaser-head"><span>TODAY'S PULSE MARKET CHECK</span><b>REVEAL AFTER AD</b></div><div class="signal-teaser-blur">REDACTED SIGNAL DATA</div><div class="signal-teaser-lock"><h2>A Pulse market setup is waiting.</h2><p>Watch the rewarded ad to reveal.</p><button class="teaser-watch">▶ Watch Ad</button></div></section></main></div><section class="container free-signal-membership"><div><h2>Move from one free signal to full ABS Pulse access.</h2><p>Choose your plan.</p></div></section></section>''',
 'news': '''<section class="container macro-v1512" id="economic-calendar"><div class="macro-v1512-head"><div><h2>Market-moving events</h2><p>CPI, PPI, FOMC</p></div><div class="macro-v1512-legend"><span class="high">High</span><span class="medium">Medium</span><span class="low">Low</span></div></div><nav class="macro-v1512-tabs"><a class="active"><span>Upcoming</span><b>104</b></a><a><span>Today</span><b>1</b></a><a><span>History</span><b>82</b></a><a><span>All</span><b>186</b></a></nav><div class="macro-impact-filter"><span>Filter calendar by impact</span><div class="macro-impact-options"><a aria-current="true">All impacts</a><a aria-current="false">High</a><a aria-current="false">Medium</a><a aria-current="false">Low</a></div><small>Showing all relevant impacts</small></div><div class="macro-v1512-list"><article class="macro-v1512-event high"><div class="macro-v1512-time"><b>18:00</b><span>14 Oct</span></div><div class="macro-v1512-event-main"><div class="macro-v1512-title"><span class="macro-impact high">HIGH</span><small>Global · USD</small><h3>US CPI Report October 2026</h3></div><div class="macro-v1512-values"><span><small>Previous</small><b>2.7%</b></span><span><small>Forecast</small><b>2.9%</b></span><span class="actual"><small>Actual</small><b>Awaiting release</b></span></div></div><div class="macro-v1512-context"><span>Upcoming · Mixed Crypto Context</span><p><b>In simple words:</b> Measures price pressure</p><p><b>Possible crypto effect:</b> Crypto can become volatile.</p></div></article></div></section>''',
 'member': '''<div class="pulse-app-shell"><header class="pulse-global-header">ALPHA BLOCK SOLUTIONS</header><div class="pulse-workspace"><aside class="pulse-sidebar"><nav class="pulse-nav"><a class="active"><svg class="pulse-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg><span>Dashboard</span></a><a><svg class="pulse-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg><span>Find Best Signal</span></a><a><svg class="pulse-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg><span>Package & Payments</span></a><a class="locked" aria-disabled="true"><svg class="pulse-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/></svg><span>Reports & P&amp;L</span><small class="pulse-nav-lock">LOCKED</small></a></nav></aside><main class="pulse-main"><section class="pulse-content"><div class="pp-page pp-profile-page"><div class="pp-title"><h1>My Profile</h1></div><section class="pp-card pp-services-panel"><div class="pp-profile-card-head"><div><small>ABS SERVICES</small><h2>Services linked to this account</h2></div></div><div class="pp-services-grid"><article class="pp-service-tile primary"><span class="pp-service-icon">◉</span><div><small>TRADING INTELLIGENCE</small><h3>Pulse Intelligence</h3><p>Market scanning and performance reporting.</p></div><span class="pp-status-chip good">ACTIVE</span><a>Open Pulse Dashboard →</a></article><article class="pp-service-tile"><span class="pp-service-icon">♧</span><div><small>PRIVATE ACCOUNT SERVICE</small><h3>Investor Portfolio</h3><p>Monthly statements and service requests.</p></div><span class="pp-status-chip good">ACTIVE</span><a>Open Investor Portfolio →</a></article></div></section></div></section></main></div></div>'''
}
checks={
 'free':['.free-signal-benefits article b','.free-signal-benefits article small','.pulse-ad-card h2','.pulse-ad-card p','.free-signal-consent strong','.free-signal-consent small','.free-signal-grow-card b','.free-signal-grow-card p','.signal-teaser-lock h2','.signal-teaser-lock p','.free-signal-membership h2','.free-signal-membership p'],
 'news':['.macro-v1512-head h2','.macro-v1512-tabs a.active','.macro-v1512-tabs a b','.macro-impact-filter>span','.macro-impact-options a[aria-current="false"]','.macro-v1512-time b','.macro-v1512-title h3','.macro-v1512-values>span>b','.macro-v1512-values>span>small','.macro-v1512-context p b'],
 'member':['.pulse-nav a>span','.pulse-nav a.locked>span','.pp-service-tile h3','.pp-service-tile p','.pp-service-tile small','.pp-service-tile>a']
}
def luminance(s):
 m=re.search(r'rgba?\((\d+),\s*(\d+),\s*(\d+)',s)
 if not m:return None
 v=[int(x)/255 for x in m.groups()]
 v=[n/12.92 if n<=.04045 else ((n+.055)/1.055)**2.4 for n in v]
 return .2126*v[0]+.7152*v[1]+.0722*v[2]
def contrast(c1,c2):
 a,b=luminance(c1),luminance(c2)
 if a is None or b is None:return None
 return round((max(a,b)+.05)/(min(a,b)+.05),2)
failed=[];out=[]
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True,executable_path='/usr/bin/chromium',args=['--no-sandbox'])
 for key in blocks:
  css='\n'.join((assets/f).read_text() for f in styles[key] if (assets/f).exists())
  for width in [390,768,1440]:
   for theme in ['light','dark']:
    ctx=browser.new_context(viewport={'width':width,'height':900});page=ctx.new_page()
    html='<html data-abs-theme="'+theme+'"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>'+css+'</style></head><body class="'+('abs-body' if key in ['free','news'] else 'pulse-body')+'">'+blocks[key]+'</body></html>'
    page.set_content(html,wait_until='domcontentloaded')
    for selector in checks[key]:
     el=page.locator(selector).first
     if not el.count():failed.append([key,width,theme,selector,'missing']);continue
     v=el.evaluate('''e=>{let st=getComputedStyle(e);let node=e;let background='';while(node){let bg=getComputedStyle(node).backgroundColor;if(bg&&!/rgba\\(0, 0, 0, 0\\)|transparent/.test(bg)){background=bg;break}node=node.parentElement}return {color:st.color,bg:background,visible:st.display!=='none'&&st.visibility!=='hidden'}}''')
     ratio=contrast(v['color'],v['bg'])
     out.append({'area':key,'width':width,'theme':theme,'selector':selector,'contrast':ratio,'color':v['color'],'background':v['bg']})
     if ratio is not None and ratio<4.5:failed.append([key,width,theme,selector,ratio])
    if key=='member':
     alignment=page.evaluate('''()=>[...document.querySelectorAll('.pulse-nav a>span')].map(n=>Math.round(n.getBoundingClientRect().x))''')
     if len(set(alignment))>1:failed.append([key,width,theme,'sidebar label alignment',alignment])
    if key=='news' and page.locator('.macro-impact-options a').count()!=4:failed.append([key,width,theme,'filter options','missing'])
    # container full widths may intentionally exceed mobile width due fixed desktop fixture; check text and colors.
    if width in [390,1440]:
     page.screenshot(path=str(Path.cwd()/f'ABS_V15_9_0_{key}_{theme}_{width}.png'),full_page=True)
    page.close();ctx.close()
 browser.close()
((Path.cwd()/'ABS_V15_9_0_COMPONENT_QA.json')).write_text(json.dumps({'counts':len(out),'failed':failed,'checks':out},indent=2))
print('CHECKS',len(out),'FAILURES',len(failed))
for f in failed[:50]:print('FAIL',f)
if failed:sys.exit(2)
