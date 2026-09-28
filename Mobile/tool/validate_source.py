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
    'lib/screens/private_investor_screen.dart',
    'lib/screens/help_center_screen.dart',
    'lib/template_rebase/screens/home_screen.dart',
    'lib/template_rebase/screens/pulse_screen.dart',
    'lib/template_rebase/screens/account_screen.dart',
    'lib/template_rebase/screens/news_screen.dart',
    'lib/template_rebase/screens/auth_screen.dart',
    'lib/template_rebase/state/app_state.dart',
    'lib/screens/calculators_screen.dart',
    'lib/template_ui/common.dart',
    'lib/template_ui/charts.dart',
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
    '/market/chart/BTCUSDT', '/private/requests', '/private/account/requests',
]
dynamic_content_routes = {
    '/research': "kind: 'research'",
    '/learning': "kind: 'learning'",
}
for endpoint in critical_api:
    present = endpoint in all_text or dynamic_content_routes.get(endpoint, '') in all_text
    check(present, f'Mobile client references API: {endpoint}')


# V1.5.0 attached-template integration checks.
check("label: 'Home'" in all_text and "label: 'Pulse'" in all_text and "label: 'Free Signal'" in all_text and "label: 'News'" in all_text and "label: 'Account'" in all_text, 'Template bottom navigation is Home / Pulse / Free Signal / News / Account')
check('class HomeScreen' in all_text and 'class PulseScreen' in all_text and 'class AccountScreen' in all_text and 'const Shell()' in all_text, 'Supplied-template rebase screens are wired into the production shell')
check((ROOT / 'assets/brand/abs-logo-master.png').exists(), 'Pulse logo asset exists')
check('PulseLogo(' in all_text, 'Real Pulse logo component is used in the template UI')
check("api.get('/market/overview'" in all_text and "api.get('/market/movers'" in all_text, 'Template Home consumes live ABS market APIs')
check("api.get('/pulse/signals/overview'" in all_text and "api.get('/pulse/scanner/overview'" in all_text, 'Template Pulse consumes live ABS signal/scanner APIs')
check('OrdersScreen()' in all_text, 'Orders remains reachable from the template Account hub')
check('PrivateInvestorHubScreen()' in all_text, 'V15.7.4 Private Investor remains reachable from template Account')
check('GlobalSearchScreen()' in all_text and 'NewsletterScreen()' in all_text and 'ServicesScreen()' in all_text, 'Content, search, newsletter and services remain reachable')
check('PublicMarketOverviewScreen()' in all_text, 'Full public market overview remains reachable from template Home')
check('MockData.applyMarketOverview' in all_text and 'MockData.applyCalendar' in all_text, 'Template data facade is populated from live ABS API responses')
check('Pulse Sparks' not in all_text, 'Deprecated Pulse Sparks copy is absent from runtime UI')

