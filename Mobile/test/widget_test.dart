import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:abs_pulse/core/theme.dart';
import 'package:abs_pulse/screens/splash_screen.dart';

void main() {
  testWidgets('ABS branded splash renders', (tester) async {
    await tester.pumpWidget(
      MaterialApp(theme: buildAbsTheme(), home: const SplashScreen()),
    );

    expect(find.text('ABS PULSE'), findsOneWidget);
    expect(find.text('Professional market intelligence'), findsOneWidget);
  });
}
