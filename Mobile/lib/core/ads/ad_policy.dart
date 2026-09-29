/// Where an inline banner may appear. Anything not listed here never shows a
/// banner (Free Signal, Account, auth, payment/USDT, security and trade flows).
enum BannerPlacement { home, pulse, news, newsDetail, signalDetail }

/// Natural transitions that may be followed by an interstitial.
enum InterstitialMoment { newsDetailClosed, signalDetailClosed }

/// Full-screen formats that share one presentation guard.
enum FullScreenKind { interstitial, rewarded, appOpen }

/// Who is looking at the app. Kept separate from [AppSession] so the policy
/// stays pure and can exempt plans later without touching any screen.
enum AdAudience { guest, member, pulseSubscriber }

class AdFrequencyCaps {
  const AdFrequencyCaps({
    this.minEligibleNavigations = 3,
    this.minInterval = const Duration(seconds: 120),
    this.maxPerSession = 3,
    this.appOpenMinInterval = const Duration(minutes: 30),
    this.fullScreenGap = const Duration(seconds: 60),
  });

  /// Meaningful eligible navigations required since the last full-screen ad.
  final int minEligibleNavigations;

  /// Minimum gap between interstitials. The session start counts as the
  /// previous one, so nothing can appear right after launch.
  final Duration minInterval;

  /// Interstitials per app session.
  final int maxPerSession;

  /// At most one App Open impression in this window (persisted across
  /// launches). Cached-ad staleness (4h) is a separate check in AdService.
  final Duration appOpenMinInterval;

  /// No App Open ad this soon after any other full-screen ad closed.
  final Duration fullScreenGap;
}

/// The one place that decides whether Pulse may show an ad. Holds all
/// frequency state; [AdService] only persists and restores parts of it.
class AdPolicy {
  AdPolicy({
    this.caps = const AdFrequencyCaps(),
    this.adsEnabled = true,
    this.exemptAudiences = const <AdAudience>{},
    DateTime Function()? clock,
  }) : _clock = clock ?? DateTime.now {
    _sessionStart = _clock();
  }

  final AdFrequencyCaps caps;
  final bool adsEnabled;

  /// Audiences that never see ads (e.g. paid plans). Empty for this release:
  /// guests and members both see ads.
  final Set<AdAudience> exemptAudiences;

  final DateTime Function() _clock;

  late final DateTime _sessionStart;
  DateTime? _lastFullScreenEndedAt;
  DateTime? _lastAppOpenAt;
  int _navigationsSinceFullScreen = 0;
  int _interstitialsThisSession = 0;
  bool _fullScreenActive = false;

  int get navigationsSinceInterstitial => _navigationsSinceFullScreen;
  int get interstitialsThisSession => _interstitialsThisSession;
  bool get fullScreenActive => _fullScreenActive;
  DateTime? get lastAppOpenAt => _lastAppOpenAt;

  /// Restores the persisted time of the last App Open impression.
  void restore({DateTime? lastAppOpenAt}) => _lastAppOpenAt = lastAppOpenAt;

  bool _audienceAllowed(AdAudience audience) =>
      adsEnabled && !exemptAudiences.contains(audience);

  bool canShowBanner(AdAudience audience, BannerPlacement placement) =>
      _audienceAllowed(audience);

  bool canShowRewarded(AdAudience audience) => adsEnabled && !_fullScreenActive;

  /// Records a meaningful eligible navigation (content detail closed).
  void recordEligibleNavigation() => _navigationsSinceFullScreen++;

  bool canShowInterstitial(AdAudience audience, InterstitialMoment moment) {
    if (!_audienceAllowed(audience) || _fullScreenActive) return false;
    if (_interstitialsThisSession >= caps.maxPerSession) return false;
    if (_navigationsSinceFullScreen < caps.minEligibleNavigations) {
      return false;
    }
    final since = _lastFullScreenEndedAt ?? _sessionStart;
    return _clock().difference(since) >= caps.minInterval;
  }

  /// Cold launches (including the first) are eligible unless an App Open
  /// impression happened in the last 30 minutes or another full-screen ad is
  /// showing / just closed.
  bool canShowAppOpen(AdAudience audience) {
    if (!_audienceAllowed(audience) || _fullScreenActive) return false;
    final now = _clock();
    final lastAppOpen = _lastAppOpenAt;
    if (lastAppOpen != null &&
        now.difference(lastAppOpen) < caps.appOpenMinInterval) {
      return false;
    }
    final lastEnded = _lastFullScreenEndedAt;
    return lastEnded == null || now.difference(lastEnded) >= caps.fullScreenGap;
  }

  void markFullScreenShowing(FullScreenKind kind) {
    _fullScreenActive = true;
    // Counted when shown so App Open can never repeat inside 30 minutes.
    if (kind == FullScreenKind.appOpen) _lastAppOpenAt = _clock();
  }

  /// Called when any full-screen ad closes, so another full-screen ad can
  /// never follow straight after it.
  void markFullScreenDismissed(FullScreenKind kind) {
    _fullScreenActive = false;
    _lastFullScreenEndedAt = _clock();
    _navigationsSinceFullScreen = 0;
    if (kind == FullScreenKind.interstitial) _interstitialsThisSession++;
  }

  /// A full-screen ad that failed to show consumes no cap.
  void markFullScreenFailed(FullScreenKind kind,
      {DateTime? previousAppOpenAt}) {
    _fullScreenActive = false;
    if (kind == FullScreenKind.appOpen) _lastAppOpenAt = previousAppOpenAt;
  }

  /// Positions (number of feed items before the slot) for in-feed banners.
  ///
  /// The first slot follows [first] items; later slots are [spacing] items
  /// apart and only appear when at least two items follow them, so a second
  /// banner exists only on genuinely long feeds. With [trailingWhenShort],
  /// a feed shorter than [first] gets one slot after its last item.
  static List<int> feedBannerPositions(
    int itemCount, {
    required int first,
    required int spacing,
    int maxSlots = 2,
    bool trailingWhenShort = false,
  }) {
    if (maxSlots <= 0) return const <int>[];
    if (itemCount < first) {
      return trailingWhenShort ? <int>[itemCount] : const <int>[];
    }
    final positions = <int>[first];
    var next = first + spacing;
    while (positions.length < maxSlots && next <= itemCount - 2) {
      positions.add(next);
      next += spacing;
    }
    return positions;
  }
}
