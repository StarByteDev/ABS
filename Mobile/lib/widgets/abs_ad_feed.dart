import 'package:flutter/material.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart';

import '../ads/ad_placement_policy.dart';
import 'abs_inline_banner_ad.dart';
import 'abs_native_ad.dart';

/// Interleaves ad slots into a feed without any screen knowing a number.
///
/// Every interval, lead-in and ceiling comes from [AdPlacementPolicy]. Slot
/// positions are derived from a caller-owned [seed] so they stay put across
/// rebuilds, searches, pull-to-refresh and pagination — which is also what
/// stops a native ad being re-requested just because Flutter repainted.
///
/// Each slot gets a stable [ValueKey], so Flutter preserves the slot's State
/// (and its already-loaded ad) instead of tearing it down and reloading.
class AdFeedSlots {
  const AdFeedSlots._();

  /// Genuine items woven with native ad cards.
  ///
  /// A feed too short to earn an ad is returned untouched, which keeps short
  /// lists, filtered searches and empty states clean.
  static List<Widget> withNativeAds({
    required AdSurface surface,
    required int itemCount,
    required Widget Function(int index) itemBuilder,
    required int seed,
    required String placement,
  }) {
    final slots = AdPlacementPolicy.allowsNative(surface)
        ? AdPlacementPolicy.nativeSlotsFor(itemCount, seed: seed).toSet()
        : const <int>{};
    return _weave(
      itemCount: itemCount,
      itemBuilder: itemBuilder,
      slots: slots,
      adBuilder: (position) => AbsNativeAdCard(
        key: ValueKey('native-$placement-$position'),
        surface: surface,
        debugLabel: '$placement index $position',
        // Compact only. The medium template is ~336dp tall and swamps Pulse
        // content cards, which is what produced the giant in-feed blocks.
        templateType: TemplateType.small,
      ),
    );
  }

  /// Genuine items woven with inline adaptive banners.
  static List<Widget> withInlineBanners({
    required AdSurface surface,
    required int itemCount,
    required Widget Function(int index) itemBuilder,
    required String placement,
  }) {
    final slots = AdPlacementPolicy.allowsInlineBanner(surface)
        ? AdPlacementPolicy.inlineBannerSlotsFor(itemCount).toSet()
        : const <int>{};
    return _weave(
      itemCount: itemCount,
      itemBuilder: itemBuilder,
      slots: slots,
      adBuilder: (position) => AbsInlineAdaptiveBanner(
        key: ValueKey('inline-$placement-$position'),
        surface: surface,
        debugLabel: '$placement index $position',
      ),
    );
  }

  /// A short informational page: genuine items with ONE banner placed in the
  /// lower-middle of real content.
  ///
  /// Returns the items untouched when the page is too thin to earn an ad, so
  /// an empty or near-empty screen can never become mostly advertisement.
  static List<Widget> withShortPageBanner({
    required AdSurface surface,
    required int itemCount,
    required Widget Function(int index) itemBuilder,
    required String placement,
  }) {
    final slot = AdPlacementPolicy.allowsInlineBanner(surface)
        ? AdPlacementPolicy.shortPageSlotFor(itemCount)
        : null;
    return _weave(
      itemCount: itemCount,
      itemBuilder: itemBuilder,
      slots: slot == null ? const <int>{} : {slot},
      adBuilder: (position) => AbsInlineAdaptiveBanner(
        key: ValueKey('short-$placement'),
        surface: surface,
        debugLabel: placement,
      ),
    );
  }

  /// Weaves the ads for [surface] using the format the placement map assigns.
  ///
  /// Screens call this instead of choosing a format themselves, so
  /// AdPlacementPolicy.placements stays the single source of truth.
  static List<Widget> forSurface({
    required AdSurface surface,
    required int itemCount,
    required Widget Function(int index) itemBuilder,
    required String placement,
    required int seed,
  }) {
    final plain = List<Widget>.generate(itemCount, itemBuilder);
    return switch (AdPlacementPolicy.formatFor(surface)) {
      AdFormat.nativeFeed => withNativeAds(
        surface: surface,
        itemCount: itemCount,
        itemBuilder: itemBuilder,
        seed: seed,
        placement: placement,
      ),
      AdFormat.nativeSmall => withShortPageNative(
        surface: surface,
        itemCount: itemCount,
        itemBuilder: itemBuilder,
        placement: placement,
      ),
      AdFormat.inlineCompact => withInlineBanners(
        surface: surface,
        itemCount: itemCount,
        itemBuilder: itemBuilder,
        placement: placement,
      ),
      AdFormat.inlineShortPage => withShortPageBanner(
        surface: surface,
        itemCount: itemCount,
        itemBuilder: itemBuilder,
        placement: placement,
      ),
      null => plain,
    };
  }

  /// A short page: genuine items with ONE compact native card placed in the
  /// lower-middle of real content. Matches [AdFormat.nativeSmall].
  static List<Widget> withShortPageNative({
    required AdSurface surface,
    required int itemCount,
    required Widget Function(int index) itemBuilder,
    required String placement,
  }) {
    final slot = AdPlacementPolicy.allowsNative(surface)
        ? AdPlacementPolicy.shortPageSlotFor(itemCount)
        : null;
    return _weave(
      itemCount: itemCount,
      itemBuilder: itemBuilder,
      slots: slot == null ? const <int>{} : {slot},
      adBuilder: (position) => AbsNativeAdCard(
        key: ValueKey('short-native-$placement'),
        surface: surface,
        debugLabel: placement,
        templateType: TemplateType.small,
      ),
    );
  }

  static List<Widget> _weave({
    required int itemCount,
    required Widget Function(int index) itemBuilder,
    required Set<int> slots,
    required Widget Function(int position) adBuilder,
  }) {
    final widgets = <Widget>[];
    for (var i = 0; i < itemCount; i++) {
      widgets.add(itemBuilder(i));
      // A slot value of n means "after the nth genuine item", so the reader
      // always meets real content before any ad.
      if (slots.contains(i + 1)) widgets.add(adBuilder(i + 1));
    }
    return widgets;
  }
}

/// A per-visit seed so ad positions vary between sessions but never move
/// while the user is looking at the list.
int newFeedAdSeed() => DateTime.now().microsecondsSinceEpoch & 0xFFFF;
