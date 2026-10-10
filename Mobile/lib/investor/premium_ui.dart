import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'investor_ui.dart';
import '../template_rebase/widgets/common.dart' show AbsLogo;

/// Shared presentation for the approved Pulse 2026 mobile redesign.
/// The widgets use live input data; they never generate financial results.
class PremiumBackdrop extends StatelessWidget {
  const PremiumBackdrop({super.key, required this.child});
  final Widget child;
  @override
  Widget build(BuildContext context) => Container(
    decoration: const BoxDecoration(gradient: LinearGradient(
      begin: Alignment.topLeft, end: Alignment.bottomRight,
      colors: [Color(0xFF081B34), invBg, Color(0xFF060E1A)],
      stops: [0.0, .32, 1.0],
    )),
    child: Stack(children: [
      Positioned(right: -145, top: -190, child: IgnorePointer(child: Container(
        width: 400, height: 400,
        decoration: BoxDecoration(shape: BoxShape.circle,
          gradient: RadialGradient(colors: [invBlue.withAlpha(38), invBlue.withAlpha(10), Colors.transparent]),
          border: Border.all(color: invBlue.withAlpha(48), width: 1.2)),
      ))),
      child,
    ]),
  );
}

class PremiumCard extends StatelessWidget {
  const PremiumCard({super.key, required this.child, this.highlight = false,
    this.padding = const EdgeInsets.all(17), this.accent});
  final Widget child;
  final bool highlight;
  final EdgeInsetsGeometry padding;
  final Color? accent;
  @override
  Widget build(BuildContext context) => Container(
    padding: padding,
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(21),
      gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight,
        colors: [const Color(0xFF102640), const Color(0xFF091827), if (highlight) const Color(0xFF201D1C)]),
      border: Border.all(color: accent ?? (highlight ? invGold.withAlpha(205) : invBlue.withAlpha(113)), width: highlight ? 1.25 : .8),
      boxShadow: highlight ? [BoxShadow(color: invGold.withAlpha(14), blurRadius: 24)] : null,
    ),
    child: child,
  );
}

class PremiumHeader extends StatelessWidget {
  const PremiumHeader({super.key, this.subtitle, this.onNotification, this.onFreeSignal});
  final String? subtitle;
  final VoidCallback? onNotification, onFreeSignal;
  @override Widget build(BuildContext context) => Row(children: [
    const AbsLogo(size: 37), const SizedBox(width: 10),
    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('Pulse', style: TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w900, letterSpacing: -.7)),
      Text(subtitle ?? 'BY ALPHA BLOCK SOLUTIONS', overflow: TextOverflow.ellipsis,
        style: const TextStyle(color: invMuted, fontSize: 9.5, letterSpacing: 1.1, fontWeight: FontWeight.w700)),
    ])),
    if (onFreeSignal != null) InkWell(onTap: onFreeSignal,
      borderRadius: BorderRadius.circular(25),
      child: Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
        decoration: BoxDecoration(border: Border.all(color: invGold.withAlpha(180)), borderRadius: BorderRadius.circular(30), color: invGold.withAlpha(25)),
        child: const Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(Icons.play_circle_fill_rounded, color: invGold, size: 17), SizedBox(width: 5),
          Text('Free Signal', style: TextStyle(color: invGold, fontSize: 11, fontWeight: FontWeight.w800))
        ]))),
    if (onNotification != null) IconButton(tooltip: 'Notifications',
      onPressed: onNotification, icon: const Icon(Icons.notifications_none_rounded, color: invGold)),
  ]);
}

class PremiumSection extends StatelessWidget {
  const PremiumSection(this.title, {super.key, this.action, this.onAction});
  final String title;
  final String? action;
  final VoidCallback? onAction;
  @override Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 23, bottom: 11),
    child: Row(children: [
      Expanded(child: Text(title, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800, letterSpacing: -.3))),
      if (action != null) TextButton(onPressed: onAction,
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Text(action!, style: const TextStyle(color: invBlue, fontSize: 12)),
          const SizedBox(width: 3), const Icon(Icons.arrow_forward_rounded, color: invBlue, size: 15),
        ])),
    ]),
  );
}

