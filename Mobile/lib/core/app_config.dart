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

  // AdMob units live in core/ads/ad_config.dart.

  static const Duration requestTimeout = Duration(seconds: 30);
  static const Duration visiblePriceRefresh = Duration(seconds: 20);
  static const Duration dashboardRefresh = Duration(seconds: 30);
}
