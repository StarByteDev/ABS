import 'package:flutter/material.dart';
import '../core/api_client.dart';
import '../core/brand.dart';
import '../widgets/pulse_widgets.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});
  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final api = ApiClient();
  final name = TextEditingController();
  final email = TextEditingController();
  final oldPassword = TextEditingController();
  final newPassword = TextEditingController();
  String? message;
  List<String> availablePairs = <String>[];
  final Set<String> selectedPairs = <String>{};
  bool loadingSettings = false;

  @override
  void initState() {
    super.initState();
    load();
    loadTradingSettings();
  }

  Future<void> load() async {
    final res = await api.get('/profile');
    final user = Map<String, dynamic>.from((res['user'] ?? {}) as Map? ?? {});
    name.text = '${user['name'] ?? ''}';
    email.text = '${user['email'] ?? ''}';
    if (user.isNotEmpty) await api.saveUser(user);
  }

  Future<void> loadTradingSettings() async {
    setState(() => loadingSettings = true);
    final res = await api.get('/settings');
    final settings = Map<String, dynamic>.from((res['settings'] ?? res['trading_settings'] ?? {}) as Map? ?? {});
    final dynamic all = res['available_pairs'] ?? settings['available_pairs'] ?? settings['all_pairs'] ?? res['pairs'];
    final dynamic selected = res['selected_pairs'] ?? settings['selected_pairs'] ?? settings['pairs'];
    final allPairs = _extractPairs(all);
    final selectedList = _extractPairs(selected);
    setState(() {
      availablePairs = allPairs.isNotEmpty ? allPairs : <String>['BTCUSDT','ETHUSDT','BNBUSDT','SOLUSDT','XRPUSDT','ADAUSDT','DOGEUSDT','AVAXUSDT','LINKUSDT','TRXUSDT'];
      selectedPairs
        ..clear()
        ..addAll(selectedList.isNotEmpty ? selectedList : availablePairs.take(5));
      loadingSettings = false;
    });
  }

  Future<void> saveTradingSettings() async {
    final pairs = selectedPairs.toList();
    final res = await api.post('/settings', {'selected_pairs': pairs, 'pairs': pairs});
    setState(() => message = res['message']?.toString() ?? 'Trading settings saved. Your next scan will use selected pairs.');
  }

  Future<void> saveProfile() async {
    final res = await api.post('/profile', {'name': name.text.trim(), 'email': email.text.trim()});
    await api.saveUser({'name': name.text.trim(), 'email': email.text.trim()});
    setState(() => message = res['message']?.toString() ?? 'Profile saved.');
  }

  Future<void> changePassword() async {
    final res = await api.post('/change-password', {'current_password': oldPassword.text, 'password': newPassword.text});
    setState(() => message = res['message']?.toString() ?? 'Password request submitted.');
  }

  @override
  Widget build(BuildContext context) {
    return PulseShell(child: Column(children: [
      const AppTopBar(title: 'Profile & Trading Settings', back: true),
      Expanded(child: ListView(padding: const EdgeInsets.all(16), children: [
        PulseCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Account Profile', style: TextStyle(color: Brand.text, fontSize: 18, fontWeight: FontWeight.w900)),
          const SizedBox(height: 14),
          PulseInput(controller: name, label: 'Name'),
          const SizedBox(height: 12),
          PulseInput(controller: email, label: 'Email'),
          const SizedBox(height: 16),
          PulseButton(text: 'Save Profile', icon: Icons.save, onPressed: saveProfile),
        ])),
        const SizedBox(height: 16),
        PulseCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Trading Settings', style: TextStyle(color: Brand.text, fontSize: 18, fontWeight: FontWeight.w900)),
          const SizedBox(height: 6),
          const Text('Choose the pairs you want the Pulse scanner to include. These selections are sent to the same mobile API used by the dashboard scan.', style: TextStyle(color: Brand.muted, height: 1.35)),
          const SizedBox(height: 14),
          if (loadingSettings) const Text('Loading pairs...', style: TextStyle(color: Brand.muted))
          else Wrap(
            spacing: 8,
            runSpacing: 8,
            children: availablePairs.map((pair) {
              final active = selectedPairs.contains(pair);
              return FilterChip(
                selected: active,
                label: Text(pair),
                labelStyle: TextStyle(color: active ? Colors.black : Brand.text, fontWeight: FontWeight.w700),
                selectedColor: Brand.green,
                backgroundColor: const Color(0xFF04071A),
                side: const BorderSide(color: Brand.border),
                onSelected: (v) => setState(() => v ? selectedPairs.add(pair) : selectedPairs.remove(pair)),
              );
            }).toList(),
          ),
          const SizedBox(height: 16),
          PulseButton(text: 'Save Trading Settings', icon: Icons.tune, onPressed: saveTradingSettings),
        ])),
        const SizedBox(height: 16),
        PulseCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Security', style: TextStyle(color: Brand.text, fontSize: 18, fontWeight: FontWeight.w900)),
          const SizedBox(height: 14),
          PulseInput(controller: oldPassword, label: 'Current password', obscure: true),
          const SizedBox(height: 12),
          PulseInput(controller: newPassword, label: 'New password', obscure: true),
          const SizedBox(height: 16),
          PulseButton(text: 'Change Password', icon: Icons.lock_reset, onPressed: changePassword),
        ])),
        if (message != null) Padding(padding: const EdgeInsets.only(top: 14), child: PulseCard(child: Text(message!, style: const TextStyle(color: Brand.green)))),
      ])),
    ]));
  }

  List<String> _extractPairs(dynamic value) {
    if (value is List) return value.map((e) => e.toString()).where((e) => e.trim().isNotEmpty).toList();
    if (value is String && value.trim().isNotEmpty) return value.split(',').map((e) => e.trim()).where((e) => e.isNotEmpty).toList();
    return <String>[];
  }
}
