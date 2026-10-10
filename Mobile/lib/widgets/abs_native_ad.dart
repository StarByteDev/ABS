import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart';

import '../ads/ad_config.dart';
import '../ads/ad_placement_policy.dart';
import '../ads/ad_service.dart';
import '../core/theme.dart';

/// A premium ABS container around a Google-rendered native ad template.
///
/// Policy notes that govern this widget:
///  * Google's native template renders the mandatory ad attribution badge and
///    the AdChoices overlay. Neither is modified, covered or recreated here.
///  * AdMob requires a native ad to be distinguishable from surrounding
///    content by a distinct background colour *or* a border on all four sides.
///    This container provides both: an `AbsColors.panel2` surface where Pulse
///    content cards use `AbsColors.panel`, plus a gold-tinted full border.
///  * The Pulse "SPONSORED" header is an additional disclosure above Google's own
///    attribution, never a replacement for it.
///  * The card deliberately carries no ABS iconography, signal styling,
///    timestamps or category chips so it can never be mistaken for a trading
///    signal, alert or news article.
class AbsNativeAdCard extends StatefulWidget {
  const AbsNativeAdCard({
    super.key,
    required this.surface,
    this.templateType = TemplateType.small,
    this.debugLabel,
    this.margin,
  });

  final AdSurface surface;
  final TemplateType templateType;

  /// Used only for `[ADS]` debug logging, e.g. `news index 7`.
  final String? debugLabel;

  /// Spacing override. Leave it null: the policy default already sets the ad
  /// apart from surrounding ABS cards on every surface.
  final EdgeInsets? margin;

  EdgeInsets get _margin =>
      margin ??
      const EdgeInsets.symmetric(
        vertical: AdPlacementPolicy.nativeCardVerticalClearance,
      );

  /// Google's published template sizing: small 320x90..400x200,
  /// medium 320x320..400x400. iOS requires a bounded container.
  /// Google publishes small as 320x90..400x200 and medium as 320x320..400x400.
  ///
  /// Pulse uses the compact template everywhere in ordinary content: the medium
  /// one is ~336dp tall and reads as a giant block between Pulse cards.
  double get _templateHeight => templateType == TemplateType.small
      ? AdPlacementPolicy.nativeSmallTemplateHeight
      : AdPlacementPolicy.nativeMediumTemplateHeight;

  @override
  State<AbsNativeAdCard> createState() => _AbsNativeAdCardState();
}