class PremiumKpi extends StatelessWidget {
  const PremiumKpi(this.title, this.value, {super.key, this.color = Colors.white, this.icon, this.subtitle});
  final String title, value;
  final String? subtitle;
  final Color color;
  final IconData? icon;
  @override Widget build(BuildContext context) => Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
    if (icon != null) ...[Icon(icon, color: invGold, size: 22), const SizedBox(height: 7)],
    Text(title, style: const TextStyle(color: invMuted, fontSize: 11.5)),
    const SizedBox(height: 5),
    Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: color, fontSize: 17, fontWeight: FontWeight.w800)),
    if (subtitle != null) Padding(padding: const EdgeInsets.only(top: 3), child: Text(subtitle!,
      maxLines: 2, style: const TextStyle(color: invMuted, fontSize: 10.5))),
  ]);
}

class PremiumAction extends StatelessWidget {
  const PremiumAction({super.key, required this.icon, required this.label, required this.onTap, this.gold = false});
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool gold;
  @override Widget build(BuildContext context) => Expanded(child: Padding(
    padding: const EdgeInsets.symmetric(horizontal: 3),
    child: InkWell(onTap: onTap, borderRadius: BorderRadius.circular(15),
      child: Container(
        constraints: const BoxConstraints(minHeight: 81),
        padding: const EdgeInsets.symmetric(horizontal: 3, vertical: 11),
        decoration: BoxDecoration(
          gradient: LinearGradient(colors: gold ? [const Color(0xFF654D15), const Color(0xFF17314B)] : [const Color(0xFF123359), const Color(0xFF0A1D31)]),
          border: Border.all(color: gold ? invGold : invBlue.withAlpha(120)), borderRadius: BorderRadius.circular(15)),
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          Icon(icon, color: invGold, size: 25), const SizedBox(height: 6),
          Text(label, textAlign: TextAlign.center, maxLines: 2,
            style: const TextStyle(color: Colors.white, fontSize: 11.5, fontWeight: FontWeight.w600)),
        ]),
      )),
  ));
}

class PremiumChip extends StatelessWidget {
  const PremiumChip(this.text, {super.key, this.color = invGold, this.icon});
  final String text;
  final Color color;
  final IconData? icon;
  @override Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
    decoration: BoxDecoration(color: color.withAlpha(22), borderRadius: BorderRadius.circular(20), border: Border.all(color: color.withAlpha(165))),
    child: Row(mainAxisSize: MainAxisSize.min, children: [
      if (icon != null) ...[Icon(icon, size: 13, color: color), const SizedBox(width: 4)],
      Text(text, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w800)),
    ]),
  );
}

class PremiumPerformanceChart extends StatelessWidget {
  const PremiumPerformanceChart({super.key, required this.values, this.height = 145, this.color = invGold, this.emptyLabel = 'No published history yet'});
  final List<double> values;
  final double height;
  final Color color;
  final String emptyLabel;
  @override Widget build(BuildContext context) => SizedBox(width: double.infinity, height: height,
    child: values.length >= 2
      ? CustomPaint(painter: _ChartPainter(values, color))
      : Center(child: Text(emptyLabel, style: const TextStyle(color: invMuted, fontSize: 12))));
}

class _ChartPainter extends CustomPainter {
  const _ChartPainter(this.values, this.color);
  final List<double> values;
  final Color color;
  @override void paint(Canvas canvas, Size size) {
    final top = values.reduce(math.max), bottom = values.reduce(math.min);
    final spread = math.max((top - bottom).abs(), .01);
    final points = <Offset>[];
    final available = math.max(10.0, size.height - 32);
    for (var i = 0; i < values.length; i++) {
      points.add(Offset(size.width * i / (values.length - 1),
        12 + available * (1 - (values[i] - bottom) / spread)));
    }
    final grid = Paint()..color = invBlue.withAlpha(25)..strokeWidth = 1;
    for (var i = 1; i <= 3; i++) {
      final y = size.height * i / 4;
      canvas.drawLine(Offset(0, y), Offset(size.width, y), grid);
    }
    final path = Path()..moveTo(points.first.dx, points.first.dy);
    for (final p in points.skip(1)) { path.lineTo(p.dx, p.dy); }
    final fill = Path.from(path)..lineTo(size.width, size.height)..lineTo(0, size.height)..close();
    canvas.drawPath(fill, Paint()..shader = LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter,
      colors: [color.withAlpha(73), color.withAlpha(0)]).createShader(Offset.zero & size));
    canvas.drawPath(path, Paint()..color=color..style=PaintingStyle.stroke..strokeWidth=2.5..strokeJoin=StrokeJoin.round..strokeCap=StrokeCap.round);
    canvas.drawCircle(points.last, 3.5, Paint()..color=color);
  }
  @override bool shouldRepaint(covariant _ChartPainter old) => old.color != color || !_same(old.values, values);
  static bool _same(List<double> a,List<double> b) => a.length == b.length && List.generate(a.length, (i) => a[i] == b[i]).every((x) => x);
}

