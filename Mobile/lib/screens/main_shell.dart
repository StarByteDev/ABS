import 'package:flutter/material.dart';

import '../core/theme.dart';
import 'content_screens.dart';
import 'free_signal_screen.dart';
import 'template_account_screen.dart';
import 'template_home_screen.dart';
import 'template_pulse_screen.dart';

class MainShell extends StatefulWidget {
  const MainShell({super.key});

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int index = 0;

  @override
  Widget build(BuildContext context) {
    final pages = <Widget>[
      TemplateHomeScreen(onTab: (value) => setState(() => index = value)),
      const TemplatePulseScreen(),
      const FreeSignalScreen(embedded: true),
      const NewsScreen(embedded: true),
      const TemplateAccountScreen(),
    ];

    return Scaffold(
      backgroundColor: AbsColors.bg,
      body: IndexedStack(index: index, children: pages),
      bottomNavigationBar: SafeArea(
        top: false,
        child: Container(
          decoration: const BoxDecoration(
            color: AbsColors.panel,
            border: Border(top: BorderSide(color: AbsColors.line)),
          ),
          child: NavigationBar(
            selectedIndex: index,
            onDestinationSelected: (value) => setState(() => index = value),
            backgroundColor: AbsColors.panel,
            indicatorColor: const Color(0x244DA3FF),
            height: 68,
            destinations: const [
              NavigationDestination(
                icon: Icon(Icons.space_dashboard_outlined),
                selectedIcon: Icon(Icons.space_dashboard_rounded),
                label: 'Home',
              ),
              NavigationDestination(
                icon: Icon(Icons.show_chart_rounded),
                selectedIcon: Icon(Icons.insights_rounded),
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
        ),
      ),
    );
  }
}
