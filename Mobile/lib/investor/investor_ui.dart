import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/json_tools.dart';

const invBg = Color(0xFF07111F);
const invCard = Color(0xFF0E1C30);
const invRaised = Color(0xFF15263D);
const invBorder = Color(0xFF243752);
const invGold = Color(0xFFE9B949);
const invGreen = Color(0xFF28D194);
const invBlue = Color(0xFF50A4FF);
const invMuted = Color(0xFFABBBD0);

String money(dynamic n, String currency, {int decimals = 2}) =>
  '$currency ${NumberFormat.decimalPatternDigits(locale: 'en_US', decimalDigits: decimals).format(JsonTools.number(n))}';

String pct(dynamic n, {bool signed = false}) {
  final d = JsonTools.number(n);
  return '${signed && d > 0 ? '+' : ''}${d.toStringAsFixed(2)}%';
}

class InvestorPanel extends StatelessWidget {
  const InvestorPanel({super.key, required this.child, this.padding = const EdgeInsets.all(16), this.borderColor});
  final Widget child;
  final EdgeInsets padding;
  final Color? borderColor;

  @override
  Widget build(BuildContext context) => Container(
    padding: padding,
    decoration: BoxDecoration(
      gradient: const LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight,
        colors: [Color(0xFF102640), Color(0xFF091827)]),
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: borderColor ?? invBlue.withAlpha(100)),
    ),
    child: child,
  );
}

class InvestorSection extends StatelessWidget {
  const InvestorSection(this.title, {super.key, this.trailing});
  final String title;
  final Widget? trailing;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 12),
    child: Row(children: [
      Expanded(child: Text(title, style: const TextStyle(
          color: Colors.white, fontWeight: FontWeight.w800, fontSize: 18))),
      if (trailing != null) trailing!,
    ]),
  );
}

class InvestorMetric extends StatelessWidget {
  const InvestorMetric(this.label, this.value, {super.key, this.color = Colors.white});
  final String label, value;
  final Color color;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(label, maxLines: 2, style: const TextStyle(color: invMuted, fontSize: 11)),
      const SizedBox(height: 5),
      FittedBox(fit: BoxFit.scaleDown, alignment: Alignment.centerLeft,
        child: Text(value, style: TextStyle(color: color, fontSize: 17, fontWeight: FontWeight.w800))),
    ],
  );
}

class InvestorQuickAction extends StatelessWidget {
  const InvestorQuickAction({super.key, required this.icon, required this.label, required this.onTap, this.highlight = false});
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool highlight;
  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(12),
    child: Column(children: [
      Container(width: 53, height: 46,
        decoration: BoxDecoration(color: highlight ? invGold : invRaised,
          borderRadius: BorderRadius.circular(13)),
        child: Icon(icon, color: highlight ? invBg : Colors.white, size: 22)),
      const SizedBox(height: 6),
      Text(label, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 11.5),
        maxLines: 2, textAlign: TextAlign.center),
    ]),
  );
}

class InvestorNotice extends StatelessWidget {
  const InvestorNotice(this.message, {super.key, this.icon = Icons.info_outline_rounded});
  final String message;
  final IconData icon;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(color: invRaised, borderRadius: BorderRadius.circular(12)),
    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Icon(icon, color: invGold, size: 18), const SizedBox(width: 9),
      Expanded(child: Text(message,
        style: const TextStyle(color: invMuted, height: 1.4, fontSize: 12))),
    ]),
  );
}

class InvestorModeSwitch extends StatelessWidget {
  const InvestorModeSwitch({super.key, required this.investorSelected, required this.onSelect});
  final bool investorSelected;
  final ValueChanged<bool> onSelect;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(5),
    decoration: BoxDecoration(
      gradient: const LinearGradient(colors: [Color(0xFF152D48), Color(0xFF0C1A2D)]),
      border: Border.all(color: invBlue.withAlpha(90)),
      borderRadius: BorderRadius.circular(15)),
    child: Row(children: [
      Expanded(child: _item('Invest', investorSelected, () => onSelect(true), invGold)),
      Expanded(child: _item('Trade', !investorSelected, () => onSelect(false), invBlue)),
    ]),
  );

  Widget _item(String text, bool selected, VoidCallback tap, Color color) => InkWell(
    onTap: tap,
    borderRadius: BorderRadius.circular(10),
    child: AnimatedContainer(duration: const Duration(milliseconds: 180),
      padding: const EdgeInsets.symmetric(vertical: 12),
      decoration: BoxDecoration(color: selected ? color : Colors.transparent,
        borderRadius: BorderRadius.circular(10)),
      child: Text(text, textAlign: TextAlign.center, style: TextStyle(
        fontWeight: FontWeight.w800, color: selected ? invBg : invMuted))),
  );
}

class InvestorLineGraph extends StatelessWidget {
  const InvestorLineGraph(this.values, {super.key, this.height = 135});
  final List<double> values;
  final double height;
  @override
  Widget build(BuildContext context) => SizedBox(
    height: height,
    width: double.infinity,
    child: values.length > 1
      ? CustomPaint(painter: _InvestorLinePainter(values))
      : const Center(child: Text('Performance history will appear when published',
          textAlign: TextAlign.center, style: TextStyle(color: invMuted, fontSize: 12))),
  );
}

class _InvestorLinePainter extends CustomPainter {
  _InvestorLinePainter(this.values);
  final List<double> values;
  @override
  void paint(Canvas c, Size size) {
    if (values.length < 2) return;
    final minV = values.reduce((a,b) => a < b ? a : b);
    final maxV = values.reduce((a,b) => a > b ? a : b);
    final span = (maxV - minV).abs() < 0.001 ? 1.0 : maxV - minV;
    final points = <Offset>[];
    for (int i=0;i<values.length;i++) {
      points.add(Offset(size.width*i/(values.length-1),
        14 + (size.height-28)*(1-(values[i]-minV)/span)));
    }
    for (int i=1;i<4;i++) {
      final y = size.height*i/4;
      c.drawLine(Offset(0,y),Offset(size.width,y), Paint()
        ..color = invBorder..strokeWidth=.6);
    }
    final path = Path()..moveTo(points.first.dx, points.first.dy);
    for(final p in points.skip(1)) { path.lineTo(p.dx,p.dy); }
    c.drawPath(path, Paint()..color=invGold..style=PaintingStyle.stroke
      ..strokeWidth=2.6..strokeCap=StrokeCap.round..strokeJoin=StrokeJoin.round);
    final shade = Path.from(path)..lineTo(size.width,size.height)..lineTo(0,size.height)..close();
    c.drawPath(shade, Paint()..shader=LinearGradient(begin: Alignment.topCenter,
      end: Alignment.bottomCenter, colors: [invGold.withAlpha(50),invGold.withAlpha(0)])
      .createShader(Offset.zero & size));
  }
  @override
  bool shouldRepaint(covariant _InvestorLinePainter old) => old.values != values;
}
