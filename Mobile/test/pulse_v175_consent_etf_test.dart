import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:abs_pulse/template_rebase/widgets/rewarded_ad_consent.dart';
import 'package:abs_pulse/template_rebase/data/mock_data.dart';

void main() {
  testWidgets('consent can be toggled by tapping its complete label', (tester) async {
    var consent = false;
    await tester.pumpWidget(MaterialApp(home: Scaffold(body:
      StatefulBuilder(builder: (context, setState) =>
        RewardedAdConsentTile(
          value: consent,
          onChanged: (next) => setState(() => consent = next),
        )),
    )));
    expect(consent, isFalse);
    await tester.tap(find.text('I agree to watch a rewarded ad for one free reveal.'));
    await tester.pump();
    expect(consent, isTrue);
    expect(tester.widget<CheckboxListTile>(find.byKey(const Key('free-signal-consent'))).value, isTrue);
    await tester.tap(find.byType(Checkbox));
    await tester.pump();
    expect(consent, isFalse);
  });

  testWidgets('consent stays disabled while a rewarded claim is processing', (tester) async {
    var changed = false;
    await tester.pumpWidget(MaterialApp(home: Scaffold(body:
      RewardedAdConsentTile(value: false, enabled: false,
        onChanged: (_) { changed = true; }),
    )));
    await tester.tap(find.text('I agree to watch a rewarded ad for one free reveal.'));
    await tester.pump();
    expect(changed, isFalse);
  });

  test('ETF status preserves provider configuration diagnostics', () {
    MockData.applyEtfFlows(<String, dynamic>{});
    expect(MockData.btcEtfStatus, 'endpoint_not_installed');
    expect(MockData.btcEtfFlow.isNaN, isTrue);
    MockData.applyEtfFlows(<String, dynamic>{'data': <String, dynamic>{
      'status': 'provider_not_configured', 'net_flow_usd': null,
    }});
    expect(MockData.btcEtfStatus, 'provider_not_configured');
    expect(MockData.btcEtfFlow.isNaN, isTrue);
    MockData.applyEtfFlows(<String, dynamic>{'data': <String, dynamic>{
      'status': 'available', 'net_flow_usd': 21000000,
      'flow_date': '2026-10-09', 'source': 'CoinGlass',
    }});
    expect(MockData.btcEtfStatus, 'available');
    expect(MockData.btcEtfFlow, 21000000);
    expect(MockData.btcEtfDate, '2026-10-09');
  });
}
