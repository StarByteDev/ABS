import 'package:flutter/foundation.dart';

import '../core/api_client.dart';
import '../models/subscription_plan.dart';

class SubscriptionProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient();

  List<SubscriptionPlan> plans = [];
  String paymentNetwork = 'TRC20';
  String paymentAddress = '';
  bool loading = false;
  String? error;

  Future<void> loadPlans() async {
    loading = true;
    error = null;
    notifyListeners();

    try {
      final data = await _api.get('/subscription-plans');
      paymentNetwork = data['payment_network']?.toString() ?? 'TRC20';
      paymentAddress = data['payment_address']?.toString() ?? '';
      plans = ((data['plans'] ?? []) as List)
          .map((e) => SubscriptionPlan.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } catch (e) {
      error = e.toString();
    }

    loading = false;
    notifyListeners();
  }

  Future<bool> submitPayment({
    required int planId,
    required String txid,
    required String network,
    String? couponCode,
    String? referralCode,
    String? proofPath,
  }) async {
    loading = true;
    error = null;
    notifyListeners();

    try {
      await _api.multipartPost(
        path: '/subscription-payment',
        fields: {
          'subscription_plan_id': planId.toString(),
          'txid': txid,
          'network': network,
          if (couponCode != null && couponCode.isNotEmpty) 'coupon_code': couponCode,
          if (referralCode != null && referralCode.isNotEmpty) 'referral_code': referralCode,
        },
        filePath: proofPath,
      );
      loading = false;
      notifyListeners();
      return true;
    } catch (e) {
      error = e.toString();
      loading = false;
      notifyListeners();
      return false;
    }
  }
}
