import 'dart:convert';

/// Every Firebase Remote Config key ABS reads, in one place.
///
/// Nothing anywhere else may spell these strings. The same keys are consumed
/// by the future ABS backend detector documented in docs/NOTIFICATION_SYSTEM.md,
/// so the app and the server always agree on one configuration.
class NotificationConfigKeys {
  const NotificationConfigKeys._();

  static const master = 'notifications_enabled';

  static const marketFastMoveEnabled = 'market_fast_move_enabled';
  static const marketFastMovePercent = 'market_fast_move_default_percent';
  static const marketFastMoveWindowMinutes = 'market_fast_move_window_minutes';
  static const marketFastMoveCooldownMinutes =
      'market_fast_move_cooldown_minutes';

  static const btcFastMoveEnabled = 'btc_fast_move_enabled';
  static const btcFastMovePercent = 'btc_fast_move_percent';
  static const ethFastMoveEnabled = 'eth_fast_move_enabled';
  static const ethFastMovePercent = 'eth_fast_move_percent';

  static const breakingNewsEnabled = 'breaking_news_enabled';

  static const qualifiedSignalEnabled =
      'qualified_signal_notifications_enabled';
  static const entryWatchEnabled = 'entry_watch_notifications_enabled';

  static const macroAlertsEnabled = 'macro_alerts_enabled';
  static const macroAlertLeadMinutes = 'macro_alert_lead_minutes';

  static const dailyBriefEnabled = 'daily_brief_enabled';
  static const freeSignalReadyEnabled =
      'free_signal_ready_notifications_enabled';
  static const packageExpiryEnabled = 'package_expiry_notifications_enabled';

  static const maxMarketAlertsPerHour = 'max_market_alerts_per_hour';

  /// Every key, used for the defaults map and for tests.
  static const all = <String>[
    master,
    marketFastMoveEnabled,
    marketFastMovePercent,
    marketFastMoveWindowMinutes,
    marketFastMoveCooldownMinutes,
    btcFastMoveEnabled,
    btcFastMovePercent,
    ethFastMoveEnabled,
    ethFastMovePercent,
    breakingNewsEnabled,
    qualifiedSignalEnabled,
    entryWatchEnabled,
    macroAlertsEnabled,
    macroAlertLeadMinutes,
    dailyBriefEnabled,
    freeSignalReadyEnabled,
    packageExpiryEnabled,
    maxMarketAlertsPerHour,
  ];
}

/// A resolved snapshot of the notification configuration.
///
/// Built from in-app defaults and overlaid with Remote Config when it is
/// available. ABS stays fully usable on the defaults alone.
class NotificationConfig {
  const NotificationConfig({
    this.enabled = true,
    this.marketFastMoveEnabled = true,
    this.marketFastMovePercent = 3.0,
    this.marketFastMoveWindowMinutes = 5,
    this.marketFastMoveCooldownMinutes = 30,
    this.btcFastMoveEnabled = true,
    this.btcFastMovePercent = 3.0,
    this.ethFastMoveEnabled = true,
    this.ethFastMovePercent = 4.0,
    this.breakingNewsEnabled = true,
    this.qualifiedSignalEnabled = true,
    this.entryWatchEnabled = false,
    this.macroAlertsEnabled = true,
    this.macroAlertLeadMinutes = 30,
    this.dailyBriefEnabled = true,
    this.freeSignalReadyEnabled = false,
    this.packageExpiryEnabled = true,
    this.maxMarketAlertsPerHour = 3,
  });

  final bool enabled;
  final bool marketFastMoveEnabled;
  final double marketFastMovePercent;
  final int marketFastMoveWindowMinutes;
  final int marketFastMoveCooldownMinutes;
  final bool btcFastMoveEnabled;
  final double btcFastMovePercent;
  final bool ethFastMoveEnabled;
  final double ethFastMovePercent;
  final bool breakingNewsEnabled;
  final bool qualifiedSignalEnabled;
  final bool entryWatchEnabled;
  final bool macroAlertsEnabled;
  final int macroAlertLeadMinutes;
  final bool dailyBriefEnabled;
  final bool freeSignalReadyEnabled;
  final bool packageExpiryEnabled;
  final int maxMarketAlertsPerHour;

