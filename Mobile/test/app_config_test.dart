import 'package:flutter_test/flutter_test.dart';
import 'package:abs_pulse/core/app_config.dart';

void main() {
  test('Production defaults point to ABS V14.9.2 API contract', () {
    expect(AppConfig.apiBaseUrl, 'https://alphablocksolutions.com/api/v1');
    expect(AppConfig.supportedBackendBuild, '14.9.2');
    expect(AppConfig.minimumBackendBuild, '14.9.2');
    expect(AppConfig.mobileVersion, '1.1.0');
  });
}
