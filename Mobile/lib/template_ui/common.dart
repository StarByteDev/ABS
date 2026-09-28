import 'package:flutter/material.dart';

import '../core/theme.dart';

Color templateFade(Color c, double opacity) =>
    c.withAlpha((opacity.clamp(0.0, 1.0) * 255).round());

class PulseLogo extends StatelessWidget {
  const PulseLogo({super.key, this.size = 38});
  final double size;

  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        padding: EdgeInsets.all(size * .12),
        decoration: BoxDecoration(
          color: AbsColors.panel2,
          borderRadius: BorderRadius.circular(size * .28),
          border: Border.all(color: AbsColors.line),
        ),
        child: Image.asset('assets/brand/abs-logo-master.png', fit: BoxFit.contain),
      );
}

class TemplateCard extends StatelessWidget {
  const TemplateCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16),
    this.onTap,
    this.borderColor,
    this.gradient,
  });
  final Widget child;
  final EdgeInsets padding;
  final VoidCallback? onTap;
  final Color? borderColor;
  final Gradient? gradient;

  @override
  Widget build(BuildContext context) {
    final box = Container(
      padding: padding,
      decoration: BoxDecoration(
        color: gradient == null ? AbsColors.panel : null,
        gradient: gradient,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: borderColor ?? AbsColors.line),
      ),
      child: child,
    );
    if (onTap == null) return box;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: box,
    );
  }
}

class TemplateSectionTitle extends StatelessWidget {
  const TemplateSectionTitle(
    this.title, {
    super.key,
    this.action,
    this.onAction,
    this.subtitle,
  });
  final String title;
  final String? action;
  final String? subtitle;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 22, bottom: 10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                      letterSpacing: -.2,
                    ),
                  ),
                  if (subtitle != null) ...[
                    const SizedBox(height: 3),
                    Text(
                      subtitle!,
                      style: const TextStyle(
                        color: AbsColors.muted,
                        fontSize: 11.5,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            if (action != null)
              TextButton(onPressed: onAction, child: Text(action!)),
          ],
        ),
      );
}

class TemplatePill extends StatelessWidget {
  const TemplatePill(this.text, {super.key, this.color = AbsColors.cyan});
  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
        decoration: BoxDecoration(
          color: templateFade(color, .10),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: templateFade(color, .38)),
        ),
        child: Text(
          text,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: color,
            fontSize: 10,
            fontWeight: FontWeight.w800,
            letterSpacing: .25,
          ),
        ),
      );
}

class TemplateChangeBadge extends StatelessWidget {
  const TemplateChangeBadge(this.value, {super.key});
  final double value;
  @override
  Widget build(BuildContext context) {
    final color = value >= 0 ? AbsColors.green : AbsColors.red;
    return TemplatePill(
      '${value >= 0 ? '+' : ''}${value.toStringAsFixed(2)}%',
      color: color,
    );
  }
}

class TemplateMiniStat extends StatelessWidget {
  const TemplateMiniStat({super.key, required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(color: AbsColors.muted, fontSize: 10.5)),
          const SizedBox(height: 3),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700),
          ),
        ],
      );
}

class TemplateMenuTile extends StatelessWidget {
  const TemplateMenuTile({
    super.key,
    required this.icon,
    required this.title,
    required this.onTap,
    this.subtitle,
    this.trailing,
  });
  final IconData icon;
  final String title;
  final String? subtitle;
  final VoidCallback onTap;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 1),
        leading: Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: templateFade(AbsColors.cyan, .09),
            borderRadius: BorderRadius.circular(11),
          ),
          child: Icon(icon, color: AbsColors.cyanSoft, size: 20),
        ),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
        subtitle: subtitle == null
            ? null
            : Text(subtitle!, style: const TextStyle(color: AbsColors.muted, fontSize: 10.5)),
        trailing: trailing ?? const Icon(Icons.chevron_right_rounded, color: AbsColors.muted2),
        onTap: onTap,
      );
}

class TemplateRiskNotice extends StatelessWidget {
  const TemplateRiskNotice({super.key});
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 22, bottom: 8),
        child: Text(
          'Pulse provides market intelligence and decision support. It is not financial advice and does not guarantee profit.',
          textAlign: TextAlign.center,
          style: const TextStyle(color: AbsColors.muted2, fontSize: 10.5, height: 1.45),
        ),
      );
}
