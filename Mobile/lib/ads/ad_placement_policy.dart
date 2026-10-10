import 'dart:math';

/// Every ABS surface that is allowed to carry monetization.
///
/// A screen that is not listed here can never show an ad. Payment, auth,
/// wallet, settings, execution-sensitive and error surfaces are deliberately
/// absent and must stay absent.
enum AdSurface {
  /// Free/basic logged-in home dashboard.
  homeDashboard,

  /// Guest public market pulse landing page.
  guestPulse,

  /// Published ABS news feed.
  newsFeed,

  /// External live headline wire.
  liveNewsFeed,

  /// Macro economic calendar.
  economicCalendar,

  /// Public market overview / movers list.
  marketOverview,

  /// Authenticated live market price list (Markets tab).
  marketPrices,

  /// Guest market discovery list.
  exploreMarkets,

  /// Published research list.
  research,

  /// Learning library list.
  learning,

  /// ABS products and services list.
  services,

  /// Saved-markets browsing list.
  watchlist,

  /// Explore ABS discovery hub.
  exploreHub,

  /// Global search results, once enough results exist to be worth scrolling.
  searchResults,

  /// Legal & Risk hub.
  legalHub,

  /// A published legal document being read.
  legalDocument,

  /// Passive, read-only market detail page.
  marketDetail,

  /// Passive Free Signal Market Watch fallback after a rewarded claim.
  marketWatchFallback,
}

/// How a surface is monetized.
///
/// One format per surface, chosen so the ad fits the page rather than the page
/// bending around the ad.
enum AdFormat {
  /// Compact Google native template inside an ABS card. Feels designed in.
  nativeSmall,

  /// Compact native repeated through a long feed, every 6-8 genuine items.
  nativeFeed,

  /// Height-capped inline adaptive banner inside scrolling content.
  inlineCompact,

  /// One height-capped banner placed in the lower-middle of a short page.
  inlineShortPage,
}

/// The single source of truth for ABS ad placement and density.
///
/// Screens never hardcode intervals, counts or cooldowns. Tuning monetization
/// pressure means editing this file only.
class AdPlacementPolicy {
  const AdPlacementPolicy._();

  // ---------------------------------------------------------------------
  // Native placements
  // ---------------------------------------------------------------------

  /// One premium native card inside the home/dashboard content flow.
  static const bool homeNativeEnabled = true;

  /// Real dashboard blocks (market snapshot rows, watchlist, signals) that must
  /// be populated before the home native slot is worth rendering. A dashboard
  /// that failed to load must never be mostly advertisement.
  static const int homeNativeMinimumContentBlocks = 2;

  /// The page-placement map: every monetizable ABS surface and its format.
  ///
  /// A surface absent from this map can never render an ad. Auth, payment,
  /// wallet, profile, execution and error surfaces are deliberately absent.
  /// Screens read their format from here instead of hardcoding a choice.
  static const Map<AdSurface, AdFormat> placements = {
    AdSurface.marketWatchFallback: AdFormat.inlineShortPage,
    AdSurface.guestPulse: AdFormat.nativeSmall,
    AdSurface.homeDashboard: AdFormat.nativeSmall,
    AdSurface.newsFeed: AdFormat.nativeFeed,
    AdSurface.liveNewsFeed: AdFormat.nativeFeed,
    AdSurface.research: AdFormat.nativeFeed,
    AdSurface.learning: AdFormat.nativeFeed,
    AdSurface.searchResults: AdFormat.nativeSmall,
    AdSurface.marketPrices: AdFormat.inlineCompact,
    AdSurface.marketOverview: AdFormat.inlineCompact,
    AdSurface.exploreMarkets: AdFormat.inlineCompact,
    AdSurface.economicCalendar: AdFormat.inlineCompact,
    AdSurface.services: AdFormat.inlineCompact,
    AdSurface.exploreHub: AdFormat.inlineShortPage,
    AdSurface.watchlist: AdFormat.inlineShortPage,
    AdSurface.legalHub: AdFormat.inlineShortPage,
    AdSurface.legalDocument: AdFormat.inlineShortPage,
    AdSurface.marketDetail: AdFormat.inlineShortPage,
  };

  static AdFormat? formatFor(AdSurface surface) => placements[surface];

  /// Surfaces allowed to render a native card.
  static Set<AdSurface> get nativeSurfaces => {
    for (final entry in placements.entries)
      if (entry.value == AdFormat.nativeSmall ||
          entry.value == AdFormat.nativeFeed)
        entry.key,
  };

  /// Genuine news items that must be read before the first native slot.
  static const int newsNativeFirstSlotAfter = 6;

