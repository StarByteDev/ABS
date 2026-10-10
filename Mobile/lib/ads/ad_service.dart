import 'dart:async';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart';

import '../core/safe_storage.dart';
import '../core/session.dart';
import 'ad_config.dart';
import 'ad_placement_policy.dart';

enum RewardedAdOutcome { earned, closedEarly, unavailable, busy }

class RewardedAdResult {
  const RewardedAdResult._(this.outcome, {this.rewardType, this.rewardAmount});

  const RewardedAdResult.earned(String type, num amount)
    : this._(RewardedAdOutcome.earned, rewardType: type, rewardAmount: amount);

  const RewardedAdResult.closedEarly() : this._(RewardedAdOutcome.closedEarly);
  const RewardedAdResult.unavailable() : this._(RewardedAdOutcome.unavailable);
  const RewardedAdResult.busy() : this._(RewardedAdOutcome.busy);

  final RewardedAdOutcome outcome;
  final String? rewardType;
  final num? rewardAmount;

  bool get earned => outcome == RewardedAdOutcome.earned;
}

/// Owns consent, eligibility, loading and all full-screen ad coordination.
class AdService extends ChangeNotifier with WidgetsBindingObserver {
  AdService._();

  static final AdService instance = AdService._();

  static const _launchCountKey = 'abs_ads_launch_count';
  static const _lastAppOpenKey = 'abs_ads_last_app_open_at';

  final DateTime _sessionStartedAt = DateTime.now();
  final Random _random = Random();
  AppSession? _session;
  int _nativeSlotsInFlight = 0;
  late int _interstitialThreshold = AdPlacementPolicy.nextInterstitialThreshold(
    _random,
  );
  bool _initializationStarted = false;
  bool _mobileAdsInitialized = false;
  bool _canRequestAds = false;
  bool _privacyOptionsRequired = false;
  bool _isFullScreenAdShowing = false;
  bool _holdSplashForAppOpen = false;
  bool _contentInteractive = false;
  int _launchCount = 0;
  int _eligibleInteractions = 0;
  int _interstitialsThisSession = 0;
  int _appOpenAdsThisSession = 0;
  DateTime? _backgroundedAt;
  DateTime? _lastFullScreenAt;
  DateTime? _lastInterstitialAt;
  late DateTime _nextInterstitialEligibleAt = _sessionStartedAt.add(
    AdPlacementPolicy.nextInterstitialEligibilityGap(_random),
  );
  DateTime? _lastAppOpenAt;
  DateTime? _lastRewardedAt;
  DateTime? _appOpenSuppressedUntil;
  DateTime? _externalFlowUntil;
  RewardedAd? _rewardedAd;
  InterstitialAd? _interstitialAd;
  AppOpenAd? _appOpenAd;
  DateTime? _appOpenLoadedAt;
  Completer<bool>? _rewardedLoad;
  Completer<bool>? _interstitialLoad;
  Completer<bool>? _appOpenLoad;

  bool get adsInitialized => _mobileAdsInitialized;
  bool get canRequestAds => _canRequestAds;
  bool get privacyOptionsRequired => _privacyOptionsRequired;
  bool get isFullScreenAdShowing => _isFullScreenAdShowing;
  bool get holdSplashForAppOpen => _holdSplashForAppOpen;
  int get launchCount => _launchCount;

  /// When the next interstitial becomes *eligible*. Reaching it shows nothing
  /// on its own: an ad is only presented at the next natural transition.
  DateTime get nextInterstitialEligibleAt => _nextInterstitialEligibleAt;
  DateTime? get lastInterstitialShownAt => _lastInterstitialAt;

  /// True once the randomised 10-20 minute window has elapsed.
  bool get interstitialWindowElapsed =>
      !DateTime.now().isBefore(_nextInterstitialEligibleAt);

  /// Native ads are an additive surface: they require their own unit but must
  /// never be blocked by an unrelated missing format.
  bool get canShowNativeAds =>
      canShowMonetizationAds && AdConfig.nativeAdUnitId != null;

  /// Caps concurrent native ad objects so a long feed never preloads more
  /// platform views than the visible area can justify.
  bool reserveNativeSlot() {
    if (!canShowNativeAds) return false;
    if (_nativeSlotsInFlight >= AdPlacementPolicy.nativeConcurrentLoadCap) {
      _log('native skipped: concurrent load cap');
      return false;
    }
    _nativeSlotsInFlight++;
    return true;
  }

