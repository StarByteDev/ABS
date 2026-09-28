import 'dart:async';

import 'package:flutter/widgets.dart';

import '../../core/api_client.dart';
import '../../core/json_tools.dart';
import '../../core/session.dart';
import '../data/mock_data.dart';

class PointsEntry {
  PointsEntry(this.label, this.amount, this.at);
  final String label;
  final int amount;
  final DateTime at;
}

class PriceAlert {
  PriceAlert({required this.symbol, required this.above, required this.price, this.active = true})
      : id = '${DateTime.now().microsecondsSinceEpoch}-${_seq++}';
  static int _seq = 0;
  final String id;
  final String symbol;
  final bool above;
  final double price;
  bool active;
}

/// Template-compatible state facade backed by the production [AppSession].
/// The supplied template can therefore remain the visible app while the real
/// ABS V15.7.4 API remains the single source of truth.
class AppState extends ChangeNotifier {
  AppState(this.session) {
    session.addListener(_sessionChanged);
  }

  final AppSession session;

  bool homeLoading = true;
  bool pulseLoading = true;
  bool newsLoading = true;
  String? homeError;
  String? pulseError;
  String? newsError;
  Map<String, dynamic> membership = <String, dynamic>{};

  final Set<String> watchlist = <String>{};
  final Set<String> savedNews = <String>{};
  final List<PriceAlert> alerts = <PriceAlert>[];
  final List<PointsEntry> history = <PointsEntry>[];
  bool notifications = true;
  bool checkedInToday = false;
  bool freeSignalUnlocked = false;
  int points = 0;
  int streak = 0;

  bool _homeRequested = false;
  bool _pulseRequested = false;
  bool _newsRequested = false;

  String get userName {
    final user = session.user ?? const <String, dynamic>{};
    return JsonTools.text(user['name'], JsonTools.text(user['first_name'], session.authenticated ? 'ABS Trader' : 'Guest'));
  }

  String get email => JsonTools.text((session.user ?? const <String, dynamic>{})['email'], session.authenticated ? '' : 'Public access');
  bool get signedIn => session.authenticated;
  bool get emailVerified => session.emailVerified;
  bool get limitedAccount => session.limitedAccount;
  bool get hasPulse => session.hasPulseAccess;

  String? get activePlanId {
    final access = JsonTools.map(membership['access']);
    final plan = JsonTools.map(access['plan']);
    return plan.isEmpty ? null : JsonTools.text(plan['slug'] ?? plan['id'] ?? plan['code'], 'pulse');
  }

  DateTime? get planExpiresAt {
    final access = JsonTools.map(membership['access']);
    final raw = JsonTools.text(access['ends_at'] ?? access['expires_at'], '');
    return raw.isEmpty ? null : DateTime.tryParse(raw.replaceFirst(' ', 'T'))?.toLocal();
  }

  int get daysLeft {
    final end = planExpiresAt;
    if (end == null) return 0;
    final hours = end.difference(DateTime.now()).inHours;
    return hours <= 0 ? 0 : (hours / 24).ceil();
  }

  int get planDays {
    final access = JsonTools.map(membership['access']);
    final days = JsonTools.integer(access['duration_days'] ?? access['plan_days']);
    return days > 0 ? days : daysLeft;
  }

  String? get pendingPlanId {
    final requests = JsonTools.mapList(membership['requests']);
    for (final request in requests) {
      final status = JsonTools.text(request['status']).toLowerCase();
      if (status == 'submitted' || status == 'under_review' || status == 'pending') {
        return JsonTools.text(request['plan_slug'] ?? request['plan_id'] ?? request['plan_name'], 'pulse');
      }
    }
    return null;
  }

  Future<void> initialize() async {
    await refreshHome();
    unawaited(refreshNews());
    if (session.authenticated) {
      unawaited(refreshAccount());
      if (session.emailVerified) unawaited(refreshPulse());
    }
  }

  void _sessionChanged() {
    notifyListeners();
  }

  Future<void> ensureHome() async {
    if (_homeRequested) return;
    _homeRequested = true;
    await refreshHome();
  }

  Future<void> ensurePulse() async {
    if (_pulseRequested) return;
    _pulseRequested = true;
    await refreshPulse();
  }

  Future<void> ensureNews() async {
    if (_newsRequested) return;
    _newsRequested = true;
    await refreshNews();
  }

