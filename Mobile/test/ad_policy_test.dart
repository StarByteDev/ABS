import 'package:flutter_test/flutter_test.dart';
import 'package:abs_pulse/core/ads/ad_policy.dart';

void main() {
  late DateTime now;
  late AdPolicy policy;

  const moment = InterstitialMoment.newsDetailClosed;
  const guest = AdAudience.guest;

  setUp(() {
    now = DateTime(2026, 9, 29, 12);
    policy = AdPolicy(clock: () => now);
  });

  void navigate(int times) {
    for (var i = 0; i < times; i++) {
      policy.recordEligibleNavigation();
    }
  }

  void show(FullScreenKind kind) {
    policy.markFullScreenShowing(kind);
    policy.markFullScreenDismissed(kind);
  }

  void advance(Duration d) => now = now.add(d);

  group('interstitial', () {
    test('no interstitial at startup even after enough navigations', () {
      navigate(5);
      expect(policy.canShowInterstitial(guest, moment), isFalse);
      advance(const Duration(seconds: 119));
      expect(policy.canShowInterstitial(guest, moment), isFalse);
    });

    test('requires 3 eligible navigations AND 120 seconds', () {
      advance(const Duration(minutes: 5));
      navigate(2);
      expect(policy.canShowInterstitial(guest, moment), isFalse);
      navigate(1);
      expect(policy.canShowInterstitial(guest, moment), isTrue);
      expect(policy.canShowInterstitial(AdAudience.member, moment), isTrue);
    });

    test('resets after an interstitial and enforces the 120 second gap', () {
      advance(const Duration(minutes: 5));
      navigate(3);
      show(FullScreenKind.interstitial);
      navigate(3);
      advance(const Duration(seconds: 119));
      expect(policy.canShowInterstitial(guest, moment), isFalse);
      advance(const Duration(seconds: 1));
      expect(policy.canShowInterstitial(guest, moment), isTrue);
    });

    test('caps interstitials at 3 per session', () {
      for (var i = 0; i < 3; i++) {
        advance(const Duration(minutes: 3));
        navigate(3);
        expect(policy.canShowInterstitial(guest, moment), isTrue);
        show(FullScreenKind.interstitial);
      }
      advance(const Duration(minutes: 3));
      navigate(10);
      expect(policy.interstitialsThisSession, 3);
      expect(policy.canShowInterstitial(guest, moment), isFalse);
    });

    test('never stacks on, or directly follows, another full-screen ad', () {
      advance(const Duration(minutes: 5));
      navigate(3);
      policy.markFullScreenShowing(FullScreenKind.rewarded);
      expect(policy.canShowInterstitial(guest, moment), isFalse);
      policy.markFullScreenDismissed(FullScreenKind.rewarded);
      expect(policy.canShowInterstitial(guest, moment), isFalse);
      expect(policy.interstitialsThisSession, 0);
    });

    test('a failed show does not consume the session cap', () {
      advance(const Duration(minutes: 5));
      navigate(3);
      policy.markFullScreenShowing(FullScreenKind.interstitial);
      policy.markFullScreenFailed(FullScreenKind.interstitial);
      expect(policy.interstitialsThisSession, 0);
      expect(policy.canShowInterstitial(guest, moment), isTrue);
    });
  });

  group('app open', () {
    test('eligible on the very first cold launch', () {
      expect(policy.canShowAppOpen(guest), isTrue);
    });

    test('max 1 impression per 30 minutes', () {
      show(FullScreenKind.appOpen);
      advance(const Duration(minutes: 29));
      expect(policy.canShowAppOpen(guest), isFalse);
      advance(const Duration(minutes: 1));
      expect(policy.canShowAppOpen(guest), isTrue);
    });

    test('30 minute cap persists across cold launches', () {
      policy.restore(lastAppOpenAt: now.subtract(const Duration(minutes: 10)));
      expect(policy.canShowAppOpen(guest), isFalse);
      policy.restore(lastAppOpenAt: now.subtract(const Duration(minutes: 31)));
      expect(policy.canShowAppOpen(guest), isTrue);
    });

    test('never during or right after another full-screen ad', () {
      policy.markFullScreenShowing(FullScreenKind.interstitial);
      expect(policy.canShowAppOpen(guest), isFalse);
      policy.markFullScreenDismissed(FullScreenKind.interstitial);
      advance(const Duration(seconds: 59));
      expect(policy.canShowAppOpen(guest), isFalse);
      advance(const Duration(seconds: 1));
      expect(policy.canShowAppOpen(guest), isTrue);
    });

    test('an app open ad blocks an immediate interstitial', () {
      advance(const Duration(minutes: 5));
      navigate(3);
      show(FullScreenKind.appOpen);
      navigate(3);
      expect(policy.canShowInterstitial(guest, moment), isFalse);
    });

    test('a failed app open does not consume the 30 minute window', () {
      final previous = policy.lastAppOpenAt;
      policy.markFullScreenShowing(FullScreenKind.appOpen);
      policy.markFullScreenFailed(FullScreenKind.appOpen,
          previousAppOpenAt: previous);
      expect(policy.canShowAppOpen(guest), isTrue);
    });
  });

  group('in-feed banner positions', () {
    test('Pulse: after 3rd, second only on long feeds', () {
      List<int> pulse(int n) => AdPolicy.feedBannerPositions(n,
          first: 3, spacing: 7, trailingWhenShort: true);
      expect(pulse(0), [0]);
      expect(pulse(2), [2]);
      expect(pulse(5), [3]);
      expect(pulse(11), [3]);
      expect(pulse(12), [3, 10]);
      expect(pulse(40), [3, 10]);
    });

    test('News: after 5th article, never after every item', () {
      List<int> news(int n) =>
          AdPolicy.feedBannerPositions(n, first: 5, spacing: 8);
      expect(news(3), isEmpty);
      expect(news(5), [5]);
      expect(news(14), [5]);
      expect(news(15), [5, 13]);
    });
  });

  test('exempt audiences and the kill switch suppress all ads', () {
    final exempt = AdPolicy(
      clock: () => now,
      exemptAudiences: const {AdAudience.pulseSubscriber},
    );
    expect(
        exempt.canShowBanner(AdAudience.pulseSubscriber, BannerPlacement.home),
        isFalse);
    expect(exempt.canShowAppOpen(AdAudience.pulseSubscriber), isFalse);
    expect(exempt.canShowBanner(guest, BannerPlacement.home), isTrue);

    final off = AdPolicy(clock: () => now, adsEnabled: false);
    expect(off.canShowBanner(guest, BannerPlacement.news), isFalse);
    expect(off.canShowAppOpen(guest), isFalse);
    advance(const Duration(minutes: 5));
    for (var i = 0; i < 3; i++) {
      off.recordEligibleNavigation();
    }
    expect(off.canShowInterstitial(guest, moment), isFalse);
  });
}
