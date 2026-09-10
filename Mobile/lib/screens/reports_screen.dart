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
  String section = 'overview';
  int periodDays = 30;
  Map<String, dynamic> report = {};
  Map<String, dynamic> signalReport = {};
  List<Map<String, dynamic>> strategyReport = [];
  Map<String, dynamic> strategyMeta = {};
  Map<String, dynamic> simulation = {};
  List<Map<String, dynamic>> learning = [];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && report.isEmpty) _load();
  }

  Map<String, dynamic> get _periodQuery {
    final to = DateTime.now().toUtc();
    final from = to.subtract(Duration(days: periodDays));
    return {
      'from': from.toIso8601String().substring(0, 10),
      'to': to.toIso8601String().substring(0, 10),
    };
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final api = SessionScope.of(context).api;
      final values = await Future.wait([
        api.get('/pulse/reports', query: _periodQuery),
        api.get('/pulse/reports/signals', query: _periodQuery),
        api.get('/pulse/reports/strategies', query: _periodQuery),
        api.get('/pulse/reports/simulation', query: _periodQuery),
        api
            .get('/pulse/reports/learning')
            .catchError((_) => <String, dynamic>{}),
      ]);
      report = JsonTools.map(
        JsonTools.at(values[0], 'data', <String, dynamic>{}),
      );
      signalReport = JsonTools.map(
        JsonTools.at(values[1], 'data', <String, dynamic>{}),
      );
      strategyReport = JsonTools.mapList(
        JsonTools.at(values[2], 'data', <dynamic>[]),
      );
      strategyMeta = JsonTools.map(
        JsonTools.at(values[2], 'meta', <String, dynamic>{}),
      );
      simulation = JsonTools.map(
        JsonTools.at(values[3], 'data', <String, dynamic>{}),
      );
      learning = JsonTools.mapList(
        JsonTools.at(values[4], 'data', <dynamic>[]),
      );
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => AbsPage(
    title: 'Pulse Intelligence',
    subtitle: 'Actual execution, signal evidence and research simulation',
    actions: [
      IconButton(
        onPressed: loading ? null : _load,
        icon: const Icon(Icons.refresh),
      ),
    ],
    child: Column(
      children: [
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
          child: Row(
            children: [
              _PeriodChoice(days: 7, current: periodDays, onTap: _changePeriod),
              _PeriodChoice(
                days: 30,
                current: periodDays,
                onTap: _changePeriod,
              ),
              _PeriodChoice(
                days: 90,
                current: periodDays,
                onTap: _changePeriod,
              ),
              const SizedBox(width: 8),
              ...const [
                ('overview', 'Overview'),
                ('signals', 'Signals'),
                ('strategies', 'Strategies'),
                ('simulation', 'Simulation'),
              ].map(
                (item) => Padding(
                  padding: const EdgeInsets.only(right: 7),
                  child: ChoiceChip(
                    label: Text(item.$2),
                    selected: section == item.$1,
                    onSelected: (_) => setState(() => section = item.$1),
                  ),
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: loading
              ? const LoadingBlock(label: 'Loading Pulse intelligence...')
              : error != null
              ? Padding(
                  padding: const EdgeInsets.all(16),
                  child: ErrorBlock(message: error!, onRetry: _load),
                )
              : ListView(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
                  children: _content(),
                ),
        ),
      ],
    ),
  );

  void _changePeriod(int value) {
    if (periodDays == value) return;
    setState(() => periodDays = value);
    _load();
  }

  List<Widget> _content() => switch (section) {
    'signals' => _signals(),
    'strategies' => _strategies(),
    'simulation' => _simulation(),
    _ => _overview(),
  };

  List<Widget> _overview() {
    final trading = JsonTools.map(report['trading'] ?? report['summary']);
    final intel = JsonTools.map(report['signal_intelligence']);
    final bySymbol = JsonTools.mapList(report['by_symbol']);
    return [
      const AbsSectionTitle(
        'Actual Binance execution',
        subtitle: 'Only reconciled exchange fills are treated as realized trading results.',
      ),
      const SizedBox(height: 10),
      GridView.count(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        crossAxisCount: 2,
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
        childAspectRatio: 1.48,
        children: [
          MetricCard(
            label: 'Realized P&L',
            value: money(trading['realized_pnl']),
            valueColor: pnlColor(trading['realized_pnl']),
            detail: '$periodDays-day selected period',
          ),
          MetricCard(
            label: 'Exchange fees',
            value: money(trading['fees']),
            detail: 'Reconciled closed trades',
          ),
          MetricCard(
            label: 'Take-profit exits',
            value: '${JsonTools.integer(trading['tp_exits'])}',
            detail: '${JsonTools.integer(trading['closed'])} closed trades',
          ),
          MetricCard(
            label: 'Stop-loss exits',
            value: '${JsonTools.integer(trading['sl_exits'])}',
            detail: '${JsonTools.integer(trading['open_now'])} open now',
          ),
        ],
      ),
      const SizedBox(height: 18),
      const AbsSectionTitle(
        'Signal validation',
        subtitle: 'Entry must be observed before TP or SL. Ambiguous cases are kept separate.',
      ),
      const SizedBox(height: 10),
      AbsCard(
        child: Column(
          children: [
            KeyValueRow(
              'Signals evaluated',
              '${JsonTools.integer(intel['signals'])}',
            ),
            KeyValueRow(
              'Entries observed',
              '${JsonTools.integer(intel['entries'])}',
            ),
            KeyValueRow(
              'TP outcomes',
              '${JsonTools.integer(intel['wins'])}',
              valueColor: AbsColors.green,
            ),
            KeyValueRow(
              'SL outcomes',
              '${JsonTools.integer(intel['losses'])}',
              valueColor: AbsColors.red,
            ),
            KeyValueRow(
              'Ambiguous outcomes',
              '${JsonTools.integer(intel['ambiguous'])}',
              valueColor: AbsColors.gold,
            ),
            KeyValueRow(
              'Decisive win rate',
              percent(intel['decisive_win_rate']),
            ),
            KeyValueRow(
              'Model Net R',
              number(intel['model_net_r'], digits: 2),
              valueColor: pnlColor(intel['model_net_r']),
            ),
          ],
        ),
      ),
      if (bySymbol.isNotEmpty) ...[
        const SizedBox(height: 18),
        const AbsSectionTitle('Actual execution by market'),
        const SizedBox(height: 10),
        ...bySymbol.map(
          (row) => Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: AbsCard(
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      JsonTools.text(row['symbol']),
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                  ),
                  Text(
                    '${JsonTools.integer(row['trades'])} trades',
                    style: const TextStyle(color: AbsColors.muted),
                  ),
                  const SizedBox(width: 14),
                  Text(
                    money(row['realized_pnl']),
                    style: TextStyle(
                      fontWeight: FontWeight.w900,
                      color: pnlColor(row['realized_pnl']),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    ];
  }

  List<Widget> _signals() {
    final summary = JsonTools.map(signalReport['summary']);
    final daily = JsonTools.mapList(signalReport['daily']);
    final recent = JsonTools.mapList(
      signalReport['recent_detailed_validations'],
    );
    return [
      const AbsSectionTitle(
        'Signal outcome evidence',
        subtitle: 'Qualified signals are separated into TP, SL, ambiguous and unresolved states.',
      ),
      const SizedBox(height: 10),
      GridView.count(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        crossAxisCount: 2,
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
        childAspectRatio: 1.5,
        children: [
          MetricCard(
            label: 'TP / Wins',
            value: '${JsonTools.integer(summary['wins'])}',
            valueColor: AbsColors.green,
          ),
          MetricCard(
            label: 'SL / Losses',
            value: '${JsonTools.integer(summary['losses'])}',
            valueColor: AbsColors.red,
          ),
          MetricCard(
            label: 'Ambiguous',
            value: '${JsonTools.integer(summary['ambiguous'])}',
            valueColor: AbsColors.gold,
          ),
          MetricCard(
            label: 'Decisive win rate',
            value: percent(summary['decisive_win_rate']),
          ),
        ],
      ),
      const SizedBox(height: 12),
      AbsCard(
        child: Column(
          children: [
            KeyValueRow('Signals', '${JsonTools.integer(summary['signals'])}'),
            KeyValueRow(
              'Entry observed',
              '${JsonTools.integer(summary['entries'])}',
            ),
            KeyValueRow(
              'Expired without entry',
              '${JsonTools.integer(summary['expired_no_entry'])}',
            ),
            KeyValueRow(
              'Expired after entry',
              '${JsonTools.integer(summary['expired_after_entry'])}',
            ),
            KeyValueRow(
              'Average favorable move',
              '${number(summary['avg_mfe_r'], digits: 2)} R',
            ),
            KeyValueRow(
              'Average adverse move',
              '${number(summary['avg_mae_r'], digits: 2)} R',
            ),
          ],
        ),
      ),
      if (daily.isNotEmpty) ...[
        const SizedBox(height: 18),
        const AbsSectionTitle('Daily evidence'),
        const SizedBox(height: 9),
        ...daily.reversed.take(14).map((row) => _EvidenceRow(row: row)),
      ],
      if (recent.isNotEmpty) ...[
        const SizedBox(height: 18),
        const AbsSectionTitle(
          'Recent detailed validations',
          subtitle: 'Server-retained evidence for recent signals.',
        ),
        const SizedBox(height: 9),
        ...recent
            .take(20)
            .map(
              (row) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: AbsCard(
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              JsonTools.text(
                                row['symbol'],
                                'Signal #${JsonTools.integer(row['signal_id'])}',
                              ),
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const SizedBox(height: 3),
                            Text(
                              '${JsonTools.text(row['timeframe']).toUpperCase()} · ${compactDate(row['generated_at'])}',
                              style: const TextStyle(
                                color: AbsColors.muted,
                                fontSize: 10.5,
                              ),
                            ),
                          ],
                        ),
                      ),
                      StatusChip(
                        JsonTools.text(row['outcome'] ?? row['status'])
                            .replaceAll('_', ' ')
                            .toUpperCase(),
                        good: JsonTools.text(row['outcome']) == 'tp',
                        warning: JsonTools.text(row['outcome']) == 'ambiguous',
                      ),
                    ],
                  ),
                ),
              ),
            ),
      ],
    ];
  }

  List<Widget> _strategies() {
    return [
      const AbsSectionTitle(
        'Strategy profitability',
        subtitle: 'Plan-eligible strategy evidence by version, timeframe and direction.',
      ),
      const SizedBox(height: 10),
      if (!JsonTools.boolean(strategyMeta['schema_ready'], true))
        const ErrorBlock(
          message: 'Strategy metrics are not ready on the server.',
        )
      else if (strategyReport.isEmpty)
        const EmptyState(
          title: 'No strategy evidence yet',
          message: 'Results appear after signal validations create a sufficient sample.',
          icon: Icons.psychology_alt_outlined,
        )
      else
        ...strategyReport.map(
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
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              JsonTools.text(row['strategy_slug'])
                                  .replaceAll('-', ' ')
                                  .toUpperCase(),
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const SizedBox(height: 3),
                            Text(
                              'v${JsonTools.text(row['strategy_version'])} · ${JsonTools.text(row['timeframe']).toUpperCase()} · ${JsonTools.text(row['direction']).toUpperCase()}',
                              style: const TextStyle(
                                color: AbsColors.muted,
                                fontSize: 10.5,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Text(
                        '${number(row['model_net_r'], digits: 2)} R',
                        style: TextStyle(
                          fontSize: 17,
                          fontWeight: FontWeight.w900,
                          color: pnlColor(row['model_net_r']),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  KeyValueRow(
                    'Evidence samples',
                    '${JsonTools.integer(row['samples'])}',
                  ),
                  KeyValueRow('Decisive win rate', percent(row['win_rate'])),
                  KeyValueRow(
                    'Model expectancy',
                    '${number(row['model_expectancy_r'], digits: 3)} R / trade',
                  ),
                  KeyValueRow(
                    'Model profit factor',
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
          'Learned reliability',
          subtitle: 'Confidence context changes only when sample protection permits it.',
        ),
        const SizedBox(height: 9),
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
                        'Samples',
                        '${JsonTools.integer(row['sample_size'])}',
                      ),
                      KeyValueRow(
                        'Reliability',
                        percent(row['reliability_score']),
                      ),
                      KeyValueRow(
                        'Evidence level',
                        JsonTools.text(row['evidence_level'])
                            .replaceAll('_', ' ')
                            .toUpperCase(),
                      ),
                    ],
                  ),
                ),
              ),
            ),
      ],
    ];
  }

  List<Widget> _simulation() {
    final summary = JsonTools.map(simulation['summary']);
    final daily = JsonTools.mapList(simulation['daily']);
    return [
      const AbsCard(
        accent: AbsColors.gold,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.science_outlined, color: AbsColors.gold),
            SizedBox(width: 10),
            Expanded(
              child: Text(
                'Research-only all-signals simulation. This is not live-account P&L and excludes leverage, fees, funding, slippage and compounding.',
                style: TextStyle(
                  color: AbsColors.goldSoft,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        ),
      ),
      const SizedBox(height: 14),
      GridView.count(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        crossAxisCount: 2,
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
        childAspectRatio: 1.48,
        children: [
          MetricCard(
            label: 'Model trades',
            value: '${JsonTools.integer(summary['model_trades'])}',
            detail: '$periodDays-day evidence window',
          ),
          MetricCard(
            label: 'Model Net R',
            value: number(summary['model_net_r'], digits: 2),
            valueColor: pnlColor(summary['model_net_r']),
          ),
          MetricCard(
            label: 'Expectancy',
            value: '${number(summary['model_expectancy_r'], digits: 3)} R',
            detail: 'Per resolved model trade',
          ),
          MetricCard(
            label: 'Profit factor',
            value: number(summary['model_profit_factor'], digits: 2),
            detail: 'Gross model profit / loss',
          ),
        ],
      ),
      const SizedBox(height: 14),
      AbsCard(
        child: Text(
          JsonTools.text(
            summary['methodology'],
            'Resolved TP/SL outcomes after entry observation only.',
          ),
          style: const TextStyle(color: AbsColors.muted, height: 1.5),
        ),
      ),
      if (daily.isNotEmpty) ...[
        const SizedBox(height: 18),
        const AbsSectionTitle('Cumulative simulation trend'),
        const SizedBox(height: 9),
        ...daily.reversed
            .take(20)
            .map(
              (row) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: AbsCard(
                  child: Row(
                    children: [
                      SizedBox(
                        width: 58,
                        child: Text(
                          JsonTools.text(row['label']),
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                      ),
                      Expanded(
                        child: Text(
                          '${JsonTools.integer(row['trades'])} model trades',
                          style: const TextStyle(color: AbsColors.muted),
                        ),
                      ),
                      Text(
                        '${number(row['cumulative_r'], digits: 2)} R',
                        style: TextStyle(
                          fontWeight: FontWeight.w900,
                          color: pnlColor(row['cumulative_r']),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
      ],
    ];
  }
}

class _PeriodChoice extends StatelessWidget {
  const _PeriodChoice({
    required this.days,
    required this.current,
    required this.onTap,
  });
  final int days;
  final int current;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(right: 7),
    child: ChoiceChip(
      label: Text('${days}D'),
      selected: days == current,
      onSelected: (_) => onTap(days),
    ),
  );
}

class _EvidenceRow extends StatelessWidget {
  const _EvidenceRow({required this.row});
  final Map<String, dynamic> row;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: AbsCard(
      child: Row(
        children: [
          Expanded(
            child: Text(
              compactDate(row['metric_date']),
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
          ),
          Text(
            'TP ${JsonTools.integer(row['wins'])}',
            style: const TextStyle(
              color: AbsColors.green,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(width: 10),
          Text(
            'SL ${JsonTools.integer(row['losses'])}',
            style: const TextStyle(
              color: AbsColors.red,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(width: 10),
          Text(
            'AMB ${JsonTools.integer(row['ambiguous'])}',
            style: const TextStyle(
              color: AbsColors.gold,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    ),
  );
}