  void releaseNativeSlot() {
    if (_nativeSlotsInFlight > 0) _nativeSlotsInFlight--;
  }

  /// True once membership has resolved to an active paid subscription.
  ///
  /// `AppSession.canShowMonetizationAds` is false both for a paid member and
  /// while membership is still loading, so those two cases are separated here:
  /// a resolved paid member is a permanent suppression, an unresolved session
  /// is a temporary one. Only the logging needs the difference, but logging
  /// that claims "paid member" for an unresolved session is actively
  /// misleading while tuning monetization.
  bool get _membershipResolvedPaid {
    final session = _session;
    return session != null &&
        !session.initializing &&
        session.authenticated &&
        session.accessResolved &&
        session.hasPulseAccess;
  }

  /// Membership has not answered yet, so ABS stays conservative and shows
  /// nothing rather than flashing an ad that a paid user should never see.
  bool get _membershipUnresolved =>
      !(_session?.canShowMonetizationAds ?? false) && !_membershipResolvedPaid;

  String get _suppressionReason => _membershipResolvedPaid
      ? 'paid member'
      : _membershipUnresolved
      ? 'subscription state unresolved'
      : 'consent or platform';

  /// Development-only override.
  ///
  /// This NEVER affects release builds. It lets us visually verify Google's
  /// official TEST ads even when the local account is paid or membership
  /// state is still resolving.
  bool get _debugTestAds => kDebugMode && AdConfig.isTest;

  bool get canShowMonetizationAds =>
      AdConfig.isSupported &&
      AdConfig.hasCompleteAdUnitConfiguration &&
      _mobileAdsInitialized &&
      _canRequestAds &&
      (_debugTestAds || (_session?.canShowMonetizationAds ?? false));

  Future<void> initialize(AppSession session) async {
    attachSession(session);
    if (_initializationStarted) return;
    _initializationStarted = true;
    WidgetsBinding.instance.addObserver(this);
    await _readLaunchState();
    if (!AdConfig.isSupported) {
      _log('ads disabled: unsupported platform');
      notifyListeners();
      return;
    }

    _log('environment: ${AdConfig.isTest ? 'TEST' : 'PRODUCTION'}');
    if (!AdConfig.hasCompleteAdUnitConfiguration) {
      _log('ads disabled: incomplete central ad-unit configuration');
      notifyListeners();
      return;
    }
    final completion = Completer<void>();
    try {
      ConsentInformation.instance.requestConsentInfoUpdate(
        ConsentRequestParameters(),
        () async {
          try {
            await ConsentForm.loadAndShowConsentFormIfRequired((error) {
              if (error != null) {
                _log('consent form error: ${error.errorCode}');
              }
            });
            await _refreshConsentState();
          } finally {
            if (!completion.isCompleted) completion.complete();
          }
        },
        (error) async {
          _log('consent update error: ${error.errorCode}');
          try {
            // A prior valid UMP decision may still permit ad requests.
            await _refreshConsentState();
          } finally {
            if (!completion.isCompleted) completion.complete();
          }
        },
      );
      await completion.future;
    } catch (error) {
      _log('consent unavailable: ${error.runtimeType}');
      await _refreshConsentState();
    }
  }

  void attachSession(AppSession session) {
    if (identical(_session, session)) return;
    _session?.removeListener(_handleSessionChanged);
    _session = session;
    session.addListener(_handleSessionChanged);
  }

  Future<void> _readLaunchState() async {
    final storage = _session?.storage;
    if (storage == null) return;
    final storedCount = await SafeStorage.read(storage, key: _launchCountKey);
    _launchCount = (int.tryParse(storedCount ?? '') ?? 0) + 1;
    await SafeStorage.write(
      storage,
      key: _launchCountKey,
      value: '$_launchCount',
    );
    final storedLastAppOpen = await SafeStorage.read(
      storage,
      key: _lastAppOpenKey,
    );
    _lastAppOpenAt = DateTime.tryParse(storedLastAppOpen ?? '');
    _log('launch count: $_launchCount');
    notifyListeners();
  }

