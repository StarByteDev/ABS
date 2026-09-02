class ApiConfig {
  // Production URL for Pulse Trading Intelligence.
  // For Android emulator local testing use: http://10.0.2.2:8000
  static const String baseUrl = 'https://pulse.alphablocksolutions.com';
  static const String apiPrefix = '/api/mobile';

  static Uri uri(String path) => Uri.parse('$baseUrl$apiPrefix$path');
  static Uri publicUri(String path) => Uri.parse('$baseUrl$path');
}
