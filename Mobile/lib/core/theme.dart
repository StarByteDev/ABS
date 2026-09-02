import 'package:flutter/material.dart';

class AbsColors {
  static const bg = Color(0xFF06080D);
  static const bgSoft = Color(0xFF090D14);
  static const panel = Color(0xFF0D131D);
  static const panel2 = Color(0xFF121C2A);
  static const panel3 = Color(0xFF172334);
  static const line = Color(0xFF223248);
  static const lineSoft = Color(0xFF172437);
  static const cyan = Color(0xFF22C7FF);
  static const cyanSoft = Color(0xFF7ADFFF);
  static const blue = Color(0xFF2B86FF);
  static const purple = Color(0xFF7A5CFF);
  static const purpleSoft = Color(0xFFB5A7FF);
  static const gold = Color(0xFFF7BC55);
  static const goldSoft = Color(0xFFFFD78A);
  static const green = Color(0xFF39D8A0);
  static const red = Color(0xFFFF6D82);
  static const text = Color(0xFFF7F9FC);
  static const muted = Color(0xFF93A3B8);
  static const muted2 = Color(0xFF64758C);

  static const premiumGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF15152B), Color(0xFF0B1320), Color(0xFF0A0D15)],
  );

  static const actionGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF7C5CFF), Color(0xFF5D49DA)],
  );
}

ThemeData buildAbsTheme() {
  const radius = 20.0;
  final scheme = ColorScheme.fromSeed(
    seedColor: AbsColors.purple,
    brightness: Brightness.dark,
    surface: AbsColors.panel,
    error: AbsColors.red,
  ).copyWith(
    primary: AbsColors.purple,
    secondary: AbsColors.gold,
    surface: AbsColors.panel,
    onSurface: AbsColors.text,
  );

  return ThemeData(
    brightness: Brightness.dark,
    colorScheme: scheme,
    scaffoldBackgroundColor: AbsColors.bg,
    useMaterial3: true,
    splashFactory: InkSparkle.splashFactory,
    fontFamilyFallback: const ['Inter', 'SF Pro Display', 'Segoe UI', 'Roboto', 'Arial', 'sans-serif'],
    dividerColor: AbsColors.lineSoft,
    textTheme: const TextTheme(
      displaySmall: TextStyle(fontWeight: FontWeight.w900, letterSpacing: -1.2, height: 1.05),
      headlineLarge: TextStyle(fontWeight: FontWeight.w900, letterSpacing: -1.0, height: 1.08),
      headlineMedium: TextStyle(fontWeight: FontWeight.w900, letterSpacing: -0.7),
      headlineSmall: TextStyle(fontWeight: FontWeight.w800, letterSpacing: -0.4),
      titleLarge: TextStyle(fontWeight: FontWeight.w800, letterSpacing: -0.25),
      titleMedium: TextStyle(fontWeight: FontWeight.w800),
      titleSmall: TextStyle(fontWeight: FontWeight.w800),
      bodyLarge: TextStyle(height: 1.48),
      bodyMedium: TextStyle(height: 1.45),
      bodySmall: TextStyle(height: 1.4),
      labelLarge: TextStyle(fontWeight: FontWeight.w800, letterSpacing: .1),
    ).apply(bodyColor: AbsColors.text, displayColor: AbsColors.text),
    cardTheme: CardThemeData(
      color: AbsColors.panel,
      elevation: 0,
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(radius),
        side: const BorderSide(color: AbsColors.lineSoft),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: AbsColors.panel2.withValues(alpha: .82),
      labelStyle: const TextStyle(color: AbsColors.muted),
      hintStyle: const TextStyle(color: AbsColors.muted2),
      prefixIconColor: AbsColors.muted,
      suffixIconColor: AbsColors.muted,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: AbsColors.line),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: AbsColors.line),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: AbsColors.purple, width: 1.35),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: AbsColors.red),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
    ),
    appBarTheme: const AppBarTheme(
      backgroundColor: Colors.transparent,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0,
      foregroundColor: AbsColors.text,
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: AbsColors.purple,
        foregroundColor: AbsColors.text,
        disabledBackgroundColor: AbsColors.panel3,
        disabledForegroundColor: AbsColors.muted,
        minimumSize: const Size(0, 54),
        elevation: 0,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        textStyle: const TextStyle(fontWeight: FontWeight.w900, letterSpacing: .1),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: AbsColors.text,
        minimumSize: const Size(0, 54),
        side: const BorderSide(color: AbsColors.line),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        textStyle: const TextStyle(fontWeight: FontWeight.w800),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(
        foregroundColor: AbsColors.purpleSoft,
        textStyle: const TextStyle(fontWeight: FontWeight.w800),
      ),
    ),
    chipTheme: ChipThemeData(
      backgroundColor: AbsColors.panel2,
      selectedColor: AbsColors.purple.withValues(alpha: .14),
      side: const BorderSide(color: AbsColors.line),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
      labelStyle: const TextStyle(color: AbsColors.text, fontWeight: FontWeight.w700),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: AbsColors.panel.withValues(alpha: .98),
      indicatorColor: AbsColors.purple.withValues(alpha: .12),
      height: 72,
      labelTextStyle: WidgetStateProperty.resolveWith((states) => TextStyle(
            color: states.contains(WidgetState.selected) ? AbsColors.text : AbsColors.muted,
            fontSize: 11,
            fontWeight: states.contains(WidgetState.selected) ? FontWeight.w900 : FontWeight.w700,
          )),
      iconTheme: WidgetStateProperty.resolveWith((states) => IconThemeData(
            color: states.contains(WidgetState.selected) ? AbsColors.purpleSoft : AbsColors.muted,
            size: states.contains(WidgetState.selected) ? 25 : 23,
          )),
    ),
    tabBarTheme: const TabBarThemeData(
      labelColor: AbsColors.text,
      unselectedLabelColor: AbsColors.muted,
      indicatorColor: AbsColors.purple,
      dividerColor: Colors.transparent,
      labelStyle: TextStyle(fontWeight: FontWeight.w800),
    ),
    sliderTheme: SliderThemeData(
      activeTrackColor: AbsColors.purple,
      inactiveTrackColor: AbsColors.line,
      thumbColor: AbsColors.purple,
      overlayColor: AbsColors.purple.withValues(alpha: .12),
    ),
    snackBarTheme: SnackBarThemeData(
      backgroundColor: AbsColors.panel3,
      contentTextStyle: const TextStyle(color: AbsColors.text, fontWeight: FontWeight.w700),
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
    ),
  );
}