  Future<void> _refreshConsentState() async {
    if (!AdConfig.isSupported) return;
    try {
      _canRequestAds = await ConsentInformation.instance.canRequestAds();
      _privacyOptionsRequired =
          await ConsentInformation.instance
              .getPrivacyOptionsRequirementStatus() ==
          PrivacyOptionsRequirementStatus.required;
      _log(_canRequestAds ? 'consent ready' : 'consent blocks ad requests');
      if (_canRequestAds) {
        await _initializeMobileAdsOnce();
      } else {
        _disposeLoadedAds();
      }
    } catch (error) {
      _canRequestAds = false;
      _log('consent state error: ${error.runtimeType}');
    }
    notifyListeners();
  }

  Future<void> _initializeMobileAdsOnce() async {
    if (_mobileAdsInitialized) return;
    await MobileAds.instance.initialize();
    _mobileAdsInitialized = true;
    _log('mobile ads initialized');
    notifyListeners();

    // Full-screen formats should already be loaded BEFORE the natural
    // transition where they may be needed.
    unawaited(preloadRewarded());
    unawaited(preloadInterstitial());

    if (_debugTestAds || _launchCount >= AdConfig.appOpenMinimumLaunchCount) {
      unawaited(preloadAppOpen());
    } else {
      _log('app-open skipped: first sessions');
    }
  }

  Future<String?> showPrivacyOptions() async {
    if (!AdConfig.isSupported || !_privacyOptionsRequired) {
      return 'Privacy options are not required for this device right now.';
    }
    FormError? result;
    try {
      await ConsentForm.showPrivacyOptionsForm((error) => result = error);
      await _refreshConsentState();
    } catch (error) {
      _log('privacy options error: ${error.runtimeType}');
      return 'Privacy options are unavailable right now. Please try again.';
    }
    if (result != null) {
      _log('privacy options form error: ${result!.errorCode}');
      return 'Privacy options are unavailable right now. Please try again.';
    }
    return null;
  }

  Future<bool> preloadRewarded() async {
    if (!canShowMonetizationAds || AdConfig.rewardedAdUnitId == null) {
      return false;
    }
    if (_rewardedAd != null) return true;
    if (_rewardedLoad != null) return _rewardedLoad!.future;
    final completion = Completer<bool>();
    _rewardedLoad = completion;
    try {
      await RewardedAd.load(
        adUnitId: AdConfig.rewardedAdUnitId!,
        request: const AdRequest(),
        rewardedAdLoadCallback: RewardedAdLoadCallback(
          onAdLoaded: (ad) {
            if (!canShowMonetizationAds) {
              ad.dispose();
              _rewardedLoad = null;
              if (!completion.isCompleted) completion.complete(false);
              return;
            }
            _rewardedAd = ad;
            _rewardedLoad = null;
            _log('rewarded loaded');
            if (!completion.isCompleted) completion.complete(true);
          },
          onAdFailedToLoad: (error) {
            _rewardedLoad = null;
            _log('rewarded load failed: ${error.code}');
            if (!completion.isCompleted) completion.complete(false);
          },
        ),
      );
    } catch (error) {
      _rewardedLoad = null;
      _log('rewarded load error: ${error.runtimeType}');
      if (!completion.isCompleted) completion.complete(false);
    }
    return completion.future;
  }

  Future<RewardedAdResult> showRewarded() async {
    if (_isFullScreenAdShowing) {
      _log('rewarded skipped: full-screen ad active');
      return const RewardedAdResult.busy();
    }
    if (!canShowMonetizationAds) {
      _log('rewarded skipped: consent, platform or paid user');
      return const RewardedAdResult.unavailable();
    }
    if (!await preloadRewarded()) {
      return const RewardedAdResult.unavailable();
    }
    if (_isFullScreenAdShowing || !canShowMonetizationAds) {
      return const RewardedAdResult.busy();
    }
    final ad = _rewardedAd;
    _rewardedAd = null;
    if (ad == null) return const RewardedAdResult.unavailable();

    final completion = Completer<RewardedAdResult>();
    var rewardEarned = false;
    _beginFullScreen();
    ad.fullScreenContentCallback = FullScreenContentCallback<RewardedAd>(
      onAdShowedFullScreenContent: (_) => _log('rewarded shown'),
      onAdImpression: (_) => _log('rewarded impression'),
      onAdClicked: (_) => _log('rewarded clicked'),
      onAdDismissedFullScreenContent: (shownAd) {
        shownAd.dispose();
        _lastRewardedAt = DateTime.now();
        _finishFullScreen();
        _log('rewarded dismissed');
        if (!rewardEarned && !completion.isCompleted) {
          completion.complete(const RewardedAdResult.closedEarly());
        }
        unawaited(preloadRewarded());
      },
      onAdFailedToShowFullScreenContent: (failedAd, error) {
        failedAd.dispose();
        _finishFullScreen();
        _log('rewarded show failed: ${error.code}');
        if (!completion.isCompleted) {
          completion.complete(const RewardedAdResult.unavailable());
        }
      },
    );
    try {
      await ad.show(
        onUserEarnedReward: (_, reward) {
          rewardEarned = true;
          _lastRewardedAt = DateTime.now();
          _log('rewarded earned');
          if (!completion.isCompleted) {
            completion.complete(
              RewardedAdResult.earned(reward.type, reward.amount),
            );
          }
        },
      );
    } catch (error) {
      ad.dispose();
      _finishFullScreen();
      _log('rewarded show error: ${error.runtimeType}');
      if (!completion.isCompleted) {
        completion.complete(const RewardedAdResult.unavailable());
      }
    }
    return completion.future;
  }

