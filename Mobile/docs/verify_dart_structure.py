#!/usr/bin/env python3
"""Lightweight structural safety check; not a substitute for Dart analyzer."""
from pathlib import Path
import sys
root=Path(__file__).resolve().parents[1]
paths=[root/p for p in [
 'lib/investor/market_command_ui.dart',
 'lib/investor/futures_command_home.dart',
 'lib/investor/markets_analysis_screen.dart',
 'lib/investor/pulse_account_home.dart',
 'lib/template_rebase/screens/shell.dart',
 'lib/template_rebase/screens/account_screen.dart',
 'lib/template_rebase/screens/free_signal_screen.dart',
 'lib/core/notifications/notification_router.dart',
 'test/pulse_v172_market_widgets_test.dart',
 'test/pulse_v174_market_fixes_test.dart',
 'lib/template_rebase/data/mock_data.dart',
 'lib/template_rebase/state/app_state.dart',
 'lib/investor/premium_ui.dart',
 'test/notification_routing_test.dart']]
pairs={')':'(',']':'[','}':'{'}
errors=[]
for f in paths:
 s=f.read_text(encoding='utf8'); stack=[]; pos=0; state='code';quote=None;triple=False
 while pos<len(s):
  c=s[pos];n=s[pos:pos+2]
  if state=='code':
   if n=='//': state='line_comment';pos+=2;continue
   if n=='/*': state='block_comment';pos+=2;continue
   if c in "\"'":
    quote=c;triple=s[pos:pos+3]==c*3;state='string';pos+=3 if triple else 1;continue
   if c in '([{':stack.append((c,pos))
   elif c in ')]}':
    if not stack or stack[-1][0]!=pairs[c]:errors.append(f'{f.name}:{s.count(chr(10),0,pos)+1}: unmatched {c}')
    else: stack.pop()
  elif state=='line_comment':
   if c=='\n':state='code'
  elif state=='block_comment':
   if n=='*/':state='code';pos+=2;continue
  elif state=='string':
   if c=='\\':pos+=2;continue
   if triple and s[pos:pos+3]==quote*3:pos+=3;state='code';continue
   if not triple and c==quote:state='code'
  pos+=1
 for c,p in stack:errors.append(f'{f.name}:{s.count(chr(10),0,p)+1}: unclosed {c}')
 if state=='string':errors.append(f'{f.name}: unclosed string')
 print(f'{f.relative_to(root)}: {"PASS" if not [e for e in errors if f.name+":" in e] else "FAIL"}')

# Check relative imports actually exist (excludes package: and dart: URIs).
import re
for f in root.rglob('*.dart'):
 for val in re.findall(r"(?:import|export|part)\s+['\"]([^'\"]+)['\"]",f.read_text(encoding='utf8')):
  if val.startswith(('../','./')) and not (f.parent/val).exists():
   errors.append(f'{f.relative_to(root)}: bad import {val}')

assert 'InvestorWelcome' not in (root/'lib/template_rebase/screens/shell.dart').read_text()
assert 'InvestorModeSwitch' not in (root/'lib/template_rebase/screens/shell.dart').read_text()
assert 'MarketMetric(label: \'24h liquidations\'' in (root/'lib/investor/futures_command_home.dart').read_text()
assert "const FreeSignalScreen(embedded: true)" in (root/'lib/template_rebase/screens/shell.dart').read_text()
assert "version: 1.7.4+174" in (root/'pubspec.yaml').read_text()
assert '2.2.20' in (root/'android/settings.gradle.kts').read_text()
assert '8.12.2' in (root/'android/settings.gradle.kts').read_text()
assert 'com.alphablocksolutions.abs' in (root/'android/app/build.gradle.kts').read_text()

if errors:
 print('\n'.join(errors),file=sys.stderr);sys.exit(1)
print('Dart structure, imports and V1.7.4 contracts: PASS')
