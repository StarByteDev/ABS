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
  List<Map<String, dynamic>> performance = [];
  List<Map<String, dynamic>> learning = [];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && performance.isEmpty && learning.isEmpty) _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final now = DateTime.now().toUtc();
      final from = now.subtract(const Duration(days: 30));
      final query = {
        'from': from.toIso8601String().substring(0, 10),
        'to': now.toIso8601String().substring(0, 10),
      };
      final api = SessionScope.of(context).api;
      final values = await Future.wait([
        api.get('/pulse/reports/strategies', query: query),
        api.get('/pulse/reports/learning'),
      ]);
      performance = JsonTools.mapList(
        JsonTools.at(values[0], 'data', <dynamic>[]),
      );
      learning = JsonTools.mapList(
        JsonTools.at(values[1], 'data', <dynamic>[]),
      );
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => AbsPage(
    title: 'Strategy Intelligence',
    subtitle: '30-day profitability and evidence-protected reliability',
    actions: [
      IconButton(
        onPressed: loading ? null : _load,
        icon: const Icon(Icons.refresh),
      ),
    ],
    child: loading
        ? const LoadingBlock()
        : error != null
        ? ErrorBlock(message: error!, onRetry: _load)
        : ListView(
            children: [
              const AbsCard(
                accent: AbsColors.cyan,
                child: Text(
                  'Pulse strategy selection remains Admin controlled. These results explain how each eligible strategy has performed; they do not let the mobile user change the engine.',
                  style: TextStyle(color: AbsColors.muted, height: 1.5),
                ),
              ),
              const SizedBox(height: 18),
              const AbsSectionTitle(
                'Strategy profitability',
                subtitle: 'Research model by strategy version, timeframe and direction.',
              ),
              const SizedBox(height: 10),
              if (performance.isEmpty)
                const EmptyState(
                  title: 'No strategy evidence yet',
                  message: 'Metrics appear after sufficient resolved signal outcomes.',
                  icon: Icons.psychology_alt_outlined,
                )
              else
                ...performance.map(
                  (row) => Padding(
                    padding: const EdgeInsets.only(bottom: 9),
                    child: AbsCard(
                      accent: pnlColor(row['model_net_r']),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  JsonTools.text(row['strategy_slug'])
                                      .replaceAll('-', ' ')
                                      .toUpperCase(),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                              ),
                              Text(
                                '${number(row['model_net_r'], digits: 2)} R',
                                style: TextStyle(
                                  color: pnlColor(row['model_net_r']),
                                  fontSize: 17,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'v${JsonTools.text(row['strategy_version'])} · ${JsonTools.text(row['timeframe']).toUpperCase()} · ${JsonTools.text(row['direction']).toUpperCase()}',
                            style: const TextStyle(
                              color: AbsColors.muted,
                              fontSize: 10.5,
                            ),
                          ),
                          const SizedBox(height: 10),
                          KeyValueRow(
                            'Samples',
                            '${JsonTools.integer(row['samples'])}',
                          ),
                          KeyValueRow('Win rate', percent(row['win_rate'])),
                          KeyValueRow(
                            'Expectancy',
                            '${number(row['model_expectancy_r'], digits: 3)} R',
                          ),
                          KeyValueRow(
                            'Profit factor',
                            number(row['model_profit_factor'], digits: 2),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              if (learning.isNotEmpty) ...[
                const SizedBox(height: 18),
                const AbsSectionTitle(
                  'Learned confidence context',
                  subtitle: 'Sample size, recency weighting and hierarchical fallback protect adjustments.',
                ),
                const SizedBox(height: 10),
                ...learning
                    .take(30)
                    .map(
                      (row) => Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: AbsCard(
                          child: Column(
                            children: [
                              KeyValueRow(
                                'Strategy',
                                JsonTools.text(row['strategy_slug'])
                                    .replaceAll('-', ' '),
                              ),
                              KeyValueRow(
                                'Scope',
                                '${JsonTools.text(row['timeframe']).toUpperCase()} · ${JsonTools.text(row['direction']).toUpperCase()}',
                              ),
                              KeyValueRow(
                                'Evidence',
                                '${JsonTools.integer(row['sample_size'])} samples · ${JsonTools.text(row['evidence_level']).replaceAll('_', ' ')}',
                              ),
                              KeyValueRow(
                                'Reliability',
                                percent(row['reliability_score']),
                              ),
                              KeyValueRow(
                                'Recency weighted',
                                percent(row['recency_weighted_score']),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
              ],
              const SizedBox(height: 24),
            ],
          ),
  );
}
