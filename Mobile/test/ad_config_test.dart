import 'package:abs_pulse/ads/ad_config.dart';
import 'package:abs_pulse/ads/ad_service.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('development builds fail safely to the explicit test environment', () {
    expect(AdConfig.environment, AdEnvironment.test);
    expect(AdConfig.isTest, isTrue);
  });

  test('Android uses every official Google test ad unit', () {
    expect(
      AdConfig.bannerAdUnitFor(AdPlatform.android),
      'ca-app-pub-3940256099942544/9214589741',
    );
    expect(
      AdConfig.interstitialAdUnitFor(AdPlatform.android),
      'ca-app-pub-3940256099942544/1033173712',
    );
    expect(
      AdConfig.rewardedAdUnitFor(AdPlatform.android),
      'ca-app-pub-3940256099942544/5224354917',
    );
    expect(
      AdConfig.appOpenAdUnitFor(AdPlatform.android),
      'ca-app-pub-3940256099942544/9257395921',
    );
  });

  test('iOS uses every official Google test ad unit', () {
    expect(
      AdConfig.bannerAdUnitFor(AdPlatform.ios),
      'ca-app-pub-3940256099942544/2435281174',
    );
    expect(
      AdConfig.interstitialAdUnitFor(AdPlatform.ios),
      'ca-app-pub-3940256099942544/4411468910',
    );
    expect(
      AdConfig.rewardedAdUnitFor(AdPlatform.ios),
      'ca-app-pub-3940256099942544/1712485313',
    );
    expect(
      AdConfig.appOpenAdUnitFor(AdPlatform.ios),
      'ca-app-pub-3940256099942544/5575463023',
    );
  });

  test('unsupported platforms disable all formats safely', () {
    expect(AdConfig.bannerAdUnitFor(AdPlatform.unsupported), isNull);
    expect(AdConfig.interstitialAdUnitFor(AdPlatform.unsupported), isNull);
    expect(AdConfig.rewardedAdUnitFor(AdPlatform.unsupported), isNull);
    expect(AdConfig.appOpenAdUnitFor(AdPlatform.unsupported), isNull);
  });

  test('full-screen frequency policy remains conservative', () {
    expect(AdConfig.appOpenMinimumLaunchCount, greaterThanOrEqualTo(3));
    expect(AdConfig.interstitialInteractionsRequired, greaterThanOrEqualTo(4));
    expect(
      AdConfig.interstitialCooldown,
      greaterThanOrEqualTo(const Duration(minutes: 3)),
    );
    expect(AdConfig.interstitialSessionCap, lessThanOrEqualTo(2));
    expect(AdConfig.appOpenSessionCap, 1);
    expect(
      AdConfig.postRewardedQuietPeriod,
      greaterThanOrEqualTo(const Duration(minutes: 10)),
    );
  });

  test('only an earned rewarded result authorizes a server claim', () {
    expect(const RewardedAdResult.earned('reward', 1).earned, isTrue);
    expect(const RewardedAdResult.closedEarly().earned, isFalse);
    expect(const RewardedAdResult.unavailable().earned, isFalse);
    expect(const RewardedAdResult.busy().earned, isFalse);
  });
}
