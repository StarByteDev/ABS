import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:abs_pulse/investor/premium_ui.dart';
import 'package:abs_pulse/investor/investor_ui.dart';

void main() {
  testWidgets('Gold investor action responds on a narrow phone', (tester) async {
    tester.view.physicalSize = const Size(320, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.reset);
    bool clicked = false;
    await tester.pumpWidget(MaterialApp(theme: ThemeData.dark(),
      home: Scaffold(body: Row(children: [
        PremiumAction(icon: Icons.add_card_rounded, label: 'Top up',
          onTap: () => clicked = true, gold: true),
      ]))));
    expect(find.text('Top up'), findsOneWidget);
    await tester.tap(find.text('Top up'));
    expect(clicked, isTrue);
    expect(tester.takeException(), isNull);
  });

  testWidgets('Portfolio chart shows empty state instead of invented returns', (tester) async {
    await tester.pumpWidget(const MaterialApp(home: Scaffold(
      body: PremiumPerformanceChart(values: <double>[], height: 120))));
    expect(find.text('No published history yet'), findsOneWidget);
  });

  testWidgets('Selected investor toggle changes into trader mode', (tester) async {
    bool? selected;
    await tester.pumpWidget(MaterialApp(theme: ThemeData.dark(),
      home: Scaffold(body: InvestorModeSwitch(investorSelected: true,
        onSelect: (value) => selected = value))));
    await tester.tap(find.text('Trade'));
    expect(selected, false);
  });

  testWidgets('Return bars display posted months', (tester) async {
    await tester.pumpWidget(const MaterialApp(home: Scaffold(
      body: PremiumReturnsBar(rows: [(label: 'Jul', amount: -25.0),
        (label: 'Aug', amount: 80.0)]))));
    expect(find.text('Jul'), findsOneWidget);
    expect(find.text('Aug'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