  Future<bool> preloadInterstitial() async {
    if (!canShowMonetizationAds || AdConfig.interstitialAdUnitId == null) {
      return false;
    }
    if (_interstitialAd != null) return true;
    if (_interstitialLoad != null) return _interstitialLoad!.future;
    final completion = Completer<bool>();
    _interstitialLoad = completion;
    try {
      await InterstitialAd.load(
        adUnitId: AdConfig.interstitialAdUnitId!,
        request: const AdRequest(),
        adLoadCallback: InterstitialAdLoadCallback(
          onAdLoaded: (ad) {
            if (!canShowMonetizationAds) {
              ad.dispose();
              _interstitialLoad = null;
              if (!completion.isCompleted) completion.complete(false);
              return;
            }
            _interstitialAd = ad;
            _interstitialLoad = null;
            _log('interstitial loaded');
            if (!completion.isCompleted) completion.complete(true);
          },
          onAdFailedToLoad: (error) {
            _interstitialLoad = null;
            _log('interstitial load failed: ${error.code}');
            if (!completion.isCompleted) completion.complete(false);
          },
        ),
      );
    } catch (error) {
      _interstitialLoad = null;
      _log('interstitial load error: ${error.runtimeType}');
      if (!completion.isCompleted) completion.complete(false);
    }
    return completion.future;
  }

  Future<bool> recordEligibleContentCompletion() async {
    if (!canShowMonetizationAds) {
      _log('interstitial skipped: $_suppressionReason');
      return false;
    }
    _eligibleInteractions++;

    // In development TEST mode we still wait for a genuine ABS content
    // boundary, but we don't make the developer wait 10–20 minutes simply
    // to verify Google's test interstitial.
    if (_debugTestAds) {
      await preloadInterstitial();
      return _maybeShowInterstitial(debugForce: true);
    }

    unawaited(preloadInterstitial());

    // Two gates, and BOTH must be open.
    //
    //  1. The randomised 10-20 minute eligibility window has elapsed. This is
    //     the pacing control; reaching it alone shows nothing.
    //  2. The user has completed a handful of passive reads, so the very first
    //     transition after the window is still a natural one.
    //
    // This method is only ever called from a genuine content transition (a
    // reader finishing an article and returning to its list), never from a
    // tab switch, a Back press, a scroll or a form.
    final windowElapsed = interstitialWindowElapsed;
    _log(
      'eligible interaction: $_eligibleInteractions/$_interstitialThreshold, '
      '${windowElapsed ? 'window open' : 'window closed'}',
    );
    if (_eligibleInteractions < _interstitialThreshold) return false;
    if (!windowElapsed) return false;
    return _maybeShowInterstitial();
  }

