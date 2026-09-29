import 'package:flutter_test/flutter_test.dart';
import 'package:abs_pulse/core/app_config.dart';
import 'package:abs_pulse/core/ads/ad_config.dart';

void main() {
  test('Production defaults point to ABS V15.7.4 API contract', () {
    expect(AppConfig.apiBaseUrl, 'https://alphablocksolutions.com/api/v1');
    expect(AppConfig.supportedBackendBuild, '15.7.4');
    expect(AppConfig.minimumBackendBuild, '15.7.4');
    expect(AppConfig.mobileVersion, '1.6.4');
    expect(AppConfig.mobileBuild, 164);
  });

  test('Ads default to Google test units in every build mode', () {
    expect(AdConfig.useTestUnits, isTrue);
    expect(AdConfig.adsEnabled, isTrue);
    expect(AdConfig.unitIdFor(AdFormat.rewarded, platform: AdPlatform.android),
        'ca-app-pub-3940256099942544/5224354917');
    expect(AdConfig.unitIdFor(AdFormat.rewarded, platform: AdPlatform.ios),
        'ca-app-pub-3940256099942544/1712485313');
    expect(AdConfig.unitIdFor(AdFormat.banner, platform: AdPlatform.android),
        'ca-app-pub-3940256099942544/9214589741');
    expect(
        AdConfig.unitIdFor(AdFormat.interstitial, platform: AdPlatform.android),
        'ca-app-pub-3940256099942544/1033173712');
  });

  test('Every default unit is a Google test unit and no real IDs are embedded',
      () {
    for (final platform in AdPlatform.values) {
      for (final format in AdFormat.values) {
        expect(
          AdConfig.unitIdFor(format, platform: platform),
          startsWith('ca-app-pub-3940256099942544/'),
        );
        // Production units are build-time overrides only; empty by default.
        expect(
          AdConfig.unitIdFor(format, platform: platform, testMode: false),
          isEmpty,
        );
      }
    }
  });
}
