import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/json_tools.dart';
import '../core/theme.dart';

class AbsBackground extends StatelessWidget {
  const AbsBackground({super.key, required this.child});
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(
        color: AbsColors.bg,
        gradient: RadialGradient(
          center: Alignment(1.05, -1.1),
          radius: 1.25,
          colors: [Color(0x1E7A5CFF), Color(0x1022C7FF), AbsColors.bg],
          stops: [0, .42, 1],
        ),
      ),
      child: child,
    );
  }
}

class AbsPage extends StatelessWidget {
  const AbsPage({
    super.key,
    required this.title,
    required this.child,
    this.subtitle,
    this.actions,
    this.padding = const EdgeInsets.fromLTRB(16, 8, 16, 28),
    this.showBrand = true,
  });

  final String title;
  final String? subtitle;
  final Widget child;
  final List<Widget>? actions;
  final EdgeInsets padding;
  final bool showBrand;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.transparent,
      appBar: AppBar(
        toolbarHeight: subtitle == null ? 64 : 72,
        titleSpacing: 16,
        title: Row(
          children: [
            if (showBrand) ...[
              Container(
                width: 34,
                height: 34,
                padding: const EdgeInsets.all(4),
                decoration: BoxDecoration(
                  color: AbsColors.panel2,
                  borderRadius: BorderRadius.circular(11),
                  border: Border.all(color: AbsColors.line),
                ),
                child: Image.asset('assets/brand/abs-logo-master.png'),
              ),
              const SizedBox(width: 10),
            ],
            Expanded(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18, letterSpacing: -.25)),
                  if (subtitle != null) ...[
                    const SizedBox(height: 2),
                    Text(subtitle!, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 10.5, color: AbsColors.muted, fontWeight: FontWeight.w600)),
                  ],
                ],
              ),
            ),
          ],
        ),
        actions: actions,
      ),
      body: AbsBackground(
        child: SafeArea(
          top: false,
          child: Padding(padding: padding, child: child),
        ),
      ),
    );
  }
}

class AbsCard extends StatelessWidget {
  const AbsCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16),
    this.accent,
    this.gradient,
  });

  final Widget child;
  final EdgeInsets padding;
  final Color? accent;
  final Gradient? gradient;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AbsColors.panel,
        gradient: gradient,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: accent?.withValues(alpha: .40) ?? AbsColors.lineSoft),
        boxShadow: const [
          BoxShadow(color: Color(0x26000000), blurRadius: 24, offset: Offset(0, 12)),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(20),
        child: Stack(
          children: [
            if (accent != null)
              Positioned(
                left: 0,
                top: 0,
                bottom: 0,
                child: Container(width: 2.5, color: accent),
              ),
            Padding(padding: padding, child: child),
          ],
        ),
      ),
    );
  }
}

class PremiumHeroCard extends StatelessWidget {
  const PremiumHeroCard({
    super.key,
    required this.eyebrow,
    required this.title,
    required this.message,
    this.trailing,
    this.footer,
  });

  final String eyebrow;
  final String title;
  final String message;
  final Widget? trailing;
  final Widget? footer;

  @override
  Widget build(BuildContext context) {
    return AbsCard(
      gradient: AbsColors.premiumGradient,
      accent: AbsColors.gold,
      padding: const EdgeInsets.fromLTRB(18, 18, 18, 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(eyebrow.toUpperCase(), style: const TextStyle(color: AbsColors.goldSoft, fontWeight: FontWeight.w900, fontSize: 10, letterSpacing: 1.2)),
                    const SizedBox(height: 7),
                    Text(title, style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900)),
                  ],
                ),
              ),
              if (trailing != null) ...[const SizedBox(width: 12), trailing!],
            ],
          ),
          const SizedBox(height: 8),
          Text(message, style: const TextStyle(color: Color(0xFFC3CFDB), fontSize: 13.5, height: 1.45)),
          if (footer != null) ...[const SizedBox(height: 16), footer!],
        ],
      ),
    );
  }
}

class AbsSectionTitle extends StatelessWidget {
  const AbsSectionTitle(this.title, {super.key, this.trailing, this.subtitle, this.eyebrow});
  final String title;
  final String? subtitle;
  final String? eyebrow;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (eyebrow != null) ...[
                Text(eyebrow!.toUpperCase(), style: const TextStyle(color: AbsColors.cyanSoft, fontSize: 9.5, fontWeight: FontWeight.w900, letterSpacing: 1.05)),
                const SizedBox(height: 4),
              ],
              Text(title, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900)),
              if (subtitle != null) ...[
                const SizedBox(height: 4),
                Text(subtitle!, style: const TextStyle(color: AbsColors.muted, fontSize: 12.5)),
              ],
            ],
          ),
        ),
        if (trailing != null) trailing!,
      ],
    );
  }
}

