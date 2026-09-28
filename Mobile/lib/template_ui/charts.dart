import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

class LineChartPainter extends CustomPainter {
  LineChartPainter({
    required this.values,
    required this.color,
    this.fill = true,
    this.strokeWidth = 2.2,
    this.highlight,
    this.showDot = true,
    this.grid = false,
  });

  final List<double> values;
  final Color color;
  final bool fill;
  final double strokeWidth;
  final int? highlight;
  final bool showDot;
  final bool grid;

  @override
  void paint(Canvas canvas, Size size) {
    if (values.length < 2 || size.width <= 0) return;
    double minV = values.first, maxV = values.first;
    for (final v in values) {
      if (v < minV) minV = v;
      if (v > maxV) maxV = v;
    }
    final range = (maxV - minV) == 0 ? 1.0 : (maxV - minV);
    final padY = size.height * 0.1;
    final h = size.height - padY * 2;
    final dx = size.width / (values.length - 1);
    Offset pt(int i) => Offset(i * dx, padY + h - (values[i] - minV) / range * h);

    if (grid) {
      final gp = Paint()
        ..color = fade(AppColors.line, .8)
        ..strokeWidth = 1;
      for (int i = 1; i < 4; i++) {
        final y = size.height * i / 4;
        for (double x = 0; x < size.width; x += 8) {
          canvas.drawLine(Offset(x, y), Offset(math.min(x + 4, size.width), y), gp);
        }
      }
    }

    final path = Path()..moveTo(pt(0).dx, pt(0).dy);
    for (int i = 1; i < values.length; i++) {
      final p0 = pt(i - 1), p1 = pt(i);
      final cx = (p0.dx + p1.dx) / 2;
      path.cubicTo(cx, p0.dy, cx, p1.dy, p1.dx, p1.dy);
    }

    if (fill) {
      final fp = Path.from(path)
        ..lineTo(size.width, size.height)
        ..lineTo(0, size.height)
        ..close();
      canvas.drawPath(
        fp,
        Paint()
          ..shader = LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [fade(color, .28), fade(color, 0)],
          ).createShader(Offset.zero & size),
      );
    }

    canvas.drawPath(
      path,
      Paint()
        ..color = color
        ..style = PaintingStyle.stroke
        ..strokeWidth = strokeWidth
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round,
    );

    final hi = highlight;
    if (hi != null && hi >= 0 && hi < values.length) {
      final p = pt(hi);
      canvas.drawLine(Offset(p.dx, 0), Offset(p.dx, size.height),
          Paint()
            ..color = fade(AppColors.text, .3)
            ..strokeWidth = 1);
      canvas.drawCircle(p, 8, Paint()..color = fade(color, .3));
      canvas.drawCircle(p, 4.5, Paint()..color = color);
      canvas.drawCircle(p, 2, Paint()..color = AppColors.bg);
    } else if (showDot) {
      final p = pt(values.length - 1);
      canvas.drawCircle(p, 7, Paint()..color = fade(color, .25));
      canvas.drawCircle(p, 3.5, Paint()..color = color);
    }
  }

  @override
  bool shouldRepaint(covariant LineChartPainter old) =>
      old.values != values || old.color != color || old.highlight != highlight;
}

/// Chart with drag-to-inspect. Reports the touched index through [onHover].
class InteractiveLineChart extends StatefulWidget {
  const InteractiveLineChart({
    super.key,
    required this.values,
    required this.color,
    this.onHover,
  });
  final List<double> values;
  final Color color;
  final ValueChanged<int?>? onHover;

  @override
  State<InteractiveLineChart> createState() => _InteractiveLineChartState();
}

class _InteractiveLineChartState extends State<InteractiveLineChart> {
  int? _idx;

  @override
  void didUpdateWidget(covariant InteractiveLineChart oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.values != widget.values) _idx = null;
  }

  void _update(Offset p, double width) {
    final n = widget.values.length;
    if (n < 2 || width <= 0) return;
    final i = ((p.dx / width) * (n - 1)).round().clamp(0, n - 1).toInt();
    if (i != _idx) {
      setState(() => _idx = i);
      widget.onHover?.call(i);
    }
  }

  void _end() {
    if (_idx == null) return;
    setState(() => _idx = null);
    widget.onHover?.call(null);
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, c) => GestureDetector(
        behavior: HitTestBehavior.opaque,
        onHorizontalDragStart: (d) => _update(d.localPosition, c.maxWidth),
        onHorizontalDragUpdate: (d) => _update(d.localPosition, c.maxWidth),
        onHorizontalDragEnd: (_) => _end(),
        onHorizontalDragCancel: _end,
        onTapDown: (d) => _update(d.localPosition, c.maxWidth),
        onTapUp: (_) => _end(),
        child: CustomPaint(
          size: Size(c.maxWidth, c.maxHeight),
          painter: LineChartPainter(
            values: widget.values,
            color: widget.color,
            highlight: _idx,
            grid: true,
          ),
        ),
      ),
    );
  }
}

class Sparkline extends StatelessWidget {
  const Sparkline({super.key, required this.values, required this.color});
  final List<double> values;
  final Color color;

  @override
  Widget build(BuildContext context) => CustomPaint(
        size: Size.infinite,
        painter: LineChartPainter(
            values: values, color: color, strokeWidth: 1.6, showDot: false),
      );
}

/// Semicircle gauge from 0 (red) to 100 (green).
class PulseGauge extends StatelessWidget {
  const PulseGauge({super.key, required this.score});
  final int score;

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Positioned.fill(child: CustomPaint(painter: _GaugePainter(score / 100))),
        Positioned(
          left: 0,
          right: 0,
          bottom: 0,
          child: Text('$score',
              textAlign: TextAlign.center,
              style: AppText.figure.copyWith(fontSize: 22)),
        ),
      ],
    );
  }
}

