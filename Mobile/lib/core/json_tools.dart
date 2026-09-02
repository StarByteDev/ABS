class JsonTools {
  static Map<String, dynamic> map(dynamic value) {
    if (value is Map<String, dynamic>) return value;
    if (value is Map) return value.map((k, v) => MapEntry(k.toString(), v));
    return <String, dynamic>{};
  }

  static List<dynamic> list(dynamic value) => value is List ? value : <dynamic>[];

  static List<Map<String, dynamic>> mapList(dynamic value) =>
      list(value).map(map).where((e) => e.isNotEmpty).toList();

  static dynamic at(dynamic source, String path, [dynamic fallback]) {
    dynamic current = source;
    for (final part in path.split('.')) {
      if (current is Map && current.containsKey(part)) {
        current = current[part];
      } else {
        return fallback;
      }
    }
    return current ?? fallback;
  }

  static String text(dynamic value, [String fallback = '—']) {
    if (value == null) return fallback;
    final result = value.toString().trim();
    return result.isEmpty ? fallback : result;
  }

  static double number(dynamic value, [double fallback = 0]) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? fallback;
  }

  static int integer(dynamic value, [int fallback = 0]) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? fallback;
  }

  static bool boolean(dynamic value, [bool fallback = false]) {
    if (value is bool) return value;
    if (value is num) return value != 0;
    if (value is String) {
      final v = value.toLowerCase();
      if (['true', '1', 'yes', 'on'].contains(v)) return true;
      if (['false', '0', 'no', 'off'].contains(v)) return false;
    }
    return fallback;
  }

  static String plain(dynamic value, [String fallback = '']) {
    final raw = text(value, fallback);
    if (raw.isEmpty) return raw;
    return raw
        .replaceAll(RegExp(r'<br\s*/?>', caseSensitive: false), '\n')
        .replaceAll(RegExp(r'</p>', caseSensitive: false), '\n\n')
        .replaceAll(RegExp(r'<[^>]*>'), '')
        .replaceAll('&nbsp;', ' ')
        .replaceAll('&amp;', '&')
        .replaceAll('&lt;', '<')
        .replaceAll('&gt;', '>')
        .replaceAll('&quot;', '"')
        .trim();
  }

  static List<Map<String, dynamic>> pageItems(dynamic response) {
    final root = map(response);
    final data = root['data'];
    if (data is List) return mapList(data);
    if (data is Map) {
      final nested = data['data'];
      if (nested is List) return mapList(nested);
    }
    return <Map<String, dynamic>>[];
  }
}
