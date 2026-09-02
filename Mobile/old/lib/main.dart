import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'core/brand.dart';
import 'screens/login_screen.dart';
import 'screens/dashboard_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const PulseApp());
}

class PulseApp extends StatelessWidget {
  const PulseApp({super.key});

  Future<bool> _hasToken() async {
    final p = await SharedPreferences.getInstance();
    return (p.getString('pulse_token') ?? '').isNotEmpty;
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Pulse',
      theme: ThemeData.dark(useMaterial3: true).copyWith(
        scaffoldBackgroundColor: Brand.bg,
        colorScheme: const ColorScheme.dark(primary: Brand.green, secondary: Brand.cyan, surface: Brand.panel),
      ),
      home: FutureBuilder<bool>(
        future: _hasToken(),
        builder: (context, snap) {
          if (!snap.hasData) return const Scaffold(backgroundColor: Brand.bg, body: Center(child: CircularProgressIndicator()));
          return snap.data! ? const DashboardScreen() : const LoginScreen();
        },
      ),
    );
  }
}