  static const defaults = NotificationConfig();

  /// The Remote Config defaults map, so a missing server value is never null.
  static Map<String, dynamic> get remoteDefaults => {
    NotificationConfigKeys.master: defaults.enabled,
    NotificationConfigKeys.marketFastMoveEnabled:
        defaults.marketFastMoveEnabled,
    NotificationConfigKeys.marketFastMovePercent:
        defaults.marketFastMovePercent,
    NotificationConfigKeys.marketFastMoveWindowMinutes:
        defaults.marketFastMoveWindowMinutes,
    NotificationConfigKeys.marketFastMoveCooldownMinutes:
        defaults.marketFastMoveCooldownMinutes,
    NotificationConfigKeys.btcFastMoveEnabled: defaults.btcFastMoveEnabled,
    NotificationConfigKeys.btcFastMovePercent: defaults.btcFastMovePercent,
    NotificationConfigKeys.ethFastMoveEnabled: defaults.ethFastMoveEnabled,
    NotificationConfigKeys.ethFastMovePercent: defaults.ethFastMovePercent,
    NotificationConfigKeys.breakingNewsEnabled: defaults.breakingNewsEnabled,
    NotificationConfigKeys.qualifiedSignalEnabled:
        defaults.qualifiedSignalEnabled,
    NotificationConfigKeys.entryWatchEnabled: defaults.entryWatchEnabled,
    NotificationConfigKeys.macroAlertsEnabled: defaults.macroAlertsEnabled,
    NotificationConfigKeys.macroAlertLeadMinutes:
        defaults.macroAlertLeadMinutes,
    NotificationConfigKeys.dailyBriefEnabled: defaults.dailyBriefEnabled,
    NotificationConfigKeys.freeSignalReadyEnabled:
        defaults.freeSignalReadyEnabled,
    NotificationConfigKeys.packageExpiryEnabled: defaults.packageExpiryEnabled,
    NotificationConfigKeys.maxMarketAlertsPerHour:
        defaults.maxMarketAlertsPerHour,
  };

