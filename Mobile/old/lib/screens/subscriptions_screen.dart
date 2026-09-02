import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/subscription_plan.dart';
import '../providers/subscription_provider.dart';

class SubscriptionsScreen extends StatefulWidget {
  const SubscriptionsScreen({super.key});

  @override
  State<SubscriptionsScreen> createState() => _SubscriptionsScreenState();
}

class _SubscriptionsScreenState extends State<SubscriptionsScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() => context.read<SubscriptionProvider>().loadPlans());
  }

  Future<void> _openPaymentSheet(SubscriptionPlan plan) async {
    final provider = context.read<SubscriptionProvider>();
    final txid = TextEditingController();
    final coupon = TextEditingController();
    final referral = TextEditingController();
    String? proofPath;

    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: const Color(0xFF0F172A),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (_) {
        return Padding(
          padding: EdgeInsets.only(
            left: 18,
            right: 18,
            top: 18,
            bottom: MediaQuery.of(context).viewInsets.bottom + 18,
          ),
          child: StatefulBuilder(
            builder: (context, setSheetState) {
              return SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Pay ${plan.price.toStringAsFixed(2)} USDT', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 10),
                    Text('Network: ${provider.paymentNetwork}'),
                    const SizedBox(height: 6),
                    SelectableText('Address: ${provider.paymentAddress}'),
                    const SizedBox(height: 18),
                    TextField(controller: txid, decoration: const InputDecoration(labelText: 'Transaction ID / Hash')),
                    const SizedBox(height: 12),
                    TextField(controller: coupon, decoration: const InputDecoration(labelText: 'Coupon optional')),
                    const SizedBox(height: 12),
                    TextField(controller: referral, decoration: const InputDecoration(labelText: 'Referral optional')),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: () async {
                        final result = await FilePicker.platform.pickFiles(
                          type: FileType.custom,
                          allowedExtensions: ['jpg', 'jpeg', 'png', 'pdf'],
                        );
                        if (result != null && result.files.single.path != null) {
                          setSheetState(() => proofPath = result.files.single.path);
                        }
                      },
                      icon: const Icon(Icons.upload_file),
                      label: Text(proofPath == null ? 'Upload Proof optional' : 'Proof selected'),
                    ),
                    const SizedBox(height: 18),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: () async {
                          final ok = await provider.submitPayment(
                            planId: plan.id,
                            txid: txid.text.trim(),
                            network: provider.paymentNetwork,
                            couponCode: coupon.text.trim(),
                            referralCode: referral.text.trim(),
                            proofPath: proofPath,
                          );

                          if (!context.mounted) return;

                          Navigator.pop(context);
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(content: Text(ok ? 'Payment submitted for admin review.' : provider.error ?? 'Payment failed')),
                          );
                        },
                        child: const Text('Submit Payment'),
                      ),
                    ),
                  ],
                ),
              );
            },
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<SubscriptionProvider>();

    return Scaffold(
      appBar: AppBar(title: const Text('Plans')),
      body: RefreshIndicator(
        onRefresh: provider.loadPlans,
        child: ListView(
          padding: const EdgeInsets.all(14),
          children: [
            if (provider.loading)
              const Center(child: Padding(padding: EdgeInsets.all(24), child: CircularProgressIndicator())),
            if (provider.error != null)
              Card(child: Padding(padding: const EdgeInsets.all(14), child: Text(provider.error!, style: const TextStyle(color: Colors.redAccent)))),
            ...provider.plans.map(
              (plan) => Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(plan.name, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 6),
                      Text('${plan.price.toStringAsFixed(2)} USDT / monthly', style: const TextStyle(fontSize: 18, color: Colors.greenAccent)),
                      const SizedBox(height: 8),
                      Text(plan.description, style: const TextStyle(color: Colors.white70)),
                      const Divider(height: 24),
                      Text('Pairs: ${plan.maxPairs} · Batch: ${plan.maxBatchSize} · Cycle: ${plan.scanFrequencyMinutes}m · Min Conf: ${plan.minConfidence}%'),
                      const SizedBox(height: 10),
                      ...plan.features.map((f) => Text('• $f')),
                      const SizedBox(height: 14),
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: () => _openPaymentSheet(plan),
                          child: const Text('Pay with USDT'),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
