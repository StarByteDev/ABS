import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';

class StrategiesScreen extends StatefulWidget {
  const StrategiesScreen({super.key});
  @override
  State<StrategiesScreen> createState() => _StrategiesScreenState();
}

class _StrategiesScreenState extends State<StrategiesScreen> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> strategies = [];
  Map<String, dynamic> summary = {};

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && strategies.isEmpty) _load();
  }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final api = SessionScope.of(context).api;
      final values = await Future.wait([
        api.get('/pulse/strategies'),
        api.get('/pulse/strategies/overview', query: {'period': '30d'}),
      ]);
      final catalog = JsonTools.map(JsonTools.at(values[0], 'data', <String, dynamic>{}));
      strategies = JsonTools.mapList(catalog['strategies'] ?? catalog['catalog'] ?? JsonTools.at(catalog, 'data'));
      summary = JsonTools.map(JsonTools.at(values[1], 'data', <String, dynamic>{}));
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => AbsPage(
        title: 'Strategies',
        subtitle: 'Plan-controlled Pulse strategy intelligence',
        actions: [IconButton(onPressed: _load, icon: const Icon(Icons.refresh))],
        child: loading
            ? const LoadingBlock()
            : error != null
                ? ErrorBlock(message: error!, onRetry: _load)
                : ListView(
                    children: [
                      Row(
                        children: [
                          Expanded(child: MetricCard(label: 'Active strategies', value: '${JsonTools.integer(summary['active_strategies'], strategies.where((e) => JsonTools.boolean(e['included'])).length)}')),
                          const SizedBox(width: 10),
                          Expanded(child: MetricCard(label: 'Signals generated', value: '${JsonTools.integer(summary['signals_generated'])}', detail: '30 days')),
                        ],
                      ),
                      const SizedBox(height: 16),
                      const AbsSectionTitle('Strategy catalog', subtitle: 'Multiple qualified strategies may support the same signal.'),
                      const SizedBox(height: 10),
                      if (strategies.isEmpty)
                        const EmptyState(title: 'No strategy catalog available', message: 'Your plan strategy list will appear here.')
                      else
                        ...strategies.map((s) {
                          final included = JsonTools.boolean(s['included'], JsonTools.text(s['status']) == 'included');
                          return Padding(
                            padding: const EdgeInsets.only(bottom: 8),
                            child: AbsCard(
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Icon(included ? Icons.check_circle : Icons.lock_outline, color: included ? AbsColors.green : AbsColors.muted),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(JsonTools.text(s['name']), style: const TextStyle(fontWeight: FontWeight.w900)),
                                        const SizedBox(height: 4),
                                        Text(JsonTools.text(s['description'], 'Pulse strategy'), style: const TextStyle(color: AbsColors.muted, fontSize: 12)),
                                        const SizedBox(height: 6),
                                        Text('${JsonTools.text(s['timeframe'])} · Minimum score ${number(s['minimum_score'], digits: 0)}', style: const TextStyle(color: AbsColors.cyan, fontSize: 10, fontWeight: FontWeight.w700)),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          );
                        }),
                    ],
                  ),
      );
}