# V1.5.0 template UI / rewarded access / market intelligence / V15.7.4 parity checks.
check("mobileVersion = '1.6.4'" in all_text, 'Mobile application version is 1.6.4')
check('mobileBuild = 164' in all_text, 'Mobile application build is 164')
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
check("supportedBackendBuild = '15.7.4'" in all_text, 'Backend compatibility target is ABS 15.7.4')
check('google_mobile_ads' in pubspec, 'Native Google rewarded ads dependency is declared')
check('share_plus' in pubspec, 'Native social share dependency is declared')
check("eyebrow: qualified ? 'QUALIFIED PULSE SIGNAL' : 'ENTRY WATCH'" in all_text and "qualified ? 'QUALIFIED' : 'WATCH ONLY'" in all_text, 'Free Signal preserves clear Entry Watch qualification disclosure')
check("'abs_public_signal_visitor'" in all_text, 'Free Signal visitor cooldown identity is persisted securely')
check('Watch one ad. Unlock one Pulse setup.' in all_text, 'Simple rewarded-access Free Signal gateway is present')
check('_RewardPulseVisual' in all_text and '_OrbitRing' in all_text, 'Rewarded gateway includes native motion visual')
check('_recoverRevealFromStatus' in all_text, 'Free Signal can recover a persisted reveal from status')
check('_recoverMemberSignal' in all_text, 'Free Signal can recover the strongest active package signal for eligible members')
check('_recoverBtcContext' in all_text and '/market/chart/BTCUSDT' in all_text, 'Free Signal can fall back to BTC 4H market context')
check('without issuing trade levels' in all_text, 'BTC fallback is explicitly context-only')
check('PrivateInvestorHubScreen' in all_text, 'V15.7.4 Private Investor hub exists')
check('Profit Paid means profit distribution' in all_text, 'Investor UI explains Profit Paid semantics')
check('Capital Withdrawal means principal return' in all_text, 'Investor UI explains Capital Withdrawal semantics')
check('Original currency is preserved' in all_text, 'Investor UI explains original-currency principal')
check('/private/requests' in all_text and '/private/account/requests' in all_text, 'Investor request API fallback is implemented')
check('HelpCenterScreen' in all_text and 'New to Pulse? Follow one simple flow' in all_text, 'Beginner Help Center is present')
check("label: 'Home'" in all_text and "label: 'Pulse'" in all_text and "label: 'Free Signal'" in all_text and "label: 'News'" in all_text and "label: 'Account'" in all_text, 'Primary navigation matches the approved Home/Pulse/Free Signal/News/Account template')
check("import 'template_rebase/screens/shell.dart';" in (ROOT / 'lib/main.dart').read_text(encoding='utf-8') and 'const Shell()' in all_text, 'Approved template-rebase Shell is the live application entry path')
check('assets/brand/abs-logo-master.png' in all_text and 'class PulseLogo' in all_text, 'Real Pulse brand asset is used by the new template UI')
check('/market/overview' in all_text and '/market/movers' in all_text and '/market/chart/BTCUSDT' in all_text, 'Template Home is driven by live ABS market APIs')
check('/pulse/signals/overview' in all_text and '/pulse/scanner/overview' in all_text, 'Template Pulse screen is driven by live Pulse APIs')
check('_hydrateSignalDetails' in all_text and "/pulse/signals/$id" in all_text, 'Free Signal hydrates full member signal detail when available')
check('_priceText' in all_text and 'toStringAsFixed(digits)' in all_text, 'Free Signal uses adaptive crypto price precision instead of fixed two decimals')
check('_PulseMarketPainter' in all_text and 'Candles + price line' in all_text, 'Free Signal market context uses candles plus a close-price line')
check('Entry, Stop Loss and Take Profit have not been issued for this setup yet.' in all_text, 'Missing trade levels are disclosed instead of rendered as false zero prices')
check("data['entry_watch']" in all_text and "data['free_signal']" in all_text, 'Free Signal accepts alternate server response shapes')
check("Tab(text: 'CALENDAR')" in all_text, 'Economic Calendar is integrated under Pulse Intelligence')
check('_CalendarColumnHeader' in all_text and "Text('TIME'" in all_text and "Text('CUR.'" in all_text and "Text('IMP.'" in all_text, 'Economic Calendar uses compact table-style mobile layout')
check(all_text.count("subtract(const Duration(days: 30))") >= 1, 'Economic Calendar includes thirty days of past events')
check("add(const Duration(days: 30))" in all_text, 'Economic Calendar includes thirty days of upcoming events')
check("label: 'Actual'" in all_text and "label: 'Forecast'" in all_text and "label: 'Previous'" in all_text, 'Economic Calendar exposes actual forecast and previous values')
check("label: 'Today'" in all_text and "label: 'Upcoming'" in all_text and "label: 'Previous'" in all_text and "label: 'All'" in all_text, 'Economic Calendar matches web Today Upcoming Previous and All views')


check('initialIndex: 2' in all_text and "Tab(text: 'CALENDAR')" in all_text, 'Pulse Intelligence opens on Calendar by default')
check('JsonTools.collectionItems' in all_text, 'News and calendar use resilient collection payload parsing')
check('limitedAccount' in all_text and 'Basic features stay available now.' in all_text, 'Unverified users keep basic mobile access')
check('Account not activated' in all_text and 'Resend link' in all_text and 'Check status' in all_text, 'Profile exposes activation state and recovery controls')
check("'allow_unverified_basic_access': true" in all_text, 'Login requests limited access for unverified accounts when backend supports it')
check("StatusChip('$selectedPairs MARKETS')" in all_text and 'Wrap(' in all_text, 'Signal scan status chips use responsive wrapping')



# V1.6.2 reliability checks from emulator review.
check("'previous_value'" in all_text and "'forecast_value'" in all_text and "'actual_value'" in all_text, 'Calendar maps V15 historical Previous/Forecast/Actual field variants')
check("isPast ? 'Not reported' : 'Pending'" in all_text, 'Past calendar events are never incorrectly labelled Pending when Actual is missing')
check("subtract(const Duration(days: 45))" in all_text and "add(const Duration(days: 60))" in all_text, 'Calendar retrieves separate historical and upcoming windows')
check("row['country_code']" in all_text and "'US': 'USD'" in all_text, 'Calendar normalizes country/currency aliases')
check("labelText: 'Mobile number'" in all_text and "'country_code': countryCode" in all_text and "'phone': phone" in all_text, 'Registration captures and submits country code plus mobile number')
check("await session.api.patch('/profile'" in all_text, 'Registration includes best-effort phone persistence through Profile for older backend contracts')
check("title: 'Activate your email to unlock Pulse plans'" in all_text and "title: 'Sign in to view your Pulse access'" in all_text, 'Membership has dedicated guest and unverified-account states')
check("title: 'Sign in again to refresh membership'" in all_text, 'Membership handles expired authenticated sessions without broken error layout')
check("final tiles = <Widget>[];" in all_text and "note: 'Not supplied'" not in (ROOT / 'lib/template_rebase/screens/home_screen.dart').read_text(encoding='utf-8'), 'Market Overview renders only populated real metrics instead of Not supplied cards')
check("_maybeNestedNumber" in all_text and "'total_volume_24h_usd'" in all_text, 'Market Overview accepts alternate V15 metric shapes')

