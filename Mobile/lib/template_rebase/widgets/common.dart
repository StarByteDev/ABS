import 'dart:ui' as ui;

import 'package:flutter/material.dart';

import '../../core/ads/ad_policy.dart';
import '../../core/ads/ad_service.dart';
import '../theme/app_theme.dart';
import '../utils/format.dart';

Future<T?> push<T>(BuildContext context, Widget page) =>
    Navigator.of(context).push<T>(MaterialPageRoute(builder: (_) => page));

/// Opens a content-detail page (news article, signal detail). When the user
/// comes back, the central ad policy may show a capped interstitial; if none
/// is ready or allowed, navigation simply continues.
Future<T?> pushContentDetail<T>(
  BuildContext context,
  Widget page,
  InterstitialMoment moment,
) async {
  final result = await push<T>(context, page);
  AdService.instance.onContentDetailClosed(moment);
  return result;
}

Route<T> fadeRoute<T>(Widget page) => PageRouteBuilder<T>(
      transitionDuration: const Duration(milliseconds: 380),
      pageBuilder: (_, __, ___) => page,
      transitionsBuilder: (_, a, __, child) =>
          FadeTransition(opacity: a, child: child),
    );

void snack(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message)));
}

class AbsCard extends StatelessWidget {
  const AbsCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16),
    this.onTap,
    this.color,
    this.borderColor,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;
  final Color? color;
  final Color? borderColor;

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(18);
    return Material(
      color: color ?? AppColors.surface,
      borderRadius: radius,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        borderRadius: radius,
        child: Container(
          padding: padding,
          decoration: BoxDecoration(
            borderRadius: radius,
            border: Border.all(color: borderColor ?? AppColors.line),
          ),
          child: child,
        ),
      ),
    );
  }
}

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.title, {super.key, this.action, this.onAction});
  final String title;
  final String? action;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 26, bottom: 10),
      child: Row(
        children: [
          Expanded(child: Text(title, style: AppText.h2)),
          if (action != null)
            GestureDetector(
              onTap: onAction,
              child: Text(action!,
                  style: const TextStyle(
                      color: AppColors.accent,
                      fontWeight: FontWeight.w600,
                      fontSize: 13)),
            ),
        ],
      ),
    );
  }
}

class PrimaryButton extends StatelessWidget {
  const PrimaryButton(
      {super.key, required this.label, this.onPressed, this.icon});
  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: icon == null
          ? FilledButton(onPressed: onPressed, child: Text(label))
          : FilledButton.icon(
              onPressed: onPressed,
              icon: Icon(icon, size: 20),
              label: Text(label)),
    );
  }
}

class SegmentToggle extends StatelessWidget {
  const SegmentToggle({
    super.key,
    required this.options,
    required this.index,
    required this.onChanged,
  });
  final List<String> options;
  final int index;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: AppColors.bg,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.line),
      ),
      child: Row(
        children: [
          for (int i = 0; i < options.length; i++)
            Expanded(
              child: GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: () => onChanged(i),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  padding: const EdgeInsets.symmetric(vertical: 9),
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: i == index
                        ? fade(AppColors.accent, .18)
                        : Colors.transparent,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    options[i],
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                      color: i == index ? AppColors.text : AppColors.muted,
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class ChangeText extends StatelessWidget {
  const ChangeText(this.value, {super.key, this.size = 13});
  final double value;
  final double size;

  @override
  Widget build(BuildContext context) => Text(
        fmtPct(value),
        style: AppText.figure.copyWith(
            fontSize: size, color: value >= 0 ? AppColors.up : AppColors.down),
      );
}

class ChangeBadge extends StatelessWidget {
  const ChangeBadge(this.value, {super.key});
  final double value;

  @override
  Widget build(BuildContext context) {
    final c = value >= 0 ? AppColors.up : AppColors.down;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
          color: fade(c, .14), borderRadius: BorderRadius.circular(8)),
      child: Text(fmtPct(value),
          style: AppText.figure.copyWith(fontSize: 12.5, color: c)),
    );
  }
}

class Pill extends StatelessWidget {
  const Pill(this.text, {super.key, this.color = AppColors.accent});
  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(
            color: fade(color, .15), borderRadius: BorderRadius.circular(6)),
        child: Text(text,
            style: TextStyle(
                color: color, fontSize: 11.5, fontWeight: FontWeight.w700)),
      );
}

class CoinAvatar extends StatelessWidget {
  const CoinAvatar(
      {super.key, required this.symbol, this.size = 36, this.color});
  final String symbol;
  final double size;
  final Color? color;

  static const _palette = [
    Color(0xFF4DA3FF),
    Color(0xFFB08CFF),
    Color(0xFF2FD4E0),
    Color(0xFFFFB347),
    Color(0xFF26D07C),
    Color(0xFFFF7A9A),
  ];

  @override
  Widget build(BuildContext context) {
    final c = color ??
        _palette[
            symbol.codeUnits.fold<int>(0, (a, b) => a + b) % _palette.length];
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: fade(c, .16),
        shape: BoxShape.circle,
        border: Border.all(color: fade(c, .5)),
      ),
      child: Text(
        symbol.isEmpty ? '?' : symbol[0],
        style: TextStyle(
            color: c, fontWeight: FontWeight.w800, fontSize: size * 0.42),
      ),
    );
  }
}

