import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';

/// Primary shell tabs, in Shell order.
enum PulseTab { home, pulse, freeSignal, news, account }

/// Where a notification tap should land.
@immutable
class NotificationDestination {
  const NotificationDestination(this.tab, {this.openPlans = false});

  final PulseTab tab;

  /// Push the membership/Plans screen on top of [tab] (package expiry).
  final bool openPlans;

  @override
  bool operator ==(Object other) =>
      other is NotificationDestination &&
      other.tab == tab &&
      other.openPlans == openPlans;

  @override
  int get hashCode => Object.hash(tab, openPlans);

  @override
  String toString() => 'NotificationDestination($tab, openPlans: $openPlans)';
}

/// Historical Pulse notification types.
class NotificationTypes {
  const NotificationTypes._();

  static const String marketMove = 'market_move';
  static const String qualifiedSignal = 'qualified_signal';
  static const String entryWatch = 'entry_watch';
  static const String macroEvent = 'macro_event';
  static const String breakingNews = 'breaking_news';
  static const String dailyBrief = 'daily_brief';
  static const String packageExpiry = 'package_expiry';
  static const String security = 'security';

  // Device-targeted mirrors of in-app Pulse alerts.
  static const String trade = 'trade';
  static const String risk = 'risk';
  static const String system = 'system';
  static const String support = 'support';
  static const String privateInvestor = 'private_investor';
  static const String marketNotice = 'market_notice';

  /// Types that concern a specific account and must only ever arrive
  /// device-targeted, never through a public topic. Qualified signals are
  /// member content and are also only sent to the member's own devices.
  static const Set<String> private = {
    packageExpiry,
    security,
    qualifiedSignal,
    trade,
    risk,
    system,
    support,
    privateInvestor,
    marketNotice,
  };
}

/// Central mapping from notification payload to in-app destination.
class NotificationRouter {
  NotificationRouter._();

  static final NotificationRouter instance = NotificationRouter._();

  /// The Shell listens to this and navigates when it becomes non-null, then
  /// clears it. Holding the value covers taps that arrive before the Shell
  /// exists (cold start from a notification).
  final ValueNotifier<NotificationDestination?> pending =
      ValueNotifier<NotificationDestination?>(null);

  static String typeOf(Map<String, dynamic> data) =>
      (data['type'] ?? data['event'] ?? data['category'] ?? '')
          .toString()
          .trim()
          .toLowerCase();

  /// Pure mapping, exposed for tests.
  ///
  /// * Private types sent to a signed-out device fall back to Account so the
  ///   user can sign in; nothing private is shown.
  /// * Unknown or incomplete payloads open Home.
  static NotificationDestination resolve(
    Map<String, dynamic> data, {
    required bool authenticated,
  }) {
    switch (typeOf(data)) {
      case NotificationTypes.marketMove:
      case NotificationTypes.dailyBrief:
        return const NotificationDestination(PulseTab.home);
      case NotificationTypes.qualifiedSignal:
      case NotificationTypes.entryWatch:
        return const NotificationDestination(PulseTab.pulse);
      case NotificationTypes.macroEvent:
      case NotificationTypes.breakingNews:
        return const NotificationDestination(PulseTab.news);
      case NotificationTypes.packageExpiry:
        return authenticated
            ? const NotificationDestination(PulseTab.account, openPlans: true)
            : const NotificationDestination(PulseTab.account);
      case NotificationTypes.security:
      case NotificationTypes.trade:
      case NotificationTypes.risk:
      case NotificationTypes.system:
      case NotificationTypes.support:
      case NotificationTypes.privateInvestor:
        return const NotificationDestination(PulseTab.account);
      case NotificationTypes.marketNotice:
        return const NotificationDestination(PulseTab.home);
      default:
        return const NotificationDestination(PulseTab.home);
    }
  }

  void open(Map<String, dynamic> data, {required bool authenticated}) {
    pending.value = resolve(data, authenticated: authenticated);
  }

  /// Returns and clears the pending destination.
  NotificationDestination? take() {
    final value = pending.value;
    pending.value = null;
    return value;
  }
}