class MetricCard extends StatelessWidget {
  const MetricCard({
    super.key,
    required this.label,
    required this.value,
    this.detail,
    this.icon,
    this.valueColor,
  });

  final String label;
  final String value;
  final String? detail;
  final IconData? icon;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    return AbsCard(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              if (icon != null) ...[
                Container(
                  width: 30,
                  height: 30,
                  decoration: BoxDecoration(color: AbsColors.cyan.withValues(alpha: .09), borderRadius: BorderRadius.circular(10)),
                  child: Icon(icon, size: 16, color: AbsColors.cyanSoft),
                ),
                const SizedBox(width: 8),
              ],
              Expanded(child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AbsColors.muted, fontSize: 11.5, fontWeight: FontWeight.w700))),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(fontSize: 21, height: 1, fontWeight: FontWeight.w900, letterSpacing: -.45, color: valueColor),
          ),
          if (detail != null) ...[
            const SizedBox(height: 6),
            Text(detail!, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AbsColors.muted2, fontSize: 10.5, fontWeight: FontWeight.w600)),
          ],
        ],
      ),
    );
  }
}

class StatusChip extends StatelessWidget {
  const StatusChip(this.text, {super.key, this.good, this.warning = false});

  final String text;
  final bool? good;
  final bool warning;

  @override
  Widget build(BuildContext context) {
    final color = warning
        ? AbsColors.gold
        : good == true
            ? AbsColors.green
            : good == false
                ? AbsColors.red
                : AbsColors.cyan;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5.5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.085),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: 0.32)),
      ),
      child: Text(text, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: color, fontSize: 9.5, fontWeight: FontWeight.w900, letterSpacing: .35)),
    );
  }
}

class ExperienceModeSwitch extends StatelessWidget {
  const ExperienceModeSwitch({super.key, required this.proMode, required this.onChanged});
  final bool proMode;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 42,
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: AbsColors.panel2,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AbsColors.lineSoft),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          _ExperienceChoice(label: 'Simple', selected: !proMode, onTap: () => onChanged(false)),
          _ExperienceChoice(label: 'Pro', selected: proMode, onTap: () => onChanged(true)),
        ],
      ),
    );
  }
}

class _ExperienceChoice extends StatelessWidget {
  const _ExperienceChoice({required this.label, required this.selected, required this.onTap});
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 8),
        decoration: BoxDecoration(
          color: selected ? AbsColors.cyan.withValues(alpha: .13) : Colors.transparent,
          borderRadius: BorderRadius.circular(10),
          border: selected ? Border.all(color: AbsColors.cyan.withValues(alpha: .35)) : null,
        ),
        child: Text(label, style: TextStyle(color: selected ? AbsColors.text : AbsColors.muted, fontSize: 11, fontWeight: FontWeight.w900)),
      ),
    );
  }
}

class QuickActionCard extends StatelessWidget {
  const QuickActionCard({
    super.key,
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.accent = AbsColors.cyan,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: AbsCard(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(color: accent.withValues(alpha: .10), borderRadius: BorderRadius.circular(13)),
              child: Icon(icon, color: accent, size: 21),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 13.5)),
                  const SizedBox(height: 3),
                  Text(subtitle, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AbsColors.muted, fontSize: 10.5)),
                ],
              ),
            ),
            const Icon(Icons.arrow_forward_ios_rounded, color: AbsColors.muted2, size: 14),
          ],
        ),
      ),
    );
  }
}

class GuidedStepCard extends StatelessWidget {
  const GuidedStepCard({
    super.key,
    required this.number,
    required this.title,
    required this.description,
    required this.actionLabel,
    required this.onTap,
    this.complete = false,
  });

  final int number;
  final String title;
  final String description;
  final String actionLabel;
  final VoidCallback onTap;
  final bool complete;