  /// Repeat window for in-feed native ads. Randomised inside this range so the
  /// cadence is never perfectly predictable.
  static const int newsNativeIntervalMinimum = 6;
  static const int newsNativeIntervalMaximum = 8;

  /// Hard ceiling on native slots in one feed, independent of feed length.
  static const int newsNativeMaximumSlots = 4;

  /// Concurrent native ad objects allowed in the whole app. Protects memory
  /// and avoids excessive simultaneous preloading.
  static const int nativeConcurrentLoadCap = 3;

  /// Google's published minimum width for both native templates is 320dp.
  /// Rendering a template narrower than this can clip the headline, the CTA
  /// or the mandatory attribution, so a slot that cannot reach this width is
  /// collapsed instead of rendered badly.
  static const double nativeTemplateMinimumWidth = 320;

  /// Google's published template height bands: small 90..200, medium 320..400.
  ///
  /// The small template is pinned to the TOP of its band, not the bottom.
  /// AdMob's native validator requires a MediaView of at least 120x120 points
  /// whenever the creative can serve video; at 116dp the whole card was under
  /// that floor and the validator reported "MediaView is too small for video".
  /// 200dp clears it while staying well under the 336dp medium template that
  /// dominated ABS content.
  static const double nativeSmallTemplateHeight = 200;
  static const double nativeMediumTemplateHeight = 336;

  /// AdMob's minimum MediaView edge, in logical pixels, for video creatives.
  static const double nativeMediaViewMinimumEdge = 120;

  /// Vertical clearance above and below a native card. Wider than the 8dp gap
  /// between ABS content cards, so the ad always reads as its own block and
  /// never sits flush against a tappable ABS row.
  static const double nativeCardVerticalClearance = 14;

  // ---------------------------------------------------------------------
  // Inline adaptive banners
  // ---------------------------------------------------------------------

  static const bool calendarInlineBannerEnabled = true;

  /// Surfaces allowed to render inline adaptive banners inside scroll content.
  static Set<AdSurface> get inlineBannerSurfaces => {
    for (final entry in placements.entries)
      if (entry.value == AdFormat.inlineCompact ||
          entry.value == AdFormat.inlineShortPage)
        entry.key,
  };

  /// A user must see at least this many real rows before any inline banner.
  static const int minimumContentBeforeInlineAd = 5;

  /// Real rows required between two inline banners on the same screen.
  static const int inlineBannerRepeatInterval = 10;

  /// Inline banners per screen. Keeps long feeds from becoming ad farms.
  static const int inlineBannerMaximumPerScreen = 2;

  /// Calendar events required before a calendar banner is worth showing.
  static const int calendarMinimumEventsForBanner = 6;

  /// Rows a short informational page needs before it earns its single banner.
  static const int minimumContentForShortPageAd = 4;

  /// Search results needed before results are worth monetizing. A thin result
  /// set must never read as "nothing here except an ad".
  static const int minimumSearchResultsForAd = 5;

  /// Maximum inline adaptive banner height, in logical pixels.
  ///
  /// Google's inline adaptive format accepts a max height and returns a real
  /// size at or below it. Capping here is what stops a banner from becoming
  /// half the screen; the rendered container always follows the size the
  /// platform actually returned, never this number.
  static const int inlineBannerMaximumHeight = 100;

  /// Floor below which a returned banner is not worth a section of its own.
  static const int inlineBannerMinimumHeight = 32;

  /// Vertical clearance above and below every inline banner.
  ///
  /// This is the primary accidental-click protection: ABS list rows are
  /// tappable, so the Google creative never sits against one. Tuning ad
  /// separation app-wide means changing this single value.
  static const double inlineBannerVerticalClearance = 18;

  // ---------------------------------------------------------------------
  // Interstitials
  // ---------------------------------------------------------------------

  /// Published content kinds whose "finished reading, returned to the list"
  /// moment counts as an eligible interstitial interaction. Passive reading
  /// only: no signal, scan, position, payment or execution flow appears here.
  static const Set<String> interstitialEligibleReadKinds = {
    'news',
    'research',
    'learning',
  };

  /// Minimum eligible content completions before an interstitial is possible.
  static const int interstitialMinimumEligibleActions = 4;

  /// Upper bound of the randomised trigger window.
  static const int interstitialMaximumEligibleActions = 6;

  /// Randomised time window that makes the NEXT interstitial *eligible*.
  ///
  /// Eligibility is not a trigger: when the window elapses nothing is shown
  /// until the user reaches a genuine natural transition. A fresh interval is
  /// drawn after every interstitial so the cadence is never predictable.
  static const Duration interstitialEligibilityMinimum = Duration(minutes: 10);
  static const Duration interstitialEligibilityMaximum = Duration(minutes: 20);

