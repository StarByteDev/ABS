/// Everything ABS is willing to be told to open from a notification.
///
/// An unknown or malformed payload never crashes and never guesses: it falls
/// back to the nearest safe parent screen. No route here performs a trade,
/// an execution step or any irreversible account action.
enum AbsNotificationType {
  marketMove,
  qualifiedSignal,
  entryWatch,
  macroEvent,
  breakingNews,
  dailyBrief,
  packageExpiry,
  security,
  unknown,
}

/// Where a notification may send the user.
enum AbsNotificationRoute {
  pulse,
  markets,
  marketDetail,
  signals,
  signalDetail,
  calendar,
  news,
  liveNews,
  account,

  /// Nothing actionable — just open the app where it already was.
  none,
}

/// A validated FCM payload.
class AbsNotificationPayload {
  const AbsNotificationPayload({
    required this.type,
    required this.route,
    this.symbol,
    this.signalId,
    this.eventId,
    this.url,
    this.title,
    this.body,
  });

  final AbsNotificationType type;
  final AbsNotificationRoute route;
  final String? symbol;
  final int? signalId;
  final String? eventId;
  final String? url;
  final String? title;
  final String? body;

  static const empty = AbsNotificationPayload(
    type: AbsNotificationType.unknown,
    route: AbsNotificationRoute.none,
  );

  bool get isActionable => route != AbsNotificationRoute.none;

  /// Parses an FCM `data` map.
  ///
  /// The `type` decides the default destination; an explicit `route` may
  /// narrow it, but only to a route that type is allowed to reach. Required
  /// arguments are validated, and a route missing its argument degrades to the
  /// nearest safe parent (signal_detail without an id becomes the signals
  /// list, market_detail without a symbol becomes the markets list).
  factory AbsNotificationPayload.fromData(Map<String, dynamic> data) {
    String? text(String key) {
      final raw = data[key];
      if (raw == null) return null;
      final value = '$raw'.trim();
      return value.isEmpty ? null : value;
    }

    final type = _typeFrom(text('type'));
    final symbol = _safeSymbol(text('symbol'));
    final signalId = int.tryParse(text('signal_id') ?? '');
    final eventId = text('event_id');
    final url = _safeUrl(text('url'));
    final requested = _routeFrom(text('route'));

    var route = requested ?? _defaultRouteFor(type);
    if (!_allowedRoutes(type).contains(route)) {
      route = _defaultRouteFor(type);
    }

    // Degrade to the nearest safe parent when the argument is missing.
    if (route == AbsNotificationRoute.signalDetail &&
        (signalId == null || signalId <= 0)) {
      route = AbsNotificationRoute.signals;
    }
    if (route == AbsNotificationRoute.marketDetail && symbol == null) {
      route = AbsNotificationRoute.markets;
    }

    return AbsNotificationPayload(
      type: type,
      route: route,
      symbol: symbol,
      signalId: signalId != null && signalId > 0 ? signalId : null,
      eventId: eventId,
      url: url,
      title: text('title'),
      body: text('body'),
    );
  }

  static AbsNotificationType _typeFrom(String? raw) =>
      switch (raw?.toLowerCase()) {
        'market_move' => AbsNotificationType.marketMove,
        'qualified_signal' => AbsNotificationType.qualifiedSignal,
        'entry_watch' => AbsNotificationType.entryWatch,
        'macro_event' => AbsNotificationType.macroEvent,
        'breaking_news' => AbsNotificationType.breakingNews,
        'daily_brief' => AbsNotificationType.dailyBrief,
        'package_expiry' => AbsNotificationType.packageExpiry,
        'security' => AbsNotificationType.security,
        _ => AbsNotificationType.unknown,
      };

  static AbsNotificationRoute? _routeFrom(String? raw) =>
      switch (raw?.toLowerCase()) {
        'pulse' || 'home' || 'dashboard' => AbsNotificationRoute.pulse,
        'markets' => AbsNotificationRoute.markets,
        'market_detail' => AbsNotificationRoute.marketDetail,
        'signals' => AbsNotificationRoute.signals,
        'signal_detail' => AbsNotificationRoute.signalDetail,
        'calendar' => AbsNotificationRoute.calendar,
        'news' => AbsNotificationRoute.news,
        'live_news' || 'live' => AbsNotificationRoute.liveNews,
        'account' || 'more' => AbsNotificationRoute.account,
        _ => null,
      };

  /// A campaign sent from the Firebase console carries no ABS type, so it
  /// simply opens the app rather than guessing a destination.
  static AbsNotificationRoute _defaultRouteFor(AbsNotificationType type) =>
      switch (type) {
        AbsNotificationType.marketMove => AbsNotificationRoute.marketDetail,
        AbsNotificationType.qualifiedSignal =>
          AbsNotificationRoute.signalDetail,
        AbsNotificationType.entryWatch => AbsNotificationRoute.signals,
        AbsNotificationType.macroEvent => AbsNotificationRoute.calendar,
        AbsNotificationType.breakingNews => AbsNotificationRoute.news,
        AbsNotificationType.dailyBrief => AbsNotificationRoute.pulse,
        AbsNotificationType.packageExpiry => AbsNotificationRoute.account,
        AbsNotificationType.security => AbsNotificationRoute.account,
        AbsNotificationType.unknown => AbsNotificationRoute.none,
      };

  /// A type may never be redirected somewhere unrelated by a crafted payload.
  static Set<AbsNotificationRoute> _allowedRoutes(AbsNotificationType type) =>
      switch (type) {
        AbsNotificationType.marketMove => {
          AbsNotificationRoute.marketDetail,
          AbsNotificationRoute.markets,
          AbsNotificationRoute.pulse,
        },
        AbsNotificationType.qualifiedSignal || AbsNotificationType.entryWatch =>
          {AbsNotificationRoute.signalDetail, AbsNotificationRoute.signals},
        AbsNotificationType.macroEvent => {AbsNotificationRoute.calendar},
        AbsNotificationType.breakingNews => {
          AbsNotificationRoute.news,
          AbsNotificationRoute.liveNews,
        },
        AbsNotificationType.dailyBrief => {AbsNotificationRoute.pulse},
        AbsNotificationType.packageExpiry ||
        AbsNotificationType.security => {AbsNotificationRoute.account},
        AbsNotificationType.unknown => {AbsNotificationRoute.none},
      };

  /// Symbols are used to build a market screen, so only plain tickers pass.
  static String? _safeSymbol(String? raw) {
    if (raw == null) return null;
    final value = raw.toUpperCase();
    if (value.length > 20) return null;
    return RegExp(r'^[A-Z0-9]{2,20}$').hasMatch(value) ? value : null;
  }

  /// Only absolute http(s) links are ever opened from a notification.
  static String? _safeUrl(String? raw) {
    if (raw == null) return null;
    final uri = Uri.tryParse(raw);
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) return null;
    return (uri.scheme == 'http' || uri.scheme == 'https')
        ? uri.toString()
        : null;
  }
}
