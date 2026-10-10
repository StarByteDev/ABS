#!/usr/bin/env python3
"""Lightweight source checks; NOT a Dart analyzer or Flutter compiler."""
from pathlib import Path
import re
root=Path(__file__).resolve().parents[1]
changed=[
 'lib/investor/investor_home.dart','lib/investor/investor_portfolio.dart',
 'lib/investor/investor_welcome.dart','lib/investor/trader_home.dart',
 'lib/investor/investor_ui.dart','lib/investor/premium_ui.dart',
 'lib/template_rebase/screens/shell.dart',
 'lib/template_rebase/screens/signal_detail_screen.dart',
 'lib/template_rebase/screens/free_signal_screen.dart',
 'lib/template_rebase/screens/account_screen.dart',
 'test/premium_redesign_test.dart',
]
errors=[]
for filename in changed:
    f=root/filename
    text=f.read_text()
    for m in re.finditer(r"^\s*import\s+['\"]([^'\"]+)['\"]",text,re.M):
        ref=m.group(1)
        if ref.startswith('.'): 
            if not (f.parent/ref).exists(): errors.append(f'{filename}: missing import {ref}')
    # Parse delimiters outside Dart single and double-quoted strings/comments.
    stack=[]; i=0; mode='normal'; quote=''; blocklevel=0
    while i<len(text):
        c=text[i]; n=text[i+1] if i+1<len(text) else ''
        if mode=='line':
            if c=='\n':mode='normal'
        elif mode=='block':
            if c=='/' and n=='*':blocklevel+=1;i+=1
            elif c=='*' and n=='/':
                blocklevel-=1;i+=1
                if blocklevel==0:mode='normal'
        elif mode=='string':
            if c=='\\':i+=1
            elif text.startswith(quote*3,i):
                i+=2;mode='normal'
            elif c==quote and not triple:mode='normal'
        else:
            if c=='/' and n=='/':mode='line';i+=1
            elif c=='/' and n=='*':mode='block';blocklevel=1;i+=1
            elif c in "\"'":
                quote=c;triple=text.startswith(c*3,i);mode='string'
                if triple:i+=2
            elif c in '([{':stack.append((c,i))
            elif c in ')]}':
                pairs={')':'(',']':'[','}':'{'}
                if not stack or stack[-1][0]!=pairs[c]:errors.append(f'{filename}: unmatched {c} at {i}')
                else:stack.pop()
        i+=1
    if stack: errors.append(f'{filename}: remaining delimiters {stack[-5:]}')
    if mode=='block':errors.append(f'{filename}: unterminated comment')
    if mode=='string':errors.append(f'{filename}: unterminated string')

shell=(root/'lib/template_rebase/screens/shell.dart').read_text()
for token in ['InvestorHome','TraderHome','InvestorPortfolioScreen','SignalsScreen','NewsScreen','AccountScreen','FreeSignalScreen']:
    if token not in shell:errors.append(f'Shell missing {token}')
for filename in ['lib/investor/investor_home.dart','lib/investor/trader_home.dart']:
    if 'Free Signal' not in (root/filename).read_text():errors.append(f'{filename}: no Free Signal entry')
for filename in ['lib/core/ads/ad_config.dart','lib/core/notifications/notification_service.dart','android/settings.gradle.kts','android/gradle/wrapper/gradle-wrapper.properties','android/app/build.gradle.kts']:
    if not (root/filename).exists():errors.append(f'Missing preserved integration: {filename}')
if errors:
    print('\n'.join('ERROR '+e for e in errors))
    raise SystemExit(1)
print(f'PASS: {len(changed)} changed Dart source/test files, basic delimiter/import tests, entry points, Firebase/AdMob and Gradle files. Not a compiler.')
