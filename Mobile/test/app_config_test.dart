import 'package:flutter_test/flutter_test.dart';
import 'package:abs_pulse/core/app_config.dart';

void main() {
  test('Production defaults point to ABS V15.7.4 API contract', () {
    expect(AppConfig.apiBaseUrl, 'https://alphablocksolutions.com/api/v1');
    expect(AppConfig.supportedBackendBuild, '15.7.4');
    expect(AppConfig.minimumBackendBuild, '15.7.4');
    expect(AppConfig.mobileVersion, '1.6.2');
    expect(AppConfig.mobileBuild, 162);
    expect(
      AppConfig.rewardedAdUnitAndroid,
      'ca-app-pub-3940256099942544/5224354917',
    );
    expect(
      AppConfig.rewardedAdUnitIos,
      'ca-app-pub-3940256099942544/1712485313',
    );
  });
}
