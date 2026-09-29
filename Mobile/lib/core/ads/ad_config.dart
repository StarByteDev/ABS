import 'package:flutter/foundation.dart';

enum AdFormat { banner, interstitial, rewarded, appOpen }

enum AdPlatform { android, ios }

/// Single source of truth for Pulse AdMob unit IDs.
///
/// Every build (debug, profile AND release) uses Google's official test units
/// unless the production build explicitly opts out with
/// `--dart-define=ABS_ADS_USE_TEST_IDS=false` and supplies the real units:
///
///   --dart-define=ABS_ADMOB_BANNER_ANDROID=ca-app-pub-…/…
///   --dart-define=ABS_ADMOB_INTERSTITIAL_ANDROID=ca-app-pub-…/…
///   --dart-define=ABS_ADMOB_REWARDED_ANDROID=ca-app-pub-…/…
///   --dart-define=ABS_ADMOB_APP_OPEN_ANDROID=ca-app-pub-…/…
///   (…_IOS equivalents for iOS)
///
/// Real Pulse IDs are intentionally NOT embedded in source. In production mode
/// a unit left empty disables that format instead of falling back to test ads.
class AdConfig {
  const AdConfig._();

  /// Emergency kill switch: --dart-define=ABS_ADS_ENABLED=false.
  static const bool adsEnabled = bool.fromEnvironment(
    'ABS_ADS_ENABLED',
    defaultValue: true,
  );

  /// Safe default: Google test units in every build mode.
  static const bool useTestUnits = bool.fromEnvironment(
    'ABS_ADS_USE_TEST_IDS',
    defaultValue: true,
  );

  static const Map<AdPlatform, Map<AdFormat, String>> testUnits = {
    AdPlatform.android: {
      AdFormat.banner: 'ca-app-pub-3940256099942544/9214589741',
      AdFormat.interstitial: 'ca-app-pub-3940256099942544/1033173712',
      AdFormat.rewarded: 'ca-app-pub-3940256099942544/5224354917',
      AdFormat.appOpen: 'ca-app-pub-3940256099942544/9257395921',
    },
    AdPlatform.ios: {
      AdFormat.banner: 'ca-app-pub-3940256099942544/2435281174',
      AdFormat.interstitial: 'ca-app-pub-3940256099942544/4411468910',
      AdFormat.rewarded: 'ca-app-pub-3940256099942544/1712485313',
      AdFormat.appOpen: 'ca-app-pub-3940256099942544/5575463023',
    },
  };

  /// Build-time overrides only. Empty until the production AAB/IPA is built.
  static const Map<AdPlatform, Map<AdFormat, String>> productionUnits = {
    AdPlatform.android: {
      AdFormat.banner: String.fromEnvironment('ABS_ADMOB_BANNER_ANDROID'),
      AdFormat.interstitial:
          String.fromEnvironment('ABS_ADMOB_INTERSTITIAL_ANDROID'),
      AdFormat.rewarded: String.fromEnvironment('ABS_ADMOB_REWARDED_ANDROID'),
      AdFormat.appOpen: String.fromEnvironment('ABS_ADMOB_APP_OPEN_ANDROID'),
    },
    AdPlatform.ios: {
      AdFormat.banner: String.fromEnvironment('ABS_ADMOB_BANNER_IOS'),
      AdFormat.interstitial:
          String.fromEnvironment('ABS_ADMOB_INTERSTITIAL_IOS'),
      AdFormat.rewarded: String.fromEnvironment('ABS_ADMOB_REWARDED_IOS'),
      AdFormat.appOpen: String.fromEnvironment('ABS_ADMOB_APP_OPEN_IOS'),
    },
  };

  static AdPlatform get currentPlatform =>
      defaultTargetPlatform == TargetPlatform.iOS
          ? AdPlatform.ios
          : AdPlatform.android;

  /// Returns the unit for [format], or an empty string when production mode is
  /// on but that unit was not supplied (the format is then treated as off).
  static String unitIdFor(
    AdFormat format, {
    AdPlatform? platform,
    bool? testMode,
  }) {
    final table = (testMode ?? useTestUnits) ? testUnits : productionUnits;
    return table[platform ?? currentPlatform]![format]!;
  }

  static bool isConfigured(AdFormat format) => unitIdFor(format).isNotEmpty;

  static String get bannerUnitId => unitIdFor(AdFormat.banner);
  static String get interstitialUnitId => unitIdFor(AdFormat.interstitial);
  static String get rewardedUnitId => unitIdFor(AdFormat.rewarded);
  static String get appOpenUnitId => unitIdFor(AdFormat.appOpen);
}