class MiniStat extends StatelessWidget {
  const MiniStat(
      {super.key, required this.label, required this.value, this.valueColor});
  final String label;
  final String value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: AppText.muted.copyWith(fontSize: 11.5)),
          const SizedBox(height: 3),
          Text(value,
              style: AppText.figure.copyWith(
                  fontSize: 13.5, color: valueColor ?? AppColors.text)),
        ],
      );
}

class StatTile extends StatelessWidget {
  const StatTile({
    super.key,
    required this.label,
    required this.value,
    this.note,
    this.noteColor = AppColors.muted,
  });
  final String label;
  final String value;
  final String? note;
  final Color noteColor;

  @override
  Widget build(BuildContext context) => AbsCard(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(label,
                style: AppText.muted,
                maxLines: 1,
                overflow: TextOverflow.ellipsis),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(value, style: AppText.figure.copyWith(fontSize: 19)),
            ),
            if (note != null)
              Text(note!,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                      color: noteColor,
                      fontSize: 12,
                      fontWeight: FontWeight.w600)),
          ],
        ),
      );
}

class LegendDot extends StatelessWidget {
  const LegendDot({super.key, required this.color, required this.label});
  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) => Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
              width: 8,
              height: 8,
              decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
          const SizedBox(width: 6),
          Text(label, style: AppText.muted),
        ],
      );
}

class PointsChip extends StatelessWidget {
  const PointsChip({super.key, required this.points, this.onTap});
  final int points;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          decoration: BoxDecoration(
            color: fade(AppColors.gold, .12),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: fade(AppColors.gold, .35)),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.stars_rounded, color: AppColors.gold, size: 16),
              const SizedBox(width: 5),
              Text('$points',
                  style: AppText.figure
                      .copyWith(color: AppColors.gold, fontSize: 13)),
            ],
          ),
        ),
      );
}

/// Blurs a preview and puts an unlock button on top.
class LockedOverlay extends StatelessWidget {
  const LockedOverlay({
    super.key,
    required this.child,
    required this.label,
    required this.onUnlock,
    this.icon = Icons.lock_open_rounded,
  });
  final Widget child;
  final String label;
  final VoidCallback onUnlock;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        ImageFiltered(
          imageFilter: ui.ImageFilter.blur(sigmaX: 7, sigmaY: 7),
          child: IgnorePointer(child: child),
        ),
        Positioned.fill(
          child: Center(
            child: FilledButton.icon(
              style: FilledButton.styleFrom(
                  minimumSize: const Size(0, 44),
                  padding: const EdgeInsets.symmetric(horizontal: 18)),
              onPressed: onUnlock,
              icon: Icon(icon, size: 18),
              label: Text(label),
            ),
          ),
        ),
      ],
    );
  }
}

class EmptyState extends StatelessWidget {
  const EmptyState(
      {super.key,
      required this.icon,
      required this.title,
      required this.message});
  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 48),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 44, color: AppColors.faint),
            const SizedBox(height: 14),
            Text(title, style: AppText.h2, textAlign: TextAlign.center),
            const SizedBox(height: 6),
            Text(message,
                textAlign: TextAlign.center,
                style: AppText.muted.copyWith(fontSize: 13.5, height: 1.45)),
          ],
        ),
      );
}

class RiskNotice extends StatelessWidget {
  const RiskNotice({super.key});

  @override
  Widget build(BuildContext context) => const Padding(
        padding: EdgeInsets.only(top: 24),
        child: Text(
          'Pulse provides market intelligence and trading tools, not financial advice. Digital assets are high risk. Verify information independently and use appropriate risk controls.',
          style: TextStyle(color: AppColors.faint, fontSize: 11.5, height: 1.5),
        ),
      );
}

/// Production Pulse logo from the live brand asset bundle.
class AbsLogo extends StatelessWidget {
  const AbsLogo({super.key, this.size = 40});
  final double size;

  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        padding: EdgeInsets.all(size * .08),
        decoration: BoxDecoration(
          color: AppColors.surfaceHi,
          borderRadius: BorderRadius.circular(size * .28),
          border: Border.all(color: AppColors.line),
        ),
        child: Image.asset(
          'assets/brand/abs-logo-512.png',
          fit: BoxFit.contain,
          filterQuality: FilterQuality.high,
        ),
      );
}

class BrandWordmark extends StatelessWidget {
  const BrandWordmark({super.key, this.size = 15});
  final double size;

  @override
  Widget build(BuildContext context) => Text.rich(
        TextSpan(children: const [
          TextSpan(text: 'ALPHA '),
          TextSpan(text: 'BLOCK', style: TextStyle(color: AppColors.accent)),
          TextSpan(text: ' SOLUTIONS'),
        ]),
        style: TextStyle(
            fontSize: size,
            fontWeight: FontWeight.w800,
            letterSpacing: size * 0.16,
            color: AppColors.text),
      );
}