  Future<void> refreshHome() async {
    homeLoading = true;
    homeError = null;
    notifyListeners();
    try {
      final results = await Future.wait<dynamic>([
        _safeGet('/market/overview'),
        _safeGet('/market/movers'),
        _safeGet('/market/chart/BTCUSDT', query: const <String, dynamic>{'interval': '1h', 'limit': 96}),
        _safeGet('/news'),
      ]);
      final overview = JsonTools.map(JsonTools.at(results[0], 'data', results[0]));
      if (overview.isNotEmpty) MockData.applyMarketOverview(overview);
      final movers = JsonTools.map(JsonTools.at(results[1], 'data', results[1]));
      if (movers.isNotEmpty) MockData.applyMovers(movers);
      _applyChartPayload('1D', results[2]);
      if (results[3] != null) MockData.applyNews(results[3]);
      if (session.authenticated) await refreshAccount(notify: false);
    } on ApiException catch (e) {
      homeError = e.message;
    } catch (_) {
      homeError = 'ABS market intelligence is temporarily unavailable.';
    } finally {
      homeLoading = false;
      notifyListeners();
    }
  }

  Future<void> loadBtcSeries(String tf) async {
    const config = <String, Map<String, dynamic>>{
      '1H': <String, dynamic>{'interval': '1m', 'limit': 60},
      '1D': <String, dynamic>{'interval': '15m', 'limit': 96},
      '1W': <String, dynamic>{'interval': '4h', 'limit': 42},
      '1M': <String, dynamic>{'interval': '1d', 'limit': 31},
      '1Y': <String, dynamic>{'interval': '1d', 'limit': 365},
      'ALL': <String, dynamic>{'interval': '1w', 'limit': 260},
    };
    final cfg = config[tf] ?? config['1D']!;
    try {
      final response = await session.api.get('/market/chart/BTCUSDT', query: cfg);
      _applyChartPayload(tf, response);
      notifyListeners();
    } on ApiException {
      // Keep the last successful server series instead of fabricating prices.
    }
  }

  void _applyChartPayload(String tf, dynamic payload) {
    final root = JsonTools.map(JsonTools.at(payload, 'data', payload));
    final candles = JsonTools.mapList(root['candles']);
    final values = candles.map((r) => JsonTools.number(r['close'])).where((v) => v > 0).toList();
    if (values.length >= 2) MockData.setBtcSeries(tf, values);
  }

  Future<void> refreshPulse() async {
    pulseLoading = true;
    pulseError = null;
    notifyListeners();
    if (!session.authenticated || !session.emailVerified || !session.hasPulseAccess) {
      MockData.signals = <Signal>[];
      pulseLoading = false;
      notifyListeners();
      return;
    }
    try {
      final results = await Future.wait<dynamic>([
        _safeGet('/pulse/signals/overview', query: const <String, dynamic>{'status': 'active'}),
        _safeGet('/watchlist'),
      ]);
      if (results[0] != null) MockData.applySignals(results[0]);
      _applyWatchlist(results[1]);
    } on ApiException catch (e) {
      pulseError = e.message;
    } finally {
      pulseLoading = false;
      notifyListeners();
    }
  }

  Future<void> refreshNews() async {
    newsLoading = true;
    newsError = null;
    notifyListeners();
    try {
      final now = DateTime.now();
      final today = DateTime(now.year, now.month, now.day);
      // Query history and upcoming releases separately. The ABS calendar API
      // may place them under different collection keys and historical rows are
      // the ones that carry Actual / Forecast / Previous release values.
      final results = await Future.wait<dynamic>([
        _safeGet('/news'),
        _safeGet('/news/live'),
        _safeGet('/economic-calendar', query: <String, dynamic>{
          'from': _apiDate(today.subtract(const Duration(days: 45))),
          'to': _apiDate(today.subtract(const Duration(days: 1))),
        }),
        _safeGet('/economic-calendar', query: <String, dynamic>{
          'from': _apiDate(today),
          'to': _apiDate(today.add(const Duration(days: 60))),
        }),
      ]);
      if (results[0] != null) MockData.applyNews(results[0]);
      if (results[1] != null) MockData.applyNews(results[1], live: true);
      // Calendar providers do not all wrap events the same way. Some ABS
      // responses use data/events, others split historical/upcoming releases,
      // and some provider payloads nest release figures several levels deep.
      // Collect every object that actually looks like a calendar event instead
      // of taking only the first list found in the response.
      final combined = <Map<String, dynamic>>[
        ..._calendarRows(results[2]),
        ..._calendarRows(results[3]),
      ];

      // The same release may appear in both historical and upcoming/result
      // collections. Merge duplicates rather than dropping the second copy:
      // one copy can contain schedule/context while another contains the final
      // Actual / Forecast / Previous figures.
      final merged = <String, Map<String, dynamic>>{};
      for (final row in combined) {
        final key = _calendarIdentity(row);
        final current = merged[key];
        merged[key] = current == null
            ? Map<String, dynamic>.from(row)
            : _deepMergeCalendar(current, row);
      }
      MockData.applyCalendar(merged.values.where(_calendarRenderable).toList());
    } on ApiException catch (e) {
      newsError = e.message;
    } catch (_) {
      newsError = 'Pulse Intelligence is temporarily unavailable.';
    } finally {
      newsLoading = false;
      notifyListeners();
    }
  }

