import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
// `show` keeps google_mobile_ads' own AppState out of files that use ABS AppState.
import 'package:google_mobile_ads/google_mobile_ads.dart'
    show
        AdRequest,
        AppOpenAd,
        AppOpenAdLoadCallback,
        ConsentForm,
        ConsentInformation,
        ConsentRequestParameters,
        FullScreenContentCallback,
        InterstitialAd,
        InterstitialAdLoadCallback,
        MobileAds;

import '../session.dart';
import 'ad_config.dart';
import 'ad_policy.dart';

/// Consent/SDK state. Banners reserve their space while [pending] and
/// collapse if ads turn out to be [unavailable].
enum AdReadiness { pending, ready, unavailable }

/// Central Pulse ad runtime: consent, SDK start-up, and the preload,
/// lifecycle and presentation of every full-screen format except the
/// screen-owned Rewarded Free Signal flow. Every decision is delegated to
/// [policy]; screens never call the ads SDK for full-screen ads directly.
class AdService {
  AdService._();

  static final AdService instance = AdService._();

  /// AdMob expires interstitials after 1h and App Open ads after 4h.
  static const Duration _interstitialMaxAge = Duration(minutes: 55);
  static const Duration _appOpenMaxAge = Duration(hours: 3, minutes: 55);

  /// App Open belongs to the cold-launch splash/loading phase only. An ad
  /// that is not ready within this budget is skipped; startup never waits.
  static const Duration _launchWindow = Duration(seconds: 8);

  /// Shell tabs (Home, Pulse, News) that may still be in first load.
  static const Set<int> _appOpenTabs = {0, 1, 3};

  static const String _lastAppOpenKey = 'abs_ads_last_app_open';

  final AdPolicy policy = AdPolicy(adsEnabled: AdConfig.adsEnabled);

  final ValueNotifier<AdReadiness> readiness =
      ValueNotifier<AdReadiness>(AdReadiness.pending);

  /// Attached to MaterialApp so the service can tell whether anything is
  /// pushed over the Shell (detail, auth, payment, sheets, dialogs).
  final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

  AppSession? _session;
  bool _started = false;
  bool _sdkReady = false;
  DateTime? _launchedAt;
  int? _shellTab;
  bool _userNavigated = false;

  InterstitialAd? _interstitial;
  DateTime? _interstitialLoadedAt;
  bool _interstitialLoading = false;

  AppOpenAd? _appOpen;
  DateTime? _appOpenLoadedAt;
  bool _appOpenLoading = false;

  bool get supported =>
      !kIsWeb &&
      (defaultTargetPlatform == TargetPlatform.android ||
          defaultTargetPlatform == TargetPlatform.iOS);

  AdAudience audienceOf(AppSession? session) {
    if (session == null || !session.authenticated) return AdAudience.guest;
    return session.hasPulseAccess
        ? AdAudience.pulseSubscriber
        : AdAudience.member;
  }

  AdAudience get audience => audienceOf(_session);

  /// Whether a banner may be shown (or its space reserved) at [placement].
  bool canShowBanner(AppSession? session, BannerPlacement placement) =>
      supported &&
      readiness.value != AdReadiness.unavailable &&
      AdConfig.isConfigured(AdFormat.banner) &&
      policy.canShowBanner(audienceOf(session), placement);

  /// Restores persisted frequency state, gathers UMP consent where required,
  /// then starts the SDK and preloads full-screen ads. Never blocks app
  /// start-up and never throws.
  void initialize(AppSession session) {
    _session = session;
    if (_started) return;
    _started = true;
    if (!supported || !AdConfig.adsEnabled) {
      readiness.value = AdReadiness.unavailable;
      return;
    }
    _launchedAt = DateTime.now();
    unawaited(_restoreState(session));
    ConsentInformation.instance.requestConsentInfoUpdate(
      ConsentRequestParameters(),
      () => ConsentForm.loadAndShowConsentFormIfRequired((_) => _startSdk()),
      (_) => _startSdk(),
    );
  }

