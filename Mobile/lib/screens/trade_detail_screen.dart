import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../widgets/abs_ui.dart';

class TradeDetailScreen extends StatefulWidget {
  const TradeDetailScreen({super.key, required this.tradeId});
  final int tradeId;

  @override
  State<TradeDetailScreen> createState() => _TradeDetailScreenState();
}

class _TradeDetailScreenState extends State<TradeDetailScreen> {
  bool loading = true;
  String? error;
  Map<String, dynamic> trade = {};

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && trade.isEmpty) _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      trade = JsonTools.map(JsonTools.at(await SessionScope.of(context).api.get('/pulse/trades/${widget.tradeId}'), 'data', <String, dynamic>{}));
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => AbsPage(
        title: 'Trade Detail',
        subtitle: 'ABS execution record #${widget.tradeId}',
        actions: [IconButton(onPressed: _load, icon: const Icon(Icons.refresh))],
        child: loading
            ? const LoadingBlock()
            : error != null
                ? ErrorBlock(message: error!, onRetry: _load)
                : ListView(children: _content()),
      );

  List<Widget> _content() {
    final signal = JsonTools.map(trade['signal']);
    final status = JsonTools.text(trade['status']).toUpperCase();
    final environment = JsonTools.text(trade['environment'], 'testnet').toUpperCase();
    return [
      Row(
        children: [
          StatusChip(environment == 'LIVE' ? 'LIVE ACCOUNT' : 'TESTNET', warning: environment == 'LIVE'),
          const SizedBox(width: 8),
          StatusChip(status, good: status == 'CLOSED' || status == 'OPEN', warning: status == 'CLOSING' || status == 'PENDING'),
        ],
      ),
      const SizedBox(height: 14),
      AbsCard(
        child: Column(
          children: [
            Row(
              children: [
                Expanded(child: Text(JsonTools.text(trade['symbol']), style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900))),
                Text(money(trade['realized_pnl'] ?? trade['unrealized_pnl']), style: TextStyle(fontSize: 19, fontWeight: FontWeight.w900, color: pnlColor(trade['realized_pnl'] ?? trade['unrealized_pnl']))),
              ],
            ),
            const SizedBox(height: 12),
            KeyValueRow('Side', JsonTools.text(trade['side'], JsonTools.text(trade['direction']))),
            KeyValueRow('Entry price', money(trade['entry_price'])),
            KeyValueRow('Exit price', money(trade['exit_price'])),
            KeyValueRow('Quantity', number(trade['quantity'], digits: 6)),
            KeyValueRow('Leverage', '${JsonTools.integer(trade['leverage'], 1)}x'),
            KeyValueRow('Take profit', money(trade['take_profit'])),
            KeyValueRow('Stop loss', money(trade['stop_loss'])),
            KeyValueRow('Protection', JsonTools.text(trade['protection_status']).replaceAll('_', ' ').toUpperCase()),
            KeyValueRow('Fees', money(trade['fees'])),
            KeyValueRow('Opened', compactDate(trade['opened_at'] ?? trade['created_at'])),
            KeyValueRow('Closed', compactDate(trade['closed_at'])),
          ],
        ),
      ),
      if (signal.isNotEmpty) ...[
        const SizedBox(height: 16),
        const AbsSectionTitle('Originating signal', subtitle: 'Frozen signal context used for this trade'),
        const SizedBox(height: 10),
        AbsCard(
          child: Column(
            children: [
              KeyValueRow('Signal ID', '#${JsonTools.integer(signal['id'])}'),
              KeyValueRow('Direction', JsonTools.text(signal['direction'])),
              KeyValueRow('Timeframe', JsonTools.text(signal['timeframe'])),
              KeyValueRow('Score', number(signal['score'], digits: 1)),
              KeyValueRow('Generated', compactDate(signal['generated_at'])),
            ],
          ),
        ),
      ],
      const SizedBox(height: 24),
    ];
  }
}
