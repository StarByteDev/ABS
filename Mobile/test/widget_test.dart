import 'package:abs_pulse/template_rebase/screens/splash_screen.dart';
import 'package:abs_pulse/template_rebase/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('template-rebased Pulse splash renders production branding', (tester) async {
    await tester.pumpWidget(
      MaterialApp(theme: AppTheme.dark(), home: const TemplateSplashScreen()),
    );

    expect(find.text('ALPHA BLOCK SOLUTIONS'), findsOneWidget);
    expect(find.text('Pulse intelligence for digital markets'), findsOneWidget);
  });
}