  Future<bool> _maybeShowInterstitial({bool debugForce = false}) async {
    final now = DateTime.now();
    final forceTest = debugForce && _debugTestAds;
    if (!canShowMonetizationAds || _isFullScreenAdShowing) return false;
    if (_interstitialsThisSession >= AdConfig.interstitialSessionCap) {
      _log('interstitial skipped: session cap');
      return false;
    }
    if (!forceTest &&
        now.difference(_sessionStartedAt) <
            AdConfig.interstitialMinimumSessionAge) {
      _log('interstitial skipped: early session');
      return false;
    }
    // The 10-20 minute window must have elapsed. It only grants eligibility —
    // this method is reached solely from a genuine content transition.
    if (!forceTest && now.isBefore(_nextInterstitialEligibleAt)) {
      final remaining = _nextInterstitialEligibleAt.difference(now);
      _log('interstitial skipped: ${remaining.inMinutes}m until eligible');
      return false;
    }
    if (AdPlacementPolicy.externalFlowSuppressesInterstitial &&
        _inExternalFlowQuietPeriod) {
      _log('interstitial skipped: external return');
      return false;
    }
    if (!forceTest &&
        (!_elapsed(_lastInterstitialAt, AdConfig.interstitialCooldown) ||
            !_elapsed(_lastFullScreenAt, AdConfig.fullScreenQuietPeriod) ||
            !_elapsed(_lastRewardedAt, AdConfig.postRewardedQuietPeriod))) {
      _log('interstitial skipped: cooldown');
      return false;
    }
    final ad = _interstitialAd;
    _interstitialAd = null;
    if (ad == null) {
      _log('interstitial skipped: not ready at content boundary');
      return false;
    }

    final completion = Completer<bool>();
    _beginFullScreen();
    ad.fullScreenContentCallback = FullScreenContentCallback<InterstitialAd>(
      onAdShowedFullScreenContent: (_) {
        _interstitialsThisSession++;
        _eligibleInteractions = 0;
        _interstitialThreshold = AdPlacementPolicy.nextInterstitialThreshold(
          _random,
        );
        _lastInterstitialAt = DateTime.now();
        // A fresh random gap every time, so the cadence never becomes
        // predictable and never repeats the same interval.
        final gap = AdPlacementPolicy.nextInterstitialEligibilityGap(_random);
        _nextInterstitialEligibleAt = _lastInterstitialAt!.add(gap);
        _log('interstitial shown; next eligible in ${gap.inMinutes}m');
      },
      onAdImpression: (_) => _log('interstitial impression'),
      onAdClicked: (_) => _log('interstitial clicked'),
      onAdDismissedFullScreenContent: (shownAd) {
        shownAd.dispose();
        _finishFullScreen();
        if (!completion.isCompleted) completion.complete(true);
        unawaited(preloadInterstitial());
      },
      onAdFailedToShowFullScreenContent: (failedAd, error) {
        failedAd.dispose();
        _finishFullScreen();
        _log('interstitial show failed: ${error.code}');
        if (!completion.isCompleted) completion.complete(false);
      },
    );
    try {
      await ad.show();
    } catch (error) {
      ad.dispose();
      _finishFullScreen();
      _log('interstitial show error: ${error.runtimeType}');
      if (!completion.isCompleted) completion.complete(false);
    }
    return completion.future;
  }

  Future<bool> preloadAppOpen() async {
    if (!canShowMonetizationAds ||
        (!_debugTestAds && _launchCount < AdConfig.appOpenMinimumLaunchCount) ||
        AdConfig.appOpenAdUnitId == null) {
      return false;
    }
    if (_hasFreshAppOpenAd) return true;
    _discardExpiredAppOpen();
    if (_appOpenLoad != null) return _appOpenLoad!.future;
    final completion = Completer<bool>();
    _appOpenLoad = completion;
    try {
      await AppOpenAd.load(
        adUnitId: AdConfig.appOpenAdUnitId!,
        request: const AdRequest(),
        adLoadCallback: AppOpenAdLoadCallback(
          onAdLoaded: (ad) {
            if (!canShowMonetizationAds) {
              ad.dispose();
              _appOpenLoad = null;
              if (!completion.isCompleted) completion.complete(false);
              return;
            }
            _appOpenAd = ad;
            _appOpenLoadedAt = DateTime.now();
            _appOpenLoad = null;
            _log('app-open loaded');
            if (!completion.isCompleted) completion.complete(true);
          },
          onAdFailedToLoad: (error) {
            _appOpenLoad = null;
            _log('app-open load failed: ${error.code}');
            if (!completion.isCompleted) completion.complete(false);
          },
        ),
      );
    } catch (error) {
      _appOpenLoad = null;
      _log('app-open load error: ${error.runtimeType}');
      if (!completion.isCompleted) completion.complete(false);
    }
    return completion.future;
  }

