import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';

class ReportsScreen extends StatefulWidget {
  const ReportsScreen({super.key});
  @override
  State<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends State<ReportsScreen> {
  bool loading = true;
  String? error;
  Map<String, dynamic> report = {};
  List<Map<String, dynamic>> learning = [];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && report.isEmpty) _load();
  }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final api = SessionScope.of(context).api;
      final values = await Future.wait([
        api.get('/pulse/reports'),
        api.get('/pulse/reports/learning').catchError((_) => <String, dynamic>{}),
      ]);
      report = JsonTools.map(JsonTools.at(values[0], 'data', <String, dynamic>{}));
      learning = JsonTools.mapList(JsonTools.at(values[1], 'data', <dynamic>[]));
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => AbsPage(
        title: 'Reports & Learning',
        subtitle: 'Trading performance plus evidence-protected signal intelligence',
        actions: [IconButton(onPressed: _load, icon: const Icon(Icons.refresh))],
        child: loading
            ? const LoadingBlock()
            : error != null
                ? ErrorBlock(message: error!, onRetry: _load)
                : ListView(children: _content()),
      );

  List<Widget> _content() {
    final trading = JsonTools.map(report['trading'] ?? report['summary']);
    final intel = JsonTools.map(report['signal_intelligence']);
    final bySymbol = JsonTools.mapList(report['by_symbol']);
    return [
      GridView.count(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        crossAxisCount: 2,
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
        childAspectRatio: 1.55,
        children: [
          MetricCard(label: 'Realized P&L', value: money(trading['realized_pnl']), valueColor: pnlColor(trading['realized_pnl']), detail: 'Last 30 days'),
          MetricCard(label: 'Closed trades', value: '${JsonTools.integer(trading['closed'])}', detail: '${JsonTools.integer(trading['trades'])} total records'),
          MetricCard(label: 'Signal wins', value: '${JsonTools.integer(intel['wins'])}', detail: '${JsonTools.integer(intel['losses'])} losses'),
          MetricCard(label: 'Decisive win rate', value: percent(intel['decisive_win_rate']), detail: '${JsonTools.integer(intel['ambiguous'])} ambiguous'),
        ],
      ),
      const SizedBox(height: 18),
      const AbsSectionTitle('Signal validation intelligence', subtitle: 'Entry must occur before TP/SL validation. Ambiguous outcomes are never counted as automatic wins.'),
      const SizedBox(height: 10),
      AbsCard(
        child: Column(
          children: [
            KeyValueRow('Signals', '${JsonTools.integer(intel['signals'])}'),
            KeyValueRow('Entries', '${JsonTools.integer(intel['entries'])}'),
            KeyValueRow('Expired without entry', '${JsonTools.integer(intel['expired_no_entry'])}'),
            KeyValueRow('Expired after entry', '${JsonTools.integer(intel['expired_after_entry'])}'),
            KeyValueRow('Average MFE (R)', number(intel['avg_mfe_r'], digits: 2)),
            KeyValueRow('Average MAE (R)', number(intel['avg_mae_r'], digits: 2)),
          ],
        ),
      ),
      if (bySymbol.isNotEmpty) ...[
        const SizedBox(height: 18),
        const AbsSectionTitle('By market'),
        const SizedBox(height: 10),
        ...bySymbol.map((row) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: AbsCard(
                child: Row(
                  children: [
                    Expanded(child: Text(JsonTools.text(row['symbol']), style: const TextStyle(fontWeight: FontWeight.w900))),
                    Text('${JsonTools.integer(row['trades'])} trades', style: const TextStyle(color: AbsColors.muted)),
                    const SizedBox(width: 14),
                    Text(money(row['realized_pnl']), style: TextStyle(fontWeight: FontWeight.w900, color: pnlColor(row['realized_pnl']))),
                  ],
                ),
              ),
            )),
      ],
      if (learning.isNotEmpty) ...[
        const SizedBox(height: 18),
        const AbsSectionTitle('Strategy learning state', subtitle: 'Protected by sample size, recency and hierarchical fallback'),
        const SizedBox(height: 10),
        ...learning.take(20).map((row) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: AbsCard(
                child: Column(
                  children: [
                    KeyValueRow('Strategy', JsonTools.text(row['strategy_slug'])),
                    KeyValueRow('Scope', '${JsonTools.text(row['timeframe'])} · ${JsonTools.text(row['direction'])}'),
                    KeyValueRow('Samples', '${JsonTools.integer(row['sample_count'] ?? row['samples'])}'),
                    KeyValueRow('Reliability', percent(row['reliability'] ?? row['reliability_score'])),
                  ],
                ),
              ),
            )),
      ],
      const SizedBox(height: 24),
    ];
  }
}
