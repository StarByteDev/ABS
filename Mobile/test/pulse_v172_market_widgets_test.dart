import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:abs_pulse/investor/market_command_ui.dart';

void main() {
  test('Market formatters never turn missing feed data into a quote', () {
    expect(pulseMoney(double.nan), '—');
    expect(pulsePercent(double.nan), '—');
    expect(pulseNumber(double.infinity), '—');
    expect(pulseMoney(2460000000000), '\$2.46T');
    expect(pulsePercent(-2.4, signed: true), '-2.40%');
  });

  testWidgets('Long-short bar derives a ratio without creating a new feed value', (tester) async {
    await tester.pumpWidget(const MaterialApp(
      home: Scaffold(body: Padding(
        padding: EdgeInsets.all(15),
        child: PulseDirectionBar(ratio: 1.5),
      )),
    ));
    expect(find.text('Long 60.0%'), findsOneWidget);
    expect(find.text('Short 40.0%'), findsOneWidget);
  });

  testWidgets('Missing long-short ratio is clearly unavailable', (tester) async {
    await tester.pumpWidget(const MaterialApp(
      home: Scaffold(body: PulseDirectionBar(ratio: double.nan)),
    ));
    expect(find.text('Long / short breakdown unavailable'), findsOneWidget);
  });
}