  Future<void> refreshAccount({bool notify = true}) async {
    if (!session.authenticated) {
      membership = <String, dynamic>{};
      watchlist.clear();
      if (notify) notifyListeners();
      return;
    }
    try {
      if (session.emailVerified) {
        final results = await Future.wait<dynamic>([
          _safeGet('/pulse/membership'),
          _safeGet('/watchlist'),
        ]);
        membership = JsonTools.map(JsonTools.at(results[0], 'data', <String, dynamic>{}));
        MockData.applyPlans(membership);
        _applyWatchlist(results[1]);
      }
    } catch (_) {
      // Account extras must never block the public template shell.
    }
    if (notify) notifyListeners();
  }

  void _applyWatchlist(dynamic payload) {
    final rows = JsonTools.mapList(JsonTools.at(payload, 'data', <dynamic>[]));
    watchlist
      ..clear()
      ..addAll(rows.map((row) => JsonTools.text(row['symbol']).replaceAll('USDT', '').replaceAll('/', '').toUpperCase()).where((s) => s.isNotEmpty));
  }

  Future<dynamic> _safeGet(String path, {Map<String, dynamic>? query}) async {
    try {
      return await session.api.get(path, query: query);
    } on ApiException catch (e) {
      if (e.statusCode == 401 || e.statusCode == 403 || e.statusCode == 404) return <String, dynamic>{};
      rethrow;
    }
  }

  Future<void> setName(String name) async {
    if (!session.authenticated || name.trim().isEmpty) return;
    try {
      await session.api.patch('/profile', body: <String, dynamic>{'name': name.trim()});
      await session.refreshAccount();
      notifyListeners();
    } on ApiException {
      rethrow;
    }
  }

  Future<void> signOut() async {
    await session.logout();
    membership = <String, dynamic>{};
    watchlist.clear();
    MockData.signals = <Signal>[];
    notifyListeners();
  }

  Future<void> toggleWatch(String symbol) async {
    if (!session.authenticated || !session.emailVerified) return;
    final base = symbol.replaceAll('USDT', '').replaceAll('/', '').toUpperCase();
    final pair = '${base}USDT';
    final had = watchlist.contains(base);
    if (had) {
      watchlist.remove(base);
    } else {
      watchlist.add(base);
    }
    notifyListeners();
    try {
      if (had) {
        await session.api.delete('/watchlist/$pair');
      } else {
        await session.api.post('/watchlist', body: <String, dynamic>{'symbol': pair});
      }
    } on ApiException {
      if (had) {
        watchlist.add(base);
      } else {
        watchlist.remove(base);
      }
      notifyListeners();
      rethrow;
    }
  }

  void toggleSaved(String newsId) {
    if (!savedNews.remove(newsId)) savedNews.add(newsId);
    notifyListeners();
  }

  void setNotifications(bool value) {
    notifications = value;
    notifyListeners();
  }

  void addAlert(PriceAlert alert) {
    alerts.insert(0, alert);
    notifyListeners();
  }

  void removeAlert(PriceAlert alert) {
    alerts.remove(alert);
    notifyListeners();
  }

  void toggleAlert(PriceAlert alert, bool value) {
    alert.active = value;
    notifyListeners();
  }

  // Compatibility shims retained for original template widgets that are not
  // part of the production flow. The retired points economy is not surfaced.
  void earn(int amount, String label) {}
  bool spend(int amount, String label) => false;
  void checkIn() {}
  void unlockFreeSignal() { freeSignalUnlocked = true; notifyListeners(); }
  void submitPayment(String planId, int days) {}
  void approvePending() {}
  void activate(String planId, int days) {}