  @override
  Widget build(BuildContext context) {
    final accent = complete ? AbsColors.green : AbsColors.cyan;
    return AbsCard(
      padding: const EdgeInsets.all(14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: accent.withValues(alpha: .10),
              borderRadius: BorderRadius.circular(11),
              border: Border.all(color: accent.withValues(alpha: .28)),
            ),
            child: complete ? Icon(Icons.check_rounded, size: 18, color: accent) : Text('$number', style: TextStyle(color: accent, fontWeight: FontWeight.w900)),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 13.5)),
                const SizedBox(height: 4),
                Text(description, style: const TextStyle(color: AbsColors.muted, fontSize: 11.5)),
                const SizedBox(height: 8),
                InkWell(
                  onTap: onTap,
                  borderRadius: BorderRadius.circular(8),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 3),
                    child: Text(actionLabel, style: TextStyle(color: accent, fontSize: 11.5, fontWeight: FontWeight.w900)),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class EmptyState extends StatelessWidget {
  const EmptyState({super.key, required this.title, required this.message, this.icon = Icons.radar});
  final String title;
  final String message;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 44, horizontal: 18),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 64,
                height: 64,
                decoration: BoxDecoration(color: AbsColors.panel2, borderRadius: BorderRadius.circular(20), border: Border.all(color: AbsColors.lineSoft)),
                child: Icon(icon, size: 30, color: AbsColors.muted),
              ),
              const SizedBox(height: 16),
              Text(title, textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900)),
              const SizedBox(height: 7),
              Text(message, textAlign: TextAlign.center, style: const TextStyle(color: AbsColors.muted, fontSize: 12.5)),
            ],
          ),
        ),
      );
}

class LoadingBlock extends StatelessWidget {
  const LoadingBlock({super.key, this.label = 'Loading ABS...'});
  final String label;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 64),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const SizedBox(width: 30, height: 30, child: CircularProgressIndicator(strokeWidth: 2.4)),
              const SizedBox(height: 15),
              Text(label, style: const TextStyle(color: AbsColors.muted, fontWeight: FontWeight.w700)),
            ],
          ),
        ),
      );
}

class ErrorBlock extends StatelessWidget {
  const ErrorBlock({super.key, required this.message, this.onRetry});
  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => AbsCard(
        accent: AbsColors.red,
        child: Column(
          children: [
            const Icon(Icons.error_outline_rounded, color: AbsColors.red, size: 32),
            const SizedBox(height: 10),
            Text(message, textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w700)),
            if (onRetry != null) ...[
              const SizedBox(height: 13),
              OutlinedButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh), label: const Text('Try again')),
            ],
          ],
        ),
      );
}

class KeyValueRow extends StatelessWidget {
  const KeyValueRow(this.label, this.value, {super.key, this.valueColor});
  final String label;
  final String value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 7),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(child: Text(label, style: const TextStyle(color: AbsColors.muted, fontSize: 12))),
            const SizedBox(width: 14),
            Flexible(
              child: Text(value, textAlign: TextAlign.right, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 12.5, color: valueColor)),
            ),
          ],
        ),
      );
}

String money(dynamic value, {String symbol = r'$'}) {
  final n = JsonTools.number(value);
  final abs = n.abs();
  final digits = abs >= 1000 ? 0 : 2;
  return '$symbol${NumberFormat.currency(symbol: '', decimalDigits: digits).format(n)}';
}

String number(dynamic value, {int digits = 2}) => NumberFormat.decimalPatternDigits(decimalDigits: digits).format(JsonTools.number(value));

String percent(dynamic value, {int digits = 1}) => '${number(value, digits: digits)}%';

String compactDate(dynamic value) {
  final parsed = DateTime.tryParse(value?.toString() ?? '')?.toLocal();
  return parsed == null ? '—' : DateFormat('d MMM · HH:mm').format(parsed);
}

Color pnlColor(dynamic value) {
  final n = JsonTools.number(value);
  if (n > 0) return AbsColors.green;
  if (n < 0) return AbsColors.red;
  return AbsColors.text;
}

void showSnack(BuildContext context, String message, {bool error = false}) {
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      content: Text(message),
      backgroundColor: error ? const Color(0xFF3A151D) : AbsColors.panel3,
    ),
  );
}

class PulseAccessGate extends StatelessWidget {
  const PulseAccessGate({super.key, required this.onViewPlans});
  final VoidCallback onViewPlans;

  @override
  Widget build(BuildContext context) => Center(
        child: PremiumHeroCard(
          eyebrow: 'Pulse membership',
          title: 'Unlock Pulse trading intelligence',
          message: 'Market scanning, signals, Binance Futures setup, guarded execution, positions and performance intelligence are available with an active Pulse plan.',
          trailing: const Icon(Icons.workspace_premium_rounded, color: AbsColors.gold, size: 34),
          footer: SizedBox(width: double.infinity, child: ElevatedButton(onPressed: onViewPlans, child: const Text('Compare Pulse plans'))),
        ),
      );
}
