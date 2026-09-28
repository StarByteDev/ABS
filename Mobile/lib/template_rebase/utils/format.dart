String fmtNum(double v, {int decimals = 2}) {
  if (!v.isFinite) return '—';
  final s = v.toStringAsFixed(decimals);
  final parts = s.split('.');
  final neg = parts[0].startsWith('-');
  final digits = neg ? parts[0].substring(1) : parts[0];
  final buf = StringBuffer();
  for (int i = 0; i < digits.length; i++) {
    if (i > 0 && (digits.length - i) % 3 == 0) buf.write(',');
    buf.write(digits[i]);
  }
  final frac = parts.length > 1 ? '.${parts[1]}' : '';
  return '${neg ? '-' : ''}$buf$frac';
}

String fmtPrice(double v) {
  if (!v.isFinite || v <= 0) return '—';
  if (v >= 1000) return fmtNum(v);
  if (v >= 1) return v.toStringAsFixed(2);
  return v.toStringAsFixed(4);
}

String fmtPct(double v, {int decimals = 2}) {
  if (!v.isFinite) return '—';
  return '${v >= 0 ? '+' : ''}${v.toStringAsFixed(decimals)}%';
}

String fmtCompact(double v, {String prefix = ''}) {
  if (!v.isFinite) return '—';
  final a = v.abs();
  final sign = v < 0 ? '-' : '';
  String s;
  if (a >= 1e12) {
    s = '${(a / 1e12).toStringAsFixed(2)}T';
  } else if (a >= 1e9) {
    s = '${(a / 1e9).toStringAsFixed(2)}B';
  } else if (a >= 1e6) {
    s = '${(a / 1e6).toStringAsFixed(1)}M';
  } else if (a >= 1e3) {
    s = '${(a / 1e3).toStringAsFixed(1)}K';
  } else {
    s = a.toStringAsFixed(2);
  }
  return '$sign$prefix$s';
}

String timeAgo(int minutes) {
  if (minutes < 1) return 'just now';
  if (minutes < 60) return '${minutes}m ago';
  if (minutes < 1440) return '${minutes ~/ 60}h ago';
  return '${minutes ~/ 1440}d ago';
}

const _months = <String>[
  'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
  'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
];

String fmtDate(DateTime d) => '${d.day} ${_months[d.month - 1]} ${d.year}';
