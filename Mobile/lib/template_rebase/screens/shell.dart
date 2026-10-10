import 'package:flutter/material.dart';

import '../../core/ads/ad_service.dart';
import '../../core/notifications/notification_router.dart';
import '../../screens/plans_screen.dart';
import '../../screens/signals_screen.dart';
import '../../investor/pulse_experience.dart';
import '../../investor/investor_portfolio.dart';
import '../../investor/futures_command_home.dart';
import '../../investor/markets_analysis_screen.dart';
import '../../investor/pulse_account_home.dart';
import '../../investor/investor_ui.dart';
import '../../investor/premium_ui.dart';
import '../../investor/market_command_ui.dart' show PulseNotice;
import '../../template_rebase/state/app_state.dart';
import 'free_signal_screen.dart';
import 'news_screen.dart';

/// V1.7.2: one navigation for everyone. No onboarding choice and no role toggle.
/// Only the Portfolio screen performs investor eligibility gating on the server.
class Shell extends StatefulWidget {
  const Shell({super.key});
  @override State<Shell> createState() => _ShellState();
}

class _ShellState extends State<Shell> {
  int _index = 0;

  @override void initState() {
    super.initState();
    AdService.instance.setShellTab(0);
    NotificationRouter.instance.pending.addListener(_openPendingNotification);
    WidgetsBinding.instance.addPostFrameCallback((_) => _openPendingNotification());
  }

  @override void dispose() {
    NotificationRouter.instance.pending.removeListener(_openPendingNotification);
    super.dispose();
  }

  void _openNews() => Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => const NewsScreen()));

  void _openPendingNotification() {
    if (!mounted) return;
    final destination = NotificationRouter.instance.take();
    if (destination == null) return;
    if (destination.tab == PulseTab.news) {
      _openNews();
    } else if (destination.tab == PulseTab.signals) {
      Navigator.of(context).push(MaterialPageRoute<void>(
        builder: (_) => const SignalsScreen()));
    } else {
      final tab = switch (destination.tab) {
        PulseTab.home => 0,
        PulseTab.pulse => 1,
        PulseTab.freeSignal => 2,
        PulseTab.news => 0,
        PulseTab.account => 4,
        PulseTab.signals => 0,
        PulseTab.portfolio => 3,
      };
      _go(tab);
    }
    if (destination.openPlans) {
      Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const PlansScreen()));
    }
  }

  void _go(int next) {
    if (next < 0 || next > 4) return;
    setState(() => _index = next);
    // Keep the existing ad-placement policy. No ads on private investor pages.
    AdService.instance.setShellTab(next == 3 ? 9 : next);
    if (next == 0) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) AppScope.read(context).refreshPulse();
      });
    }
  }

  @override Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: invBg,
      body: IndexedStack(index: _index, children: [
        FuturesCommandHome(onTab: _go, onNews: _openNews),
        MarketsAnalysisScreen(onNews: _openNews),
        const FreeSignalScreen(embedded: true),
        _PortfolioGateway(onAccount: () => _go(4)),
        PulseAccountHome(onTab: _go, onNews: _openNews),
      ]),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          gradient: const LinearGradient(colors: [Color(0xFF12243C), invBg]),
          border: Border(top: BorderSide(color: invGold.withAlpha(60))),
          borderRadius: const BorderRadius.vertical(top: Radius.circular(22)),
        ),
        child: SafeArea(top: false, child: Padding(
          padding: const EdgeInsets.fromLTRB(5, 7, 5, 4),
          child: Row(children: [
            _nav(0, Icons.home_outlined, Icons.home_rounded, 'Home'),
            _nav(1, Icons.candlestick_chart_outlined, Icons.candlestick_chart_rounded, 'Markets'),
            _nav(2, Icons.card_giftcard_outlined, Icons.card_giftcard_rounded, 'Free Signal', featured: true),
            _nav(3, Icons.account_balance_wallet_outlined, Icons.account_balance_wallet_rounded, 'Portfolio'),
            _nav(4, Icons.person_outline, Icons.person_rounded, 'Account'),
          ]),
        )),
      ),
    );
  }

  Widget _nav(int target, IconData idle, IconData active, String title, {bool featured = false}) {
    final chosen = target == _index;
    final tone = chosen ? invGold : invMuted;
    return Expanded(child: InkWell(
      onTap: () => _go(target), borderRadius: BorderRadius.circular(12),
      child: Padding(padding: const EdgeInsets.symmetric(vertical: 6),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(
            width: featured ? 40 : 34, height: 34,
            alignment: Alignment.center,
            decoration: featured ? BoxDecoration(
              borderRadius: BorderRadius.circular(13),
              color: invGold.withAlpha(chosen ? 45 : 22),
              border: Border.all(color: invGold.withAlpha(chosen ? 235 : 110)),
            ) : null,
            child: Icon(chosen ? active : idle, color: featured ? invGold : tone, size: 23),
          ),
          const SizedBox(height: 4),
          Text(title, maxLines: 1, overflow: TextOverflow.ellipsis,
            style: TextStyle(color: tone, fontSize: 10.5,
              fontWeight: chosen ? FontWeight.w800 : FontWeight.w600)),
        ]),
      ),
    ));
  }
}

