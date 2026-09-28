import 'package:flutter/material.dart';

import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';
import 'content_screens.dart';
import '../template_rebase/screens/free_signal_screen.dart';
import 'plans_screen.dart';
import 'private_investor_screen.dart';
import 'scanner_screen.dart';
import 'signals_screen.dart';
import 'trading_setup_screen.dart';

class HelpCenterScreen extends StatelessWidget {
  const HelpCenterScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    return AbsPage(
      title: 'Help Center',
      subtitle: 'Clear guidance for new and experienced ABS users',
      child: ListView(
        children: [
          PremiumHeroCard(
            eyebrow: 'START HERE',
            title: 'New to Pulse? Follow one simple flow',
            message: 'Set up your account, scan markets, review a signal, then decide whether to execute. ABS keeps risk and execution checks server-side throughout the process.',
            trailing: const Icon(Icons.route_rounded, color: AbsColors.cyan, size: 38),
            footer: SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: session.hasPulseAccess ? () => _push(context, const TradingSetupScreen()) : () => _push(context, const PlansScreen()),
                icon: const Icon(Icons.play_arrow_rounded),
                label: Text(session.hasPulseAccess ? 'Start guided setup' : 'View Pulse access'),
              ),
            ),
          ),
          const SizedBox(height: 20),
          const AbsSectionTitle('Quick guides', eyebrow: 'Beginner friendly'),
          const SizedBox(height: 10),
          _HelpTile(icon: Icons.tune_rounded, title: 'Set up Pulse safely', subtitle: 'Binance connection, risk limits, selected markets and execution controls.', onTap: () => _push(context, const TradingSetupScreen())),
          _HelpTile(icon: Icons.radar_rounded, title: 'How Market Scan works', subtitle: 'Understand how ABS checks your selected markets on 15M and 4H.', onTap: () => _push(context, const ScannerScreen())),
          _HelpTile(icon: Icons.bolt_rounded, title: 'How to read a signal', subtitle: 'Review direction, score, entry, stop loss, take profit and validation evidence.', onTap: () => _push(context, const SignalsScreen())),
          _HelpTile(icon: Icons.card_giftcard_rounded, title: 'Free Signal', subtitle: 'Understand rewarded access, qualified signals, Entry Watch and fallback behavior.', onTap: () => _push(context, const FreeSignalScreen())),
          _HelpTile(icon: Icons.account_balance_rounded, title: 'Private Investor guide', subtitle: 'Principal, monthly performance, Profit Paid, capital withdrawal and statements.', onTap: () => _push(context, const PrivateInvestorHubScreen())),
          _HelpTile(icon: Icons.newspaper_rounded, title: 'Pulse Intelligence', subtitle: 'News, verified live headlines and the economic calendar in one place.', onTap: () => _push(context, const NewsScreen())),
          const SizedBox(height: 20),
          const AbsSectionTitle('Common questions'),
          const SizedBox(height: 10),
          const _Faq(question: 'Does the mobile app trade directly with Binance?', answer: 'No. The app sends requests to the ABS backend. Market data, readiness, permissions, risk controls and execution remain server-authoritative.'),
          const _Faq(question: 'What is Simple mode?', answer: 'Simple mode keeps the same account permissions and safety rules, but shows fewer metrics and clearer next steps. You can switch to Pro mode from the Pulse dashboard.'),
          const _Faq(question: 'What is Entry Watch?', answer: 'Entry Watch is a setup worth monitoring that has not met the full qualification standard. It must not be treated as a fully qualified Pulse signal.'),
          const _Faq(question: 'What does Profit Paid mean for Private Investors?', answer: 'Profit Paid is a profit distribution outside the investment. In the V15.7.4 model it does not reduce investor principal.'),
          const _Faq(question: 'What does Capital Withdrawal mean?', answer: 'Capital Withdrawal means part of the investment principal is being returned. It is not the normal monthly profit payout.'),
          const SizedBox(height: 20),
          const AbsSectionTitle('Still need help?'),
          const SizedBox(height: 10),
          QuickActionCard(icon: Icons.support_agent_rounded, title: 'Contact ABS Support', subtitle: 'Send your question directly to the ABS team', accent: AbsColors.gold, onTap: () => _push(context, const ContactScreen())),
          const SizedBox(height: 8),
          QuickActionCard(icon: Icons.gavel_outlined, title: 'Legal & Risk', subtitle: 'Review privacy, terms, trading risk and market disclaimers', onTap: () => _push(context, const LegalHubScreen())),
          const SizedBox(height: 24),
        ],
      ),
    );
  }

  static void _push(BuildContext context, Widget page) => Navigator.of(context).push(MaterialPageRoute(builder: (_) => page));
}

class _HelpTile extends StatelessWidget {
  const _HelpTile({required this.icon, required this.title, required this.subtitle, required this.onTap});
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: InkWell(
          borderRadius: BorderRadius.circular(18),
          onTap: onTap,
          child: AbsCard(
            padding: const EdgeInsets.all(13),
            child: Row(children: [Container(width: 42, height: 42, alignment: Alignment.center, decoration: BoxDecoration(color: AbsColors.panel2, borderRadius: BorderRadius.circular(12)), child: Icon(icon, color: AbsColors.cyan, size: 20)), const SizedBox(width: 11), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(subtitle, style: const TextStyle(color: AbsColors.muted, fontSize: 10.8, height: 1.35))])), const Icon(Icons.chevron_right_rounded, color: AbsColors.muted2)]),
          ),
        ),
      );
}

class _Faq extends StatefulWidget {
  const _Faq({required this.question, required this.answer});
  final String question;
  final String answer;

  @override
  State<_Faq> createState() => _FaqState();
}

class _FaqState extends State<_Faq> {
  bool open = false;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: AbsCard(
          padding: EdgeInsets.zero,
          child: InkWell(
            borderRadius: BorderRadius.circular(18),
            onTap: () => setState(() => open = !open),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Row(children: [Expanded(child: Text(widget.question, style: const TextStyle(fontWeight: FontWeight.w900))), const SizedBox(width: 8), Icon(open ? Icons.expand_less_rounded : Icons.expand_more_rounded, color: AbsColors.cyan)]), if (open) ...[const SizedBox(height: 8), Text(widget.answer, style: const TextStyle(color: AbsColors.muted, fontSize: 11.5, height: 1.45))]]),
            ),
          ),
        ),
      );
}