  /// Draws the next eligibility gap, uniformly across the 10-20 minute window.
  static Duration nextInterstitialEligibilityGap(Random random) {
    final min = interstitialEligibilityMinimum.inSeconds;
    final max = interstitialEligibilityMaximum.inSeconds;
    return Duration(seconds: min + random.nextInt(max - min + 1));
  }

  static const Duration interstitialCooldown = Duration(minutes: 4);
  static const int interstitialSessionCap = 2;

  /// An interstitial never interrupts the opening moments of a session.
  static const Duration interstitialMinimumSessionAge = Duration(seconds: 90);

  // ---------------------------------------------------------------------
  // App open
  // ---------------------------------------------------------------------

  /// A brand-new user sees no App Open ad until this many genuine sessions.
  static const int appOpenFirstEligibleSession = 3;

  static const Duration appOpenCooldown = Duration(minutes: 30);
  static const int appOpenSessionCap = 1;

  /// A brief app switch is not a meaningful foreground return.
  static const Duration appOpenMinimumBackgroundTime = Duration(minutes: 1);

  /// Google expires App Open ads after four hours.
  static const Duration appOpenMaximumAge = Duration(hours: 4);

  // ---------------------------------------------------------------------
  // Full-screen coordination
  // ---------------------------------------------------------------------

  /// Quiet window after any full-screen ad before another may show.
  static const Duration postFullScreenSuppression = Duration(minutes: 2);

  /// Long quiet window after a rewarded unlock. The user paid attention once;
  /// ABS does not immediately charge them again.
  static const Duration postRewardSuppression = Duration(minutes: 10);

  /// Suppression after returning from a browser, payment, share or picker.
  static const Duration externalFlowSuppression = Duration(minutes: 10);

  /// An external return is never a natural ABS content transition, so the
  /// quiet window applies to interstitials as well as to App Open.
  static const bool externalFlowSuppressesInterstitial = true;

  // ---------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------

  static bool allowsNative(AdSurface surface) =>
      nativeSurfaces.contains(surface) &&
      (surface != AdSurface.homeDashboard || homeNativeEnabled);

  static bool allowsInlineBanner(AdSurface surface) =>
      inlineBannerSurfaces.contains(surface) &&
      (surface != AdSurface.economicCalendar || calendarInlineBannerEnabled);

  /// Index positions of native slots for a feed of [itemCount] real items.
  ///
  /// Returned values are insertion points counted in real items: a value of 6
  /// means "after the sixth genuine item". Slots are never adjacent and never
  /// precede useful content. [seed] keeps the layout stable across rebuilds
  /// while still varying between feeds.
  static List<int> nativeSlotsFor(int itemCount, {int seed = 0}) {
    if (itemCount <= newsNativeFirstSlotAfter) return const [];
    final random = Random(seed);
    final slots = <int>[];
    var cursor = newsNativeFirstSlotAfter;
    while (cursor < itemCount && slots.length < newsNativeMaximumSlots) {
      slots.add(cursor);
      final span =
          newsNativeIntervalMinimum +
          random.nextInt(
            newsNativeIntervalMaximum - newsNativeIntervalMinimum + 1,
          );
      cursor += span;
    }
    return slots;
  }

  /// The single slot index for a short informational page, or null when the
  /// page is too thin to carry an ad at all.
  ///
  /// The banner lands in the lower-middle of real content: never first, never
  /// jammed against the end of the page and the navigation bar beneath it.
  static int? shortPageSlotFor(int itemCount) {
    if (itemCount < minimumContentForShortPageAd) return null;
    final slot = (itemCount * 0.6).round();
    return slot.clamp(minimumContentForShortPageAd - 1, itemCount - 1);
  }

  /// Index positions of inline banner slots for a list of [itemCount] rows.
  static List<int> inlineBannerSlotsFor(int itemCount) {
    if (itemCount < minimumContentBeforeInlineAd) return const [];
    final slots = <int>[];
    var cursor = minimumContentBeforeInlineAd;
    while (cursor < itemCount && slots.length < inlineBannerMaximumPerScreen) {
      slots.add(cursor);
      cursor += inlineBannerRepeatInterval;
    }
    return slots;
  }

  /// Randomised interstitial threshold for one trigger cycle. Never below the
  /// conservative minimum, never perfectly predictable.
  static int nextInterstitialThreshold(Random random) =>
      interstitialMinimumEligibleActions +
      random.nextInt(
        interstitialMaximumEligibleActions -
            interstitialMinimumEligibleActions +
            1,
      );
}
