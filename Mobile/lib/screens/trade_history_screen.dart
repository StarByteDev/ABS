import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';
import 'trade_detail_screen.dart';

class TradeHistoryScreen extends StatefulWidget {
  const TradeHistoryScreen({super.key});
  @override
  State<TradeHistoryScreen> createState() => _TradeHistoryScreenState();
}

class _TradeHistoryScreenState extends State<TradeHistoryScreen> {
  bool loading = true;
  String? error;
  String status = 'all';
  List<Map<String, dynamic>> trades = [];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && trades.isEmpty) _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final response = await SessionScope.of(context).api.get('/pulse/trades', query: {if (status != 'all') 'status': status, 'per_page': 50});
      trades = JsonTools.pageItems(response);
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => AbsPage(
        title: 'Trade History',
        subtitle: 'Closed and lifecycle execution records',
        actions: [IconButton(onPressed: _load, icon: const Icon(Icons.refresh))],
        child: loading
            ? const LoadingBlock()
            : error != null
                ? ErrorBlock(message: error!, onRetry: _load)
                : ListView(
                    children: [
                      SegmentedButton<String>(
                        segments: const [
                          ButtonSegment(value: 'all', label: Text('All')),
                          ButtonSegment(value: 'closed', label: Text('Closed')),
                          ButtonSegment(value: 'open', label: Text('Open')),
                        ],
                        selected: {status},
                        onSelectionChanged: (v) {
                          setState(() => status = v.first);
                          _load();
                        },
                      ),
                      const SizedBox(height: 16),
                      if (trades.isEmpty)
                        const EmptyState(title: 'No trades found', message: 'Your ABS execution history will appear here.', icon: Icons.history)
                      else
                        ...trades.map((trade) => Padding(
                              padding: const EdgeInsets.only(bottom: 9),
                              child: InkWell(
                                borderRadius: BorderRadius.circular(18),
                                onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => TradeDetailScreen(tradeId: JsonTools.integer(trade['id'])))),
                                child: AbsCard(
                                  child: Row(
                                    children: [
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text(JsonTools.text(trade['symbol']), style: const TextStyle(fontWeight: FontWeight.w900)),
                                            const SizedBox(height: 4),
                                            Text('${JsonTools.text(trade['side'], JsonTools.text(trade['direction']))} · ${JsonTools.text(trade['status']).toUpperCase()} · ${compactDate(trade['created_at'])}', style: const TextStyle(color: AbsColors.muted, fontSize: 11)),
                                          ],
                                        ),
                                      ),
                                      Text(money(trade['realized_pnl']), style: TextStyle(fontWeight: FontWeight.w900, color: pnlColor(trade['realized_pnl']))),
                                      const SizedBox(width: 6),
                                      const Icon(Icons.chevron_right, color: AbsColors.muted),
                                    ],
                                  ),
                                ),
                              ),
                            )),
                    ],
                  ),
      );
}