class _AbsNativeAdCardState extends State<AbsNativeAdCard> {
  NativeAd? _ad;
  bool _loaded = false;
  bool _requested = false;
  bool _slotReserved = false;
  bool _tooNarrow = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!(AdServiceScope.maybeOf(context)?.canShowNativeAds ?? false)) {
      _collapse();
    }
  }

  /// One request per mounted slot, and only after layout has confirmed the
  /// slot can host a full-width template. Nothing here runs inside build().
  void _requestOnce() {
    if (_requested) return;
    _requested = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _load();
    });
  }

  void _load() {
    final ads = AdService.instance;
    final unitId = AdConfig.nativeAdUnitId;
    if (unitId == null ||
        !AdPlacementPolicy.allowsNative(widget.surface) ||
        !ads.reserveNativeSlot()) {
      return;
    }
    _slotReserved = true;

    final ad = NativeAd(
      adUnitId: unitId,
      request: const AdRequest(),
      nativeTemplateStyle: NativeTemplateStyle(
        templateType: widget.templateType,
        mainBackgroundColor: AbsColors.panel2,
        cornerRadius: 14,
        primaryTextStyle: NativeTemplateTextStyle(
          textColor: AbsColors.text,
          backgroundColor: Colors.transparent,
          size: 15,
        ),
        secondaryTextStyle: NativeTemplateTextStyle(
          textColor: AbsColors.muted,
          backgroundColor: Colors.transparent,
          size: 13,
        ),
        tertiaryTextStyle: NativeTemplateTextStyle(
          textColor: AbsColors.muted2,
          backgroundColor: Colors.transparent,
          size: 12,
        ),
        callToActionTextStyle: NativeTemplateTextStyle(
          textColor: AbsColors.bg,
          backgroundColor: AbsColors.gold,
          size: 14,
        ),
      ),
      listener: NativeAdListener(
        onAdLoaded: (loaded) {
          if (!mounted || !AdService.instance.canShowNativeAds) {
            loaded.dispose();
            _releaseSlot();
            return;
          }
          setState(() {
            _ad = loaded as NativeAd;
            _loaded = true;
          });
          _log('native loaded: ${widget.debugLabel ?? widget.surface.name}');
        },
        onAdFailedToLoad: (failed, error) {
          failed.dispose();
          _releaseSlot();
          // Collapse cleanly: an unfilled slot must leave no blank space.
          if (mounted) {
            setState(() {
              _ad = null;
              _loaded = false;
            });
          }
          _log(
            'native failed: ${widget.debugLabel ?? widget.surface.name} '
            '(${error.code})',
          );
        },
        onAdImpression: (_) => _log(
          'native impression: ${widget.debugLabel ?? widget.surface.name}',
        ),
        onAdClicked: (_) =>
            _log('native clicked: ${widget.debugLabel ?? widget.surface.name}'),
      ),
    );
    _ad = ad;
    unawaited(ad.load());
  }

  void _releaseSlot() {
    if (!_slotReserved) return;
    _slotReserved = false;
    AdService.instance.releaseNativeSlot();
  }

  void _collapse() {
    final ad = _ad;
    if (ad == null && !_loaded) return;
    _ad = null;
    _loaded = false;
    unawaited(ad?.dispose());
    _releaseSlot();
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    _ad?.dispose();
    _releaseSlot();
    super.dispose();
  }

  void _log(String message) {
    if (kDebugMode) debugPrint('[ADS] $message');
  }

  @override
  Widget build(BuildContext context) {
    // The load cycle is driven from the LayoutBuilder below, so this method
    // must never short-circuit before it. Nothing is reserved while loading:
    // _card() returns zero height until a real ad is in hand.
    final ads = AdServiceScope.maybeOf(context);
    if (ads == null || !ads.canShowNativeAds) return const SizedBox.shrink();
    return LayoutBuilder(
      builder: (context, constraints) {
        final width = constraints.maxWidth;
        // Google's templates are published for a 320dp minimum width. A
        // narrower slot can clip the headline, the CTA or the mandatory
        // attribution, so it collapses rather than rendering a broken ad.
        if (!width.isFinite ||
            width < AdPlacementPolicy.nativeTemplateMinimumWidth) {
          if (!_tooNarrow) {
            _tooNarrow = true;
            _log(
              'native skipped: slot narrower than template minimum '
              '(${widget.debugLabel ?? widget.surface.name})',
            );
          }
          return const SizedBox.shrink();
        }
        _tooNarrow = false;
        _requestOnce();
        return _card();
      },
    );
  }

  Widget _card() {
    final ad = _ad;
    if (!_loaded || ad == null) return const SizedBox.shrink();
    return Padding(
      padding: widget._margin,
      child: Container(
        decoration: BoxDecoration(
          color: AbsColors.panel2,
          borderRadius: BorderRadius.circular(20),
          // Distinct border on all four sides, as AdMob requires for native.
          border: Border.all(color: AbsColors.gold.withValues(alpha: .26)),
        ),
        // Only vertical padding. Google's templates want the full available
        // width, so the creative runs edge to edge inside the Pulse frame.
        padding: const EdgeInsets.symmetric(vertical: 9),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 12),
              child: _SponsoredLabel(),
            ),
            const SizedBox(height: 8),
            ClipRRect(
              borderRadius: BorderRadius.circular(14),
              child: SizedBox(
                width: double.infinity,
                height: widget._templateHeight,
                child: AdWidget(ad: ad),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Pulse disclosure shown in addition to Google's in-template attribution.
class _SponsoredLabel extends StatelessWidget {
  const _SponsoredLabel();

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(left: 2),
    child: Row(
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
          decoration: BoxDecoration(
            color: AbsColors.gold.withValues(alpha: .13),
            borderRadius: BorderRadius.circular(6),
            border: Border.all(color: AbsColors.gold.withValues(alpha: .30)),
          ),
          child: const Text(
            'SPONSORED',
            style: TextStyle(
              color: AbsColors.goldSoft,
              fontSize: 8.5,
              fontWeight: FontWeight.w900,
              letterSpacing: .9,
            ),
          ),
        ),
        const SizedBox(width: 8),
        const Expanded(
          child: Text(
            'Paid advertisement · not Pulse market content',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: AbsColors.muted2,
              fontSize: 8.75,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    ),
  );
}
