import 'package:flutter/material.dart';

import '../data/mock_data.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../utils/format.dart';
import '../widgets/charts.dart';
import '../widgets/common.dart';
import '../widgets/tiles.dart';
import '../../screens/alerts_screen.dart';

class SignalDetailScreen extends StatelessWidget {
  const SignalDetailScreen({super.key, required this.signal});
  final Signal signal;

  @override
  Widget build(BuildContext context) {
    final s = signal;
    final isLong = s.side == Side.long;
    final sideColor = isLong ? AppColors.up : AppColors.down;
    final levels = <(String, double, Color)>[
      for (int i = 0; i < s.targets.length; i++) ('Target ${i + 1}', s.targets[i], AppColors.up),
      ('Entry', s.entry, AppColors.accent),
      ('Stop', s.stop, AppColors.down),
    ]..sort((a, b) => b.$2.compareTo(a.$2));
    final v15 = s.votes('15M');
    final v4 = s.votes('4H');

    return Scaffold(
      appBar: AppBar(title: Text(s.pair)),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
        children: [
          AbsCard(
            child: Row(children: [
              ConfidenceRing(value: s.confidence, size: 84, stroke: 7, caption: 'confidence'),
              const SizedBox(width: 18),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      Pill(isLong ? 'Long' : 'Short', color: sideColor),
                      Pill(s.timeframe, color: AppColors.muted),
                      StatusLabel(signal: s),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Text(isLong ? 'Long setup' : 'Short setup',
                      style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 4),
                  Text(
                      'Posted ${timeAgo(s.minutesAgo)}. ${s.agree} of 15 strategies agree on the 15M chart.',
                      style: AppText.muted.copyWith(height: 1.4)),
                ]),
              ),
            ]),
          ),
          const SectionTitle('Price levels'),
          AbsCard(
            child: Column(children: [
              for (final l in levels)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: Row(children: [
                    Container(
                        width: 10,
                        height: 10,
                        decoration: BoxDecoration(color: l.$3, shape: BoxShape.circle)),
                    const SizedBox(width: 12),
                    Expanded(
                        child: Text(l.$1,
                            style: TextStyle(
                                fontWeight:
                                    l.$1 == 'Entry' ? FontWeight.w700 : FontWeight.w500))),
                    Text(fmtPrice(l.$2), style: AppText.figure.copyWith(fontSize: 14.5)),
                    SizedBox(
                      width: 72,
                      child: Text(
                        l.$1 == 'Entry' ? '' : fmtPct((l.$2 - s.entry) / s.entry * 100),
                        textAlign: TextAlign.right,
                        style: AppText.muted,
                      ),
                    ),
                  ]),
                ),
              const Divider(height: 20),
              Row(children: [
                const Expanded(child: Text('Risk to reward at target 1', style: AppText.muted)),
                Text('1 : ${s.riskReward.toStringAsFixed(2)}',
                    style: AppText.figure.copyWith(fontSize: 14.5)),
              ]),
            ]),
          ),
          const SectionTitle('Strategy breakdown'),
          AbsCard(
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 8),
            child: Column(children: [
              const Row(children: [
                Expanded(child: Text('Strategy', style: AppText.label)),
                SizedBox(width: 48, child: Center(child: Text('15M', style: AppText.label))),
                SizedBox(width: 48, child: Center(child: Text('4H', style: AppText.label))),
              ]),
              const Divider(height: 18),
              for (int i = 0; i < MockData.strategies.length; i++)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: Row(children: [
                    Expanded(
                        child: Text(MockData.strategies[i],
                            style: const TextStyle(fontSize: 13.5))),
                    SizedBox(width: 48, child: Center(child: VoteIcon(v15[i]))),
                    SizedBox(width: 48, child: Center(child: VoteIcon(v4[i]))),
                  ]),
                ),
              const SizedBox(height: 6),
              const Wrap(spacing: 14, runSpacing: 6, children: [
                _Key(icon: Icons.check_circle_rounded, color: AppColors.up, label: 'Agrees'),
                _Key(icon: Icons.remove_circle_outline_rounded, color: AppColors.faint, label: 'Neutral'),
                _Key(icon: Icons.cancel_rounded, color: AppColors.down, label: 'Disagrees'),
              ]),
              const SizedBox(height: 6),
            ]),
          ),
          const SectionTitle('Analyst note'),
          AbsCard(child: Text(s.note, style: const TextStyle(height: 1.55, fontSize: 14.5))),
          const SizedBox(height: 20),
          PrimaryButton(
            label: 'Open price alerts',
            icon: Icons.add_alert_outlined,
            onPressed: AppScope.read(context).emailVerified
                ? () => push(context, const AlertsScreen())
                : () => snack(context, 'Activate your account to create trading alerts.'),
          ),
          const RiskNotice(),
        ],
      ),
    );
  }
}

class _Key extends StatelessWidget {
  const _Key({required this.icon, required this.color, required this.label});
  final IconData icon;
  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) => Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, size: 14, color: color),
        const SizedBox(width: 4),
        Text(label, style: AppText.muted.copyWith(fontSize: 11.5)),
      ]);
}