  /// Called immediately when backend/session bootstrap completes. A cold-start
  /// ad is shown only if it is already loaded; a late load never covers active
  /// dashboard content.
  Future<void> onCoreContentReady() async {
    if (_contentInteractive) return;

    final eligibleForColdStart =
        canShowMonetizationAds &&
        (_debugTestAds || _launchCount >= AdConfig.appOpenMinimumLaunchCount);

    if (eligibleForColdStart) {
      // Keep the actual ABS loading screen visible while we give App Open
      // a SHORT bounded chance to become ready. Never show it late over an
      // already-interactive dashboard.
      _holdSplashForAppOpen = true;
      notifyListeners();

      if (!_hasFreshAppOpenAd) {
        try {
          await preloadAppOpen().timeout(
            _debugTestAds
                ? const Duration(seconds: 4)
                : const Duration(milliseconds: 1500),
            onTimeout: () => false,
          );
        } catch (_) {
          // App loading must never fail because an advertisement did.
        }
      }

      if (_hasFreshAppOpenAd && _canShowAppOpenNow()) {
        await _showAppOpen();
      } else {
        _log('app-open skipped: cold-start ad not ready or ineligible');
      }
    } else {
      _log('app-open skipped: cold-start ineligible');
    }

    _contentInteractive = true;
    _holdSplashForAppOpen = false;
    notifyListeners();
  }

  bool get _hasFreshAppOpenAd {
    final loaded = _appOpenLoadedAt;
    return _appOpenAd != null &&
        loaded != null &&
        DateTime.now().difference(loaded) < AdConfig.appOpenMaximumAge;
  }

  bool _canShowAppOpenNow() {
    final now = DateTime.now();
    return canShowMonetizationAds &&
        (_debugTestAds || _launchCount >= AdConfig.appOpenMinimumLaunchCount) &&
        _appOpenAdsThisSession < AdConfig.appOpenSessionCap &&
        !_isFullScreenAdShowing &&
        (_appOpenSuppressedUntil == null ||
            !now.isBefore(_appOpenSuppressedUntil!)) &&
        (_debugTestAds || _elapsed(_lastAppOpenAt, AdConfig.appOpenCooldown)) &&
        (_debugTestAds ||
            _elapsed(_lastFullScreenAt, AdConfig.fullScreenQuietPeriod)) &&
        (_debugTestAds ||
            _elapsed(_lastRewardedAt, AdConfig.postRewardedQuietPeriod));
  }

  Future<bool> _showAppOpen() async {
    if (!_hasFreshAppOpenAd || !_canShowAppOpenNow()) return false;
    final ad = _appOpenAd;
    _appOpenAd = null;
    _appOpenLoadedAt = null;
    if (ad == null) return false;
    final completion = Completer<bool>();
    _beginFullScreen();
    ad.fullScreenContentCallback = FullScreenContentCallback<AppOpenAd>(
      onAdShowedFullScreenContent: (_) {
        _appOpenAdsThisSession++;
        _lastAppOpenAt = DateTime.now();
        final storage = _session?.storage;
        if (storage != null) {
          unawaited(
            SafeStorage.write(
              storage,
              key: _lastAppOpenKey,
              value: _lastAppOpenAt!.toIso8601String(),
            ),
          );
        }
        _log('app-open shown');
      },
      onAdImpression: (_) => _log('app-open impression'),
      onAdClicked: (_) => _log('app-open clicked'),
      onAdDismissedFullScreenContent: (shownAd) {
        shownAd.dispose();
        _finishFullScreen();
        if (!completion.isCompleted) completion.complete(true);
        unawaited(preloadAppOpen());
      },
      onAdFailedToShowFullScreenContent: (failedAd, error) {
        failedAd.dispose();
        _finishFullScreen();
        _log('app-open show failed: ${error.code}');
        if (!completion.isCompleted) completion.complete(false);
      },
    );
    try {
      await ad.show();
    } catch (error) {
      ad.dispose();
      _finishFullScreen();
      _log('app-open show error: ${error.runtimeType}');
      if (!completion.isCompleted) completion.complete(false);
    }
    return completion.future;
  }

  /// Marks a browser, payment, share, picker or system-permission handoff.
  ///
  /// Returning from one of these is never a natural ABS content transition, so
  /// the quiet window covers interstitials as well as App Open.
  void suppressAppOpenForExternalFlow({Duration? duration}) {
    final until = DateTime.now().add(
      duration ?? AdConfig.externalFlowQuietPeriod,
    );
    if (_appOpenSuppressedUntil == null ||
        until.isAfter(_appOpenSuppressedUntil!)) {
      _appOpenSuppressedUntil = until;
    }
    if (_externalFlowUntil == null || until.isAfter(_externalFlowUntil!)) {
      _externalFlowUntil = until;
    }
    _log('ad suppressed: external/system flow');
  }