  static List<Map<String, dynamic>> _calendarRows(dynamic response) {
    final rows = <Map<String, dynamic>>[];

    bool looksLikeEvent(Map<String, dynamic> row) {
      final hasTitle = <String>['title', 'event', 'name', 'event_name', 'eventName']
          .any((key) => row[key] != null && row[key].toString().trim().isNotEmpty);
      final hasDate = <String>[
        'event_at', 'scheduled_at', 'release_at', 'scheduled_for', 'datetime',
        'date_time', 'dateTime', 'event_datetime', 'event_date_time', 'date',
        'event_date', 'release_date', 'timestamp',
      ].any((key) => row[key] != null && row[key].toString().trim().isNotEmpty);
      return hasTitle && hasDate;
    }

    void visit(dynamic value, int depth) {
      if (value == null || depth > 8) return;
      if (value is List) {
        for (final item in value) {
          visit(item, depth + 1);
        }
        return;
      }
      if (value is! Map) return;
      final map = JsonTools.map(value);
      final id = JsonTools.text(map['id'] ?? map['event_id'] ?? map['uuid'] ?? map['eventId'], '');
      final normalizedKeys = map.keys
          .map((key) => key.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]'), ''))
          .toSet();
      final hasReleaseFigures = normalizedKeys.any((key) =>
          key.contains('actual') || key.contains('forecast') || key.contains('previous') ||
          key.contains('consensus') || key.contains('prior') || key == 'figures' ||
          key == 'releasevalues' || key == 'values');
      if (looksLikeEvent(map) || (id.isNotEmpty && hasReleaseFigures)) {
        // Result-only fragments can be merged by event id with their schedule
        // row later, which preserves provider feeds that separate event details
        // from Actual / Forecast / Previous values.
        rows.add(map);
        return;
      }
      for (final entry in map.entries) {
        final key = entry.key.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]'), '');
        if (<String>{'meta', 'pagination', 'links'}.contains(key) && entry.value is Map) continue;
        visit(entry.value, depth + 1);
      }
    }

    visit(response, 0);
    return rows;
  }

  static bool _calendarRenderable(Map<String, dynamic> row) {
    final hasTitle = <String>['title', 'event', 'name', 'event_name', 'eventName']
        .any((key) => row[key] != null && row[key].toString().trim().isNotEmpty);
    final hasDate = <String>[
      'event_at', 'scheduled_at', 'release_at', 'scheduled_for', 'datetime',
      'date_time', 'dateTime', 'event_datetime', 'event_date_time', 'date',
      'event_date', 'release_date', 'timestamp',
    ].any((key) => row[key] != null && row[key].toString().trim().isNotEmpty);
    return hasTitle && hasDate;
  }

  static String _calendarIdentity(Map<String, dynamic> row) {
    final id = JsonTools.text(row['id'] ?? row['event_id'] ?? row['uuid'] ?? row['eventId'], '');
    if (id.isNotEmpty) return 'id:$id';
    final title = JsonTools.text(
      row['title'] ?? row['event'] ?? row['name'] ?? row['event_name'] ?? row['eventName'],
      '',
    ).toLowerCase().replaceAll(RegExp(r'\s+'), ' ').trim();
    final at = JsonTools.text(
      row['event_at'] ?? row['scheduled_at'] ?? row['release_at'] ?? row['scheduled_for'] ??
          row['datetime'] ?? row['date_time'] ?? row['dateTime'] ?? row['event_datetime'] ??
          row['event_date_time'] ?? row['date'] ?? row['event_date'] ?? row['release_date'] ?? row['timestamp'],
      '',
    );
    return '$at|$title';
  }

  static bool _calendarMissing(dynamic value) {
    if (value == null) return true;
    if (value is Map) return value.isEmpty;
    if (value is List) return value.isEmpty;
    final text = value.toString().trim().toLowerCase();
    return text.isEmpty || <String>{'null', 'n/a', 'na', '-', '—', 'pending', 'not available'}.contains(text);
  }

  static Map<String, dynamic> _deepMergeCalendar(
    Map<String, dynamic> base,
    Map<String, dynamic> incoming,
  ) {
    final out = Map<String, dynamic>.from(base);
    for (final entry in incoming.entries) {
      final oldValue = out[entry.key];
      final newValue = entry.value;
      if (oldValue is Map && newValue is Map) {
        out[entry.key] = _deepMergeCalendar(JsonTools.map(oldValue), JsonTools.map(newValue));
      } else if (_calendarMissing(oldValue) && !_calendarMissing(newValue)) {
        out[entry.key] = newValue;
      } else if (!out.containsKey(entry.key)) {
        out[entry.key] = newValue;
      }
    }
    return out;
  }

  String _apiDate(DateTime d) => '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  @override
  void dispose() {
    session.removeListener(_sessionChanged);
    super.dispose();
  }
}

class AppScope extends InheritedNotifier<AppState> {
  const AppScope({super.key, required super.notifier, required super.child});

  static AppState of(BuildContext context) => context.dependOnInheritedWidgetOfExactType<AppScope>()!.notifier!;
  static AppState read(BuildContext context) => context.getInheritedWidgetOfExactType<AppScope>()!.notifier!;
}
