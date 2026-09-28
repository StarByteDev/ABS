import 'package:abs_pulse/template_rebase/data/mock_data.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('economic calendar maps V15 value field variants', () {
    MockData.applyCalendar(<Map<String, dynamic>>[
      <String, dynamic>{
        'id': 77,
        'event_name': 'US Consumer Confidence',
        'event_date': '2026-09-24',
        'event_time': '18:00:00',
        'importance': 'medium',
        'country_code': 'US',
        'previous_value': '97.4',
        'forecast_value': '98.1',
        'actual_value': '99.0',
      },
    ]);

    expect(MockData.calendar, hasLength(1));
    final event = MockData.calendar.single;
    expect(event.title, 'US Consumer Confidence');
    expect(event.currency, 'USD');
    expect(event.previous, '97.4');
    expect(event.forecast, '98.1');
    expect(event.actual, '99.0');
    expect(event.at, isNotNull);
  });

  test('economic calendar reads nested provider figure maps', () {
    MockData.applyCalendar(<Map<String, dynamic>>[
      <String, dynamic>{
        'id': 78,
        'eventName': 'Policy Rate Decision',
        'date': '2026-09-24',
        'time': '12:00:00',
        'figures': <String, dynamic>{
          'previous': <String, dynamic>{'value': 4.25, 'unit': '%'},
          'forecast': <String, dynamic>{'formatted': '4.00%'},
          'actual': <String, dynamic>{'display': '4.00%'},
        },
      },
    ]);

    final event = MockData.calendar.single;
    expect(event.previous, '4.25%');
    expect(event.forecast, '4.00%');
    expect(event.actual, '4.00%');
  });

  test('economic calendar reads labelled provider value arrays', () {
    MockData.applyCalendar(<Map<String, dynamic>>[
      <String, dynamic>{
        'id': 79,
        'event': 'Employment Change',
        'date': '2026-09-24',
        'time': '05:30:00',
        'release_values': <Map<String, dynamic>>[
          <String, dynamic>{'label': 'Previous', 'value': '4.5%'},
          <String, dynamic>{'label': 'Forecast', 'value': '4.4%'},
          <String, dynamic>{'label': 'Actual', 'value': '4.3%'},
        ],
      },
    ]);

    final event = MockData.calendar.single;
    expect(event.previous, '4.5%');
    expect(event.forecast, '4.4%');
    expect(event.actual, '4.3%');
  });

  test('economic calendar reads labelled values from summary fallback', () {
    MockData.applyCalendar(<Map<String, dynamic>>[
      <String, dynamic>{
        'id': 80,
        'event': 'Inflation Release',
        'date': '2026-09-24',
        'time': '09:00:00',
        'release_summary': 'Previous: 2.8% | Forecast: 2.7% | Actual: 2.6%',
      },
    ]);

    final event = MockData.calendar.single;
    expect(event.previous, '2.8%');
    expect(event.forecast, '2.7%');
    expect(event.actual, '2.6%');
  });

  test('missing actual stays empty so UI can distinguish past from future', () {
    MockData.applyCalendar(<Map<String, dynamic>>[
      <String, dynamic>{
        'id': 88,
        'event': 'Historical release',
        'date': '2026-09-20',
        'time': '12:30:00',
        'previous': '1.2%',
        'forecast': '1.3%',
      },
    ]);

    expect(MockData.calendar.single.actual, isEmpty);
  });

  test('market overview accepts alternate metrics containers', () {
    MockData.applyMarketOverview(<String, dynamic>{
      'metrics': <String, dynamic>{
        'total_market_cap_usd': 2840000000000,
        'total_volume_24h_usd': 117100000000,
        'btc_dominance_percentage': 58.7,
        'fear_greed': <String, dynamic>{'value': 21},
      },
      'market_pulse': <String, dynamic>{'score': 42, 'label': 'Defensive'},
    });

    expect(MockData.totalMcap, 2840000000000);
    expect(MockData.volume24h, 117100000000);
    expect(MockData.btcDominance, 58.7);
    expect(MockData.fearGreed, 21);
    expect(MockData.pulseScore, 42);
  });
}