# V1.6.3 web-parity checks from emulator review.
check('Get current quote' not in all_text, 'Membership no longer exposes a user-facing quote step')
check('Direct USDT transfer' in all_text and 'Submit Payment for Verification' in all_text, 'Membership mirrors web Direct USDT verification flow')
check("await Clipboard.setData(ClipboardData(text: _wallet))" in all_text, 'Membership payment wallet has a copy action')
check("'/pulse/membership/quote'" in all_text and 'Preparing secure payment instructions...' in all_text, 'Server quote validation is automatic/internal to the payment flow')
check("child: const Text('Pay with USDT')" in all_text, 'Upgrade CTA opens the direct USDT flow')
check("body: const <String, dynamic>{'timeframe': 'all'}" in all_text and 'Find Best Signal' in all_text, 'Pulse Find Best Signal runs the combined 15M + 4H server scan')
check('_ScanningOrb' in all_text and '_ScanStageRail' in all_text and 'Finding best signal...' in all_text, 'Find Best Signal includes interactive mobile scan animation')
check('Preparing package market universe' in all_text and 'Checking 15M market structure' in all_text and 'Checking 4H trend alignment' in all_text and 'Ranking qualifying setups' in all_text, 'Best Signal scan exposes staged progress without fake percentage precision')
check("['best_signal', 'bestSignal', 'signal', 'setup']" in all_text, 'Signal mapping accepts singular web Best Signal response shapes')
check('Scanner overview may include evaluated but unqualified rows' in all_text and 'if (!qualified && id <= 0 && actionSignalId <= 0) return null;' in all_text, 'Mobile does not invent Best Signal qualification')
check('Full scan completed. No setup currently meets the active ABS qualification rules' in all_text, 'No-qualified-setup scan state is explicit')
check('refreshPulse();' in (ROOT / 'lib/template_rebase/screens/shell.dart').read_text(encoding='utf-8'), 'Opening Pulse refreshes server signals to avoid stale mobile results')
check((ROOT / 'RELEASE_NOTES_V1_6_3.md').exists(), 'V1.6.3 release notes exist')


# V1.6.4 calendar completeness + Pulse-only mobile branding checks.
check('_calendarRows(results[2])' in all_text and '_calendarRows(results[3])' in all_text, 'Calendar recursively discovers historical/upcoming provider event rows')
check('_deepMergeCalendar' in all_text and 'Merge duplicates rather than dropping the second copy' in all_text, 'Duplicate calendar releases are merged so richer result figures are preserved')
check('_normaliseCalendarKey' in all_text and "'actual_result'" in all_text and "'forecast_result'" in all_text and "'previous_result'" in all_text, 'Calendar accepts broad case/style variants for Actual Forecast Previous')
check("'release_values'" in (ROOT / 'test/template_data_mapping_test.dart').read_text(encoding='utf-8'), 'Calendar mapping tests cover labelled provider value arrays')
check("'figures': <String, dynamic>" in (ROOT / 'test/template_data_mapping_test.dart').read_text(encoding='utf-8'), 'Calendar mapping tests cover nested provider figure maps')
check('Not provided' in all_text and 'Not reported' in all_text, 'Calendar distinguishes genuinely unavailable provider values from pending future releases')
check("static const String name = 'Pulse'" in all_text and "static const String shortName = 'Pulse'" in all_text, 'Mobile product identity is Pulse')
check('ABS Pulse' not in all_text, 'No ABS Pulse product name remains in runtime Dart UI')
check('ABS Intelligence' not in all_text and 'Pulse Intelligence' in all_text, 'Intelligence section uses Pulse branding')
check('<string name="app_name">Pulse</string>' in (ROOT / 'android/app/src/main/res/values/strings.xml').read_text(encoding='utf-8'), 'Android launcher display name is Pulse')
check('<string>Pulse</string>' in (ROOT / 'ios/Runner/Info.plist').read_text(encoding='utf-8'), 'iOS display name is Pulse')
check((ROOT / 'RELEASE_NOTES_V1_6_4.md').exists(), 'V1.6.4 release notes exist')

# Flutter compile-sanity guard: FontWeight constants only exist in 100-step values.
invalid_font_weights = re.findall(r'FontWeight\.w(\d+)', all_text)
invalid_font_weights = [w for w in invalid_font_weights if w not in {'100','200','300','400','500','600','700','800','900'}]
check(not invalid_font_weights, 'Only supported Flutter FontWeight constants are used')

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

print(f'Pulse Flutter static validation: {checks - len(failures)}/{checks} checks passed')
if failures:
    for item in failures:
        print(f'FAIL: {item}')
    sys.exit(1)
print('PASS')
