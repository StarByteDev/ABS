import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../core/json_tools.dart';
import '../../core/session.dart';
import '../../screens/account_extra_screens.dart';
import '../../screens/alerts_screen.dart';
import '../../screens/calculators_screen.dart';
import '../../screens/content_screens.dart';
import '../../screens/dashboard_screen.dart';
import '../../screens/help_center_screen.dart';
import '../../screens/market_extra_screens.dart';
import '../../screens/plans_screen.dart';
import '../../screens/positions_screen.dart';
import '../../screens/private_investor_screen.dart';
import '../../screens/profile_screen.dart';
import '../../screens/reports_screen.dart';
import '../../screens/scanner_screen.dart';
import '../../screens/signals_screen.dart';
import '../../screens/strategies_screen.dart';
import '../../screens/trade_history_screen.dart';
import '../../screens/trading_setup_screen.dart';
import '../data/mock_data.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../utils/format.dart';
import '../widgets/common.dart';
import 'auth_screen.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({super.key});

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  bool _activationBusy = false;

  String _initials(String name) {
    final parts = name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first[0].toUpperCase();
    return (parts.first[0] + parts.last[0]).toUpperCase();
  }

  Future<void> _editName(AppState app) async {
    if (!app.signedIn) return;
    final controller = TextEditingController(text: app.userName);
    final name = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppColors.surface,
        title: const Text('Edit name'),
        content: TextField(
          controller: controller,
          autofocus: true,
          textCapitalization: TextCapitalization.words,
          decoration: const InputDecoration(labelText: 'Display name'),
        ),
        actions: <Widget>[
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(ctx, controller.text.trim()), child: const Text('Save')),
        ],
      ),
    );
    controller.dispose();
    if (name == null || name.isEmpty || !mounted) return;
    try {
      await app.setName(name);
      if (mounted) snack(context, 'Profile updated.');
    } on ApiException catch (e) {
      if (mounted) snack(context, e.message);
    }
  }

  Future<void> _resendActivation() async {
    final session = SessionScope.of(context);
    final email = JsonTools.text(session.user?['email'], '');
    if (email.isEmpty || _activationBusy) return;
    setState(() => _activationBusy = true);
    try {
      await session.api.post('/auth/activation/resend', body: <String, dynamic>{'email': email});
      if (mounted) snack(context, 'Activation email sent. Check your inbox and spam folder.');
    } on ApiException catch (e) {
      if (mounted) snack(context, e.message);
    } finally {
      if (mounted) setState(() => _activationBusy = false);
    }
  }

  Future<void> _checkActivation() async {
    final session = SessionScope.of(context);
    if (_activationBusy) return;
    setState(() => _activationBusy = true);
    try {
      await session.refreshIdentity();
      if (!mounted) return;
      await AppScope.read(context).refreshAccount();
      if (session.emailVerified) {
        await AppScope.read(context).refreshPulse();
        if (mounted) snack(context, 'Account activated. Full Pulse access is now available according to your plan.');
      } else if (mounted) {
        snack(context, 'Activation is still pending. Open the email link, then check again.');
      }
    } on ApiException catch (e) {
      if (mounted) snack(context, e.message);
    } finally {
      if (mounted) setState(() => _activationBusy = false);
    }
  }

  void _openProtected(AppState app, Widget page) {
    if (!app.signedIn) {
      push(context, const AuthScreen());
      return;
    }
    if (!app.emailVerified) {
      snack(context, 'Activate your email first to unlock this account feature.');
      return;
    }
    push(context, page);
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return Scaffold(
      appBar: AppBar(
        title: const Row(
          children: <Widget>[
            AbsLogo(size: 34),
            SizedBox(width: 10),
            Text('Account'),
          ],
        ),
      ),
      body: RefreshIndicator(
        color: AppColors.accent,
        backgroundColor: AppColors.surface,
        onRefresh: app.refreshAccount,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
          children: <Widget>[
            _identityCard(app),
            if (!app.signedIn) ...<Widget>[
              const SizedBox(height: 12),
              _guestAccessCard(),
            ] else if (!app.emailVerified) ...<Widget>[
              const SizedBox(height: 12),
              _activationCard(app),
            ] else ...<Widget>[
              const SizedBox(height: 12),
              _membershipCard(app),
            ],
            _group('Pulse trading', <Widget>[
              _menu(Icons.radar_rounded, 'Market scanner', 'Scan selected markets with ABS strategy rules', () => _openProtected(app, const ScannerScreen())),
              _menu(Icons.bolt_rounded, 'Trade signals', 'Qualified setups and Entry Watch', () => _openProtected(app, const SignalsScreen())),
              _menu(Icons.candlestick_chart_rounded, 'Positions', 'Open and recently reconciled positions', () => _openProtected(app, const PositionsScreen())),
              _menu(Icons.history_rounded, 'Trade history', 'Closed trades and realized outcomes', () => _openProtected(app, const TradeHistoryScreen())),
              _menu(Icons.receipt_long_outlined, 'Orders', 'Exchange order and execution status', () => _openProtected(app, const OrdersScreen())),
              _menu(Icons.hub_outlined, 'Strategies', 'Strategy performance and learning', () => _openProtected(app, const StrategiesScreen())),
              _menu(Icons.analytics_outlined, 'Reports', 'Signals, trades, strategy and simulation reports', () => _openProtected(app, const ReportsScreen())),
            ]),
            _group('Market & risk tools', <Widget>[
              _menu(Icons.bookmark_border_rounded, 'Watchlist', '${app.watchlist.length} saved market${app.watchlist.length == 1 ? '' : 's'}', () => _openProtected(app, const WatchlistScreen())),
              _menu(Icons.notifications_active_outlined, 'Alerts', 'Market and account alerts', () => _openProtected(app, const AlertsScreen())),
              _menu(Icons.calculate_outlined, 'Calculators', 'Position size and profit/loss', () => push(context, const CalculatorsScreen())),
              _menu(Icons.tune_rounded, 'Trading setup', 'Binance, risk, pairs and execution settings', () => _openProtected(app, const TradingSetupScreen())),
              _menu(Icons.space_dashboard_outlined, 'Pulse dashboard', 'Detailed account and system overview', () => _openProtected(app, const DashboardScreen())),
            ]),
            _group('Intelligence & learning', <Widget>[
              _menu(Icons.science_outlined, 'Research', 'ABS research and substantive analysis', () => push(context, const ResearchScreen())),
              _menu(Icons.school_outlined, 'Learning', 'Beginner-friendly Pulse education', () => push(context, const LearningScreen())),
              _menu(Icons.search_rounded, 'Global search', 'Search ABS market intelligence', () => push(context, const GlobalSearchScreen())),
              _menu(Icons.mark_email_read_outlined, 'Newsletter', 'Market briefs and product updates', () => push(context, const NewsletterScreen())),
              _menu(Icons.grid_view_rounded, 'ABS services', 'Explore Alpha Block Solutions services', () => push(context, const ServicesScreen())),
              _menu(Icons.help_outline_rounded, 'Help center', 'Understand Pulse features and trading workflow', () => push(context, const HelpCenterScreen())),
            ]),
            if (app.signedIn)
              _group('Account & security', <Widget>[
                _menu(Icons.person_outline_rounded, 'Profile', app.email, () => push(context, const ProfileScreen())),
                _menu(Icons.workspace_premium_outlined, 'Membership', app.hasPulse ? 'Pulse access active' : 'View packages and requests', () => _openProtected(app, const PlansScreen())),
                _menu(Icons.notifications_none_rounded, 'Account notifications', 'ABS account and system notifications', () => _openProtected(app, const AccountNotificationsScreen())),
                _menu(Icons.tune_rounded, 'Notification preferences', 'Choose what ABS sends you', () => _openProtected(app, const NotificationPreferencesScreen())),
                _menu(Icons.devices_rounded, 'Registered devices', 'Review devices linked to your account', () => _openProtected(app, const RegisteredDevicesScreen())),
                _menu(Icons.security_rounded, 'Sessions', 'Review and revoke active sessions', () => _openProtected(app, const SessionsScreen())),
                _menu(Icons.password_rounded, 'Change password', 'Update your account password', () => _openProtected(app, const ChangePasswordScreen())),
              ]),
            if (app.signedIn && app.emailVerified)
              _group('Private Investor', <Widget>[
                _menu(Icons.account_balance_wallet_outlined, 'Private Investor portal', 'Principal, monthly progress, statements and requests', () => push(context, const PrivateInvestorHubScreen())),
              ]),
            _group('Legal & risk', <Widget>[
              _menu(Icons.description_outlined, 'Privacy, terms & disclosures', 'ABS policies and market-risk documents', () => push(context, const LegalHubScreen())),
              _menu(Icons.info_outline_rounded, 'About Pulse', 'Alpha Block Solutions mobile platform', () => push(context, const AboutAppScreen())),
            ]),
            const SizedBox(height: 22),
            if (app.signedIn)
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.down,
                    side: BorderSide(color: fade(AppColors.down, .5)),
                  ),
                  onPressed: () async {
                    await app.signOut();
                    if (mounted) snack(context, 'Signed out. Public Pulse access remains available.');
                  },
                  icon: const Icon(Icons.logout_rounded),
                  label: const Text('Sign out'),
                ),
              )
            else
              PrimaryButton(label: 'Sign in to Pulse', onPressed: () => push(context, const AuthScreen())),
            const SizedBox(height: 14),
            const Center(
              child: Text(
                'Pulse Mobile · V1.6.4+164',
                style: TextStyle(color: AppColors.faint, fontSize: 12),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _identityCard(AppState app) => AbsCard(
        child: Row(
          children: <Widget>[
            CircleAvatar(
              radius: 28,
              backgroundColor: fade(AppColors.accent, .18),
              child: app.signedIn
                  ? Text(
                      _initials(app.userName),
                      style: const TextStyle(color: AppColors.accent, fontWeight: FontWeight.w800, fontSize: 18),
                    )
                  : const AbsLogo(size: 38),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    app.signedIn ? app.userName : 'Pulse',
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    app.signedIn ? app.email : 'Public market intelligence',
                    style: AppText.muted,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 7),
                  Pill(
                    !app.signedIn
                        ? 'PUBLIC'
                        : app.emailVerified
                            ? (app.hasPulse ? 'PULSE ACTIVE' : 'VERIFIED')
                            : 'ACTIVATION PENDING',
                    color: !app.signedIn
                        ? AppColors.muted
                        : app.emailVerified
                            ? AppColors.up
                            : AppColors.amber,
                  ),
                ],
              ),
            ),
            if (app.signedIn)
              IconButton(
                tooltip: 'Edit profile name',
                onPressed: () => _editName(app),
                icon: const Icon(Icons.edit_outlined, color: AppColors.muted),
              ),
          ],
        ),
      );

  Widget _guestAccessCard() => AbsCard(
        borderColor: fade(AppColors.accent, .35),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            const Text('Public access', style: AppText.h2),
            const SizedBox(height: 7),
            const Text(
              'Explore Markets, Free Signal and Pulse Intelligence without an account. Sign in to sync membership, watchlists, alerts and trading tools.',
              style: TextStyle(color: AppColors.muted, height: 1.45),
            ),
            const SizedBox(height: 14),
            Row(
              children: <Widget>[
                Expanded(child: FilledButton(onPressed: () => push(context, const AuthScreen()), child: const Text('Sign in'))),
                const SizedBox(width: 10),
                Expanded(child: OutlinedButton(onPressed: () => push(context, const AuthScreen(startRegister: true)), child: const Text('Create account'))),
              ],
            ),
          ],
        ),
      );

  Widget _activationCard(AppState app) => AbsCard(
        borderColor: fade(AppColors.amber, .45),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            const Row(
              children: <Widget>[
                Icon(Icons.mark_email_unread_outlined, color: AppColors.amber),
                SizedBox(width: 10),
                Expanded(child: Text('Account not activated', style: AppText.h2)),
                Pill('BASIC ACCESS', color: AppColors.amber),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              'You can use Markets, Free Signal, Pulse Intelligence, Research, Learning and public content now. Activate ${app.email} to unlock membership, scanner, signals, positions and secured account tools.',
              style: const TextStyle(color: AppColors.muted, height: 1.45),
            ),
            const SizedBox(height: 14),
            Row(
              children: <Widget>[
                Expanded(
                  child: FilledButton.icon(
                    onPressed: _activationBusy ? null : _resendActivation,
                    icon: const Icon(Icons.send_outlined, size: 18),
                    label: const Text('Resend link'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _activationBusy ? null : _checkActivation,
                    icon: const Icon(Icons.refresh_rounded, size: 18),
                    label: const Text('Check status'),
                  ),
                ),
              ],
            ),
          ],
        ),
      );

  Widget _membershipCard(AppState app) {
    final expires = app.planExpiresAt;
    if (app.hasPulse) {
      return AbsCard(
        borderColor: fade(AppColors.up, .42),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            const Row(
              children: <Widget>[
                Icon(Icons.workspace_premium_rounded, color: AppColors.gold),
                SizedBox(width: 10),
                Expanded(child: Text('Pulse membership', style: AppText.h2)),
                Pill('ACTIVE', color: AppColors.up),
              ],
            ),
            const SizedBox(height: 9),
            Text(
              expires == null ? 'Your Pulse package is active.' : '${app.daysLeft} day${app.daysLeft == 1 ? '' : 's'} left · until ${fmtDate(expires)}',
              style: AppText.muted,
            ),
            const SizedBox(height: 12),
            OutlinedButton(onPressed: () => push(context, const PlansScreen()), child: const Text('Manage membership')),
          ],
        ),
      );
    }
    final pending = app.pendingPlanId;
    if (pending != null) {
      final plan = MockData.plan(pending);
      return AbsCard(
        borderColor: fade(AppColors.amber, .4),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            const Icon(Icons.hourglass_top_rounded, color: AppColors.amber),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(plan.name, style: AppText.h2),
                  const SizedBox(height: 5),
                  const Text('Your membership request/payment is awaiting ABS verification.', style: AppText.muted),
                  const SizedBox(height: 8),
                  TextButton(onPressed: () => push(context, const PlansScreen()), child: const Text('View request')),
                ],
              ),
            ),
          ],
        ),
      );
    }
    return AbsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Row(
            children: <Widget>[
              Icon(Icons.workspace_premium_outlined, color: AppColors.gold),
              SizedBox(width: 10),
              Expanded(child: Text('Verified account', style: AppText.h2)),
            ],
          ),
          const SizedBox(height: 8),
          const Text('Choose an available Pulse package to unlock member scanning, signals and trading tools.', style: TextStyle(color: AppColors.muted, height: 1.4)),
          const SizedBox(height: 13),
          PrimaryButton(label: 'View Pulse packages', onPressed: () => push(context, const PlansScreen())),
        ],
      ),
    );
  }

  Widget _group(String title, List<Widget> tiles) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.only(top: 24, bottom: 8, left: 4),
            child: Text(title, style: AppText.label),
          ),
          AbsCard(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Column(
              children: <Widget>[
                for (int i = 0; i < tiles.length; i++) ...<Widget>[
                  if (i > 0) const Divider(height: 1, indent: 56),
                  tiles[i],
                ],
              ],
            ),
          ),
        ],
      );

  Widget _menu(IconData icon, String title, String subtitle, VoidCallback onTap) => ListTile(
        leading: Icon(icon, color: AppColors.muted),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Text(subtitle, style: AppText.muted),
        trailing: const Icon(Icons.chevron_right_rounded, color: AppColors.faint),
        onTap: onTap,
      );
}
