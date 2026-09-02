import 'dart:async';

import 'package:flutter/foundation.dart';

import '../core/api_client.dart';
import '../models/dashboard_stats.dart';
import '../models/signal_model.dart';

class DashboardProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient();

  DashboardStats stats = DashboardStats.empty();
  List<SignalModel> signals = [];

  bool loading = false;
  bool scanning = false;
  String? error;

  int cumulativeScanned = 0;
  int totalSymbols = 0;
  int batchNumber = 0;
  String currentBatch = 'Current batch: waiting to start.';
  String lastBatch = 'Last batch: none yet.';
  Timer? _timer;

  Future<void> loadDashboard() async {
    loading = true;
    error = null;
    notifyListeners();

    try {
      final data = await _api.get('/dashboard');
      stats = DashboardStats.fromJson(data['stats'] ?? {});
      signals = ((data['signals'] ?? []) as List)
          .map((e) => SignalModel.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } catch (e) {
      error = e.toString();
    }

    loading = false;
    notifyListeners();
  }

  void startAutoScan() {
    if (scanning) return;

    scanning = true;
    cumulativeScanned = 0;
    batchNumber = 0;
    currentBatch = 'Current batch: preparing batch 1...';
    lastBatch = 'Last batch: waiting for first completed batch.';
    notifyListeners();

    _runBatch();
  }

  void stopAutoScan() {
    scanning = false;
    _timer?.cancel();
    currentBatch = 'Current batch: stopped.';
    notifyListeners();
  }

  Future<void> _runBatch() async {
    if (!scanning) return;

    currentBatch = 'Current batch: scanning batch ${batchNumber + 1}...';
    notifyListeners();

    try {
      final data = await _api.post('/scan-next-batch', {});

      batchNumber++;
      final foundSignals = ((data['signals'] ?? []) as List)
          .map((e) => SignalModel.fromJson(Map<String, dynamic>.from(e)))
          .toList();

      signals.insertAll(0, foundSignals);

      if (data['stats'] != null) {
        stats = DashboardStats.fromJson(Map<String, dynamic>.from(data['stats']));
      } else if (foundSignals.isNotEmpty) {
        stats = DashboardStats(
          signals: stats.signals + foundSignals.length,
          active: stats.active + foundSignals.length,
          trades: stats.trades,
          pnl: stats.pnl,
        );
      }

      final scannedSymbols = ((data['scanned_symbols'] ?? []) as List)
          .map((e) => e.toString())
          .toList();

      totalSymbols = int.tryParse(data['total_symbols'].toString()) ?? totalSymbols;
      cumulativeScanned += scannedSymbols.length;

      if (totalSymbols > 0 && cumulativeScanned > totalSymbols) {
        cumulativeScanned = totalSymbols;
      }

      currentBatch = 'Current batch scanned: ${scannedSymbols.length}/$totalSymbols pair(s) · Batch $batchNumber';
      lastBatch = 'Last batch: ${scannedSymbols.isEmpty ? 'No symbols returned' : scannedSymbols.join(', ')}';

      final nextIndex = int.tryParse(data['next_index'].toString()) ?? 0;
      final isCycleComplete = totalSymbols > 0 && cumulativeScanned >= totalSymbols;

      if (isCycleComplete || nextIndex == 0) {
        currentBatch = 'Current batch: cycle complete.';
        cumulativeScanned = 0;
        batchNumber = 0;
        _timer = Timer(const Duration(seconds: 60), _runBatch);
      } else {
        _timer = Timer(const Duration(milliseconds: 3500), _runBatch);
      }
    } catch (e) {
      error = e.toString();
      scanning = false;
      currentBatch = 'Current batch: stopped due to error.';
    }

    notifyListeners();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }
}
