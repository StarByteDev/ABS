import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:abs_pulse/core/theme.dart';
import 'package:abs_pulse/screens/splash_screen.dart';

void main() {
  testWidgets('ABS branded splash renders', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: buildAbsTheme(),
        home: const SplashScreen(),
      ),
    );

    expect(find.text('ALPHA BLOCK SOLUTIONS'), findsOneWidget);
    expect(find.text('Pulse Trading Intelligence'), findsOneWidget);
  });
}
