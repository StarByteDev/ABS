import 'package:flutter/material.dart';

import '../core/theme.dart';
import 'dashboard_screen.dart';
import 'market_screen.dart';
import 'more_screen.dart';
import 'positions_screen.dart';
import 'signals_screen.dart';

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
      const DashboardScreen(),
      const MarketScreen(),
      const SignalsScreen(),
      const PositionsScreen(),
      MoreScreen(onOpenTab: (value) => setState(() => index = value)),
    ];

    return Scaffold(
      body: IndexedStack(index: index, children: pages),
      bottomNavigationBar: SafeArea(
        top: false,
        child: Container(
          decoration: const BoxDecoration(
            color: Color(0xFF090D16),
            border: Border(top: BorderSide(color: AbsColors.lineSoft)),
          ),
          child: NavigationBar(
            selectedIndex: index,
            onDestinationSelected: (value) => setState(() => index = value),
            destinations: const [
              NavigationDestination(icon: Icon(Icons.monitor_heart_outlined), selectedIcon: Icon(Icons.monitor_heart_rounded), label: 'Pulse'),
              NavigationDestination(icon: Icon(Icons.show_chart_rounded), selectedIcon: Icon(Icons.candlestick_chart_rounded), label: 'Markets'),
              NavigationDestination(icon: Icon(Icons.bolt_outlined), selectedIcon: Icon(Icons.bolt_rounded), label: 'Signals'),
              NavigationDestination(icon: Icon(Icons.pie_chart_outline_rounded), selectedIcon: Icon(Icons.pie_chart_rounded), label: 'Portfolio'),
              NavigationDestination(icon: Icon(Icons.more_horiz_rounded), selectedIcon: Icon(Icons.more_horiz_rounded), label: 'More'),
            ],
          ),
        ),
      ),
    );
  }
}