class PremiumReturnsBar extends StatelessWidget {
  const PremiumReturnsBar({super.key, required this.rows});
  final List<({String label, double amount})> rows;
  @override Widget build(BuildContext context) {
    if (rows.isEmpty) return const Text('Monthly performance will appear after publication.', style: TextStyle(color: invMuted, fontSize: 12));
    final peak = rows.fold<double>(1, (a,r) => math.max(a,r.amount.abs()));
    return SizedBox(height: 154, child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
      for (final row in rows) Expanded(child: Padding(padding: const EdgeInsets.symmetric(horizontal: 3),
        child: Column(mainAxisAlignment: MainAxisAlignment.end, children: [
          Text(row.amount >= 0 ? '+' : '−', style: TextStyle(color: row.amount >= 0 ? invGreen : const Color(0xFFFF6479), fontSize: 12, fontWeight: FontWeight.w900)),
          const SizedBox(height: 3),
          Container(height: math.max(7.0, row.amount.abs() / peak * 105),
            decoration: BoxDecoration(borderRadius: const BorderRadius.vertical(top: Radius.circular(5)),
              gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: row.amount>=0
                ? [invGreen, invGreen.withAlpha(112)] : [const Color(0xFFFF6576), const Color(0xFF7B324A)]))),
          const SizedBox(height: 6),
          Text(row.label, maxLines: 1, overflow: TextOverflow.fade,
            style: const TextStyle(color: invMuted, fontSize: 10.5)),
        ]))),
    ]));
  }
}

class PremiumButton extends StatelessWidget {
  const PremiumButton({super.key, required this.label, required this.onTap, this.icon = Icons.arrow_forward_rounded, this.gold = true});
  final String label;
  final VoidCallback? onTap;
  final IconData icon;
  final bool gold;
  @override Widget build(BuildContext context) => SizedBox(width: double.infinity, child: FilledButton.icon(
    onPressed: onTap, icon: Icon(icon, size: 19), label: Text(label),
    style: FilledButton.styleFrom(
      backgroundColor: gold ? invGold : invRaised,
      foregroundColor: gold ? invBg : Colors.white,
      minimumSize: const Size.fromHeight(47),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(13)),
      textStyle: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14),
    ),
  ));
}

class PremiumTinyRow extends StatelessWidget {
  const PremiumTinyRow({super.key, required this.icon, required this.title, required this.subtitle, this.trailing, this.onTap, this.iconColor = invBlue});
  final IconData icon;
  final String title, subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;
  final Color iconColor;
  @override Widget build(BuildContext context) => InkWell(onTap: onTap, borderRadius: BorderRadius.circular(10),
    child: Padding(padding: const EdgeInsets.symmetric(vertical: 10), child: Row(children: [
      Container(width: 37, height: 37, alignment: Alignment.center,
        decoration: BoxDecoration(color: iconColor.withAlpha(22), borderRadius: BorderRadius.circular(11)),
        child: Icon(icon, color: iconColor, size: 21)),
      const SizedBox(width: 11),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(title, maxLines: 1, overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w700)),
        const SizedBox(height: 3),
        Text(subtitle, maxLines: 2, style: const TextStyle(color: invMuted, fontSize: 11)),
      ])),
      if (trailing != null) ...[const SizedBox(width: 5), trailing!],
      const Icon(Icons.chevron_right_rounded, size: 19, color: invMuted),
    ])));
}
