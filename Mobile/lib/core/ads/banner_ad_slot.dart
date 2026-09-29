import 'package:flutter/material.dart';
// `show` keeps google_mobile_ads' own AppState out of files that use ABS AppState.
import 'package:google_mobile_ads/google_mobile_ads.dart'
    show AdRequest, AdSize, AdWidget, BannerAd, BannerAdListener;

import '../session.dart';
import 'ad_config.dart';
import 'ad_policy.dart';
import 'ad_service.dart';

/// Inline adaptive banner embedded in scrollable content.
///
/// The slot reserves its full height up front so surrounding content never
/// jumps when the ad arrives, keeps a loaded ad alive while scrolled out of
/// view, and collapses only if ads are unavailable or the request fails.
class InlineBannerAdSlot extends StatefulWidget {
  const InlineBannerAdSlot({
    super.key,
    required this.placement,
    this.margin = const EdgeInsets.symmetric(vertical: 18),
  });

  final BannerPlacement placement;

  /// Separation from neighbouring content and controls.
  final EdgeInsets margin;

  /// Height cap requested from AdMob and reserved in the layout.
  static const int maxAdHeight = 100;

  @override
  State<InlineBannerAdSlot> createState() => _InlineBannerAdSlotState();
}

class _InlineBannerAdSlotState extends State<InlineBannerAdSlot>
    with AutomaticKeepAliveClientMixin {
  BannerAd? _ad;
  AdSize? _loadedSize;
  bool _failed = false;
  int? _requestedWidth;

  @override
  bool get wantKeepAlive => _ad != null;

  @override
  void initState() {
    super.initState();
    AdService.instance.readiness.addListener(_onReadinessChanged);
  }

  @override
  void dispose() {
    AdService.instance.readiness.removeListener(_onReadinessChanged);
    _disposeAd();
    super.dispose();
  }

  void _onReadinessChanged() {
    if (mounted) setState(() {});
  }

  void _load(int width) {
    _requestedWidth = width;
    _disposeAd();
    _failed = false;
    final ad = BannerAd(
      adUnitId: AdConfig.bannerUnitId,
      size: AdSize.getInlineAdaptiveBannerAdSize(
        width,
        InlineBannerAdSlot.maxAdHeight,
      ),
      request: const AdRequest(),
      listener: BannerAdListener(
        onAdLoaded: (loaded) async {
          // Inline adaptive ads report their real height only after loading.
          final size = await (loaded as BannerAd).getPlatformAdSize();
          if (!mounted || !identical(_ad, loaded) || size == null) return;
          setState(() => _loadedSize = size);
          updateKeepAlive();
        },
        onAdFailedToLoad: (failed, _) {
          failed.dispose();
          if (identical(_ad, failed)) _ad = null;
          if (!mounted) return;
          setState(() {
            _loadedSize = null;
            _failed = true;
          });
          updateKeepAlive();
        },
      ),
    );
    _ad = ad;
    ad.load();
  }

  void _disposeAd() {
    _ad?.dispose();
    _ad = null;
    _loadedSize = null;
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final service = AdService.instance;
    // Depending on SessionScope re-evaluates eligibility on login/logout.
    final eligible =
        service.canShowBanner(SessionScope.of(context), widget.placement);
    if (!eligible || _failed) {
      if (_ad != null) _disposeAd();
      return const SizedBox.shrink();
    }
    return Padding(
      padding: widget.margin,
      child: LayoutBuilder(
        builder: (context, constraints) {
          final width = constraints.maxWidth.truncate();
          if (service.readiness.value == AdReadiness.ready &&
              width != _requestedWidth) {
            // Request after this frame; the reserved box is already laid out.
            WidgetsBinding.instance.addPostFrameCallback((_) {
              if (mounted && width != _requestedWidth) {
                setState(() => _load(width));
              }
            });
          }
          final ad = _ad;
          final size = _loadedSize;
          return Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Advertisement',
                style: TextStyle(
                  fontSize: 10.5,
                  letterSpacing: .4,
                  color: Theme.of(context).hintColor,
                ),
              ),
              const SizedBox(height: 6),
              SizedBox(
                height: InlineBannerAdSlot.maxAdHeight.toDouble(),
                child: Center(
                  child: ad != null && size != null
                      ? SizedBox(
                          width: size.width.toDouble(),
                          height: size.height.toDouble(),
                          child: AdWidget(ad: ad),
                        )
                      : null,
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