  Future<void> _restoreState(AppSession session) async {
    try {
      policy.restore(
        lastAppOpenAt: DateTime.tryParse(
          await session.storage.read(key: _lastAppOpenKey) ?? '',
        ),
      );
    } catch (_) {
      // Unreadable state simply means no recent impression is known.
    }
  }

  Future<void> _startSdk() async {
    if (_sdkReady) return;
    try {
      if (!await ConsentInformation.instance.canRequestAds()) {
        readiness.value = AdReadiness.unavailable;
        return;
      }
      await MobileAds.instance.initialize();
      _sdkReady = true;
      readiness.value = AdReadiness.ready;
      // App Open first: it only has the short launch window to be useful.
      _preloadAppOpen();
      _preloadInterstitial();
    } catch (_) {
      // Ads are supplementary; Pulse must keep working without them.
      readiness.value = AdReadiness.unavailable;
    }
  }

  // ---------------------------------------------------------------------
  // Shell / lifecycle signals
  // ---------------------------------------------------------------------

  /// Reported by the Shell whenever its visible tab changes. The first
  /// report is the Shell appearing; any later one is the user navigating,
  /// which ends the launch phase.
  void setShellTab(int index) {
    if (_shellTab != null && _shellTab != index) _userNavigated = true;
    _shellTab = index;
    if (_inLaunchPhase) _tryShowAppOpen();
  }

  /// The cold-launch splash/loading phase: shortly after launch and before
  /// the user has started navigating.
  bool get _inLaunchPhase {
    final launchedAt = _launchedAt;
    return launchedAt != null &&
        !_userNavigated &&
        DateTime.now().difference(launchedAt) <= _launchWindow;
  }

  /// During launch the app shows either the splash (session restoring) or a
  /// root Home/Pulse/News tab still loading. Never auth, payment, security,
  /// trade, Free Signal, Account, maintenance screens or an open sheet.
  bool get _appOpenSurfaceEligible {
    final navigator = navigatorKey.currentState;
    if (navigator == null || navigator.canPop()) return false;
    if (_session?.initializing ?? false) return true;
    final tab = _shellTab;
    return tab != null && _appOpenTabs.contains(tab);
  }

  // ---------------------------------------------------------------------
  // Interstitial
  // ---------------------------------------------------------------------

  void _preloadInterstitial() {
    if (!_sdkReady || _interstitialLoading || _interstitial != null) return;
    if (!AdConfig.isConfigured(AdFormat.interstitial)) return;
    _interstitialLoading = true;
    InterstitialAd.load(
      adUnitId: AdConfig.interstitialUnitId,
      request: const AdRequest(),
      adLoadCallback: InterstitialAdLoadCallback(
        onAdLoaded: (ad) {
          _interstitialLoading = false;
          _interstitial = ad;
          _interstitialLoadedAt = DateTime.now();
        },
        onAdFailedToLoad: (_) {
          _interstitialLoading = false;
          // Retry quietly later rather than hammering the network.
          Timer(const Duration(seconds: 60), _preloadInterstitial);
        },
      ),
    );
  }

  /// Call after the user leaves a content-detail screen. Returns immediately;
  /// if no interstitial is ready or allowed, nothing happens.
  void onContentDetailClosed(InterstitialMoment moment) {
    if (!supported) return;
    policy.recordEligibleNavigation();
    final ad = _interstitial;
    if (ad == null) {
      _preloadInterstitial();
      return;
    }
    final loadedAt = _interstitialLoadedAt;
    if (loadedAt != null &&
        DateTime.now().difference(loadedAt) > _interstitialMaxAge) {
      ad.dispose();
      _interstitial = null;
      _preloadInterstitial();
      return;
    }
    if (WidgetsBinding.instance.lifecycleState != AppLifecycleState.resumed) {
      return;
    }
    if (!policy.canShowInterstitial(audience, moment)) return;

    _interstitial = null;
    policy.markFullScreenShowing(FullScreenKind.interstitial);
    ad.fullScreenContentCallback = FullScreenContentCallback(
      onAdDismissedFullScreenContent: (ad) {
        ad.dispose();
        policy.markFullScreenDismissed(FullScreenKind.interstitial);
        _preloadInterstitial();
      },
      onAdFailedToShowFullScreenContent: (ad, _) {
        ad.dispose();
        policy.markFullScreenFailed(FullScreenKind.interstitial);
        _preloadInterstitial();
      },
    );
    ad.show();
  }

