import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:abs_pulse/investor/investor_ui.dart';

void main() {
  testWidgets('Investor mode selector is readable and responds to selection', (tester) async {
    bool? selected;
    await tester.pumpWidget(MaterialApp(
      theme:ThemeData.dark(),
      home: Scaffold(body:InvestorModeSwitch(
        investorSelected:true,onSelect:(v)=>selected=v,
      )),
    ));
    expect(find.text('Invest'),findsOneWidget);
    expect(find.text('Trade'),findsOneWidget);
    await tester.tap(find.text('Trade'));
    expect(selected,false);
  });

  testWidgets('No artificial investor performance is shown for empty history', (tester) async {
    await tester.pumpWidget(const MaterialApp(home:Scaffold(body:InvestorLineGraph([]))));
    expect(find.textContaining('when published'),findsOneWidget);
  });
}
