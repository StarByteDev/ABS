import 'package:abs_pulse/core/theme.dart';
import 'package:abs_pulse/screens/content_screens.dart';
import 'package:abs_pulse/widgets/abs_ui.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// Real iPhone portrait widths, including the iPhone 16e the overflow was
/// reported on, and the accessibility text scales that stress them.
const _widths = <double>[320, 360, 375, 390, 430];
const _scales = <double>[1.0, 1.3, 1.6];

Future<void> _pump(
  WidgetTester tester,
  Widget child,
  double width,
  double scale,
) async {
  tester.view.physicalSize = Size(width, 1600);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
  await tester.pumpWidget(
    MediaQuery(
      data: MediaQueryData(
        size: Size(width, 1600),
        textScaler: TextScaler.linear(scale),
      ),
      child: MaterialApp(
        theme: buildAbsTheme(),
        home: Scaffold(
          backgroundColor: AbsColors.bg,
          // Matches AbsPage's real content padding.
          body: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: SingleChildScrollView(child: child),
          ),
        ),
      ),
    ),
  );
  await tester.pump();
}

void _noOverflow(WidgetTester tester, String label) {
  final error = tester.takeException();
  expect(error, isNull, reason: '$label produced: $error');
}

Map<String, dynamic> _event({
  String title = 'Core Personal Consumption Expenditures Price Index m/m',
  String currency = 'USD',
  String impact = 'high',
}) => {
  'title': title,
  'currency': currency,
  'impact': impact,
  'event_time': '2026-09-16T23:30:00Z',
  'actual_value': '-0.4823%',
  'forecast_value': '0.3000%',
  'previous_value': '1.2000%',
};

void main() {
  for (final width in _widths) {
    for (final scale in _scales) {
      final at = '${width.toInt()}px @ ${scale}x';

      testWidgets('calendar filters fit $at', (tester) async {
        await _pump(
          tester,
          AbsCalendarFilters(
            impact: 'all',
            currency: 'all',
            currencies: const ['USD', 'EUR', 'GBP', 'JPY', 'AUD', 'CAD'],
            onImpactChanged: (_) {},
            onCurrencyChanged: (_) {},
          ),
          width,
          scale,
        );
        _noOverflow(tester, 'AbsCalendarFilters $at');
      });

      testWidgets('period switch fits $at', (tester) async {
        await _pump(
          tester,
          CalendarPeriodSwitch(value: 'today', onChanged: (_) {}),
          width,
          scale,
        );
        _noOverflow(tester, 'CalendarPeriodSwitch $at');
      });

      testWidgets('pulse header fits $at', (tester) async {
        await _pump(
          tester,
          CalendarPulseHeader(
            todayCount: 128,
            highToday: 24,
            nextEvent: _event(),
          ),
          width,
          scale,
        );
        _noOverflow(tester, 'CalendarPulseHeader $at');
      });

      testWidgets('column header fits $at', (tester) async {
        await _pump(tester, const CalendarColumnHeader(), width, scale);
        _noOverflow(tester, 'CalendarColumnHeader $at');
      });

      testWidgets('event card fits $at', (tester) async {
        await _pump(
          tester,
          Column(children: [EconomicEventCard(event: _event())]),
          width,
          scale,
        );
        _noOverflow(tester, 'EconomicEventCard $at');
      });
    }
  }
}
