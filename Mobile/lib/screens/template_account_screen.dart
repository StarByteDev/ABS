import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api_client.dart';
import '../core/app_config.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../template_ui/common.dart';
import '../widgets/abs_ui.dart';
import 'account_extra_screens.dart';
import 'alerts_screen.dart';
import 'auth_screens.dart';
import 'calculators_screen.dart';
import 'content_screens.dart';
import 'help_center_screen.dart';
import 'market_extra_screens.dart';
import 'plans_screen.dart';
import 'positions_screen.dart';
import 'private_investor_screen.dart';
import 'profile_screen.dart';
import 'reports_screen.dart';
import 'scanner_screen.dart';
import 'signals_screen.dart';
import 'strategies_screen.dart';
import 'trade_history_screen.dart';
import 'trading_setup_screen.dart';

class TemplateAccountScreen extends StatefulWidget {
  const TemplateAccountScreen({super.key});

  @override
  State<TemplateAccountScreen> createState() => _TemplateAccountScreenState();
}

class _TemplateAccountScreenState extends State<TemplateAccountScreen> {
  bool loadingMembership = false;
  Map<String, dynamic> membership = {};

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final session = SessionScope.of(context);
    if (session.authenticated && session.emailVerified && membership.isEmpty && !loadingMembership) {
      _loadMembership();
    }
  }

  Future<void> _loadMembership() async {
    setState(() => loadingMembership = true);
    try {
      final response = await SessionScope.of(context).api.get('/pulse/membership');
      membership = JsonTools.map(JsonTools.at(response, 'data', <String, dynamic>{}));
    } on ApiException {
      membership = {};
    } finally {
      if (mounted) setState(() => loadingMembership = false);
    }
  }

  String _name(AppSession session) {
    final user = session.user ?? <String, dynamic>{};
    final name = JsonTools.text(user['name'], '');
    if (name.isNotEmpty) return name;
    final first = JsonTools.text(user['first_name'], '');
    final last = JsonTools.text(user['last_name'], '');
    final full = '$first $last'.trim();
    return full.isEmpty ? 'ABS Trader' : full;
  }

  String _email(AppSession session) => JsonTools.text((session.user ?? <String, dynamic>{})['email'], '');

  String _initials(String value) {
    final parts = value.trim().split(RegExp(r'\s+')).where((e) => e.isNotEmpty).toList();
    if (parts.isEmpty) return 'A';
    if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();
    return '${parts.first.substring(0, 1)}${parts.last.substring(0, 1)}'.toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    return Scaffold(
      backgroundColor: AbsColors.bg,
      appBar: AppBar(
        titleSpacing: 16,
        title: const Row(
          children: [
            PulseLogo(size: 36),
            SizedBox(width: 10),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Account', style: TextStyle(fontSize: 19, fontWeight: FontWeight.w900)),
                Text('Pulse access, settings & support', style: TextStyle(color: AbsColors.muted, fontSize: 10.5, fontWeight: FontWeight.w500)),
              ],
            ),
          ],
        ),
      ),
      body: session.authenticated ? _signedIn(session) : _guest(),
    );
  }

  Widget _guest() => ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
        children: [
          TemplateCard(
            gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [templateFade(AbsColors.cyan, .12), AbsColors.panel]),
            borderColor: templateFade(AbsColors.cyan, .35),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const PulseLogo(size: 52),
                const SizedBox(height: 14),
                const Text('Your Pulse account', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900)),
                const SizedBox(height: 7),
                const Text('Sign in to manage membership, scanner settings, signals, positions, reports, alerts and your trading setup.', style: TextStyle(color: AbsColors.muted, fontSize: 12.5, height: 1.5)),
                const SizedBox(height: 16),
                SizedBox(width: double.infinity, child: ElevatedButton(onPressed: () => _push(const LoginScreen()), child: const Text('Sign in'))),
                const SizedBox(height: 8),
                SizedBox(width: double.infinity, child: OutlinedButton(onPressed: () => _push(const RegisterScreen()), child: const Text('Create account'))),
              ],
            ),
          ),
          _group('Explore Pulse', [
            TemplateMenuTile(icon: Icons.workspace_premium_outlined, title: 'Pulse Membership', subtitle: 'View available packages', onTap: () => _push(const PlansScreen())),
            TemplateMenuTile(icon: Icons.help_outline_rounded, title: 'Help Center', subtitle: 'How Pulse works for beginners', onTap: () => _push(const HelpCenterScreen())),
            TemplateMenuTile(icon: Icons.grid_view_rounded, title: 'ABS Services', subtitle: 'Products and intelligence services', onTap: () => _push(const ServicesScreen())),
            TemplateMenuTile(icon: Icons.gavel_outlined, title: 'Legal & Risk', subtitle: 'Terms, privacy and disclosures', onTap: () => _push(const LegalHubScreen())),
          ]),
          const TemplateRiskNotice(),
        ],
      );

  Widget _signedIn(AppSession session) {
    final name = _name(session);
    return RefreshIndicator(
      onRefresh: () async {
        try {
          await session.refreshAccount();
        } catch (_) {}
        await _loadMembership();
      },
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
        children: [
          TemplateCard(
            child: Row(
              children: [
                CircleAvatar(
                  radius: 27,
                  backgroundColor: templateFade(AbsColors.cyan, .14),
                  child: Text(_initials(name), style: const TextStyle(color: AbsColors.cyanSoft, fontWeight: FontWeight.w900, fontSize: 16)),
                ),
                const SizedBox(width: 13),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
                      const SizedBox(height: 2),
                      Text(_email(session), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AbsColors.muted, fontSize: 11)),
                    ],
                  ),
                ),
                IconButton(onPressed: () => _push(const ProfileScreen()), icon: const Icon(Icons.edit_outlined, color: AbsColors.muted)),
              ],
            ),
          ),
          const SizedBox(height: 11),
          if (session.limitedAccount) _activationCard() else _membershipCard(session),
          if (session.emailVerified) ...[
            _group('Pulse trading', [
              TemplateMenuTile(icon: Icons.radar_rounded, title: 'Market Scanner', subtitle: 'Scan selected pairs with the ABS strategy engine', onTap: () => _push(const ScannerScreen())),
              TemplateMenuTile(icon: Icons.bolt_rounded, title: 'Trade Signals', subtitle: 'Qualified setups and signal history', onTap: () => _push(const SignalsScreen())),
              TemplateMenuTile(icon: Icons.candlestick_chart_rounded, title: 'Open Positions', subtitle: 'Exchange-confirmed positions and protection', onTap: () => _push(const PositionsScreen())),
              TemplateMenuTile(icon: Icons.history_rounded, title: 'Trade History', subtitle: 'Closed and reconciled trade activity', onTap: () => _push(const TradeHistoryScreen())),
              TemplateMenuTile(icon: Icons.receipt_long_outlined, title: 'Orders', subtitle: 'Order status and exchange execution records', onTap: () => _push(const OrdersScreen())),
              TemplateMenuTile(icon: Icons.auto_graph_rounded, title: 'Strategies', subtitle: 'Strategy status and performance', onTap: () => _push(const StrategiesScreen())),
              TemplateMenuTile(icon: Icons.analytics_outlined, title: 'Reports & Learning', subtitle: 'Signals, strategies, simulation and learning', onTap: () => _push(const ReportsScreen())),
              TemplateMenuTile(icon: Icons.star_outline_rounded, title: 'Watchlist', subtitle: 'Your saved ABS markets', onTap: () => _push(const WatchlistScreen())),
              TemplateMenuTile(icon: Icons.notifications_active_outlined, title: 'Price Alerts', subtitle: 'Manage Pulse market alerts', onTap: () => _push(const AlertsScreen())),
              TemplateMenuTile(icon: Icons.calculate_outlined, title: 'Calculators', subtitle: 'Position size and profit / loss', onTap: () => _push(const CalculatorsScreen())),
            ]),
            _group('Trading setup', [
              TemplateMenuTile(icon: Icons.currency_bitcoin_rounded, title: 'Binance Connection', subtitle: 'Testnet / live exchange connection', onTap: () => _push(const TradingSetupScreen(initialSection: 'binance'))),
              TemplateMenuTile(icon: Icons.shield_outlined, title: 'Risk Settings', subtitle: 'Risk per trade and account safeguards', onTap: () => _push(const TradingSetupScreen(initialSection: 'risk'))),
              TemplateMenuTile(icon: Icons.grid_view_rounded, title: 'Pair Selection', subtitle: 'Markets available to your scanner', onTap: () => _push(const TradingSetupScreen(initialSection: 'markets'))),
              TemplateMenuTile(icon: Icons.tune_rounded, title: 'Execution Settings', subtitle: 'Trading and execution preferences', onTap: () => _push(const TradingSetupScreen(initialSection: 'execution'))),
            ]),
          ],
          if (session.emailVerified)
            _group('Account & security', [
              TemplateMenuTile(icon: Icons.person_outline_rounded, title: 'Profile', subtitle: 'Account activated', onTap: () => _push(const ProfileScreen()), trailing: const TemplatePill('ACTIVE', color: AbsColors.green)),
              TemplateMenuTile(icon: Icons.workspace_premium_outlined, title: 'Membership', subtitle: session.hasPulseAccess ? 'Pulse access is active' : 'Packages and payment requests', onTap: () => _push(const PlansScreen())),
              TemplateMenuTile(icon: Icons.notifications_none_rounded, title: 'Notifications', subtitle: 'Signal, market and account preferences', onTap: () => _push(const AccountNotificationsScreen())),
              TemplateMenuTile(icon: Icons.devices_other_rounded, title: 'Registered Devices', subtitle: 'Devices linked to your ABS account', onTap: () => _push(const RegisteredDevicesScreen())),
              TemplateMenuTile(icon: Icons.security_rounded, title: 'Sessions & Security', subtitle: 'Sessions and password controls', onTap: () => _push(const SessionsScreen())),
            ])
          else
            _group('Account', [
              TemplateMenuTile(icon: Icons.person_outline_rounded, title: 'Profile & Activation', subtitle: 'Activate your email to unlock full Pulse access', onTap: () => _push(const ProfileScreen()), trailing: const TemplatePill('ACTIVATE', color: AbsColors.gold)),
            ]),
          _group('ABS Intelligence', [
            TemplateMenuTile(icon: Icons.newspaper_rounded, title: 'ABS News', subtitle: 'Published content and live headlines', onTap: () => _push(const NewsScreen())),
            TemplateMenuTile(icon: Icons.event_note_rounded, title: 'Economic Calendar', subtitle: 'Upcoming and previous market-moving releases', onTap: () => _push(const EconomicCalendarScreen())),
            TemplateMenuTile(icon: Icons.manage_search_rounded, title: 'Research', subtitle: 'Market and asset research', onTap: () => _push(const ResearchScreen())),
            TemplateMenuTile(icon: Icons.school_rounded, title: 'Learning', subtitle: 'Structured market education', onTap: () => _push(const LearningScreen())),
            TemplateMenuTile(icon: Icons.search_rounded, title: 'Global Search', subtitle: 'Search Pulse content and intelligence', onTap: () => _push(const GlobalSearchScreen())),
            TemplateMenuTile(icon: Icons.mark_email_read_outlined, title: 'Newsletter', subtitle: 'Pulse market updates and subscription', onTap: () => _push(const NewsletterScreen())),
          ]),
          _group('Support & ABS', [
            TemplateMenuTile(icon: Icons.help_outline_rounded, title: 'Help Center', subtitle: 'Beginner-friendly Pulse guidance', onTap: () => _push(const HelpCenterScreen())),
            TemplateMenuTile(icon: Icons.grid_view_rounded, title: 'ABS Services', subtitle: 'Pulse and Alpha Block Solutions services', onTap: () => _push(const ServicesScreen())),
            TemplateMenuTile(icon: Icons.mail_outline_rounded, title: 'Contact Support', subtitle: AppConfig.supportEmail, onTap: () => _push(const ContactScreen())),
            if (session.emailVerified)
              TemplateMenuTile(icon: Icons.account_balance_outlined, title: 'Private Investor', subtitle: 'Portfolio, progress, statements and requests', onTap: () => _push(const PrivateInvestorHubScreen())),
            TemplateMenuTile(icon: Icons.info_outline_rounded, title: 'About ABS Pulse', subtitle: 'Application and backend information', onTap: () => _push(const AboutAppScreen())),
            TemplateMenuTile(icon: Icons.gavel_outlined, title: 'Legal & Risk', subtitle: 'Privacy, terms and disclosures', onTap: () => _push(const LegalHubScreen())),
            TemplateMenuTile(icon: Icons.public_rounded, title: 'ABS Website', subtitle: AppConfig.website, onTap: () => launchUrl(Uri.parse(AppConfig.website), mode: LaunchMode.externalApplication)),
          ]),
          const SizedBox(height: 22),
          OutlinedButton.icon(
            style: OutlinedButton.styleFrom(foregroundColor: AbsColors.red, side: BorderSide(color: templateFade(AbsColors.red, .45))),
            onPressed: _logout,
            icon: const Icon(Icons.logout_rounded),
            label: const Text('Sign out securely'),
          ),
          const SizedBox(height: 14),
          Center(
            child: Text(
              'ABS Pulse ${AppConfig.mobileVersion}+${AppConfig.mobileBuild} · Backend V${AppConfig.supportedBackendBuild}',
              style: const TextStyle(color: AbsColors.muted2, fontSize: 10),
            ),
          ),
        ],
      ),
    );
  }

  Widget _activationCard() => TemplateCard(
        borderColor: templateFade(AbsColors.gold, .45),
        gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [templateFade(AbsColors.gold, .12), AbsColors.panel]),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.mark_email_unread_outlined, color: AbsColors.gold),
                SizedBox(width: 9),
                Expanded(child: Text('Account not activated', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900))),
                TemplatePill('BASIC ACCESS', color: AbsColors.gold),
              ],
            ),
            const SizedBox(height: 9),
            const Text('You can use market intelligence, Free Signal, news, research and learning now. Open Profile to resend the activation link or check your activation status.', style: TextStyle(color: AbsColors.muted, fontSize: 11.5, height: 1.45)),
            const SizedBox(height: 12),
            SizedBox(width: double.infinity, child: ElevatedButton(onPressed: () => _push(const ProfileScreen()), child: const Text('Activate Account'))),
          ],
        ),
      );

  Widget _membershipCard(AppSession session) {
    final package = JsonTools.map(membership['package']);
    final name = JsonTools.text(membership['package_name'] ?? package['name'], session.hasPulseAccess ? 'Pulse Membership' : 'Free account');
    final expires = JsonTools.text(membership['expires_at'] ?? membership['ends_at'], '');
    final pending = JsonTools.boolean(membership['pending']) || JsonTools.text(membership['status'], '').toLowerCase().contains('pending');
    final active = session.hasPulseAccess;
    final color = pending ? AbsColors.gold : active ? AbsColors.green : AbsColors.muted;
    return TemplateCard(
      borderColor: templateFade(color, .38),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(active ? Icons.workspace_premium_rounded : Icons.workspace_premium_outlined, color: active ? AbsColors.gold : AbsColors.muted),
              const SizedBox(width: 10),
              Expanded(child: Text(name, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16))),
              TemplatePill(pending ? 'IN REVIEW' : active ? 'ACTIVE' : 'FREE', color: color),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            pending
                ? 'Your membership request is being reviewed.'
                : active
                    ? (expires.isEmpty ? 'Pulse trading access is active.' : 'Pulse access active until ${compactDate(expires)}.')
                    : 'Upgrade when you are ready for scanner, full signals, execution and performance intelligence.',
            style: const TextStyle(color: AbsColors.muted, fontSize: 11.5, height: 1.45),
          ),
          const SizedBox(height: 10),
          Align(alignment: Alignment.centerRight, child: TextButton(onPressed: () => _push(const PlansScreen()), child: Text(active ? 'Manage access' : 'View plans'))),
        ],
      ),
    );
  }

  Widget _group(String title, List<Widget> tiles) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 23, bottom: 8, left: 4),
            child: Text(title, style: const TextStyle(color: AbsColors.muted, fontSize: 10.5, fontWeight: FontWeight.w800, letterSpacing: .45)),
          ),
          TemplateCard(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Column(
              children: [
                for (int i = 0; i < tiles.length; i++) ...[
                  if (i > 0) const Divider(height: 1, indent: 56),
                  tiles[i],
                ],
              ],
            ),
          ),
        ],
      );

  void _push(Widget page) => Navigator.of(context).push(MaterialPageRoute(builder: (_) => page)).then((_) {
        if (mounted) setState(() {});
      });

  Future<void> _logout() async {
    await SessionScope.of(context).logout();
  }
}
