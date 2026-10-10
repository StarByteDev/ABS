import '../core/safe_storage.dart';
import 'notification_config.dart';

/// A user-facing notification category.
///
/// [topic] is non-null only for genuinely broadcast categories. Personal
/// notifications (security, package expiry) are delivered to a device token by
/// the backend and deliberately carry no topic — a topic would leak who is
/// subscribed and is not per-user targetable.
enum NotificationCategory {
  rapidMarketMoves(
    key: 'rapid_market_moves',
    label: 'Rapid Market Moves',
    description: 'BTC, ETH and tracked markets moving sharply.',
    group: 'MARKET',
    topic: 'abs_market_alerts',
  ),
  breakingNews(
    key: 'breaking_news',
    label: 'Breaking Market News',
    description: 'Headlines Pulse judges genuinely significant.',
    group: 'MARKET',
    topic: 'abs_breaking_news',
  ),
  qualifiedSignals(
    key: 'qualified_signals',
    label: 'Qualified Pulse Signals',
    description: 'A setup that met the full Pulse qualification threshold.',
    group: 'PULSE',
    topic: 'abs_pulse_signals',
  ),
  entryWatch(
    key: 'entry_watch',
    label: 'Entry Watch alerts',
    description: 'Watch-only setups below the qualification threshold.',
    group: 'PULSE',
    topic: 'abs_entry_watch',
    defaultEnabled: false,
  ),
  macroEvents(
    key: 'macro_events',
    label: 'High-impact Economic Events',
    description: 'CPI, PPI, FOMC and similar, shortly before release.',
    group: 'MACRO',
    topic: 'abs_macro_alerts',
  ),
  dailyBrief(
    key: 'daily_brief',
    label: 'Daily Pulse Brief',
    description: 'One market summary a day. Never a "come back" nudge.',
    group: 'DAILY',
    topic: 'abs_daily_brief',
  ),
  packageExpiry(
    key: 'package_expiry',
    label: 'Package Expiry',
    description: 'Before your Pulse package lapses.',
    group: 'ACCOUNT',
  ),
  security(
    key: 'security',
    label: 'Security & Login Alerts',
    description: 'New devices and security-relevant account changes.',
    group: 'ACCOUNT',
    mandatory: true,
  );

  const NotificationCategory({
    required this.key,
    required this.label,
    required this.description,
    required this.group,
    this.topic,
    this.defaultEnabled = true,
    this.mandatory = false,
  });

  final String key;
  final String label;
  final String description;
  final String group;

  /// Broadcast FCM topic, or null for personally targeted notifications.
  final String? topic;
  final bool defaultEnabled;

  /// Security alerts protect the account and cannot be switched off.
  final bool mandatory;

  String get storageKey => 'abs_notify_$key';

  /// Whether Remote Config currently permits this category at all.
  bool allowedBy(NotificationConfig config) {
    if (!config.enabled) return false;
    return switch (this) {
      NotificationCategory.rapidMarketMoves => config.marketFastMoveEnabled,
      NotificationCategory.breakingNews => config.breakingNewsEnabled,
      NotificationCategory.qualifiedSignals => config.qualifiedSignalEnabled,
      NotificationCategory.entryWatch => config.entryWatchEnabled,
      NotificationCategory.macroEvents => config.macroAlertsEnabled,
      NotificationCategory.dailyBrief => config.dailyBriefEnabled,
      NotificationCategory.packageExpiry => config.packageExpiryEnabled,
      NotificationCategory.security => true,
    };
  }
}

/// Locally persisted notification choices.
class NotificationPreferences {
  NotificationPreferences(this._storage);

  final dynamic _storage;

  static const masterKey = 'abs_notify_master';

  final Map<String, bool> _values = {};
  bool _master = true;

  bool get master => _master;

  /// The effective state of one category: the master switch, the user's own
  /// choice, and the Remote Config gate must all agree.
  bool isEnabled(NotificationCategory category, NotificationConfig config) {
    if (category.mandatory) return true;
    if (!_master) return false;
    if (!category.allowedBy(config)) return false;
    return _values[category.key] ?? category.defaultEnabled;
  }

  /// The user's raw switch position, ignoring Remote Config gating.
  bool rawValue(NotificationCategory category) =>
      category.mandatory || (_values[category.key] ?? category.defaultEnabled);

  Future<void> load() async {
    final storedMaster = await SafeStorage.read(_storage, key: masterKey);
    _master = storedMaster == null ? true : storedMaster == 'true';
    for (final category in NotificationCategory.values) {
      final stored = await SafeStorage.read(_storage, key: category.storageKey);
      if (stored != null) _values[category.key] = stored == 'true';
    }
  }

  Future<void> setMaster(bool value) async {
    _master = value;
    final storage = _storage;
    if (storage == null) return;
    await SafeStorage.write(storage, key: masterKey, value: value.toString());
  }

  Future<void> setCategory(NotificationCategory category, bool value) async {
    if (category.mandatory) return;
    _values[category.key] = value;
    final storage = _storage;
    if (storage == null) return;
    await SafeStorage.write(
      storage,
      key: category.storageKey,
      value: value.toString(),
    );
  }

  /// Topics this device should be subscribed to right now.
  ///
  /// Only broadcast categories appear. Personal ones are token-targeted.
  Set<String> desiredTopics(NotificationConfig config) => {
    for (final category in NotificationCategory.values)
      if (category.topic != null && isEnabled(category, config))
        category.topic!,
  };

  /// Topics this device must not be subscribed to.
  Set<String> unwantedTopics(NotificationConfig config) {
    final wanted = desiredTopics(config);
    return {
      for (final category in NotificationCategory.values)
        if (category.topic != null && !wanted.contains(category.topic))
          category.topic!,
    };
  }
}
