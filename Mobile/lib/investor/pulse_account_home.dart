import 'package:flutter/material.dart';

import '../screens/alerts_screen.dart';
import '../screens/help_center_screen.dart';
import '../screens/plans_screen.dart';
import '../screens/profile_screen.dart';
import '../screens/reports_screen.dart';
import '../screens/scanner_screen.dart';
import '../screens/trade_history_screen.dart';
import '../template_rebase/screens/account_screen.dart';
import '../template_rebase/screens/auth_screen.dart';
import '../template_rebase/state/app_state.dart';
import 'investor_ui.dart';
import 'market_command_ui.dart';
import 'premium_ui.dart';
import 'pulse_experience.dart';

/// A concise premium gateway. The full legacy Account toolbox is preserved
/// behind "All account & trading tools" so no existing action is removed.
class PulseAccountHome extends StatelessWidget {
  const PulseAccountHome({super.key, required this.onTab, required this.onNews});
  final ValueChanged<int> onTab;
  final VoidCallback onNews;

  void _push(BuildContext context, Widget page) => Navigator.of(context)
    .push(MaterialPageRoute<void>(builder: (_) => page));

  @override Widget build(BuildContext context) {
    final app = AppScope.of(context), investor = InvestorScope.of(context);
    void openProtected(Widget page) {
      if (!app.signedIn || !app.emailVerified) {
        _push(context, const AuthScreen());
      } else { _push(context, page); }
    }
    return PremiumBackdrop(child: SafeArea(bottom: false,
      child: RefreshIndicator(color: invGold, onRefresh: app.refreshAccount,
        child: ListView(physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(15, 17, 15, 32), children: [
          const Text('Account', style: TextStyle(color: Colors.white,
            fontSize: 25, fontWeight: FontWeight.w900)),
          const SizedBox(height: 16),
          PremiumCard(highlight: true, child: Row(children: [
            CircleAvatar(radius: 26, backgroundColor: invRaised,
              child: Text(app.userName.trim().isNotEmpty ? app.userName.trim()[0].toUpperCase() : 'P',
                style: const TextStyle(color: invGold, fontSize: 23, fontWeight: FontWeight.w900))),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(app.userName, maxLines: 1, overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 17)),
              const SizedBox(height: 4),
              Text(app.email, maxLines: 1, overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: invMuted, fontSize: 11.5)),
            ])),
            PremiumChip(investor.eligible ? 'INVESTOR' : app.hasPulse ? 'PRO' : 'PULSE',
              color: invGold),
          ])),
          const MarketSectionHeading('Quick access'),
          _shortcutGrid([
            _Shortcut('My plan', Icons.workspace_premium_outlined,
              () => _push(context, const PlansScreen())),
            _Shortcut('Portfolio', Icons.account_balance_wallet_outlined, () => onTab(3)),
            _Shortcut('Alerts', Icons.notifications_none_rounded,
              () => openProtected(const AlertsScreen())),
            _Shortcut('Free Signal', Icons.card_giftcard_rounded, () => onTab(2)),
          ]),
          const MarketSectionHeading('Trading tools'),
          _shortcutGrid([
            _Shortcut('Scanner', Icons.radar_rounded,
              () => openProtected(const ScannerScreen())),
            _Shortcut('Trade history', Icons.history_rounded,
              () => openProtected(const TradeHistoryScreen())),
            _Shortcut('Reports & P/L', Icons.bar_chart_rounded,
              () => openProtected(const ReportsScreen())),
            _Shortcut('News', Icons.article_outlined, onNews),
          ]),
          const MarketSectionHeading('Preferences & support'),
          PremiumCard(child: Column(children: [
            _link(Icons.person_outline_rounded, 'Profile & security',
              () => openProtected(const ProfileScreen())),
            const Divider(height: 1, color: invBorder),
            _link(Icons.help_outline_rounded, 'Help center',
              () => _push(context, const HelpCenterScreen())),
            const Divider(height: 1, color: invBorder),
            _link(Icons.settings_outlined, 'All account & trading tools',
              () => _push(context, const AccountScreen())),
          ])),
          const SizedBox(height: 13),
          if (!app.signedIn)
            PremiumButton(label: 'Sign in or create account', icon: Icons.login_rounded,
              onTap: () => _push(context, const AuthScreen()))
          else if (!app.emailVerified)
            PremiumCard(child: const Text('Email activation is pending. Open Account settings to activate your access.',
              style: TextStyle(color: invMuted, fontSize: 12))),
          const SizedBox(height: 12),
          const PulseNotice('Pulse by Alpha Block Solutions · Private investor access is governed by ABS permissions.'),
        ])),
      ));
  }

  Widget _link(IconData icon, String title, VoidCallback action) =>
    ListTile(onTap: action, dense: true, contentPadding: EdgeInsets.zero,
      leading: Icon(icon, color: invGold, size: 23),
      title: Text(title, style: const TextStyle(color: Colors.white,
        fontSize: 13, fontWeight: FontWeight.w700)),
      trailing: const Icon(Icons.chevron_right_rounded, color: invMuted, size: 19));

  Widget _shortcutGrid(List<_Shortcut> shortcuts) => GridView.count(
    crossAxisCount: 2,
    childAspectRatio: 2.6,
    mainAxisSpacing: 8,
    crossAxisSpacing: 8,
    shrinkWrap: true,
    physics: const NeverScrollableScrollPhysics(),
    children: [
      for (final s in shortcuts)
        Material(
          color: invCard,
          borderRadius: BorderRadius.circular(13),
          child: InkWell(
            onTap: s.tap,
            borderRadius: BorderRadius.circular(13),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(13),
                border: Border.all(color: invBorder)),
              child: Row(children: [
                Icon(s.icon, color: invGold, size: 21),
                const SizedBox(width: 8),
                Expanded(child: Text(s.title, maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Colors.white, fontSize: 12,
                    fontWeight: FontWeight.w700))),
              ]),
            ),
          ),
        ),
    ],
  );
}

class _Shortcut {
  const _Shortcut(this.title, this.icon, this.tap);
  final String title;
  final IconData icon;
  final VoidCallback tap;
}
