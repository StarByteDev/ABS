import 'package:flutter/material.dart';

import '../../core/ads/ad_service.dart';
import '../../core/notifications/notification_router.dart';
import '../../screens/plans_screen.dart';
import '../theme/app_theme.dart';
import '../state/app_state.dart';
import 'account_screen.dart';
import 'free_signal_screen.dart';
import 'home_screen.dart';
import 'news_screen.dart';
import 'pulse_screen.dart';

/// The production shell is intentionally the supplied template shell.
/// Deep production tools are opened from Account while the five primary tabs
/// remain exactly Home / Pulse / Free Signal / News / Account.
class Shell extends StatefulWidget {
  const Shell({super.key});

  @override
  State<Shell> createState() => _ShellState();
}

class _ShellState extends State<Shell> {
  int _index = 0;

  @override
  void initState() {
    super.initState();
    AdService.instance.setShellTab(_index);
    NotificationRouter.instance.pending.addListener(_openPendingNotification);
    // A notification may have launched the app before the Shell existed.
    WidgetsBinding.instance
        .addPostFrameCallback((_) => _openPendingNotification());
  }

  @override
  void dispose() {
    NotificationRouter.instance.pending
        .removeListener(_openPendingNotification);
    super.dispose();
  }

  void _openPendingNotification() {
    if (!mounted || NotificationRouter.instance.pending.value == null) return;
    final destination = NotificationRouter.instance.take()!;
    Navigator.of(context).popUntil((route) => route.isFirst);
    _go(destination.tab.index);
    if (destination.openPlans) {
      Navigator.of(context)
          .push(MaterialPageRoute<void>(builder: (_) => const PlansScreen()));
    }
  }

  void _go(int index) {
    setState(() => _index = index);
    AdService.instance.setShellTab(index);
    if (index == 1) {
      // Pulse may have been scanned on web or another device while this app was
      // in the background. Refresh the qualified signal list whenever the tab
      // is opened so mobile does not appear stale.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) AppScope.read(context).refreshPulse();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(
        index: _index,
        children: <Widget>[
          HomeScreen(onTab: _go),
          const PulseScreen(),
          const FreeSignalScreen(),
          const NewsScreen(),
          const AccountScreen(),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: _go,
        backgroundColor: AppColors.surface,
        indicatorColor: fade(AppColors.accent, .18),
        height: 68,
        destinations: const <NavigationDestination>[
          NavigationDestination(
            icon: Icon(Icons.space_dashboard_outlined),
            selectedIcon: Icon(Icons.space_dashboard_rounded),
            label: 'Home',
          ),
          NavigationDestination(
            icon: Icon(Icons.show_chart_rounded),
            selectedIcon: Icon(Icons.insights),
            label: 'Pulse',
          ),
          NavigationDestination(
            icon: Icon(Icons.play_circle_outline_rounded),
            selectedIcon: Icon(Icons.play_circle_rounded),
            label: 'Free Signal',
          ),
          NavigationDestination(
            icon: Icon(Icons.article_outlined),
            selectedIcon: Icon(Icons.article_rounded),
            label: 'News',
          ),
          NavigationDestination(
            icon: Icon(Icons.person_outline_rounded),
            selectedIcon: Icon(Icons.person_rounded),
            label: 'Account',
          ),
        ],
      ),
    );
  }
}
