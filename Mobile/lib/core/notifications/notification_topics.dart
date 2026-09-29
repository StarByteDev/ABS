/// Pulse FCM topics. Topics are PUBLIC broadcast channels: anyone with the
/// app can subscribe, so they must only ever carry public market content.
/// Private/account/security notifications are device-targeted through the
/// push_token registered on POST /devices, never through a topic.
class NotificationTopics {
  const NotificationTopics._();

  static const String marketAlerts = 'abs_market_alerts';
  static const String breakingNews = 'abs_breaking_news';
  static const String pulseSignals = 'abs_pulse_signals';
  static const String entryWatch = 'abs_entry_watch';
  static const String macroAlerts = 'abs_macro_alerts';
  static const String dailyBrief = 'abs_daily_brief';

  static const Set<String> all = {
    marketAlerts,
    breakingNews,
    pulseSignals,
    entryWatch,
    macroAlerts,
    dailyBrief,
  };

  /// Public market/news/macro/daily topics available to everyone.
  static const Set<String> guest = {
    marketAlerts,
    breakingNews,
    macroAlerts,
    dailyBrief,
  };

  /// Additional topics for signed-in accounts.
  static const Set<String> memberExtras = {pulseSignals, entryWatch};

  /// Which backend notification preference (GET /notification-preferences)
  /// controls each topic for signed-in users.
  static const Map<String, String> preferenceKey = {
    marketAlerts: 'market',
    breakingNews: 'market',
    macroAlerts: 'market',
    dailyBrief: 'daily_brief',
    pulseSignals: 'signals',
    entryWatch: 'signals',
  };

  /// The exact topic set a device should be subscribed to.
  ///
  /// Guests get the public set. Signed-in users additionally get signal and
  /// Entry Watch topics, each filtered by their saved preferences (missing
  /// preferences default to on, matching the backend defaults).
  static Set<String> desiredFor({
    required bool authenticated,
    Map<String, dynamic> preferences = const <String, dynamic>{},
  }) {
    if (!authenticated) return {...guest};
    final candidates = {...guest, ...memberExtras};
    return candidates.where((topic) {
      final value = preferences[preferenceKey[topic]];
      return value is bool ? value : true;
    }).toSet();
  }
}
