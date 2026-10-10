import 'package:flutter/foundation.dart';

import 'ad_placement_policy.dart';

enum AdEnvironment { test, production }

enum AdPlatform { android, ios, unsupported }

/// Central, policy-safe AdMob configuration for Pulse.
///
/// Test mode is the fail-safe default. A production build must explicitly set
/// `ABS_ADMOB_ENV=production` and provide every platform-specific ad-unit ID.
class AdConfig {
  const AdConfig._();

  static const String _environmentName = String.fromEnvironment(
    'ABS_ADMOB_ENV',
    defaultValue: 'test',
  );

  static const String androidTestAppId =
      'ca-app-pub-3940256099942544~3347511713';
  static const String iosTestAppId = 'ca-app-pub-3940256099942544~1458002511';

  static const String androidTestBanner =
      'ca-app-pub-3940256099942544/9214589741';
  static const String iosTestBanner = 'ca-app-pub-3940256099942544/2435281174';
  static const String androidTestInterstitial =
      'ca-app-pub-3940256099942544/1033173712';
  static const String iosTestInterstitial =
      'ca-app-pub-3940256099942544/4411468910';
  static const String androidTestRewarded =
      'ca-app-pub-3940256099942544/5224354917';
  static const String iosTestRewarded =
      'ca-app-pub-3940256099942544/1712485313';
  static const String androidTestAppOpen =
      'ca-app-pub-3940256099942544/9257395921';
  static const String iosTestAppOpen = 'ca-app-pub-3940256099942544/5575463023';
  static const String androidTestNative =
      'ca-app-pub-3940256099942544/2247696110';
  static const String iosTestNative = 'ca-app-pub-3940256099942544/3986624511';

  static const String _androidProductionBanner = String.fromEnvironment(
    'ABS_ADMOB_BANNER_ANDROID',
  );
  static const String _iosProductionBanner = String.fromEnvironment(
    'ABS_ADMOB_BANNER_IOS',
  );
  static const String _androidProductionInterstitial = String.fromEnvironment(
    'ABS_ADMOB_INTERSTITIAL_ANDROID',
  );
  static const String _iosProductionInterstitial = String.fromEnvironment(
    'ABS_ADMOB_INTERSTITIAL_IOS',
  );
  static const String _androidProductionRewarded = String.fromEnvironment(
    'ABS_ADMOB_REWARDED_ANDROID',
  );
  static const String _iosProductionRewarded = String.fromEnvironment(
    'ABS_ADMOB_REWARDED_IOS',
  );
  static const String _androidProductionAppOpen = String.fromEnvironment(
    'ABS_ADMOB_APP_OPEN_ANDROID',
  );
  static const String _iosProductionAppOpen = String.fromEnvironment(
    'ABS_ADMOB_APP_OPEN_IOS',
  );
  static const String _androidProductionNative = String.fromEnvironment(
    'ABS_ADMOB_NATIVE_ANDROID',
  );
  static const String _iosProductionNative = String.fromEnvironment(
    'ABS_ADMOB_NATIVE_IOS',
  );

  // Frequency and density live in AdPlacementPolicy so monetization can be
  // tuned in one file. These aliases keep ad-unit configuration and placement
  // policy reachable from a single import at the call sites that need both.
  static const int appOpenMinimumLaunchCount =
      AdPlacementPolicy.appOpenFirstEligibleSession;
  static const int interstitialInteractionsRequired =
      AdPlacementPolicy.interstitialMinimumEligibleActions;
  static const int interstitialSessionCap =
      AdPlacementPolicy.interstitialSessionCap;
  static const int appOpenSessionCap = AdPlacementPolicy.appOpenSessionCap;
  static const Duration interstitialCooldown =
      AdPlacementPolicy.interstitialCooldown;
  static const Duration interstitialMinimumSessionAge =
      AdPlacementPolicy.interstitialMinimumSessionAge;
  static const Duration appOpenCooldown = AdPlacementPolicy.appOpenCooldown;
  static const Duration appOpenMinimumBackgroundTime =
      AdPlacementPolicy.appOpenMinimumBackgroundTime;
  static const Duration fullScreenQuietPeriod =
      AdPlacementPolicy.postFullScreenSuppression;
  static const Duration postRewardedQuietPeriod =
      AdPlacementPolicy.postRewardSuppression;
  static const Duration externalFlowQuietPeriod =
      AdPlacementPolicy.externalFlowSuppression;
  static const Duration appOpenMaximumAge = AdPlacementPolicy.appOpenMaximumAge;

