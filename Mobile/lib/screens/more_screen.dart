import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/app_config.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';
import 'account_extra_screens.dart';
import 'alerts_screen.dart';
import 'content_screens.dart';
import 'market_extra_screens.dart';
import 'plans_screen.dart';
import 'profile_screen.dart';
import 'reports_screen.dart';
import 'scanner_screen.dart';
import 'strategies_screen.dart';
import 'trade_history_screen.dart';
import 'trading_setup_screen.dart';

class MoreScreen extends StatelessWidget {
  const MoreScreen({super.key, required this.onOpenTab});
  final ValueChanged<int> onOpenTab;

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    final user = session.user ?? {};
    return AbsPage(
      title: 'More',
      subtitle: 'Trading tools, account controls and ABS services',
      child: ListView(
        children: [
          AbsCard(
            gradient: const LinearGradient(colors: [Color(0xFF17152D), Color(0xFF0B1421)]),
            accent: AbsColors.purple,
            child: Row(
              children: [
                Container(
                  width: 52,
                  height: 52,
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(color: AbsColors.panel2, borderRadius: BorderRadius.circular(16), border: Border.all(color: AbsColors.line)),
                  child: Image.asset('assets/brand/abs-logo-master.png'),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(JsonTools.text(user['name'], 'ABS Member'), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
                      const SizedBox(height: 3),
                      Text(JsonTools.text(user['email']), style: const TextStyle(color: AbsColors.muted, fontSize: 10.5)),
                    ],
                  ),
                ),
                StatusChip(session.hasPulseAccess ? 'PULSE ACTIVE' : 'ABS', good: session.hasPulseAccess),
              ],
            ),
          ),
          const SizedBox(height: 18),
          if (session.hasPulseAccess) ...[
            const _MoreSectionTitle('TRADING TOOLS'),
            const SizedBox(height: 9),
            _MoreGrid(items: [
              _MoreItem(Icons.radar_rounded, 'Market Scan', () => _push(context, const ScannerScreen())),
              _MoreItem(Icons.psychology_alt_outlined, 'Strategies', () => _push(context, const StrategiesScreen())),
              _MoreItem(Icons.notifications_active_outlined, 'Alerts', () => _push(context, const AlertsScreen())),
              _MoreItem(Icons.event_note_outlined, 'Economic Calendar', () => _push(context, const EconomicCalendarScreen())),
              _MoreItem(Icons.article_outlined, 'News', () => _push(context, const NewsScreen())),
              _MoreItem(Icons.insights_outlined, 'Research', () => _push(context, const ResearchScreen())),
              _MoreItem(Icons.school_outlined, 'Learning', () => _push(context, const LearningScreen())),
              _MoreItem(Icons.analytics_outlined, 'Reports', () => _push(context, const ReportsScreen())),
            ]),
            const SizedBox(height: 18),
            const _MoreSectionTitle('TRADING ACCOUNT'),
            const SizedBox(height: 9),
            _MoreGrid(items: [
              _MoreItem(Icons.pie_chart_outline_rounded, 'Portfolio', () => onOpenTab(3)),
              _MoreItem(Icons.candlestick_chart_rounded, 'Open Positions', () => onOpenTab(3)),
              _MoreItem(Icons.receipt_long_outlined, 'Orders', () => _push(context, const OrdersScreen())),
              _MoreItem(Icons.history_rounded, 'Trade History', () => _push(context, const TradeHistoryScreen())),
              _MoreItem(Icons.star_outline_rounded, 'Watchlist', () => _push(context, const WatchlistScreen())),
              _MoreItem(Icons.bolt_outlined, 'Signals', () => onOpenTab(2)),
            ]),
            const SizedBox(height: 18),
            const _MoreSectionTitle('SETTINGS'),
            const SizedBox(height: 9),
            _MoreGrid(items: [
              _MoreItem(Icons.currency_bitcoin_rounded, 'Binance Connection', () => _push(context, const TradingSetupScreen(initialSection: 'binance'))),
              _MoreItem(Icons.shield_outlined, 'Risk Settings', () => _push(context, const TradingSetupScreen(initialSection: 'risk'))),
              _MoreItem(Icons.grid_view_rounded, 'Pair Selection', () => _push(context, const TradingSetupScreen(initialSection: 'markets'))),
              _MoreItem(Icons.tune_rounded, 'Execution Settings', () => _push(context, const TradingSetupScreen(initialSection: 'execution'))),
            ]),
          ],
          const SizedBox(height: 18),
          const _MoreSectionTitle('ACCOUNT'),
          const SizedBox(height: 9),
          _MoreGrid(items: [
            _MoreItem(Icons.person_outline_rounded, 'Profile', () => _push(context, const ProfileScreen())),
            _MoreItem(Icons.workspace_premium_outlined, 'Subscription', () => _push(context, const PlansScreen())),
            _MoreItem(Icons.shield_outlined, 'Security', () => _push(context, const ProfileScreen())),
            _MoreItem(Icons.devices_other_rounded, 'Sessions', () => _push(context, const SessionsScreen())),
            _MoreItem(Icons.notifications_none_rounded, 'Notifications', () => _push(context, const AccountNotificationsScreen())),
            _MoreItem(Icons.phone_android_rounded, 'Devices', () => _push(context, const RegisteredDevicesScreen())),
          ]),
          const SizedBox(height: 18),
          const _MoreSectionTitle('SUPPORT & ABS'),
          const SizedBox(height: 9),
          _MoreGrid(items: [
            _MoreItem(Icons.help_outline_rounded, 'Help Center', () => _push(context, const ExploreAbsScreen())),
            _MoreItem(Icons.mail_outline_rounded, 'Contact Support', () => _push(context, const ContactScreen())),
            _MoreItem(Icons.info_outline_rounded, 'About ABS', () => _push(context, const AboutAppScreen())),
            _MoreItem(Icons.public_rounded, 'Website', () => launchUrl(Uri.parse(AppConfig.website), mode: LaunchMode.externalApplication)),
            _MoreItem(Icons.account_balance_outlined, 'Private Member', () => _push(context, const PrivateMemberScreen())),
            _MoreItem(Icons.gavel_outlined, 'Legal & Risk', () => _push(context, const LegalHubScreen())),
          ]),
          const SizedBox(height: 18),
          OutlinedButton.icon(onPressed: () => _logout(context), icon: const Icon(Icons.logout_rounded, color: AbsColors.red), label: const Text('Sign out securely')),
          const SizedBox(height: 12),
          const Text('ABS Pulse 1.2.6 · Backend V14.9.2+', textAlign: TextAlign.center, style: TextStyle(color: AbsColors.muted2, fontSize: 9.5, fontWeight: FontWeight.w700)),
          const SizedBox(height: 10),
        ],
      ),
    );
  }

  static void _push(BuildContext context, Widget page) => Navigator.of(context).push(MaterialPageRoute(builder: (_) => page));

  Future<void> _logout(BuildContext context) async {
    await SessionScope.of(context).logout();
    if (context.mounted) Navigator.of(context).popUntil((r) => r.isFirst);
  }
}