class _PortfolioGateway extends StatefulWidget {
  const _PortfolioGateway({required this.onAccount});
  final VoidCallback onAccount;
  @override State<_PortfolioGateway> createState() => _PortfolioGatewayState();
}
class _PortfolioGatewayState extends State<_PortfolioGateway> {
  @override Widget build(BuildContext context) {
    final exp = InvestorScope.of(context);
    final app = AppScope.of(context);
    if (exp.eligible && app.emailVerified) return const InvestorPortfolioScreen();
    return PremiumBackdrop(child: SafeArea(bottom: false,
      child: RefreshIndicator(color: invGold, onRefresh: exp.refresh,
        child: ListView(physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(18, 22, 18, 25), children: [
          const Text('Portfolio', style: TextStyle(color: Colors.white,
            fontSize: 25, fontWeight: FontWeight.w900)),
          const SizedBox(height: 8),
          const Text('Private investor reporting',
            style: TextStyle(color: invMuted, fontSize: 12)),
          const SizedBox(height: 20),
          PremiumCard(highlight: true, child: Column(children: [
            const Icon(Icons.account_balance_wallet_outlined, color: invGold, size: 40),
            const SizedBox(height: 15),
            Text(exp.loading ? 'Checking investor access…' : !app.signedIn
                ? 'Sign in to view your portfolio' : !app.emailVerified
                  ? 'Activate your account' : 'Private investor access required',
              textAlign: TextAlign.center, style: const TextStyle(color: Colors.white,
                fontWeight: FontWeight.w800, fontSize: 18)),
            const SizedBox(height: 9),
            const Text('Approved investors can securely view their capital, performance, '
              'payouts, monthly statements and requests. Access is verified by ABS.',
              textAlign: TextAlign.center,
              style: TextStyle(color: invMuted, fontSize: 12, height: 1.5)),
            if (exp.error != null) ...[
              const SizedBox(height: 8),
              Text(exp.error!, textAlign: TextAlign.center,
                style: const TextStyle(color: invMuted, fontSize: 11)),
            ],
            const SizedBox(height: 16),
            PremiumButton(label: app.signedIn ? 'Account & access' : 'Sign in',
              icon: Icons.person_outline_rounded, onTap: widget.onAccount),
            if (app.signedIn) TextButton(onPressed: exp.refresh,
              child: const Text('Recheck portfolio access', style: TextStyle(color: invGold))),
          ])),
          const SizedBox(height: 20),
          const PulseNotice('Investor figures are never displayed until the private account API confirms authorization.'),
        ])),
      ));
  }
}
