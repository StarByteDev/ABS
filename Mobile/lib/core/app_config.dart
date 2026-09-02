class AppConfig {
  static const String name = 'ABS Pulse';
  static const String shortName = 'ABS';
  static const String mobileVersion = '1.2.2';
  static const int mobileBuild = 122;
  static const String supportedBackendBuild = '14.9.2';
  static const String minimumBackendBuild = '14.9.2';

  static const String apiBaseUrl = String.fromEnvironment(
    'ABS_API_BASE_URL',
    defaultValue: 'https://alphablocksolutions.com/api/v1',
  );
  static const String website = String.fromEnvironment(
    'ABS_WEBSITE_URL',
    defaultValue: 'https://alphablocksolutions.com',
  );
  static const String supportEmail = 'support@alphablocksolutions.com';

  static const Duration requestTimeout = Duration(seconds: 30);
  static const Duration visiblePriceRefresh = Duration(seconds: 20);
  static const Duration dashboardRefresh = Duration(seconds: 30);
}
