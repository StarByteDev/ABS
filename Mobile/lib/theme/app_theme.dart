import 'dart:ui' show FontFeature;

import 'package:flutter/material.dart';

/// Brand palette, based on alphablocksolutions.com (theme colour #07111f).
class AppColors {
  static const bg = Color(0xFF07111F);
  static const surface = Color(0xFF0D1A2D);
  static const surfaceHi = Color(0xFF132338);
  static const line = Color(0xFF1D3150);
  static const text = Color(0xFFE9EFF8);
  static const muted = Color(0xFF8B9BB3);
  static const faint = Color(0xFF55667F);
  static const accent = Color(0xFF4DA3FF);
  static const gold = Color(0xFFE9B949);
  static const up = Color(0xFF26D07C);
  static const down = Color(0xFFFF5A6A);
  static const amber = Color(0xFFFFB347);
}

/// Opacity helper that works on every Flutter 3.x version.
Color fade(Color c, double opacity) =>
    c.withAlpha((opacity.clamp(0.0, 1.0) * 255).round());

const List<FontFeature> kTabular = [FontFeature.tabularFigures()];

class AppText {
  static const muted = TextStyle(color: AppColors.muted, fontSize: 12.5);
  static const label = TextStyle(
      color: AppColors.muted, fontSize: 12, fontWeight: FontWeight.w600);
  static const h2 = TextStyle(
      fontSize: 17,
      fontWeight: FontWeight.w700,
      letterSpacing: -0.2,
      color: AppColors.text);
  static const figure = TextStyle(
      fontWeight: FontWeight.w700,
      fontFeatures: kTabular,
      color: AppColors.text);
}

class AppTheme {
  static ThemeData dark() {
    final radius = BorderRadius.circular(12);
    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      scaffoldBackgroundColor: AppColors.bg,
      canvasColor: AppColors.bg,
      dividerColor: AppColors.line,
      dividerTheme: const DividerThemeData(color: AppColors.line, thickness: 1),
      colorScheme: const ColorScheme.dark(
        primary: AppColors.accent,
        onPrimary: AppColors.bg,
        secondary: AppColors.gold,
        surface: AppColors.surface,
        onSurface: AppColors.text,
        error: AppColors.down,
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.bg,
        foregroundColor: AppColors.text,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleTextStyle: TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.w700,
            color: AppColors.text,
            letterSpacing: -0.3),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.surfaceHi,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        border: OutlineInputBorder(borderRadius: radius, borderSide: BorderSide.none),
        enabledBorder:
            OutlineInputBorder(borderRadius: radius, borderSide: BorderSide.none),
        focusedBorder: OutlineInputBorder(
            borderRadius: radius,
            borderSide: const BorderSide(color: AppColors.accent, width: 1.4)),
        errorBorder: OutlineInputBorder(
            borderRadius: radius,
            borderSide: const BorderSide(color: AppColors.down)),
        focusedErrorBorder: OutlineInputBorder(
            borderRadius: radius,
            borderSide: const BorderSide(color: AppColors.down, width: 1.4)),
        hintStyle: const TextStyle(color: AppColors.faint),
        labelStyle: const TextStyle(color: AppColors.muted),
        prefixIconColor: AppColors.muted,
        suffixIconColor: AppColors.muted,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.accent,
          foregroundColor: AppColors.bg,
          disabledBackgroundColor: AppColors.surfaceHi,
          disabledForegroundColor: AppColors.faint,
          minimumSize: const Size(64, 52),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.text,
          minimumSize: const Size(64, 52),
          side: const BorderSide(color: AppColors.line),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.accent,
          textStyle: const TextStyle(fontWeight: FontWeight.w600),
        ),
      ),
      snackBarTheme: const SnackBarThemeData(
        backgroundColor: AppColors.surfaceHi,
        contentTextStyle: TextStyle(color: AppColors.text),
        behavior: SnackBarBehavior.floating,
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: AppColors.surface,
        showDragHandle: true,
        dragHandleColor: AppColors.line,
      ),
      progressIndicatorTheme:
          const ProgressIndicatorThemeData(color: AppColors.accent),
    );
  }
}
