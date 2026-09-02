import 'package:flutter/material.dart';
import '../core/api_client.dart';
import '../core/brand.dart';
import '../widgets/pulse_widgets.dart';
import 'dashboard_screen.dart';
import 'register_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final api = ApiClient();
  final email = TextEditingController();
  final password = TextEditingController();
  bool loading = false;
  String? error;

  Future<void> login() async {
    setState(() { loading = true; error = null; });
    final res = await api.post('/login', {'email': email.text.trim(), 'password': password.text});
    final token = res['token']?.toString() ?? res['access_token']?.toString();
    if ((res['success'] == true || token != null) && token != null) {
      await api.saveToken(token);
      final user = res['user'];
      if (user is Map) { await api.saveUser(Map<String, dynamic>.from(user)); }
      if (!mounted) return;
      Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const DashboardScreen()));
    } else {
      setState(() => error = res['message']?.toString() ?? 'Login failed. Please check your email and password.');
    }
    if (mounted) setState(() => loading = false);
  }

  @override
  Widget build(BuildContext context) {
    return PulseShell(
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 460),
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(20, 24, 20, 28),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Image.asset(Brand.logo, height: 116, fit: BoxFit.contain),
              const SizedBox(height: 24),
              const Text(
                Brand.cleanTitle,
                textAlign: TextAlign.center,
                style: TextStyle(color: Brand.text, fontSize: 25, height: 1.16, fontWeight: FontWeight.w900, letterSpacing: .3),
              ),
              const SizedBox(height: 10),
              const Text(
                'Market scanning, confidence-ranked crypto signals, subscription access, and mobile trader monitoring connected to the Pulse web platform.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Brand.muted, fontSize: 15.5, height: 1.45),
              ),
              const SizedBox(height: 26),
              PulseCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('Sign in', style: TextStyle(color: Brand.text, fontSize: 23, fontWeight: FontWeight.w900)),
                const SizedBox(height: 6),
                const Text('Access your Pulse workspace and review live market opportunities.', style: TextStyle(color: Brand.muted, height: 1.4)),
                const SizedBox(height: 18),
                PulseInput(controller: email, label: 'Email', keyboardType: TextInputType.emailAddress),
                const SizedBox(height: 14),
                PulseInput(controller: password, label: 'Password', obscure: true),
                const SizedBox(height: 18),
                PulseButton(text: loading ? 'Checking...' : 'Login', icon: Icons.login, onPressed: loading ? null : login),
                if (error != null) Padding(padding: const EdgeInsets.only(top: 14), child: Text(error!, style: const TextStyle(color: Brand.danger, height: 1.35))),
                const SizedBox(height: 8),
                Center(
                  child: TextButton(
                    onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const RegisterScreen())),
                    child: const Text('Create account', style: TextStyle(color: Brand.green, fontWeight: FontWeight.w800)),
                  ),
                ),
              ])),
            ]),
          ),
        ),
      ),
    );
  }
}
