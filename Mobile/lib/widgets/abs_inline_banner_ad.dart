import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart';

import '../ads/ad_config.dart';
import '../ads/ad_placement_policy.dart';
import '../ads/ad_service.dart';
import '../core/theme.dart';

/// An inline adaptive banner that lives *inside* scrolling content.
///
/// Unlike an anchored banner it never sits above the bottom navigation bar,
/// never overlays content and never occupies fixed chrome. It requests the
/// real available width, honours the container's horizontal padding, resizes
/// to the size the platform actually returned, and collapses to zero height
/// when no ad is available.
///
/// Generous vertical separation keeps the creative away from tappable Pulse rows
/// so an accidental click is unlikely.
class AbsInlineAdaptiveBanner extends StatefulWidget {
  const AbsInlineAdaptiveBanner({
    super.key,
    required this.surface,
    this.debugLabel,
    this.horizontalPadding = 0,
    this.padding,
  });

  final AdSurface surface;
  final String? debugLabel;

  /// Horizontal padding already applied by the surrounding layout, subtracted
  /// from the requested ad width.
  final double horizontalPadding;

  /// Optional spacing override for a surface that genuinely needs different
  /// rhythm. Leave it null: the policy default already keeps the creative
  /// clear of tappable Pulse rows on every screen.
  final EdgeInsets? padding;

  @override
  State<AbsInlineAdaptiveBanner> createState() =>
      _AbsInlineAdaptiveBannerState();
}

class _AbsInlineAdaptiveBannerState extends State<AbsInlineAdaptiveBanner> {
  BannerAd? _banner;
  AdSize? _renderedSize;
  int? _requestedWidth;
  bool _loading = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!(AdServiceScope.maybeOf(context)?.canShowMonetizationAds ?? false)) {
      _collapse();
    }
  }

  Future<void> _load(int width) async {
    if (_loading) return;
    _loading = true;
    _requestedWidth = width;

    final previous = _banner;
    _banner = null;
    _renderedSize = null;
    unawaited(previous?.dispose());

    final unitId = AdConfig.bannerAdUnitId;
    if (unitId == null || !AdService.instance.canShowMonetizationAds) {
      _loading = false;
      return;
    }

    // Inline adaptive sizing, capped so a banner can never dominate the feed.
    final size = AdSize.getInlineAdaptiveBannerAdSize(
      width,
      AdPlacementPolicy.inlineBannerMaximumHeight,
    );

    final banner = BannerAd(
      adUnitId: unitId,
      size: size,
      request: const AdRequest(),
      listener: BannerAdListener(
        onAdLoaded: (ad) async {
          final loaded = ad as BannerAd;
          if (!mounted || !AdService.instance.canShowMonetizationAds) {
            unawaited(loaded.dispose());
            return;
          }
          // The platform decides the final height; the container follows it.
          final platformSize = await loaded.getPlatformAdSize();
          if (!mounted || platformSize == null) {
            unawaited(loaded.dispose());
            return;
          }
          setState(() {
            _banner = loaded;
            _renderedSize = platformSize;
            _loading = false;
          });
          _log('inline loaded: ${widget.debugLabel ?? widget.surface.name}');
        },
        onAdFailedToLoad: (ad, error) {
          ad.dispose();
          if (mounted) {
            setState(() {
              _banner = null;
              _renderedSize = null;
              _loading = false;
            });
          }
          _log(
            'inline failed: ${widget.debugLabel ?? widget.surface.name} '
            '(${error.code})',
          );
        },
        onAdImpression: (_) => _log(
          'inline impression: ${widget.debugLabel ?? widget.surface.name}',
        ),
        onAdClicked: (_) =>
            _log('inline clicked: ${widget.debugLabel ?? widget.surface.name}'),
      ),
    );
    _banner = banner;
    await banner.load();
  }

  void _collapse() {
    final banner = _banner;
    if (banner == null && _renderedSize == null && !_loading) return;
    _banner = null;
    _renderedSize = null;
    _loading = false;
    _requestedWidth = null;
    unawaited(banner?.dispose());
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    _banner?.dispose();
    super.dispose();
  }

  void _log(String message) {
    if (kDebugMode) debugPrint('[ADS] $message');
  }

  @override
  Widget build(BuildContext context) {
    final ads = AdServiceScope.maybeOf(context);
    if (ads == null ||
        !ads.canShowMonetizationAds ||
        !AdPlacementPolicy.allowsInlineBanner(widget.surface)) {
      return const SizedBox.shrink();
    }
    return LayoutBuilder(
      builder: (context, constraints) {
        final available = constraints.maxWidth - (widget.horizontalPadding * 2);
        final width = available.isFinite ? available.floor() : 0;
        if (width > 0 && _requestedWidth != width && !_loading) {
          // Ad objects are never created during build.
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted) unawaited(_load(width));
          });
        }

        final banner = _banner;
        final size = _renderedSize;
        if (banner == null || size == null) return const SizedBox.shrink();

        return Padding(
          // Deliberate breathing room above and below keeps the creative clear
          // of tappable Pulse rows.
          padding:
              widget.padding ??
              EdgeInsets.fromLTRB(
                widget.horizontalPadding,
                AdPlacementPolicy.inlineBannerVerticalClearance,
                widget.horizontalPadding,
                AdPlacementPolicy.inlineBannerVerticalClearance,
              ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const _InlineAdLabel(),
              const SizedBox(height: 7),
              Center(
                child: SizedBox(
                  width: size.width.toDouble(),
                  height: size.height.toDouble(),
                  child: AdWidget(ad: banner),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

/// Quiet Pulse-side labelling. The banner creative itself is Google-rendered and
/// is never restyled or disguised.
class _InlineAdLabel extends StatelessWidget {
  const _InlineAdLabel();

  @override
  Widget build(BuildContext context) => Row(
    children: [
      const Text(
        'ADVERTISEMENT',
        style: TextStyle(
          color: AbsColors.muted2,
          fontSize: 8.25,
          fontWeight: FontWeight.w900,
          letterSpacing: .9,
        ),
      ),
      const SizedBox(width: 8),
      const Expanded(child: Divider(height: 1, color: AbsColors.lineSoft)),
    ],
  );
}
