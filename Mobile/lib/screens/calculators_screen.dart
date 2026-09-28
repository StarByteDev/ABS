import 'package:flutter/material.dart';

import '../core/theme.dart';
import '../template_ui/common.dart';

class CalculatorsScreen extends StatelessWidget {
  const CalculatorsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        backgroundColor: AbsColors.bg,
        appBar: AppBar(
          title: const Row(
            children: [
              PulseLogo(size: 34),
              SizedBox(width: 10),
              Text('Calculators', style: TextStyle(fontWeight: FontWeight.w900)),
            ],
          ),
          bottom: const TabBar(
            labelColor: AbsColors.text,
            unselectedLabelColor: AbsColors.muted,
            indicatorColor: AbsColors.cyan,
            dividerColor: AbsColors.line,
            tabs: [Tab(text: 'Position size'), Tab(text: 'Profit & loss')],
          ),
        ),
        body: const TabBarView(children: [_PositionSizeCalc(), _PnlCalc()]),
      ),
    );
  }
}

double _value(TextEditingController c) =>
    double.tryParse(c.text.replaceAll(',', '').trim()) ?? 0;

String _fmt(double value, {int decimals = 2}) {
  final negative = value < 0;
  final raw = value.abs().toStringAsFixed(decimals);
  final parts = raw.split('.');
  final digits = parts.first;
  final out = StringBuffer();
  for (var i = 0; i < digits.length; i++) {
    if (i > 0 && (digits.length - i) % 3 == 0) out.write(',');
    out.write(digits[i]);
  }
  return '${negative ? '-' : ''}$out${parts.length > 1 ? '.${parts[1]}' : ''}';
}

class _NumberField extends StatelessWidget {
  const _NumberField({required this.controller, required this.label, this.suffix});
  final TextEditingController controller;
  final String label;
  final String? suffix;

  @override
  Widget build(BuildContext context) => TextField(
        controller: controller,
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        decoration: InputDecoration(labelText: label, suffixText: suffix),
      );
}

class _ResultRow extends StatelessWidget {
  const _ResultRow(this.label, this.value, {this.color});
  final String label;
  final String value;
  final Color? color;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(
          children: [
            Expanded(child: Text(label, style: const TextStyle(color: AbsColors.muted, fontSize: 12.5))),
            Text(value, style: TextStyle(fontWeight: FontWeight.w900, fontSize: 13.5, color: color ?? AbsColors.text)),
          ],
        ),
      );
}

mixin _Controllers<T extends StatefulWidget> on State<T> {
  final List<TextEditingController> _controllers = [];

  TextEditingController ctrl(String initial) {
    final c = TextEditingController(text: initial)..addListener(() => setState(() {}));
    _controllers.add(c);
    return c;
  }

  @override
  void dispose() {
    for (final c in _controllers) c.dispose();
    super.dispose();
  }
}

class _PositionSizeCalc extends StatefulWidget {
  const _PositionSizeCalc();
  @override
  State<_PositionSizeCalc> createState() => _PositionSizeCalcState();
}

class _PositionSizeCalcState extends State<_PositionSizeCalc> with _Controllers {
  late final balance = ctrl('1000');
  late final risk = ctrl('1');
  late final entry = ctrl('68200');
  late final stop = ctrl('66950');

  @override
  Widget build(BuildContext context) {
    final bal = _value(balance);
    final riskPct = _value(risk);
    final entryPrice = _value(entry);
    final stopPrice = _value(stop);
    final riskAmount = bal * riskPct / 100;
    final perUnit = (entryPrice - stopPrice).abs();
    final size = perUnit > 0 ? riskAmount / perUnit : 0.0;
    final notional = size * entryPrice;
    final leverage = bal > 0 ? notional / bal : 0.0;
    final side = stopPrice < entryPrice ? 'Long' : stopPrice > entryPrice ? 'Short' : '—';
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const Text('Estimate a position size so the stop-loss risks only the share of your account you choose.', style: TextStyle(color: AbsColors.muted, height: 1.45)),
        const SizedBox(height: 18),
        Row(children: [Expanded(child: _NumberField(controller: balance, label: 'Account balance', suffix: 'USDT')), const SizedBox(width: 10), Expanded(child: _NumberField(controller: risk, label: 'Risk per trade', suffix: '%'))]),
        const SizedBox(height: 12),
        Row(children: [Expanded(child: _NumberField(controller: entry, label: 'Entry price')), const SizedBox(width: 10), Expanded(child: _NumberField(controller: stop, label: 'Stop-loss price'))]),
        const SizedBox(height: 18),
        TemplateCard(
          child: Column(
            children: [
              _ResultRow('Direction', side, color: side == 'Long' ? AbsColors.green : side == 'Short' ? AbsColors.red : null),
              _ResultRow('Amount at risk', '${_fmt(riskAmount)} USDT'),
              _ResultRow('Position size', '${size.toStringAsFixed(6)} units'),
              _ResultRow('Position value', '${_fmt(notional)} USDT'),
              _ResultRow('Effective leverage', '${leverage.toStringAsFixed(2)}x', color: leverage > 10 ? AbsColors.gold : null),
            ],
          ),
        ),
        if (leverage > 10) ...[
          const SizedBox(height: 10),
          const Text('Higher leverage increases liquidation risk. Consider a smaller risk amount or wider stop.', style: TextStyle(color: AbsColors.gold, fontSize: 11.5, height: 1.4)),
        ],
        const TemplateRiskNotice(),
      ],
    );
  }
}

