import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart';

import 'core/session.dart';
import 'core/theme.dart';
import 'screens/auth_screens.dart';
import 'screens/main_shell.dart';
import 'screens/splash_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  if (Platform.isAndroid || Platform.isIOS) {
    unawaited(MobileAds.instance.initialize().then<void>((_) {}));
  }
  final session = AppSession();
  runApp(AbsMobileApp(session: session));
  await session.initialize();
}

class AbsMobileApp extends StatelessWidget {
  const AbsMobileApp({super.key, required this.session});

  final AppSession session;

  @override
  Widget build(BuildContext context) {
    return SessionScope(
      session: session,
      child: AnimatedBuilder(
        animation: session,
        builder: (context, _) {
          return MaterialApp(
            debugShowCheckedModeBanner: false,
            title: 'ABS Pulse',
            theme: buildAbsTheme(),
            home: session.initializing
                ? const SplashScreen()
                : session.backendTooOld
                ? BackendCompatibilityScreen(backendBuild: session.backendBuild)
                : session.updateRequired
                ? UpdateRequiredScreen(
                    requiredVersion: session.minimumMobileVersion,
                  )
                : session.maintenanceMode
                ? MaintenanceScreen(message: session.maintenanceMessage)
                : session.authenticated
                ? const MainShell()
                : const GuestLandingScreen(),
          );
        },
      ),
    );
  }
}