  static AdEnvironment get environment => _environmentName == 'production'
      ? AdEnvironment.production
      : AdEnvironment.test;

  static bool get isTest => environment == AdEnvironment.test;

  static AdPlatform get platform {
    if (kIsWeb) return AdPlatform.unsupported;
    return switch (defaultTargetPlatform) {
      TargetPlatform.android => AdPlatform.android,
      TargetPlatform.iOS => AdPlatform.ios,
      _ => AdPlatform.unsupported,
    };
  }

  static bool get isSupported => platform != AdPlatform.unsupported;

  static bool get hasCompleteAdUnitConfiguration =>
      bannerAdUnitId != null &&
      interstitialAdUnitId != null &&
      rewardedAdUnitId != null &&
      appOpenAdUnitId != null;

  static String? get bannerAdUnitId => bannerAdUnitFor(platform);
  static String? get interstitialAdUnitId => interstitialAdUnitFor(platform);
  static String? get rewardedAdUnitId => rewardedAdUnitFor(platform);
  static String? get appOpenAdUnitId => appOpenAdUnitFor(platform);

  /// Native is an additive surface: a deployment missing a native unit keeps
  /// every other format working instead of disabling monetization entirely.
  static String? get nativeAdUnitId => nativeAdUnitFor(platform);

  static String? bannerAdUnitFor(AdPlatform target) => _forPlatform(
    target,
    androidTest: androidTestBanner,
    iosTest: iosTestBanner,
    androidProduction: _androidProductionBanner,
    iosProduction: _iosProductionBanner,
  );

  static String? interstitialAdUnitFor(AdPlatform target) => _forPlatform(
    target,
    androidTest: androidTestInterstitial,
    iosTest: iosTestInterstitial,
    androidProduction: _androidProductionInterstitial,
    iosProduction: _iosProductionInterstitial,
  );

  static String? rewardedAdUnitFor(AdPlatform target) => _forPlatform(
    target,
    androidTest: androidTestRewarded,
    iosTest: iosTestRewarded,
    androidProduction: _androidProductionRewarded,
    iosProduction: _iosProductionRewarded,
  );

  static String? appOpenAdUnitFor(AdPlatform target) => _forPlatform(
    target,
    androidTest: androidTestAppOpen,
    iosTest: iosTestAppOpen,
    androidProduction: _androidProductionAppOpen,
    iosProduction: _iosProductionAppOpen,
  );

  static String? nativeAdUnitFor(AdPlatform target) => _forPlatform(
    target,
    androidTest: androidTestNative,
    iosTest: iosTestNative,
    androidProduction: _androidProductionNative,
    iosProduction: _iosProductionNative,
  );

  static String? _forPlatform(
    AdPlatform target, {
    required String androidTest,
    required String iosTest,
    required String androidProduction,
    required String iosProduction,
  }) {
    if (target == AdPlatform.unsupported) return null;
    final value = switch ((environment, target)) {
      (AdEnvironment.test, AdPlatform.android) => androidTest,
      (AdEnvironment.test, AdPlatform.ios) => iosTest,
      (AdEnvironment.production, AdPlatform.android) => androidProduction,
      (AdEnvironment.production, AdPlatform.ios) => iosProduction,
      _ => '',
    };
    return value.trim().isEmpty ? null : value.trim();
  }
}