  /// Builds a config from raw Remote Config values.
  ///
  /// Every field is parsed defensively: a malformed or out-of-range server
  /// value falls back to the in-app default rather than disabling alerts or
  /// letting a nonsense threshold through.
  factory NotificationConfig.fromRemote(Map<String, Object?> values) {
    bool flag(String key, bool fallback) {
      final raw = values[key];
      if (raw is bool) return raw;
      if (raw is String) {
        final text = raw.trim().toLowerCase();
        if (text == 'true' || text == '1') return true;
        if (text == 'false' || text == '0') return false;
      }
      if (raw is num) return raw != 0;
      return fallback;
    }

    double percent(String key, double fallback) {
      final raw = values[key];
      final parsed = raw is num
          ? raw.toDouble()
          : double.tryParse('${raw ?? ''}'.trim());
      if (parsed == null || parsed.isNaN || parsed <= 0 || parsed > 100) {
        return fallback;
      }
      return parsed;
    }

    int minutes(String key, int fallback, {int max = 1440}) {
      final raw = values[key];
      final parsed = raw is num
          ? raw.round()
          : int.tryParse('${raw ?? ''}'.trim());
      if (parsed == null || parsed <= 0 || parsed > max) return fallback;
      return parsed;
    }

    int cap(String key, int fallback) {
      final raw = values[key];
      final parsed = raw is num
          ? raw.round()
          : int.tryParse('${raw ?? ''}'.trim());
      if (parsed == null || parsed < 0 || parsed > 60) return fallback;
      return parsed;
    }

    const d = defaults;
    return NotificationConfig(
      enabled: flag(NotificationConfigKeys.master, d.enabled),
      marketFastMoveEnabled: flag(
        NotificationConfigKeys.marketFastMoveEnabled,
        d.marketFastMoveEnabled,
      ),
      marketFastMovePercent: percent(
        NotificationConfigKeys.marketFastMovePercent,
        d.marketFastMovePercent,
      ),
      marketFastMoveWindowMinutes: minutes(
        NotificationConfigKeys.marketFastMoveWindowMinutes,
        d.marketFastMoveWindowMinutes,
      ),
      marketFastMoveCooldownMinutes: minutes(
        NotificationConfigKeys.marketFastMoveCooldownMinutes,
        d.marketFastMoveCooldownMinutes,
      ),
      btcFastMoveEnabled: flag(
        NotificationConfigKeys.btcFastMoveEnabled,
        d.btcFastMoveEnabled,
      ),
      btcFastMovePercent: percent(
        NotificationConfigKeys.btcFastMovePercent,
        d.btcFastMovePercent,
      ),
      ethFastMoveEnabled: flag(
        NotificationConfigKeys.ethFastMoveEnabled,
        d.ethFastMoveEnabled,
      ),
      ethFastMovePercent: percent(
        NotificationConfigKeys.ethFastMovePercent,
        d.ethFastMovePercent,
      ),
      breakingNewsEnabled: flag(
        NotificationConfigKeys.breakingNewsEnabled,
        d.breakingNewsEnabled,
      ),
      qualifiedSignalEnabled: flag(
        NotificationConfigKeys.qualifiedSignalEnabled,
        d.qualifiedSignalEnabled,
      ),
      entryWatchEnabled: flag(
        NotificationConfigKeys.entryWatchEnabled,
        d.entryWatchEnabled,
      ),
      macroAlertsEnabled: flag(
        NotificationConfigKeys.macroAlertsEnabled,
        d.macroAlertsEnabled,
      ),
      macroAlertLeadMinutes: minutes(
        NotificationConfigKeys.macroAlertLeadMinutes,
        d.macroAlertLeadMinutes,
      ),
      dailyBriefEnabled: flag(
        NotificationConfigKeys.dailyBriefEnabled,
        d.dailyBriefEnabled,
      ),
      freeSignalReadyEnabled: flag(
        NotificationConfigKeys.freeSignalReadyEnabled,
        d.freeSignalReadyEnabled,
      ),
      packageExpiryEnabled: flag(
        NotificationConfigKeys.packageExpiryEnabled,
        d.packageExpiryEnabled,
      ),
      maxMarketAlertsPerHour: cap(
        NotificationConfigKeys.maxMarketAlertsPerHour,
        d.maxMarketAlertsPerHour,
      ),
    );
  }

  /// The backend contract snapshot, for docs and debugging.
  String toPrettyJson() => const JsonEncoder.withIndent('  ').convert({
    NotificationConfigKeys.master: enabled,
    NotificationConfigKeys.marketFastMoveEnabled: marketFastMoveEnabled,
    NotificationConfigKeys.marketFastMovePercent: marketFastMovePercent,
    NotificationConfigKeys.marketFastMoveWindowMinutes:
        marketFastMoveWindowMinutes,
    NotificationConfigKeys.marketFastMoveCooldownMinutes:
        marketFastMoveCooldownMinutes,
    NotificationConfigKeys.btcFastMoveEnabled: btcFastMoveEnabled,
    NotificationConfigKeys.btcFastMovePercent: btcFastMovePercent,
    NotificationConfigKeys.ethFastMoveEnabled: ethFastMoveEnabled,
    NotificationConfigKeys.ethFastMovePercent: ethFastMovePercent,
    NotificationConfigKeys.breakingNewsEnabled: breakingNewsEnabled,
    NotificationConfigKeys.qualifiedSignalEnabled: qualifiedSignalEnabled,
    NotificationConfigKeys.entryWatchEnabled: entryWatchEnabled,
    NotificationConfigKeys.macroAlertsEnabled: macroAlertsEnabled,
    NotificationConfigKeys.macroAlertLeadMinutes: macroAlertLeadMinutes,
    NotificationConfigKeys.dailyBriefEnabled: dailyBriefEnabled,
    NotificationConfigKeys.freeSignalReadyEnabled: freeSignalReadyEnabled,
    NotificationConfigKeys.packageExpiryEnabled: packageExpiryEnabled,
    NotificationConfigKeys.maxMarketAlertsPerHour: maxMarketAlertsPerHour,
  });
}
