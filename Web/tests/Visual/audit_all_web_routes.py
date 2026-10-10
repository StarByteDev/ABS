#!/usr/bin/env python3
"""ABS V15.9.0 authenticated READ-ONLY theme navigation audit.

Usage (run on STAGING only):
  pip install playwright && playwright install chromium
  python tests/Visual/audit_all_web_routes.py --base-url http://127.0.0.1:8000 \
    --storage-state /path/to/your-own-staging-session.json --out /path/to/private/report.json

Sign in in your own browser and produce a Playwright storageState out of band.
Never commit credentials, session tokens, storageState, report/screenshot folders.
This script intentionally does not automate login or trigger forms/action endpoints.
"""
import argparse, json, re
from urllib.parse import urlparse, urljoin, parse_qs, urlencode
from pathlib import Path
from collections import deque
from playwright.sync_api import sync_playwright

EXCLUDE = re.compile(r'/(?:logout|signout|reset|delete|remove|run|execute|import|export|download|sync|purge|rollback|cancel|close|approve|reject|submit|rebuild|pay|purchase|install|test-push|send)(?:[/?#-]|$)',re.I)
EXCLUDED_QUERY = re.compile(r'(?:^|&)(?:action|do|delete|run|reset|export|update)=',re.I)
JS_AUDIT = r'''() => {
  function lum(rgb){ let m=rgb.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/); if(!m)return null;
    let v=m.slice(1).map(n=>+n/255).map(n=>n<=.04045?n/12.92:((n+.055)/1.055)**2.4);
    return .2126*v[0]+.7152*v[1]+.0722*v[2];}
  let examples=[],count=0;
  const visible=el=>{const s=getComputedStyle(el),r=el.getBoundingClientRect();return s.display!='none'&&s.visibility!='hidden'&&+s.opacity>=.9&&r.width>1&&r.height>1;};
  document.querySelectorAll('h1,h2,h3,h4,p,small,label,td,th,button,a,li,b,strong,em,span,input,select,textarea').forEach(el=>{
    if(!visible(el)||(!el.textContent.trim()&&!el.matches('input,select,textarea')))return;
    if(el.children.length>3||el.closest('[aria-hidden="true"]'))return;
    let s=getComputedStyle(el),bg='rgb(255,255,255)'; let node=el;
    while(node){let b=getComputedStyle(node).backgroundColor; let m=b.match(/rgba?\(([^)]+)\)/);
      if(m){let a=m[1].split(',').map(x=>x.trim()); if(a.length==3 || +a[3]>.95){bg=b;break;}}
      node=node.parentElement;}
    let fg=lum(s.color),back=lum(bg);if(fg===null||back===null)return;
    let ratio=(Math.max(fg,back)+.05)/(Math.min(fg,back)+.05);
    const large=parseFloat(s.fontSize)>=24 || (parseFloat(s.fontSize)>=18.66 && (+s.fontWeight||400)>=700);
    let threshold=large?3:4.5;
    // Colored gradients and imagery need human visual confirmation; this is a screening tool.
    if(ratio<threshold){count++;if(examples.length<35)examples.push({selector:el.tagName.toLowerCase()+'.'+String(el.className).trim().split(' ').slice(0,2).join('.'),text:(el.textContent||el.value||'').trim().slice(0,65),contrast:+ratio.toFixed(2),foreground:s.color,background:bg});}
  });
  return {contrast_findings:count,samples:examples,overflow_px:Math.max(0,document.documentElement.scrollWidth-innerWidth),theme:document.documentElement.dataset.absTheme||'missing'};
}'''

def safe_href(href,base):
 u=urlparse(urljoin(base,href));b=urlparse(base)
 if u.netloc!=b.netloc or u.scheme not in ('http','https'):return None
 if EXCLUDE.search(u.path) or EXCLUDED_QUERY.search(u.query) or u.path.startswith(('/api/','/storage/')):return None
 if '.' in u.path.rsplit('/',1)[-1] and not u.path.endswith(('.html','.php')):return None
 # Keep only explicitly safe economic-calendar view filters so they are audited too.
 if u.path.rstrip('/')=='/news':
  q=parse_qs(u.query)
  filtered={key:values[0] for key,values in q.items() if key in ('calendar','impact') and len(values)==1 and values[0] in ('today','upcoming','history','all','high','medium','low')}
  return u._replace(query=urlencode(filtered),fragment='').geturl()
 return u._replace(query='',fragment='').geturl()

def main():
 p=argparse.ArgumentParser();p.add_argument('--base-url',required=True);p.add_argument('--storage-state',required=True);p.add_argument('--out',required=True);p.add_argument('--max-pages',type=int,default=120);p.add_argument('--screenshots',action='store_true');args=p.parse_args()
 base=args.base_url.rstrip('/')+'/'
 if not Path(args.storage_state).is_file():p.error('Use a local authorized staging session storage-state JSON (not inside source repo).')
 dest=Path(args.out);dest.parent.mkdir(parents=True,exist_ok=True)
 queue=deque([base]+[urljoin(base,f'news?calendar={mode}&impact={impact}') for mode in ('today','upcoming','history','all') for impact in ('high','medium','low','all')]);seen=set();report=[]
 with sync_playwright() as pw:
  browser=pw.chromium.launch(headless=True)
  ctx=browser.new_context(storage_state=args.storage_state,viewport={'width':1440,'height':900})
  page=ctx.new_page()
  # Discover nav links from desktop pass, then audit per route / viewport / theme.
  while queue and len(seen)<args.max_pages:
   url=queue.popleft();url=safe_href(url,base)
   if not url or url in seen:continue
   seen.add(url)
   try:
    result=page.goto(url,wait_until='domcontentloaded',timeout=20000)
    if not result or result.status>=400:report.append({'url':url,'error':'HTTP '+str(result.status if result else 'missing')});continue
    if urlparse(page.url).path.startswith(('/login','/signin')) and urlparse(url).path not in ('/login','/signin'):
     report.append({'url':url,'error':'redirected to login; session may be invalid'});continue
    for href in page.eval_on_selector_all('a[href]','els=>els.map(a=>a.getAttribute("href"))'):
     other=safe_href(href,url)
     if other and other not in seen and other not in queue:queue.append(other)
    for width in (390,768,1440):
     page.set_viewport_size({'width':width,'height':844 if width==390 else 900})
     for theme in ('light','dark'):
      page.evaluate('(mode)=>{try{localStorage.setItem("abs.theme",mode)}catch(e){}document.documentElement.dataset.absTheme=mode}',theme)
      page.wait_for_timeout(50)
      checks=page.evaluate(JS_AUDIT)
      rec={'url':url,'width':width,'target_theme':theme,'actual_title':page.title(),**checks}
      report.append(rec)
      if args.screenshots:
       folder=dest.parent/'private_theme_screenshots';folder.mkdir(exist_ok=True)
       name=re.sub(r'[^a-zA-Z0-9]+','_',urlparse(url).path).strip('_') or 'home'
       page.screenshot(path=str(folder/f'{len(seen):03d}_{name[:40]}_{width}_{theme}.png'),full_page=True,timeout=30000)
   except Exception as e:report.append({'url':url,'error':str(e)[:150]})
  browser.close()
 dest.write_text(json.dumps({'note':'Unauthenticated/self-contained pages or state variants may need manual review. CSS contrast can misestimate on gradients/images. No forms were submitted.','pages_discovered':len(seen),'scans':report},indent=2))
 print(f'Completed {len(seen)} GET-only route audits. Report: {dest}')

if __name__=='__main__':main()
