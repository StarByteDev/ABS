import 'package:flutter/material.dart';
import '../core/api_client.dart';
import '../core/brand.dart';
import '../widgets/pulse_widgets.dart';

class PlansScreen extends StatefulWidget {
  const PlansScreen({super.key});

  @override
  State<PlansScreen> createState() => _PlansScreenState();
}

class _PlansScreenState extends State<PlansScreen> {
  final ApiClient api = ApiClient();
  Map<String, dynamic> data = <String, dynamic>{};
  String? error;

  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    final Map<String, dynamic> res = await api.get('/subscription-plans');
    if (!mounted) return;
    setState(() {
      data = res;
      error = res['success'] == false ? res['message']?.toString() : null;
    });
  }

  @override
  Widget build(BuildContext context) {
    final List<dynamic> plans = data['plans'] is List ? data['plans'] as List<dynamic> : <dynamic>[];
    final String wallet = (data['wallet_address'] ?? data['payment_wallet'] ?? data['wallet'] ?? 'Wallet will appear after admin configuration.').toString();

    return PulseShell(
      child: Column(
        children: <Widget>[
          const AppTopBar(title: 'Plans', back: true),
          Expanded(
            child: RefreshIndicator(
              onRefresh: load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: <Widget>[
                  PulseCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        const Text('Subscription Access', style: TextStyle(color: Brand.text, fontSize: 20, fontWeight: FontWeight.w900)),
                        const SizedBox(height: 8),
                        const Text(
                          'Choose a plan, pay using the wallet configured by admin, then share your payment reference for activation.',
                          style: TextStyle(color: Brand.muted, height: 1.4),
                        ),
                        const SizedBox(height: 14),
                        const Text('Payment Wallet', style: TextStyle(color: Brand.green, fontWeight: FontWeight.w800)),
                        const SizedBox(height: 6),
                        SelectableText(wallet, style: const TextStyle(color: Brand.text)),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                  if (error != null)
                    PulseCard(child: Text(error!, style: const TextStyle(color: Colors.redAccent))),
                  if (plans.isEmpty)
                    const PulseCard(child: Text('No plans available yet.', style: TextStyle(color: Brand.text)))
                  else
                    ...plans.map((dynamic item) => _plan(_asMap(item))),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _plan(Map<String, dynamic> plan) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: PulseCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('${plan['name'] ?? 'Plan'}', style: const TextStyle(color: Brand.text, fontSize: 18, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            Text('${plan['price'] ?? plan['amount'] ?? '-'} USDT', style: const TextStyle(color: Brand.green, fontSize: 22, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            Text('${plan['description'] ?? 'Access Pulse Trading Intelligence features.'}', style: const TextStyle(color: Brand.muted)),
          ],
        ),
      ),
    );
  }

  Map<String, dynamic> _asMap(dynamic value) {
    if (value is Map) return Map<String, dynamic>.from(value);
    return <String, dynamic>{};
  }
}