class _PnlCalc extends StatefulWidget {
  const _PnlCalc();
  @override
  State<_PnlCalc> createState() => _PnlCalcState();
}

class _PnlCalcState extends State<_PnlCalc> with _Controllers {
  bool long = true;
  late final entry = ctrl('68200');
  late final exit = ctrl('70650');
  late final qty = ctrl('0.05');
  late final leverage = ctrl('5');

  @override
  Widget build(BuildContext context) {
    final entryPrice = _value(entry);
    final exitPrice = _value(exit);
    final quantity = _value(qty);
    final lev = _value(leverage) <= 0 ? 1.0 : _value(leverage);
    final pnl = (exitPrice - entryPrice) * quantity * (long ? 1 : -1);
    final margin = entryPrice * quantity / lev;
    final roe = margin > 0 ? pnl / margin * 100 : 0.0;
    final move = entryPrice > 0 ? (exitPrice - entryPrice) / entryPrice * 100 : 0.0;
    final color = pnl >= 0 ? AbsColors.green : AbsColors.red;
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Container(
          height: 42,
          padding: const EdgeInsets.all(4),
          decoration: BoxDecoration(color: AbsColors.panel2, borderRadius: BorderRadius.circular(12), border: Border.all(color: AbsColors.line)),
          child: Row(
            children: [
              Expanded(child: _choice('Long', long, () => setState(() => long = true))),
              Expanded(child: _choice('Short', !long, () => setState(() => long = false))),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Row(children: [Expanded(child: _NumberField(controller: entry, label: 'Entry price')), const SizedBox(width: 10), Expanded(child: _NumberField(controller: exit, label: 'Exit price'))]),
        const SizedBox(height: 12),
        Row(children: [Expanded(child: _NumberField(controller: qty, label: 'Quantity', suffix: 'units')), const SizedBox(width: 10), Expanded(child: _NumberField(controller: leverage, label: 'Leverage', suffix: 'x'))]),
        const SizedBox(height: 18),
        TemplateCard(
          borderColor: templateFade(color, .4),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Estimated profit or loss', style: TextStyle(color: AbsColors.muted, fontSize: 11.5)),
              const SizedBox(height: 4),
              Text('${pnl >= 0 ? '+' : ''}${_fmt(pnl)} USDT', style: TextStyle(fontSize: 27, fontWeight: FontWeight.w900, color: color)),
              const Divider(height: 24),
              _ResultRow('Return on margin', '${roe.toStringAsFixed(2)}%', color: color),
              _ResultRow('Price move', '${move >= 0 ? '+' : ''}${move.toStringAsFixed(2)}%'),
              _ResultRow('Margin used', '${_fmt(margin)} USDT'),
            ],
          ),
        ),
        const TemplateRiskNotice(),
      ],
    );
  }

  Widget _choice(String label, bool selected, VoidCallback onTap) => InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(9),
        child: Container(
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: selected ? templateFade(AbsColors.cyan, .13) : Colors.transparent,
            borderRadius: BorderRadius.circular(9),
            border: selected ? Border.all(color: templateFade(AbsColors.cyan, .35)) : null,
          ),
          child: Text(label, style: TextStyle(color: selected ? AbsColors.cyanSoft : AbsColors.muted, fontWeight: FontWeight.w800, fontSize: 11.5)),
        ),
      );
}
