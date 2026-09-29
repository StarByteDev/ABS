import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'core/ads/ad_service.dart';
import 'core/notifications/notification_service.dart';
import 'core/session.dart';
import 'screens/splash_screen.dart'
    show BackendCompatibilityScreen, MaintenanceScreen, UpdateRequiredScreen;
import 'template_rebase/screens/shell.dart';
import 'template_rebase/screens/splash_screen.dart';
import 'template_rebase/state/app_state.dart';
import 'template_rebase/theme/app_theme.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarIconBrightness: Brightness.light,
    statusBarBrightness: Brightness.dark,
    systemNavigationBarColor: AppColors.surface,
    systemNavigationBarIconBrightness: Brightness.light,
  ));

  // Firebase + FCM background handler must be ready before the app starts.
  await NotificationService.instance.initializeFirebase();

  final session = AppSession();
  final uiState = AppState(session);
  runApp(AbsMobileApp(session: session, uiState: uiState));
  // Consent + SDK start-up run in the background and never block launch.
  AdService.instance.initialize(session);
  await session.initialize();
  // After session restore so topics/device registration match the account.
  unawaited(NotificationService.instance.initialize(session));
  await uiState.initialize();
}

class AbsMobileApp extends StatelessWidget {
  const AbsMobileApp({
    super.key,
    required this.session,
    required this.uiState,
  });

  final AppSession session;
  final AppState uiState;

  @override
  Widget build(BuildContext context) {
    return SessionScope(
      session: session,
      child: AppScope(
        notifier: uiState,
        child: AnimatedBuilder(
          animation: session,
          builder: (context, _) {
            return MaterialApp(
              navigatorKey: AdService.instance.navigatorKey,
              debugShowCheckedModeBanner: false,
              title: 'Pulse',
              theme: AppTheme.dark(),
              home: session.initializing
                  ? const TemplateSplashScreen()
                  : session.backendTooOld
                      ? BackendCompatibilityScreen(
                          backendBuild: session.backendBuild)
                      : session.updateRequired
                          ? UpdateRequiredScreen(
                              requiredVersion: session.minimumMobileVersion)
                          : session.maintenanceMode
                              ? MaintenanceScreen(
                                  message: session.maintenanceMessage)
                              : const Shell(),
            );
          },
        ),
      ),
    );
  }
}
