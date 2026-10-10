import 'package:flutter/material.dart';

import '../screens/content_screens.dart';
import '../screens/market_extra_screens.dart';
import '../screens/signal_detail_screen.dart';
import '../screens/signals_screen.dart';
import 'notification_payload.dart';
import 'notification_service.dart';

/// Navigator key so a notification tap can route without a BuildContext.
final GlobalKey<NavigatorState> absNavigatorKey = GlobalKey<NavigatorState>();

/// Listens for a pending notification route and opens the matching screen.
///
/// Routing is deliberately conservative: it only ever pushes a passive,
/// read-only ABS screen. No notification can auto-execute a trade, open an
/// execution ticket, or take any irreversible account action.
class NotificationRouter extends StatefulWidget {
  const NotificationRouter({super.key, required this.child});

  final Widget child;

  @override
  State<NotificationRouter> createState() => _NotificationRouterState();
}

class _NotificationRouterState extends State<NotificationRouter> {
  @override
  void initState() {
    super.initState();
    NotificationService.instance.pendingRoute.addListener(_handle);
  }

  @override
  void dispose() {
    NotificationService.instance.pendingRoute.removeListener(_handle);
    super.dispose();
  }

  void _handle() {
    final payload = NotificationService.instance.pendingRoute.value;
    if (payload == null || !payload.isActionable) return;
    NotificationService.instance.consumeRoute();
    // Wait for the navigator to exist (a tap can arrive during cold start).
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final navigator = absNavigatorKey.currentState;
      if (navigator == null) return;
      final page = _pageFor(payload);
      if (page != null) navigator.push(MaterialPageRoute(builder: (_) => page));
    });
  }

  /// Maps a validated payload to a screen.
  ///
  /// The payload parser has already degraded any route whose argument was
  /// missing (signal_detail without an id became signals), so anything
  /// unresolved here simply opens nothing rather than guessing.
  Widget? _pageFor(AbsNotificationPayload payload) => switch (payload.route) {
    AbsNotificationRoute.marketDetail =>
      payload.symbol == null
          ? const PublicMarketOverviewScreen()
          : MarketDetailScreen(symbol: payload.symbol!),
    AbsNotificationRoute.markets => const PublicMarketOverviewScreen(),
    AbsNotificationRoute.signalDetail =>
      payload.signalId == null
          ? const SignalsScreen()
          : SignalDetailScreen(signalId: payload.signalId!),
    AbsNotificationRoute.signals => const SignalsScreen(),
    AbsNotificationRoute.calendar => const EconomicCalendarScreen(),
    AbsNotificationRoute.news ||
    AbsNotificationRoute.liveNews => const NewsScreen(),
    // Package expiry and security open the app; the relevant account state is
    // already surfaced there. Nothing is auto-actioned.
    AbsNotificationRoute.account => null,
    AbsNotificationRoute.pulse => null,
    AbsNotificationRoute.none => null,
  };

  @override
  Widget build(BuildContext context) => widget.child;
}