  /// True while a browser, payment, share, picker or permission handoff is
  /// still too recent to count as a natural ABS content transition.
  bool get _inExternalFlowQuietPeriod {
    final until = _externalFlowUntil;
    return until != null && DateTime.now().isBefore(until);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.hidden) {
      _backgroundedAt ??= DateTime.now();
      return;
    }
    if (state != AppLifecycleState.resumed) return;
    final backgroundedAt = _backgroundedAt;
    _backgroundedAt = null;
    if (!_contentInteractive || backgroundedAt == null) return;
    final requiredBackgroundTime = _debugTestAds
        ? const Duration(seconds: 5)
        : AdConfig.appOpenMinimumBackgroundTime;

    if (DateTime.now().difference(backgroundedAt) < requiredBackgroundTime) {
      _log('app-open skipped: short background');
      return;
    }
    if (_hasFreshAppOpenAd && _canShowAppOpenNow()) {
      unawaited(_showAppOpen());
    } else {
      _log(
        _inExternalFlowQuietPeriod
            ? 'app-open skipped: external return'
            : 'app-open skipped: foreground cooldown or not ready',
      );
      unawaited(preloadAppOpen());
    }
  }

  void _beginFullScreen() {
    _isFullScreenAdShowing = true;
    // Only App Open is held back here. Interstitial/rewarded spacing already
    // comes from _lastFullScreenAt, and routing this through the external-flow
    // window would wrongly treat ABS's own ad as a third-party handoff.
    final until = DateTime.now().add(AdConfig.fullScreenQuietPeriod);
    if (_appOpenSuppressedUntil == null ||
        until.isAfter(_appOpenSuppressedUntil!)) {
      _appOpenSuppressedUntil = until;
    }
    notifyListeners();
  }

  void _finishFullScreen() {
    _isFullScreenAdShowing = false;
    _lastFullScreenAt = DateTime.now();
    notifyListeners();
  }

  bool _elapsed(DateTime? value, Duration duration) =>
      value == null || DateTime.now().difference(value) >= duration;

  void _discardExpiredAppOpen() {
    if (_appOpenAd == null || _hasFreshAppOpenAd) return;
    _appOpenAd?.dispose();
    _appOpenAd = null;
    _appOpenLoadedAt = null;
  }

  void _handleSessionChanged() {
    if (!(_session?.canShowMonetizationAds ?? false)) {
      // Drops every preloaded full-screen ad the moment a user becomes paid,
      // so an already-loaded interstitial can never fire after the upgrade.
      _disposeLoadedAds();
      _log(
        _membershipResolvedPaid
            ? 'ad suppressed: subscription active'
            : 'ad suppressed: subscription state unresolved',
      );
    } else if (_mobileAdsInitialized && _canRequestAds) {
      unawaited(preloadAppOpen());
    }
    notifyListeners();
  }

  void _disposeLoadedAds() {
    _rewardedAd?.dispose();
    _rewardedAd = null;
    _interstitialAd?.dispose();
    _interstitialAd = null;
    _appOpenAd?.dispose();
    _appOpenAd = null;
    _appOpenLoadedAt = null;
  }

  void _log(String message) {
    if (kDebugMode) debugPrint('[ADS] $message');
  }
}

class AdServiceScope extends InheritedNotifier<AdService> {
  const AdServiceScope({
    super.key,
    required AdService service,
    required super.child,
  }) : super(notifier: service);

  static AdService of(BuildContext context) {
    final scope = context.dependOnInheritedWidgetOfExactType<AdServiceScope>();
    assert(scope != null, 'AdServiceScope not found');
    return scope!.notifier!;
  }

  /// The service if this subtree is inside an [AdServiceScope], otherwise null.
  ///
  /// Ad widgets use this so a subtree without the scope (a test harness, a
  /// detached route) renders nothing instead of throwing. A missing scope must
  /// never take an ABS screen down.
  static AdService? maybeOf(BuildContext context) =>
      context.dependOnInheritedWidgetOfExactType<AdServiceScope>()?.notifier;
}
