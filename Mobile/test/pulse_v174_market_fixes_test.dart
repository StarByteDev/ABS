import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:abs_pulse/investor/premium_ui.dart';
import 'package:abs_pulse/template_rebase/data/mock_data.dart';

void main() {
  testWidgets('BTC price chart occupies the available width', (tester) async {
    await tester.pumpWidget(const MaterialApp(home: Scaffold(
      body: Padding(padding: EdgeInsets.all(12),
        child: PremiumPerformanceChart(
          values: <double>[83000, 83100, 82920, 83300, 83610],
          height: 175,
        )),
    )));
    final size = tester.getSize(find.byType(PremiumPerformanceChart));
    expect(size.width, greaterThan(240));
    expect(size.height, 175);
  });

  test('V15.9.1 stablecoin market cap delta is read from insights', () {
    MockData.applyMarketOverview(<String, dynamic>{
      'global': <String, dynamic>{
        'stablecoin_market_cap': 200000000000,
        'stablecoin_market_cap_change_24h': 0.5,
      },
      'insights': <String, dynamic>{
        'stablecoin_flow_24h_usd': 995024875.62,
      },
    });
    expect(MockData.stablecoinFlow, closeTo(995024875.62, 0.01));
    expect(MockData.stablecoinMarketCap, 200000000000);
  });

  test('stablecoin capitalization change fallback does not fabricate exchange netflow', () {
    MockData.applyMarketOverview(<String, dynamic>{
      'global': <String, dynamic>{
        'stablecoin_market_cap': 200000000000,
        'stablecoin_market_cap_change_24h': 0.5,
      },
    });
    expect(MockData.stablecoinFlow, closeTo(995024875.62, 0.01));
  });

  test('ETF flow parses only verified backend fields', () {
    MockData.applyEtfFlows(<String, dynamic>{'data': <String, dynamic>{
      'status': 'available',
      'net_flow_usd': -50000000,
      'flow_date': '2026-10-09',
      'source': 'CoinGlass · US spot Bitcoin ETF daily flows',
    }});
    expect(MockData.btcEtfFlow, -50000000);
    expect(MockData.btcEtfDate, '2026-10-09');
    MockData.applyEtfFlows(<String, dynamic>{'data': <String, dynamic>{
      'status': 'provider_not_configured',
      'net_flow_usd': null,
    }});
    expect(MockData.btcEtfFlow.isNaN, isTrue);
  });
}
