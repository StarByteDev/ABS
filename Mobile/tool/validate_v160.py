from pathlib import Path
import re, sys, zipfile

ROOT = Path(__file__).resolve().parents[1]
checks=[]

def check(name, ok, detail=''):
    checks.append((name,bool(ok),detail))

def text(rel): return (ROOT/rel).read_text(errors='ignore')

# Required exact-template rebase runtime files.
required = [
 'lib/main.dart',
 'lib/template_rebase/screens/shell.dart',
 'lib/template_rebase/screens/home_screen.dart',
 'lib/template_rebase/screens/pulse_screen.dart',
 'lib/template_rebase/screens/free_signal_screen.dart',
 'lib/template_rebase/screens/news_screen.dart',
 'lib/template_rebase/screens/account_screen.dart',
 'lib/template_rebase/screens/auth_screen.dart',
 'lib/template_rebase/screens/splash_screen.dart',
 'lib/template_rebase/state/app_state.dart',
 'lib/template_rebase/data/mock_data.dart',
 'lib/template_rebase/theme/app_theme.dart',
 'lib/template_rebase/widgets/common.dart',
 'lib/template_rebase/widgets/charts.dart',
 'lib/template_rebase/widgets/tiles.dart',
 'assets/brand/abs-logo-512.png',
]
for rel in required: check(f'exists:{rel}', (ROOT/rel).exists())

main=text('lib/main.dart')
shell=text('lib/template_rebase/screens/shell.dart')
news=text('lib/template_rebase/screens/news_screen.dart')
account=text('lib/template_rebase/screens/account_screen.dart')
free=text('lib/template_rebase/screens/free_signal_screen.dart')
state=text('lib/template_rebase/state/app_state.dart')
config=text('lib/core/app_config.dart')
pub=text('pubspec.yaml')

check('main uses template rebase shell', "template_rebase/screens/shell.dart" in main)
check('main does not use legacy MainShell', 'MainShell' not in main and 'screens/main_shell.dart' not in main)
check('legacy main shell removed', not (ROOT/'lib/screens/main_shell.dart').exists())
for old in ['template_home_screen.dart','template_pulse_screen.dart','template_account_screen.dart']:
    check(f'legacy adapter removed:{old}', not (ROOT/'lib/screens'/old).exists())

for label in ['Home','Pulse','Free Signal','News','Account']:
    check(f'bottom navigation:{label}', f"label: '{label}'" in shell)
check('calendar tab first', "options: const ['Calendar', 'ABS News', 'Live']" in news)
check('calendar default selected', 'int _view = 0' in news)
check('calendar upcoming default', "String _period = 'Upcoming'" in news)
for period in ['Today','Upcoming','Previous','All']:
    check(f'calendar period:{period}', period in news)

check('activation pending card', 'Account not activated' in account)
check('activation resend', '/auth/activation/resend' in account)
check('activation check status', 'refreshIdentity' in account)
check('basic access explanation', 'basic access' in account.lower())
for feature in ['Market scanner','Trade signals','Positions','Trade history','Orders','Strategies','Reports','Watchlist','Alerts','Calculators','Trading setup','Research','Learning','Global search','Newsletter','ABS services','Help center','Private Investor portal']:
    check(f'account feature:{feature}', feature in account)

for endpoint in ['/pulse/free-signal/status','/pulse/free-signal/session','/pulse/free-signal/claim']:
    check(f'free signal endpoint:{endpoint}', endpoint in free)
check('free signal BTC 4H fallback', "'/market/chart/BTCUSDT'" in free and "'interval': '4h'" in free)
check('free signal no fabricated levels', 'ABS will not fabricate them' in free)
check('rewarded ad production plugin', 'RewardedAd.load' in free)

for endpoint in ['/market/overview','/market/movers','/news','/news/live','/economic-calendar','/pulse/signals/overview','/pulse/membership','/watchlist']:
    check(f'live data endpoint:{endpoint}', endpoint in state)

check('ABS API base', "https://alphablocksolutions.com/api/v1" in config)
check('backend V15.7.4 target', "supportedBackendBuild = '15.7.4'" in config and "minimumBackendBuild = '15.7.4'" in config)
check('mobile version code', "mobileVersion = '1.6.0'" in config and 'mobileBuild = 160' in config)
check('pubspec release version', 'version: 1.6.0+160' in pub)
check('production ABS logo asset', "assets/brand/abs-logo-512.png" in text('lib/template_rebase/widgets/common.dart'))

