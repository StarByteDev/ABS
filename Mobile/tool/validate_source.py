#!/usr/bin/env python3
"""Static release validation for ABS Flutter Mobile.

This intentionally does not replace `flutter analyze` / `flutter test` / native builds.
It provides an offline-safe integrity and architecture check for the distributed source.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LIB = ROOT / "lib"

failures: list[str] = []
checks = 0


def check(condition: bool, label: str) -> None:
    global checks
    checks += 1
    if not condition:
        failures.append(label)


def balanced_dart(text: str) -> bool:
    stack: list[str] = []
    pairs = {')': '(', ']': '[', '}': '{'}
    i, n = 0, len(text)
    state, quote = 'code', ''
    while i < n:
        c = text[i]
        nxt = text[i + 1] if i + 1 < n else ''
        if state == 'code':
            if c == '/' and nxt == '/':
                state = 'line'; i += 2; continue
            if c == '/' and nxt == '*':
                state = 'block'; i += 2; continue
            if c in ("'", '"'):
                if text[i:i + 3] == c * 3:
                    state, quote = 'triple', c; i += 3; continue
                state, quote = 'string', c; i += 1; continue
            if c in '([{':
                stack.append(c)
            elif c in ')]}':
                if not stack or stack[-1] != pairs[c]:
                    return False
                stack.pop()
            i += 1
        elif state == 'line':
            if c == '\n':
                state = 'code'
            i += 1
        elif state == 'block':
            if c == '*' and nxt == '/':
                state = 'code'; i += 2
            else:
                i += 1
        elif state == 'string':
            if c == '\\':
                i += 2
            elif c == quote:
                state = 'code'; i += 1
            else:
                i += 1
        else:
            if text[i:i + 3] == quote * 3:
                state = 'code'; i += 3
            else:
                i += 1
    return not stack and state not in {'string', 'triple', 'block'}


dart_files = sorted(LIB.rglob('*.dart'))
test_files = sorted((ROOT / 'test').rglob('*.dart')) if (ROOT / 'test').exists() else []
check(bool(dart_files), 'Dart source files exist')

all_text = ''
classes: set[str] = set()
used_screens: set[str] = set()
for file in dart_files:
    text = file.read_text(encoding='utf-8')
    all_text += '\n' + text
    check(balanced_dart(text), f'Balanced Dart delimiters: {file.relative_to(ROOT)}')
    duplicate_named_arguments = re.findall(
        r'^(\s*[a-zA-Z_]\w*:[^\n]+)\n\1$', text, re.M
    )
    check(
        not duplicate_named_arguments,
        f'No adjacent duplicate named arguments: {file.relative_to(ROOT)}',
    )
    classes.update(re.findall(r'\bclass\s+([A-Z]\w*)', text))
    used_screens.update(re.findall(r'\b([A-Z]\w*Screen)\b', text))

for screen in sorted(used_screens):
    check(screen in classes, f'Custom screen class is defined: {screen}')

for file in test_files:
    text = file.read_text(encoding='utf-8')
    check(balanced_dart(text), f'Balanced Dart test delimiters: {file.relative_to(ROOT)}')

# Relative Dart imports must resolve.
for file in dart_files:
    text = file.read_text(encoding='utf-8')
    for match in re.finditer(r"^import\s+['\"]([^'\"]+)['\"];", text, re.M):
        target = match.group(1)
        if target.startswith('dart:') or target.startswith('package:'):
            continue
        check((file.parent / target).resolve().exists(), f'Import resolves: {file.relative_to(ROOT)} -> {target}')

critical_files = [
    'lib/main.dart',
    'lib/core/api_client.dart',
    'lib/core/session.dart',
    'lib/screens/auth_screens.dart',
    'lib/screens/dashboard_screen.dart',
    'lib/screens/market_screen.dart',
    'lib/screens/scanner_screen.dart',
    'lib/screens/signals_screen.dart',
    'lib/screens/signal_detail_screen.dart',
    'lib/screens/positions_screen.dart',
    'lib/screens/trade_history_screen.dart',
    'lib/screens/trading_setup_screen.dart',
    'lib/screens/plans_screen.dart',
    'lib/screens/profile_screen.dart',
    'lib/screens/content_screens.dart',
    'lib/screens/account_extra_screens.dart',
    'lib/screens/free_signal_screen.dart',
]
for rel in critical_files:
    check((ROOT / rel).exists(), f'Critical source exists: {rel}')

critical_api = [
    '/bootstrap', '/auth/register', '/auth/login', '/auth/me', '/auth/logout',
    '/dashboard', '/profile', '/notifications', '/notification-preferences', '/devices',
    '/watchlist', '/market/overview', '/market/movers', '/market/chart/',
    '/products', '/news', '/research', '/learning', '/economic-calendar', '/contact', '/newsletter',
    '/pulse/access', '/pulse/membership', '/pulse/membership/quote', '/pulse/membership/requests',
    '/pulse/free-signal/status', '/pulse/free-signal/session', '/pulse/free-signal/claim',
    '/pulse/dashboard', '/pulse/usage', '/pulse/pairs',
    '/pulse/execution/readiness', '/pulse/execution/ticket', '/pulse/positions',
    '/pulse/risk-controls', '/pulse/settings', '/pulse/scanner/overview', '/pulse/scanner/run',
    '/pulse/signals', '/pulse/signals/${widget.signalId}/share', '/pulse/signals/${widget.signalId}/explain',
    '/pulse/trades', '/pulse/trades/sync', '/pulse/orders',
    '/pulse/reports', '/pulse/reports/signals', '/pulse/reports/strategies',
    '/pulse/reports/simulation', '/pulse/reports/learning', '/pulse/market-data/health',
    '/pulse/market-data/prices', '/pulse/binance/connections', '/pulse/alerts',
    '/pulse/emergency-stop', '/private/account', '/private/statements/',
]
dynamic_content_routes = {
    '/research': "kind: 'research'",
    '/learning': "kind: 'learning'",
}
for endpoint in critical_api:
    present = endpoint in all_text or dynamic_content_routes.get(endpoint, '') in all_text
    check(present, f'Mobile client references API: {endpoint}')


# V1.3.5 rewarded access / market chart / calendar table and V15.1.6 parity checks.
check("mobileVersion = '1.3.5'" in all_text, 'Mobile application version is 1.3.5')
check("traderExperience = 'simple'" in all_text, 'Guided Simple experience defaults safely')
check("setTraderExperience" in all_text, 'Simple/Pro experience preference is switchable')
check('ExperienceModeSwitch' in all_text, 'Simple/Pro experience control exists')
check(all_text.count('PremiumHeroCard(') >= 6, 'Premium hero system is used across core journeys')
check('GuidedStepCard(' in all_text, 'Guided new-trader workflow exists')
check('Professional mode' in all_text or 'Professional controls' in all_text, 'Professional trader experience is represented')
check('minSdk = 23' in (ROOT / 'RUN_ANDROID.bat').read_text(encoding='utf-8'), 'Android runner verifies minSdk 23')
configure_native = (ROOT / 'tool/configure_native.py').read_text(encoding='utf-8')
check('ANDROID_MIN_SDK = 23' in configure_native, 'Native configuration pins Android minSdk 23')
check('ANDROID_COMPILE_SDK = 36' in configure_native, 'Native configuration pins compileSdk 36')
check("ANDROID_NDK = '27.0.12077973'" in configure_native, 'Native configuration pins supported Android NDK')

# Signal-discovery clarity checks.
check('Scan Markets Now' in all_text, 'Trade Signals exposes one-tap Scan Markets Now action')
check("body: {'timeframe': 'all'}" in all_text, 'Quick scan uses the combined 15M + 4H server scan')
check('Select Markets to Scan' in all_text, 'Signals redirects users to market selection when required')
check("TradingSetupScreen(initialSection: 'markets')" in all_text, 'Missing market selection opens Trading Setup markets section')
check('HOW A MARKET SCAN WORKS' in all_text, 'Market Scan explains the scanning process')
check('New trader tip:' not in all_text, 'No informal New trader tip copy remains')
# User-facing copy no longer uses the informal workspace label. Internal Dart method names
# and Material icon identifiers such as Icons.workspace_premium are not visible text.
workspace_copy = []
for line in all_text.splitlines():
    lower_line = line.lower()
    if 'workspace' not in lower_line or 'icons.workspace_' in lower_line or '_workspace' in lower_line:
        continue
    if re.search(r"Text\([^\n]*workspace|title:\s*['\"][^'\"]*workspace|subtitle:\s*['\"][^'\"]*workspace|message:\s*['\"][^'\"]*workspace", line, re.I):
        workspace_copy.append(line.strip())
check(not workspace_copy, 'No user-facing workspace terminology remains')

# Architecture guardrails: mobile market and execution must go through ABS.
lower = all_text.lower()
pubspec = (ROOT / 'pubspec.yaml').read_text(encoding='utf-8')
for forbidden in ['api.binance.com', 'fapi.binance.com', 'testnet.binancefuture.com', 'binance.com/fapi']:
    check(forbidden not in lower, f'No direct Binance endpoint in Flutter source: {forbidden}')
check('https://alphablocksolutions.com/api/v1' in all_text, 'Production ABS API base URL is configured')
check("supportedBackendBuild = '15.1.6'" in all_text, 'Backend compatibility target is ABS 15.1.6')
check('google_mobile_ads' in pubspec, 'Native Google rewarded ads dependency is declared')
check('share_plus' in pubspec, 'Native social share dependency is declared')
check("eyebrow: qualified ? 'QUALIFIED PULSE SIGNAL' : 'ENTRY WATCH'" in all_text and "qualified ? 'QUALIFIED' : 'WATCH ONLY'" in all_text, 'Free Signal preserves clear Entry Watch qualification disclosure')
check("'abs_public_signal_visitor'" in all_text, 'Free Signal visitor cooldown identity is persisted securely')
check('Watch one ad. Unlock one Pulse setup.' in all_text, 'Simple rewarded-access Free Signal gateway is present')
check('_RewardPulseVisual' in all_text and '_OrbitRing' in all_text, 'Rewarded gateway includes native motion visual')
check('_recoverRevealFromStatus' in all_text, 'Free Signal can recover a persisted reveal from status')
check('_recoverMemberSignal' in all_text, 'Free Signal can recover the strongest active package signal for eligible members')
check('_hydrateSignalDetails' in all_text and "/pulse/signals/$id" in all_text, 'Free Signal hydrates full member signal detail when available')
check('_priceText' in all_text and 'toStringAsFixed(digits)' in all_text, 'Free Signal uses adaptive crypto price precision instead of fixed two decimals')
check('_PulseMarketPainter' in all_text and 'Candles + price line' in all_text, 'Free Signal market context uses candles plus a close-price line')
check('Entry, Stop Loss and Take Profit have not been issued for this setup yet.' in all_text, 'Missing trade levels are disclosed instead of rendered as false zero prices')
check("data['entry_watch']" in all_text and "data['free_signal']" in all_text, 'Free Signal accepts alternate V15.1.6 response shapes')
check("Tab(text: 'CALENDAR')" in all_text, 'Economic Calendar is integrated under ABS Intelligence')
check('_CalendarColumnHeader' in all_text and "Text('TIME'" in all_text and "Text('CUR.'" in all_text and "Text('IMP.'" in all_text, 'Economic Calendar uses compact table-style mobile layout')
check(all_text.count("subtract(const Duration(days: 30))") >= 1, 'Economic Calendar includes thirty days of past events')
check("add(const Duration(days: 30))" in all_text, 'Economic Calendar includes thirty days of upcoming events')
check("label: 'Actual'" in all_text and "label: 'Forecast'" in all_text and "label: 'Previous'" in all_text, 'Economic Calendar exposes actual forecast and previous values')
check("label: 'Yesterday'" in all_text and "label: 'Today'" in all_text and "label: 'Tomorrow'" in all_text and "label: 'This Week'" in all_text, 'Economic Calendar period switch exposes yesterday today tomorrow and this week views')


# Secret-leak sanity checks. Binance *field labels* are allowed; literal credentials are not.
secret_patterns = [
    r'(?i)sk_live_[a-z0-9]{10,}',
    r'(?i)-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----',
    r'(?i)bearer\s+[a-z0-9._-]{30,}',
]
for pattern in secret_patterns:
    check(re.search(pattern, all_text) is None, f'No embedded secret matching {pattern}')

# Declared assets must exist.
for asset in re.findall(r'^\s+-\s+(assets/[^\s]+)\s*$', pubspec, re.M):
    check((ROOT / asset).exists(), f'Pubspec asset exists: {asset}')

# Release docs/scripts.
for rel in [
    'README.md', 'CHANGELOG.md', 'BUILD_VERSION.txt', 'LOCAL_TEST_GUIDE.md',
    'VALIDATION_REPORT.md', 'docs/API_COVERAGE.md', 'docs/DESIGN_SYSTEM.md',
    'tool/bootstrap_windows.bat', 'tool/bootstrap_macos.sh',
    'tool/build_android_windows.bat', 'tool/build_android_macos.sh',
]:
    check((ROOT / rel).exists(), f'Release support file exists: {rel}')

print(f'ABS Flutter static validation: {checks - len(failures)}/{checks} checks passed')
if failures:
    for item in failures:
        print(f'FAIL: {item}')
    sys.exit(1)
print('PASS')