class _MoreItem {
  const _MoreItem(this.icon, this.title, this.onTap);
  final IconData icon;
  final String title;
  final VoidCallback onTap;
}

class _MoreSectionTitle extends StatelessWidget {
  const _MoreSectionTitle(this.title);
  final String title;
  @override
  Widget build(BuildContext context) => Text(title, style: const TextStyle(color: AbsColors.muted, fontSize: 10, fontWeight: FontWeight.w900, letterSpacing: 1.05));
}

class _MoreGrid extends StatelessWidget {
  const _MoreGrid({required this.items});
  final List<_MoreItem> items;

  @override
  Widget build(BuildContext context) => GridView.builder(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        itemCount: items.length,
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 4, crossAxisSpacing: 8, mainAxisSpacing: 10, childAspectRatio: .82),
        itemBuilder: (_, index) {
          final item = items[index];
          return InkWell(
            onTap: item.onTap,
            borderRadius: BorderRadius.circular(16),
            child: Container(
              padding: const EdgeInsets.fromLTRB(6, 11, 6, 8),
              decoration: BoxDecoration(color: AbsColors.panel, borderRadius: BorderRadius.circular(16), border: Border.all(color: AbsColors.lineSoft)),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(width: 35, height: 35, decoration: BoxDecoration(color: AbsColors.purple.withValues(alpha: .10), borderRadius: BorderRadius.circular(11), border: Border.all(color: AbsColors.purple.withValues(alpha: .22))), child: Icon(item.icon, color: AbsColors.purpleSoft, size: 18)),
                  const SizedBox(height: 8),
                  Text(item.title, textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 9.3, height: 1.15)),
                ],
              ),
            ),
          );
        },
      );
}