# Compatibility for user's Flutter install: avoid unsupported FontWeight values and newer Color.withValues API.
allowed={'100','200','300','400','500','600','700','800','900'}
font_bad=[]; with_values=[]; missing_imports=[]; demo_runtime=[]
for f in (ROOT/'lib').rglob('*.dart'):
    src=f.read_text(errors='ignore')
    for w in re.findall(r'FontWeight\.w(\d+)',src):
        if w not in allowed: font_bad.append(f'{f.relative_to(ROOT)}:w{w}')
    if '.withValues(' in src: with_values.append(str(f.relative_to(ROOT)))
    for imp in re.findall(r"import\s+'([^']+)'",src):
        if imp.startswith(('package:','dart:')): continue
        target=(f.parent/imp).resolve()
        if not target.exists(): missing_imports.append(f'{f.relative_to(ROOT)} -> {imp}')
check('supported FontWeight only', not font_bad, ', '.join(font_bad))
check('no Color.withValues compatibility hazard', not with_values, ', '.join(with_values))
check('all relative imports resolve', not missing_imports, '; '.join(missing_imports))

# No old demo/points economy surfaced in primary production screens.
primary=[
 ROOT/'lib/template_rebase/screens/home_screen.dart',
 ROOT/'lib/template_rebase/screens/pulse_screen.dart',
 ROOT/'lib/template_rebase/screens/free_signal_screen.dart',
 ROOT/'lib/template_rebase/screens/news_screen.dart',
 ROOT/'lib/template_rebase/screens/account_screen.dart',
 ROOT/'lib/template_rebase/screens/auth_screen.dart',
]
for f in primary:
    src=f.read_text(errors='ignore').lower()
    for phrase in ['simulate admin approval','demo build','rewarded points','pulse points','points balance']:
        if phrase in src: demo_runtime.append(f'{f.relative_to(ROOT)}:{phrase}')
check('no retired demo/points UI in primary screens', not demo_runtime, '; '.join(demo_runtime))

# Approximate delimiter/string/comment balance catches packaging/editing corruption.
def strip_dart(src):
    out=[]; i=0; n=len(src); state='code'; quote=''
    while i<n:
        c=src[i]; two=src[i:i+2]
        if state=='code':
            if two=='//': state='line'; out.extend('  '); i+=2; continue
            if two=='/*': state='block'; out.extend('  '); i+=2; continue
            if src.startswith("'''",i) or src.startswith('"""',i): quote=src[i:i+3]; state='triple'; out.extend('   '); i+=3; continue
            if c in "'\"": quote=c; state='string'; out.append(' '); i+=1; continue
            out.append(c); i+=1; continue
        if state=='line':
            if c=='\n': state='code'; out.append('\n')
            else: out.append(' ')
            i+=1; continue
        if state=='block':
            if two=='*/': state='code'; out.extend('  '); i+=2
            else: out.append('\n' if c=='\n' else ' '); i+=1
            continue
        if state=='string':
            if c=='\\': out.extend('  '); i+=2; continue
            if c==quote: state='code'
            out.append('\n' if c=='\n' else ' '); i+=1; continue
        if state=='triple':
            if src.startswith(quote,i): state='code'; out.extend('   '); i+=3; continue
            out.append('\n' if c=='\n' else ' '); i+=1
    return ''.join(out),state

balance_bad=[]
for f in (ROOT/'lib').rglob('*.dart'):
    clean,state=strip_dart(f.read_text(errors='ignore'))
    stack=[]; pair={')':'(',']':'[','}':'{'}; line=1; err=None
    for c in clean:
        if c=='\n': line+=1
        elif c in '([{': stack.append((c,line))
        elif c in ')]}':
            if not stack or stack[-1][0]!=pair[c]: err=f'unmatched {c} at {line}'; break
            stack.pop()
    if err or stack or state not in ('code','line'):
        balance_bad.append(f'{f.relative_to(ROOT)}:{err or stack[-1] if stack else state}')
check('Dart delimiter/string balance', not balance_bad, '; '.join(balance_bad))

# Tests/docs release metadata.
check('activation tests preserved', (ROOT/'test/session_activation_test.dart').exists())
check('config test updated', "'1.6.0'" in text('test/app_config_test.dart') and 'mobileBuild, 160' in text('test/app_config_test.dart'))
check('template widget test', 'TemplateSplashScreen' in text('test/widget_test.dart'))

passed=sum(1 for _,ok,_ in checks if ok)
failed=[c for c in checks if not c[1]]
print(f'{passed}/{len(checks)} checks passed')
for name,ok,detail in checks:
    print(('PASS' if ok else 'FAIL') + ' | ' + name + (f' | {detail}' if detail else ''))
if failed: sys.exit(1)
