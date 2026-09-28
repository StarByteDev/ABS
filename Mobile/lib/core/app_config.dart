class AppConfig {
  static const String name = 'Pulse';
  static const String shortName = 'Pulse';
  static const String mobileVersion = '1.6.4';
  static const int mobileBuild = 164;
  static const String supportedBackendBuild = '15.7.4';
  static const String minimumBackendBuild = '15.7.4';

  static const String apiBaseUrl = String.fromEnvironment(
    'ABS_API_BASE_URL',
    defaultValue: 'https://alphablocksolutions.com/api/v1',
  );
  static const String website = String.fromEnvironment(
    'ABS_WEBSITE_URL',
    defaultValue: 'https://alphablocksolutions.com',
  );
  static const String supportEmail = 'support@alphablocksolutions.com';

  // Google test rewarded units are safe for local development. Supply the live
  // platform-specific unit at build time with --dart-define before publishing.
  static const String rewardedAdUnitAndroid = String.fromEnvironment(
    'ABS_ADMOB_REWARDED_ANDROID',
    defaultValue: 'ca-app-pub-3940256099942544/5224354917',
  );
  static const String rewardedAdUnitIos = String.fromEnvironment(
    'ABS_ADMOB_REWARDED_IOS',
    defaultValue: 'ca-app-pub-3940256099942544/1712485313',
  );

  static const Duration requestTimeout = Duration(seconds: 30);
  static const Duration visiblePriceRefresh = Duration(seconds: 20);
  static const Duration dashboardRefresh = Duration(seconds: 30);
}