  // ---------------------------------------------------------------------
  // App Open
  // ---------------------------------------------------------------------

  void _preloadAppOpen() {
    if (!_sdkReady || _appOpenLoading || _appOpen != null) return;
    if (!AdConfig.isConfigured(AdFormat.appOpen)) return;
    _appOpenLoading = true;
    AppOpenAd.load(
      adUnitId: AdConfig.appOpenUnitId,
      request: const AdRequest(),
      adLoadCallback: AppOpenAdLoadCallback(
        onAdLoaded: (ad) {
          _appOpenLoading = false;
          _appOpen = ad;
          _appOpenLoadedAt = DateTime.now();
          // Show only if it arrived during the launch phase; otherwise it
          // stays cached (max 4h) for the next cold launch.
          if (_inLaunchPhase) _tryShowAppOpen();
        },
        onAdFailedToLoad: (_) {
          _appOpenLoading = false;
          Timer(const Duration(minutes: 2), _preloadAppOpen);
        },
      ),
    );
  }

  void _tryShowAppOpen() {
    final ad = _appOpen;
    if (ad == null) {
      _preloadAppOpen();
      return;
    }
    final loadedAt = _appOpenLoadedAt;
    if (loadedAt != null &&
        DateTime.now().difference(loadedAt) > _appOpenMaxAge) {
      ad.dispose();
      _appOpen = null;
      _preloadAppOpen();
      return;
    }
    if (WidgetsBinding.instance.lifecycleState != AppLifecycleState.resumed) {
      return;
    }
    if (!_appOpenSurfaceEligible || !policy.canShowAppOpen(audience)) return;

    _appOpen = null;
    final previousAppOpenAt = policy.lastAppOpenAt;
    policy.markFullScreenShowing(FullScreenKind.appOpen);
    unawaited(_persistLastAppOpen());
    ad.fullScreenContentCallback = FullScreenContentCallback(
      onAdDismissedFullScreenContent: (ad) {
        ad.dispose();
        policy.markFullScreenDismissed(FullScreenKind.appOpen);
        _preloadAppOpen();
      },
      onAdFailedToShowFullScreenContent: (ad, _) {
        ad.dispose();
        policy.markFullScreenFailed(
          FullScreenKind.appOpen,
          previousAppOpenAt: previousAppOpenAt,
        );
        unawaited(_persistLastAppOpen());
        _preloadAppOpen();
      },
    );
    ad.show();
  }

  Future<void> _persistLastAppOpen() async {
    try {
      final storage = _session?.storage;
      if (storage == null) return;
      final last = policy.lastAppOpenAt;
      if (last == null) {
        await storage.delete(key: _lastAppOpenKey);
      } else {
        await storage.write(
            key: _lastAppOpenKey, value: last.toIso8601String());
      }
    } catch (_) {}
  }

  // ---------------------------------------------------------------------
  // Rewarded (screen-owned flow; these hooks keep the shared guard exact)
  // ---------------------------------------------------------------------

  /// Rewarded ads keep their own Free Signal flow; these hooks only keep the
  /// shared full-screen guard accurate so no other full-screen ad stacks on,
  /// or immediately follows, a rewarded ad.
  void rewardedShowing() =>
      policy.markFullScreenShowing(FullScreenKind.rewarded);

  void rewardedDismissed() =>
      policy.markFullScreenDismissed(FullScreenKind.rewarded);

  void rewardedFailed() => policy.markFullScreenFailed(FullScreenKind.rewarded);
}
