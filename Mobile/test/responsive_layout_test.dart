import 'package:abs_pulse/core/theme.dart';
import 'package:abs_pulse/screens/content_screens.dart';
import 'package:abs_pulse/widgets/abs_ui.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// The narrowest devices ABS Pulse supports, plus the common modern sizes.
const _widths = <double>[320, 360, 375, 390, 430];

Future<void> _pumpAt(
  WidgetTester tester,
  Widget child, {
  required double width,
  double textScale = 1.0,
}) async {
  tester.view.physicalSize = Size(width, 1400);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
  await tester.pumpWidget(
    MediaQuery(
      data: MediaQueryData(
        size: Size(width, 1400),
        textScaler: TextScaler.linear(textScale),
      ),
      child: MaterialApp(theme: buildAbsTheme(), home: child),
    ),
  );
  await tester.pump();
}

/// Any RenderFlex overflow surfaces as an exception during layout, so a clean
/// pump is the assertion.
void _expectNoOverflow(WidgetTester tester, String label) {
  final error = tester.takeException();
  expect(error, isNull, reason: '$label overflowed: $error');
}

void main() {
  group('shared ABS widgets survive narrow screens', () {
    for (final width in _widths) {
      testWidgets('metric + status + key/value row at ${width}px', (
        tester,
      ) async {
        await _pumpAt(
          tester,
          Scaffold(
            body: ListView(
              children: const [
                Row(
                  children: [
                    Expanded(
                      child: MetricCard(
                        label: 'UNREALISED P&L',
                        value: r'-$12,480.55',
                      ),
                    ),
                    SizedBox(width: 8),
                    Expanded(
                      child: MetricCard(
                        label: 'PORTFOLIO EXPOSURE',
                        value: r'$1,284,900.00',
                      ),
                    ),
                  ],
                ),
                KeyValueRow(
                  'Estimated liquidation price',
                  r'$64,201.998877 USDT',
                ),
                StatusChip('QUALIFIED PULSE SIGNAL · HIGH CONVICTION'),
                EmptyState(
                  title: 'No published research yet',
                  message:
                      'ABS research is published here as it is released. '
                      'Pull down to refresh.',
                  icon: Icons.insights_outlined,
                ),
              ],
            ),
          ),
          width: width,
        );
        _expectNoOverflow(tester, 'shared widgets at ${width}px');
      });
    }

    testWidgets('shared widgets tolerate large accessibility text', (
      tester,
    ) async {
      await _pumpAt(
        tester,
        Scaffold(
          body: ListView(
            children: const [
              MetricCard(label: 'DAILY DRAWDOWN LIMIT', value: r'-$4,250.00'),
              KeyValueRow('Take profit level three', r'$71,880.40 USDT'),
            ],
          ),
        ),
        width: 320,
        textScale: 1.6,
      );
      _expectNoOverflow(tester, 'shared widgets at 1.6x text scale');
    });
  });

  group('Economic Calendar filters never overflow', () {
    for (final width in _widths) {
      testWidgets('impact + currency filters at ${width}px', (tester) async {
        await _pumpAt(
          tester,
          Scaffold(
            body: Padding(
              padding: const EdgeInsets.all(16),
              child: AbsCalendarFilters(
                impact: 'all',
                currency: 'all',
                currencies: const ['USD', 'EUR', 'GBP', 'JPY', 'AUD', 'CAD'],
                onImpactChanged: (_) {},
                onCurrencyChanged: (_) {},
              ),
            ),
          ),
          width: width,
        );
        _expectNoOverflow(tester, 'calendar filters at ${width}px');
        expect(find.text('All impact'), findsOneWidget);
        expect(find.text('All currencies'), findsOneWidget);
      });
    }

    testWidgets('filters stack rather than overflow at large text', (
      tester,
    ) async {
      await _pumpAt(
        tester,
        Scaffold(
          body: Padding(
            padding: const EdgeInsets.all(16),
            child: AbsCalendarFilters(
              impact: 'high',
              currency: 'USD',
              currencies: const ['USD', 'EUR'],
              onImpactChanged: (_) {},
              onCurrencyChanged: (_) {},
            ),
          ),
        ),
        width: 320,
        textScale: 1.6,
      );
      _expectNoOverflow(tester, 'calendar filters at 1.6x text scale');
    });

    testWidgets('a currency no longer in the feed does not crash', (
      tester,
    ) async {
      await _pumpAt(
        tester,
        Scaffold(
          body: AbsCalendarFilters(
            impact: 'all',
            // The feed no longer returns CHF; the control must fall back.
            currency: 'CHF',
            currencies: const ['USD', 'EUR'],
            onImpactChanged: (_) {},
            onCurrencyChanged: (_) {},
          ),
        ),
        width: 390,
      );
      _expectNoOverflow(tester, 'calendar filters with a stale currency');
      expect(find.text('All currencies'), findsOneWidget);
    });
  });

  group('rebuilt secondary screens fit narrow devices', () {
    for (final width in _widths) {
      testWidgets('Legal & Risk at ${width}px', (tester) async {
        await _pumpAt(tester, const LegalHubScreen(), width: width);
        _expectNoOverflow(tester, 'LegalHubScreen at ${width}px');
        expect(find.text('Risk Disclosure'), findsOneWidget);
      });

      testWidgets('Explore ABS at ${width}px', (tester) async {
        await _pumpAt(tester, const ExploreAbsScreen(), width: width);
        _expectNoOverflow(tester, 'ExploreAbsScreen at ${width}px');
      });
    }

    testWidgets('Legal & Risk keeps its documents at 1.5x text', (
      tester,
    ) async {
      await _pumpAt(tester, const LegalHubScreen(), width: 320, textScale: 1.5);
      _expectNoOverflow(tester, 'LegalHubScreen at 1.5x text scale');
    });
  });
}
