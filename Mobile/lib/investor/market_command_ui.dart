import 'package:flutter/material.dart';

import '../template_rebase/data/mock_data.dart';
import 'investor_ui.dart';
import 'premium_ui.dart';

/// One design language for Home and Markets. Values always come from the
/// authenticated/public ABS state; the widgets never synthesize quotes.
const pulseRed = Color(0xFFFF647A);
const pulseCyan = Color(0xFF5CB9FF);

String pulseMoney(double value, {int decimals = 2, bool signed = false}) {
  if (!value.isFinite) return '—';
  final neg = value < 0;
  final sign = neg ? '-' : signed && value > 0 ? '+' : '';
  final n = value.abs();
  String out;
  if (n >= 1000000000000) {
    out = '\$${(n / 1000000000000).toStringAsFixed(2)}T';
  } else if (n >= 1000000000) {
    out = '\$${(n / 1000000000).toStringAsFixed(2)}B';
  } else if (n >= 1000000) {
    out = '\$${(n / 1000000).toStringAsFixed(1)}M';
  } else if (n >= 1000) {
    out = '\$${(n / 1000).toStringAsFixed(1)}K';
  } else {
    out = '\$${n.toStringAsFixed(decimals)}';
  }
  return '$sign$out';
}

String pulsePercent(double value, {int decimals = 2, bool signed = false}) =>
    value.isFinite ? '${signed && value > 0 ? '+' : ''}${value.toStringAsFixed(decimals)}%' : '—';

String pulseNumber(double value, {int decimals = 2}) =>
    value.isFinite ? value.toStringAsFixed(decimals) : '—';

Color pulseChangeColor(double value) => value < 0 ? pulseRed : invGreen;

class MarketSectionHeading extends StatelessWidget {
  const MarketSectionHeading(this.title, {super.key, this.action, this.onTap});
  final String title;
  final String? action;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(2, 21, 0, 11),
    child: Row(children: [
      Expanded(child: Text(title, style: const TextStyle(color: Colors.white,
        fontWeight: FontWeight.w800, fontSize: 17))),
      if (action != null) TextButton(onPressed: onTap, child: Text(action!,
        style: const TextStyle(color: pulseCyan, fontSize: 12, fontWeight: FontWeight.w600))),
    ]),
  );
}

class MarketMetric extends StatelessWidget {
  const MarketMetric({super.key, required this.label, required this.value,
    required this.icon, this.detail, this.tone = Colors.white});
  final String label, value;
  final String? detail;
  final IconData icon;
  final Color tone;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: const Color(0xFF101F32),
      border: Border.all(color: const Color(0xFF26405C)),
      borderRadius: BorderRadius.circular(14),
    ),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Icon(icon, size: 16, color: invGold),
        const SizedBox(width: 6),
        Expanded(child: Text(label, maxLines: 2, overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: invMuted, fontSize: 11.2, height: 1.15))),
      ]),
      const SizedBox(height: 11),
      Text(value, maxLines: 1, overflow: TextOverflow.ellipsis,
        style: TextStyle(color: tone, fontSize: 19, fontWeight: FontWeight.w800)),
      if (detail != null) ...[
        const SizedBox(height: 4),
        Text(detail!, maxLines: 1, overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: invMuted, fontSize: 10.2)),
      ],
    ]),
  );
}

class MetricGrid extends StatelessWidget {
  const MetricGrid({super.key, required this.children});
  final List<Widget> children;
  @override
  Widget build(BuildContext context) => LayoutBuilder(builder: (context, size) {
    final width = size.maxWidth;
    final cols = width >= 610 ? 3 : 2;
    return GridView.count(
      crossAxisCount: cols,
      childAspectRatio: width >= 610 ? 2.05 : width < 350 ? 1.48 : 1.66,
      mainAxisSpacing: 8, crossAxisSpacing: 8,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      children: children,
    );
  });
}

class PulseDirectionBar extends StatelessWidget {
  const PulseDirectionBar({super.key, required this.ratio});
  final double ratio;
  @override
  Widget build(BuildContext context) {
    if (!ratio.isFinite || ratio <= 0) return const Text('Long / short breakdown unavailable',
      style: TextStyle(color: invMuted, fontSize: 11));
    final pctLong = ratio / (1 + ratio) * 100;
    return Column(children: [
      ClipRRect(borderRadius: BorderRadius.circular(6), child: Row(children: [
        Expanded(flex: pctLong.round().clamp(1, 99).toInt(), child: Container(height: 7, color: invGreen)),
        Expanded(flex: (100-pctLong).round().clamp(1, 99).toInt(), child: Container(height: 7, color: pulseRed)),
      ])),
      const SizedBox(height: 6),
      Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Text('Long ${pctLong.toStringAsFixed(1)}%', style: const TextStyle(color: invGreen, fontSize: 10.5)),
        Text('Short ${(100-pctLong).toStringAsFixed(1)}%', style: const TextStyle(color: pulseRed, fontSize: 10.5)),
      ]),
    ]);
  }
}

class MarketCoinTile extends StatelessWidget {
  const MarketCoinTile({super.key, required this.coin, this.onTap});
  final Coin coin;
  final VoidCallback? onTap;
  @override
  Widget build(BuildContext context) => InkWell(onTap: onTap,
    child: Padding(padding: const EdgeInsets.symmetric(vertical: 10), child: Row(children: [
      CircleAvatar(radius: 17, backgroundColor: coin.color.withAlpha(35),
        child: Text(coin.symbol.length > 1 ? coin.symbol.substring(0, 1) : coin.symbol,
          style: TextStyle(color: coin.color, fontWeight: FontWeight.w900, fontSize: 15))),
      const SizedBox(width: 10),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(coin.symbol, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13)),
        Text(coin.name, overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: invMuted, fontSize: 10.5)),
      ])),
      Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
        Text(coin.price > 0 ? pulseMoney(coin.price) : '—',
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12.5)),
        Text(pulsePercent(coin.change, signed: true),
          style: TextStyle(color: pulseChangeColor(coin.change), fontSize: 10.5, fontWeight: FontWeight.w700)),
      ]),
    ])));
}

class PulseNotice extends StatelessWidget {
  const PulseNotice(this.text, {super.key});
  final String text;
  @override Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 12),
    child: Text(text, textAlign: TextAlign.center,
      style: const TextStyle(color: invMuted, fontSize: 10.5, height: 1.4)));
}

class SignalRow extends StatelessWidget {
  const SignalRow({super.key, required this.signal, required this.onTap});
  final Signal signal;
  final VoidCallback onTap;
  @override Widget build(BuildContext context) => InkWell(onTap: onTap,
    borderRadius: BorderRadius.circular(10), child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 10), child: Row(children: [
        const Icon(Icons.candlestick_chart_rounded, color: invGold, size: 20),
        const SizedBox(width: 9),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(signal.pair, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12.5)),
          Text('${signal.timeframe} · Confidence ${signal.confidence}%',
            style: const TextStyle(color: invMuted, fontSize: 10.5)),
        ])),
        PremiumChip(signal.side == Side.long ? 'LONG' : 'SHORT',
          color: signal.side == Side.long ? invGreen : pulseRed),
        const SizedBox(width: 5),
        const Icon(Icons.chevron_right, color: invMuted, size: 17),
      ])));
}
