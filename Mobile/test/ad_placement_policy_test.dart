import 'dart:math';

import 'package:abs_pulse/ads/ad_config.dart';
import 'package:abs_pulse/ads/ad_placement_policy.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('native feed slots', () {
    test('a short feed earns no ad at all', () {
      for (
        var count = 0;
        count <= AdPlacementPolicy.newsNativeFirstSlotAfter;
        count++
      ) {
        expect(
          AdPlacementPolicy.nativeSlotsFor(count),
          isEmpty,
          reason: '$count items must stay ad-free',
        );
      }
    });

    test('the reader always meets genuine content before the first ad', () {
      for (var seed = 0; seed < 40; seed++) {
        final slots = AdPlacementPolicy.nativeSlotsFor(60, seed: seed);
        expect(
          slots.first,
          greaterThanOrEqualTo(AdPlacementPolicy.newsNativeFirstSlotAfter),
        );
      }
    });

    test('ads stay 6-8 real items apart and never sit next to each other', () {
      for (var seed = 0; seed < 40; seed++) {
        final slots = AdPlacementPolicy.nativeSlotsFor(80, seed: seed);
        for (var i = 1; i < slots.length; i++) {
          final gap = slots[i] - slots[i - 1];
          expect(
            gap,
            greaterThanOrEqualTo(AdPlacementPolicy.newsNativeIntervalMinimum),
          );
          expect(
            gap,
            lessThanOrEqualTo(AdPlacementPolicy.newsNativeIntervalMaximum),
          );
        }
      }
    });

    test('slot count is capped however long the feed grows', () {
      expect(
        AdPlacementPolicy.nativeSlotsFor(500).length,
        lessThanOrEqualTo(AdPlacementPolicy.newsNativeMaximumSlots),
      );
    });

    test('a given seed always produces the same layout', () {
      expect(
        AdPlacementPolicy.nativeSlotsFor(50, seed: 99),
        AdPlacementPolicy.nativeSlotsFor(50, seed: 99),
      );
    });

    test('slots never run past the end of the feed', () {
      for (var count = 7; count < 60; count++) {
        for (final slot in AdPlacementPolicy.nativeSlotsFor(count, seed: 3)) {
          expect(slot, lessThan(count));
        }
      }
    });
  });

  group('inline banner slots', () {
    test('a thin list gets no banner', () {
      expect(
        AdPlacementPolicy.inlineBannerSlotsFor(
          AdPlacementPolicy.minimumContentBeforeInlineAd - 1,
        ),
        isEmpty,
      );
    });

    test('the first banner never precedes useful content', () {
      final slots = AdPlacementPolicy.inlineBannerSlotsFor(40);
      expect(slots.first, AdPlacementPolicy.minimumContentBeforeInlineAd);
    });

    test('banners are capped per screen and spaced apart', () {
      final slots = AdPlacementPolicy.inlineBannerSlotsFor(200);
      expect(
        slots.length,
        lessThanOrEqualTo(AdPlacementPolicy.inlineBannerMaximumPerScreen),
      );
      for (var i = 1; i < slots.length; i++) {
        expect(
          slots[i] - slots[i - 1],
          greaterThanOrEqualTo(AdPlacementPolicy.inlineBannerRepeatInterval),
        );
      }
    });
  });

  group('surface eligibility', () {
    test('native and inline surfaces never overlap', () {
      expect(
        AdPlacementPolicy.nativeSurfaces.intersection(
          AdPlacementPolicy.inlineBannerSurfaces,
        ),
        isEmpty,
      );
    });

    test('every surface carries at most one ad format', () {
      for (final surface in AdSurface.values) {
        final formats = [
          AdPlacementPolicy.allowsNative(surface),
          AdPlacementPolicy.allowsInlineBanner(surface),
        ].where((allowed) => allowed).length;
        expect(formats, lessThanOrEqualTo(1), reason: surface.name);
      }
    });

    test('the home dashboard carries the premium native slot', () {
      expect(AdPlacementPolicy.allowsNative(AdSurface.homeDashboard), isTrue);
      expect(
        AdPlacementPolicy.allowsInlineBanner(AdSurface.homeDashboard),
        isFalse,
      );
    });

    test('the calendar uses inline banners, never native', () {
      expect(
        AdPlacementPolicy.allowsInlineBanner(AdSurface.economicCalendar),
        isTrue,
      );
      expect(
        AdPlacementPolicy.allowsNative(AdSurface.economicCalendar),
        isFalse,
      );
    });
  });

  group('interstitial pacing', () {
    test('the trigger is randomised inside the conservative window', () {
      final random = Random(7);
      final seen = <int>{};
      for (var i = 0; i < 300; i++) {
        final threshold = AdPlacementPolicy.nextInterstitialThreshold(random);
        expect(
          threshold,
          greaterThanOrEqualTo(
            AdPlacementPolicy.interstitialMinimumEligibleActions,
          ),
        );
        expect(
          threshold,
          lessThanOrEqualTo(
            AdPlacementPolicy.interstitialMaximumEligibleActions,
          ),
        );
        seen.add(threshold);
      }
      // Not perfectly predictable.
      expect(seen.length, greaterThan(1));
    });

    test('only passive reading counts as an eligible interaction', () {
      expect(
        AdPlacementPolicy.interstitialEligibleReadKinds,
        containsAll(<String>['news', 'research', 'learning']),
      );
      for (final sensitive in const [
        'signal',
        'plans',
        'payment',
        'positions',
        'scanner',
      ]) {
        expect(
          AdPlacementPolicy.interstitialEligibleReadKinds.contains(sensitive),
          isFalse,
          reason: '$sensitive must never trigger an interstitial',
        );
      }
    });
  });

  group('policy floors', () {
    test(
      'frequency stays at or below the conservative premium starting point',
      () {
        expect(
          AdPlacementPolicy.interstitialMinimumEligibleActions,
          greaterThanOrEqualTo(4),
        );
        expect(
          AdPlacementPolicy.interstitialCooldown,
          greaterThanOrEqualTo(const Duration(minutes: 3)),
        );
        expect(AdPlacementPolicy.interstitialSessionCap, lessThanOrEqualTo(2));
        expect(
          AdPlacementPolicy.appOpenFirstEligibleSession,
          greaterThanOrEqualTo(3),
        );
        expect(
          AdPlacementPolicy.appOpenCooldown,
          greaterThanOrEqualTo(const Duration(minutes: 30)),
        );
        expect(AdPlacementPolicy.appOpenSessionCap, 1);
        expect(
          AdPlacementPolicy.postRewardSuppression,
          greaterThanOrEqualTo(const Duration(minutes: 10)),
        );
      },
    );

    test('AdConfig reads its pacing from the single policy source', () {
      expect(
        AdConfig.interstitialCooldown,
        AdPlacementPolicy.interstitialCooldown,
      );
      expect(
        AdConfig.appOpenMinimumLaunchCount,
        AdPlacementPolicy.appOpenFirstEligibleSession,
      );
      expect(
        AdConfig.postRewardedQuietPeriod,
        AdPlacementPolicy.postRewardSuppression,
      );
    });
  });

  group('native template sizing', () {
    test('the minimum width matches Google\'s published template floor', () {
      // Both templates are published for 320dp minimum width. A narrower slot
      // can clip the headline, the CTA or the mandatory attribution.
      expect(AdPlacementPolicy.nativeTemplateMinimumWidth, 320);
    });

    test('template heights sit inside Google\'s published bands', () {
      expect(
        AdPlacementPolicy.nativeSmallTemplateHeight,
        inInclusiveRange(90, 200),
      );
      expect(
        AdPlacementPolicy.nativeMediumTemplateHeight,
        inInclusiveRange(320, 400),
      );
    });
  });

  group('accidental-click protection', () {
    test('inline banners keep generous clearance from tappable rows', () {
      expect(
        AdPlacementPolicy.inlineBannerVerticalClearance,
        greaterThanOrEqualTo(16),
      );
    });

    test('a banner can never dominate the feed it sits in', () {
      expect(
        AdPlacementPolicy.inlineBannerMaximumHeight,
        lessThanOrEqualTo(120),
      );
    });

    test('an external return suppresses interstitials, not just App Open', () {
      expect(AdPlacementPolicy.externalFlowSuppressesInterstitial, isTrue);
      expect(
        AdPlacementPolicy.externalFlowSuppression,
        greaterThanOrEqualTo(const Duration(minutes: 5)),
      );
    });
  });

  group('excluded surfaces', () {
    test('no auth, payment, wallet or execution surface is monetizable', () {
      // The enum itself is the allowlist: a screen with no AdSurface can never
      // render an ad, so these names must never appear in it.
      final names = AdSurface.values.map((s) => s.name.toLowerCase()).toList();
      for (final forbidden in const [
        'login',
        'register',
        'payment',
        'plans',
        'package',
        'wallet',
        'usdt',
        'profile',
        'settings',
        'signal',
        'scanner',
        'positions',
        'strategy',
        'strategies',
        'reports',
        'error',
        'freesignal',
      ]) {
        expect(
          names.any((name) => name.contains(forbidden)),
          isFalse,
          reason: '\'$forbidden\' must never be a monetizable surface',
        );
      }
    });

    test('the Markets browse list is a monetizable long-scroll surface', () {
      expect(
        AdPlacementPolicy.allowsInlineBanner(AdSurface.marketPrices),
        isTrue,
      );
      expect(AdPlacementPolicy.allowsNative(AdSurface.marketPrices), isFalse);
    });
  });

  group('ads never take over an empty screen', () {
    test('an empty feed gets no native and no inline slot', () {
      expect(AdPlacementPolicy.nativeSlotsFor(0), isEmpty);
      expect(AdPlacementPolicy.inlineBannerSlotsFor(0), isEmpty);
    });

    test('a feed below the content floor stays completely ad-free', () {
      for (
        var count = 0;
        count < AdPlacementPolicy.minimumContentBeforeInlineAd;
        count++
      ) {
        expect(
          AdPlacementPolicy.inlineBannerSlotsFor(count),
          isEmpty,
          reason: '$count rows must not carry a banner',
        );
      }
      for (
        var count = 0;
        count <= AdPlacementPolicy.newsNativeFirstSlotAfter;
        count++
      ) {
        expect(
          AdPlacementPolicy.nativeSlotsFor(count),
          isEmpty,
          reason: '$count items must not carry a native card',
        );
      }
    });

    test('a sparse calendar never earns a banner', () {
      // The calendar needs its own floor: a two-event day with an ad wedged
      // into it reads as an advert with a calendar attached.
      expect(
        AdPlacementPolicy.calendarMinimumEventsForBanner,
        greaterThanOrEqualTo(AdPlacementPolicy.minimumContentBeforeInlineAd),
      );
    });

    test('a thin dashboard skips the home native slot', () {
      expect(
        AdPlacementPolicy.homeNativeMinimumContentBlocks,
        greaterThanOrEqualTo(2),
      );
    });
  });

  group('interstitial eligibility window', () {
    test('every drawn gap lands inside the 10-20 minute window', () {
      final random = Random(11);
      final seen = <int>{};
      for (var i = 0; i < 500; i++) {
        final gap = AdPlacementPolicy.nextInterstitialEligibilityGap(random);
        expect(
          gap,
          greaterThanOrEqualTo(
            AdPlacementPolicy.interstitialEligibilityMinimum,
          ),
        );
        expect(
          gap,
          lessThanOrEqualTo(AdPlacementPolicy.interstitialEligibilityMaximum),
        );
        seen.add(gap.inSeconds);
      }
      // Redrawn every time, so the cadence is never predictable.
      expect(seen.length, greaterThan(50));
    });

    test('the window is exactly 10 to 20 minutes', () {
      expect(
        AdPlacementPolicy.interstitialEligibilityMinimum,
        const Duration(minutes: 10),
      );
      expect(
        AdPlacementPolicy.interstitialEligibilityMaximum,
        const Duration(minutes: 20),
      );
    });
  });

  group('short informational pages', () {
    test('a thin page earns no banner at all', () {
      for (
        var count = 0;
        count < AdPlacementPolicy.minimumContentForShortPageAd;
        count++
      ) {
        expect(
          AdPlacementPolicy.shortPageSlotFor(count),
          isNull,
          reason: '$count rows must stay ad-free',
        );
      }
    });

    test('the single slot never lands first and never past the end', () {
      for (
        var count = AdPlacementPolicy.minimumContentForShortPageAd;
        count < 60;
        count++
      ) {
        final slot = AdPlacementPolicy.shortPageSlotFor(count);
        expect(slot, isNotNull, reason: '$count rows should earn one banner');
        expect(slot, greaterThan(0), reason: 'never before real content');
        expect(slot, lessThanOrEqualTo(count), reason: 'never past the end');
      }
    });

    test('thin search results stay ad-free', () {
      expect(
        AdPlacementPolicy.minimumSearchResultsForAd,
        greaterThanOrEqualTo(AdPlacementPolicy.minimumContentForShortPageAd),
      );
    });
  });

  group('broadened monetization allowlist', () {
    test('every passive browse surface is monetizable', () {
      // Which format a surface uses is the map's decision; this only asserts
      // that each of these pages does carry some ad.
      for (final surface in const [
        AdSurface.marketPrices,
        AdSurface.marketOverview,
        AdSurface.exploreMarkets,
        AdSurface.economicCalendar,
        AdSurface.research,
        AdSurface.learning,
        AdSurface.services,
        AdSurface.watchlist,
        AdSurface.exploreHub,
        AdSurface.searchResults,
        AdSurface.homeDashboard,
        AdSurface.guestPulse,
        AdSurface.newsFeed,
        AdSurface.liveNewsFeed,
      ]) {
        expect(
          AdPlacementPolicy.allowsNative(surface) ||
              AdPlacementPolicy.allowsInlineBanner(surface),
          isTrue,
          reason: '${surface.name} should carry an ad',
        );
      }
    });

    test('every surface still carries at most one format', () {
      for (final surface in AdSurface.values) {
        final formats = [
          AdPlacementPolicy.allowsNative(surface),
          AdPlacementPolicy.allowsInlineBanner(surface),
        ].where((allowed) => allowed).length;
        expect(formats, lessThanOrEqualTo(1), reason: surface.name);
      }
    });
  });

  group('placement map is the single source of truth', () {
    test('every monetizable surface has exactly one assigned format', () {
      for (final surface in AdPlacementPolicy.placements.keys) {
        expect(AdPlacementPolicy.formatFor(surface), isNotNull);
      }
      // The two legacy sets are now derived from the map, so they cannot drift.
      for (final surface in AdPlacementPolicy.nativeSurfaces) {
        expect(
          AdPlacementPolicy.inlineBannerSurfaces.contains(surface),
          isFalse,
          reason: '${surface.name} cannot be both native and banner',
        );
      }
    });

    test('an unmapped surface can never render an ad', () {
      for (final surface in AdSurface.values) {
        if (AdPlacementPolicy.placements.containsKey(surface)) continue;
        expect(AdPlacementPolicy.allowsNative(surface), isFalse);
        expect(AdPlacementPolicy.allowsInlineBanner(surface), isFalse);
      }
    });

    test('every page the product monetizes is mapped', () {
      for (final surface in const [
        AdSurface.guestPulse,
        AdSurface.homeDashboard,
        AdSurface.newsFeed,
        AdSurface.liveNewsFeed,
        AdSurface.research,
        AdSurface.learning,
        AdSurface.services,
        AdSurface.marketPrices,
        AdSurface.marketOverview,
        AdSurface.marketDetail,
        AdSurface.watchlist,
        AdSurface.economicCalendar,
        AdSurface.exploreMarkets,
        AdSurface.exploreHub,
        AdSurface.searchResults,
        AdSurface.legalHub,
        AdSurface.legalDocument,
      ]) {
        expect(
          AdPlacementPolicy.formatFor(surface),
          isNotNull,
          reason: '${surface.name} must be monetized',
        );
      }
    });
  });

  group('no ad may reserve a giant block', () {
    test('the compact native template stays inside the small band', () {
      // Google publishes small as 320x90..400x200. Medium (~336dp) is banned
      // from ordinary ABS content because it dwarfs the surrounding cards.
      expect(
        AdPlacementPolicy.nativeSmallTemplateHeight,
        inInclusiveRange(90, 200),
      );
      expect(
        AdPlacementPolicy.nativeSmallTemplateHeight,
        lessThan(AdPlacementPolicy.nativeMediumTemplateHeight),
      );
    });

    test('the card clears AdMob\'s 120pt MediaView floor for video', () {
      // The native validator reports "MediaView is too small for video" when
      // the media area is under 120x120 points.
      expect(
        AdPlacementPolicy.nativeSmallTemplateHeight,
        greaterThanOrEqualTo(AdPlacementPolicy.nativeMediaViewMinimumEdge),
      );
      expect(
        AdPlacementPolicy.nativeTemplateMinimumWidth,
        greaterThanOrEqualTo(AdPlacementPolicy.nativeMediaViewMinimumEdge),
      );
    });

    test('inline banners are capped inside the 60-100dp target', () {
      expect(
        AdPlacementPolicy.inlineBannerMaximumHeight,
        lessThanOrEqualTo(100),
      );
      expect(
        AdPlacementPolicy.inlineBannerMaximumHeight,
        greaterThanOrEqualTo(60),
      );
      expect(
        AdPlacementPolicy.inlineBannerMinimumHeight,
        lessThan(AdPlacementPolicy.inlineBannerMaximumHeight),
      );
    });
  });

  group('test-ads-only guarantee', () {
    test('native units are the official Google test units', () {
      expect(
        AdConfig.nativeAdUnitFor(AdPlatform.android),
        'ca-app-pub-3940256099942544/2247696110',
      );
      expect(
        AdConfig.nativeAdUnitFor(AdPlatform.ios),
        'ca-app-pub-3940256099942544/3986624511',
      );
      expect(AdConfig.nativeAdUnitFor(AdPlatform.unsupported), isNull);
    });

    test('every configured unit belongs to the Google test publisher', () {
      for (final platform in const [AdPlatform.android, AdPlatform.ios]) {
        for (final unit in [
          AdConfig.bannerAdUnitFor(platform),
          AdConfig.interstitialAdUnitFor(platform),
          AdConfig.rewardedAdUnitFor(platform),
          AdConfig.appOpenAdUnitFor(platform),
          AdConfig.nativeAdUnitFor(platform),
        ]) {
          expect(unit, startsWith('ca-app-pub-3940256099942544/'));
        }
      }
    });
  });
}
