import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart' show MobileAds;

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
  if (Platform.isAndroid || Platform.isIOS) {
    unawaited(MobileAds.instance.initialize().then<void>((_) {}));
  }

  final session = AppSession();
  final uiState = AppState(session);
  runApp(AbsMobileApp(session: session, uiState: uiState));
  await session.initialize();
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
              debugShowCheckedModeBanner: false,
              title: 'Pulse',
              theme: AppTheme.dark(),
              home: session.initializing
                  ? const TemplateSplashScreen()
                  : session.backendTooOld
                      ? BackendCompatibilityScreen(backendBuild: session.backendBuild)
                      : session.updateRequired
                          ? UpdateRequiredScreen(requiredVersion: session.minimumMobileVersion)
                          : session.maintenanceMode
                              ? MaintenanceScreen(message: session.maintenanceMessage)
                              : const Shell(),
            );
          },
        ),
      ),
    );
  }
}