class _GaugePainter extends CustomPainter {
  _GaugePainter(this.value);
  final double value;

  @override
  void paint(Canvas canvas, Size size) {
    final stroke = size.height * 0.15;
    final r = math.min(size.width / 2 - stroke / 2, size.height - stroke);
    final center = Offset(size.width / 2, size.height - stroke / 2);
    final rect = Rect.fromCircle(center: center, radius: r);

    canvas.drawArc(rect, math.pi, math.pi, false,
        Paint()
          ..color = AppColors.line
          ..style = PaintingStyle.stroke
          ..strokeWidth = stroke
          ..strokeCap = StrokeCap.round);

    canvas.drawArc(rect, math.pi, math.pi * value.clamp(0.01, 1.0).toDouble(), false,
        Paint()
          ..shader = const SweepGradient(
            startAngle: math.pi,
            endAngle: math.pi * 2,
            colors: [AppColors.down, AppColors.amber, AppColors.up],
          ).createShader(rect)
          ..style = PaintingStyle.stroke
          ..strokeWidth = stroke
          ..strokeCap = StrokeCap.round);

    final a = math.pi + math.pi * value.clamp(0.0, 1.0).toDouble();
    final knob = center + Offset(math.cos(a), math.sin(a)) * r;
    canvas.drawCircle(knob, stroke * 0.7, Paint()..color = AppColors.bg);
    canvas.drawCircle(knob, stroke * 0.45, Paint()..color = AppColors.text);
  }

  @override
  bool shouldRepaint(covariant _GaugePainter old) => old.value != value;
}

class ConfidenceRing extends StatelessWidget {
  const ConfidenceRing({
    super.key,
    required this.value,
    this.size = 46,
    this.stroke = 4.5,
    this.caption,
  });
  final int value;
  final double size;
  final double stroke;
  final String? caption;

  Color get color => value >= 75
      ? AppColors.up
      : value >= 60
          ? AppColors.accent
          : AppColors.amber;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: CustomPaint(
        painter: _RingPainter(value / 100, color, stroke),
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('$value',
                  style: AppText.figure.copyWith(fontSize: size * 0.3, color: color)),
              if (caption != null)
                Text(caption!,
                    style: TextStyle(fontSize: size * 0.12, color: AppColors.muted)),
            ],
          ),
        ),
      ),
    );
  }
}

class _RingPainter extends CustomPainter {
  _RingPainter(this.value, this.color, this.stroke);
  final double value;
  final Color color;
  final double stroke;

  @override
  void paint(Canvas canvas, Size size) {
    final rect = (Offset.zero & size).deflate(stroke / 2);
    canvas.drawArc(rect, 0, math.pi * 2, false,
        Paint()
          ..color = AppColors.line
          ..style = PaintingStyle.stroke
          ..strokeWidth = stroke);
    canvas.drawArc(rect, -math.pi / 2, math.pi * 2 * value, false,
        Paint()
          ..color = color
          ..style = PaintingStyle.stroke
          ..strokeWidth = stroke
          ..strokeCap = StrokeCap.round);
  }

  @override
  bool shouldRepaint(covariant _RingPainter old) =>
      old.value != value || old.color != color;
}

/// Horizontal bar split into proportional coloured parts.
class SplitBar extends StatelessWidget {
  const SplitBar({super.key, required this.parts, this.height = 8});
  final List<(double, Color)> parts;
  final double height;

  @override
  Widget build(BuildContext context) {
    final total = parts.fold<double>(0, (a, p) => a + p.$1);
    return ClipRRect(
      borderRadius: BorderRadius.circular(height),
      child: SizedBox(
        height: height,
        child: Row(
          children: [
            for (int i = 0; i < parts.length; i++) ...[
              if (i > 0) const SizedBox(width: 3),
              Expanded(
                flex: math.max(1, (parts[i].$1 / (total == 0 ? 1 : total) * 1000).round()),
                child: ColoredBox(color: parts[i].$2),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Decorative QR-style block for the payment screen (not scannable).
class FakeQr extends StatelessWidget {
  const FakeQr({super.key, required this.data});
  final String data;

  @override
  Widget build(BuildContext context) => CustomPaint(
      painter: _QrPainter(data.codeUnits.fold<int>(0, (a, b) => (a * 31 + b) % 1000003)));
}

class _QrPainter extends CustomPainter {
  _QrPainter(this.seed);
  final int seed;

  @override
  void paint(Canvas canvas, Size size) {
    const n = 25;
    final cell = size.width / n;
    final r = math.Random(seed);
    final black = Paint()..color = const Color(0xFF07111F);
    final white = Paint()..color = Colors.white;
    bool inFinder(int x, int y) =>
        (x < 8 && y < 8) || (x >= n - 8 && y < 8) || (x < 8 && y >= n - 8);
    for (int y = 0; y < n; y++) {
      for (int x = 0; x < n; x++) {
        if (!inFinder(x, y) && r.nextBool()) {
          canvas.drawRect(Rect.fromLTWH(x * cell, y * cell, cell, cell), black);
        }
      }
    }
    void finder(int ox, int oy) {
      canvas.drawRect(Rect.fromLTWH(ox * cell, oy * cell, cell * 7, cell * 7), black);
      canvas.drawRect(Rect.fromLTWH((ox + 1) * cell, (oy + 1) * cell, cell * 5, cell * 5), white);
      canvas.drawRect(Rect.fromLTWH((ox + 2) * cell, (oy + 2) * cell, cell * 3, cell * 3), black);
    }
    finder(0, 0);
    finder(n - 7, 0);
    finder(0, n - 7);
  }

  @override
  bool shouldRepaint(covariant _QrPainter old) => old.seed != seed;
}
