import 'package:flutter/material.dart';
import '../core/api_client.dart';
import '../core/brand.dart';
import '../widgets/pulse_widgets.dart';
import 'dashboard_screen.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});
  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final api = ApiClient();
  final name = TextEditingController();
  final email = TextEditingController();
  final password = TextEditingController();
  bool loading = false;
  String? error;

  Future<void> register() async {
    setState(() { loading = true; error = null; });
    final res = await api.post('/register', {'name': name.text.trim(), 'email': email.text.trim(), 'password': password.text});
    final token = res['token']?.toString() ?? res['access_token']?.toString();
    if ((res['success'] == true || token != null) && token != null) {
      await api.saveToken(token);
      if (!mounted) return;
      Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => const DashboardScreen()));
    } else {
      setState(() => error = res['message']?.toString() ?? 'Registration failed.');
    }
    if (mounted) setState(() => loading = false);
  }

  @override
  Widget build(BuildContext context) {
    return PulseShell(
      child: Column(children: [
        const AppTopBar(title: 'Create Account', back: true),
        Expanded(child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: PulseCard(child: Column(children: [
            Image.asset(Brand.logo, height: 90),
            const SizedBox(height: 16),
            PulseInput(controller: name, label: 'Full name'),
            const SizedBox(height: 14),
            PulseInput(controller: email, label: 'Email', keyboardType: TextInputType.emailAddress),
            const SizedBox(height: 14),
            PulseInput(controller: password, label: 'Password', obscure: true),
            const SizedBox(height: 18),
            PulseButton(text: loading ? 'Creating...' : 'Create Account', icon: Icons.person_add_alt, onPressed: loading ? null : register),
            if (error != null) Padding(padding: const EdgeInsets.only(top: 14), child: Text(error!, style: const TextStyle(color: Colors.redAccent))),
          ])),
        )),
      ]),
    );
  }
}
