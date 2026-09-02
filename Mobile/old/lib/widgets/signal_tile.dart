import 'package:flutter/material.dart';

import '../models/signal_model.dart';

class SignalTile extends StatelessWidget {
  final SignalModel signal;

  const SignalTile({super.key, required this.signal});

  @override
  Widget build(BuildContext context) {
    final isBuy = signal.side.toUpperCase() == 'BUY';

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(signal.symbol, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: isBuy ? Colors.green.shade700 : Colors.red.shade700,
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(signal.side),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(signal.strategy, style: const TextStyle(color: Colors.white70)),
            const SizedBox(height: 4),
            Text(signal.reason, style: const TextStyle(color: Colors.white54)),
            const Divider(height: 22),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                _mini('Entry', signal.entryPrice),
                _mini('SL', signal.stopLoss),
                _mini('TP', signal.takeProfit),
                _mini('Conf', '${signal.confidence}%'),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _mini(String label, String value) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(color: Colors.white38, fontSize: 11)),
        const SizedBox(height: 3),
        Text(value, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
      ],
    );
  }
}
