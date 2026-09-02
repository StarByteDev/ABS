import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';
import 'plans_screen.dart';
import 'scanner_screen.dart';
import 'signal_detail_screen.dart';
import 'trading_setup_screen.dart';

class SignalsScreen extends StatefulWidget {
  const SignalsScreen({super.key});

  @override
  State<SignalsScreen> createState() => _SignalsScreenState();
}

class _SignalsScreenState extends State<SignalsScreen> {
  bool loading = true;
  bool scanning = false;
  String? error;
  String status = 'active';
  Map<String, dynamic> overview = {};
  Map<String, dynamic> usage = {};
  Map<String, dynamic> scanOverview = {};
  Map<String, dynamic> feed = {};

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && overview.isEmpty && error == null) _load();
  }

  Future<void> _load() async {
    final session = SessionScope.of(context);
    if (!session.hasPulseAccess) {
      setState(() => loading = false);
      return;
    }
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final values = await Future.wait([
        session.api.get('/pulse/signals/overview', query: {'status': status}),
        session.api.get('/pulse/scanner/overview'),
        session.api.get('/pulse/market-data/health'),
      ]);
      overview = JsonTools.map(JsonTools.at(values[0], 'data', <String, dynamic>{}));
      usage = JsonTools.map(JsonTools.at(values[0], 'usage', <String, dynamic>{}));
      scanOverview = JsonTools.map(JsonTools.at(values[1], 'data', <String, dynamic>{}));
      feed = JsonTools.map(JsonTools.at(values[2], 'data', <String, dynamic>{}));
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _quickScan() async {
    if (scanning) return;
    setState(() => scanning = true);
    try {
      final response = await SessionScope.of(context).api.post('/pulse/scanner/run', body: {'timeframe': 'all'});
      usage = JsonTools.map(JsonTools.at(response, 'usage', usage));
      final run = JsonTools.map(JsonTools.at(response, 'data', <String, dynamic>{}));
      final created = JsonTools.integer(run['signals_created']);
      final pairs = JsonTools.integer(run['pairs_scanned']);
      if (mounted) {
        showSnack(
          context,
          created > 0
              ? 'Market scan complete · $created new signal${created == 1 ? '' : 's'} from $pairs selected markets.'
              : 'Market scan complete · no new setup met your current signal criteria.',
        );
      }
      await _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => scanning = false);
    }
  }

  Future<void> _openScanOptions() async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ScannerScreen()));
    if (mounted) _load();
  }

  Future<void> _handlePrimaryScan() async {
    final selectedPairs = JsonTools.integer(scanOverview['selected_pairs']);
    final healthy = JsonTools.boolean(feed['healthy'], JsonTools.boolean(feed['is_healthy']));
    if (selectedPairs <= 0) {
      await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const TradingSetupScreen(initialSection: 'markets')));
      if (mounted) _load();
      return;
    }
    if (!healthy) {
      showSnack(context, 'ABS market data is updating. New scan decisions are protected until the central feed is fresh.', error: true);
      return;
    }
    await _quickScan();
  }

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    if (!session.hasPulseAccess) {
      return AbsPage(
        title: 'Trade Signals',
        subtitle: 'Market opportunities, clearly ranked',
        child: PulseAccessGate(onViewPlans: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PlansScreen()))),
      );
    }
    return AbsPage(
      title: 'Trade Signals',
      subtitle: 'Scan · review · act with clarity',
      actions: [IconButton(onPressed: loading ? null : _load, icon: const Icon(Icons.refresh_rounded), tooltip: 'Refresh signals')],
      child: loading
          ? const LoadingBlock(label: 'Loading signals...')
          : error != null
              ? ErrorBlock(message: error!, onRetry: _load)
              : RefreshIndicator(onRefresh: _load, child: _content()),
    );
  }

  Widget _content() {
    final session = SessionScope.of(context);
    final signals = JsonTools.mapList(overview['signals']);
    final activeCount = JsonTools.integer(overview['active_count']);
    final highConviction = JsonTools.integer(overview['high_conviction_count']);
    final selectedPairs = JsonTools.integer(scanOverview['selected_pairs']);
    final healthy = JsonTools.boolean(feed['healthy'], JsonTools.boolean(feed['is_healthy']));
    final scanUnlimited = JsonTools.boolean(JsonTools.at(usage, 'scans.unlimited'));
    final scanRemaining = JsonTools.at(
      usage,
      'scans.remaining',
      JsonTools.at(usage, 'scanner.remaining', JsonTools.at(usage, 'scanner_runs_remaining', '—')),
    );
    final scanAllowance = scanUnlimited ? 'UNLIMITED SCANS' : '${JsonTools.text(scanRemaining)} SCANS LEFT';

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        AbsCard(
          padding: const EdgeInsets.all(18),
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFF15152C), Color(0xFF0D1625), Color(0xFF091019)],
          ),
          accent: AbsColors.gold,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 46,
                    height: 46,
                    decoration: BoxDecoration(
                      color: AbsColors.gold.withValues(alpha: .10),
                      borderRadius: BorderRadius.circular(15),
                      border: Border.all(color: AbsColors.gold.withValues(alpha: .28)),
                    ),
                    child: const Icon(Icons.radar_rounded, color: AbsColors.gold, size: 24),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('MARKET SCAN', style: TextStyle(color: AbsColors.goldSoft, fontSize: 10.5, fontWeight: FontWeight.w900, letterSpacing: 1.2)),
                        SizedBox(height: 4),
                        Text('Find fresh opportunities', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 20)),
                      ],
                    ),
                  ),
                  StatusChip('$selectedPairs MARKETS'),
                  StatusChip(healthy ? 'DATA READY' : 'DATA UPDATING', good: healthy),
                  StatusChip(scanAllowance),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                session.proMode
                    ? 'Evaluate your selected markets across 15M and 4H using the strategies and minimum score configured for your Pulse plan.'
                    : 'One tap checks your selected markets and shows only setups that pass your ABS strategy and signal requirements.',
                style: const TextStyle(color: AbsColors.muted, fontSize: 12, height: 1.45),
              ),
              const SizedBox(height: 15),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: scanning ? null : _handlePrimaryScan,
                  icon: scanning
                      ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                      : Icon(selectedPairs <= 0 ? Icons.grid_view_rounded : Icons.radar_rounded),
                  label: Text(
                    scanning
                        ? 'Scanning selected markets...'
                        : selectedPairs <= 0
                            ? 'Select Markets to Scan'
                            : healthy
                                ? 'Scan $selectedPairs Markets Now'
                                : 'Market Data Updating',
                  ),
                ),
              ),
              const SizedBox(height: 8),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: scanning ? null : _openScanOptions,
                  icon: const Icon(Icons.tune_rounded, size: 19),
                  label: const Text('Scan Options'),
                ),
              ),
              const SizedBox(height: 9),
              Text('Last completed scan · ${compactDate(overview['last_scan_at'])}', style: const TextStyle(color: AbsColors.muted2, fontSize: 10.5)),
            ],
          ),
        ),
        const SizedBox(height: 14),
        AbsCard(
          accent: AbsColors.purple,
          child: const Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(Icons.shield_outlined, color: AbsColors.purpleSoft, size: 19),
                  SizedBox(width: 8),
                  Text('SIGNAL GUIDE', style: TextStyle(color: AbsColors.purpleSoft, fontSize: 10.5, fontWeight: FontWeight.w900, letterSpacing: 1.1)),
                ],
              ),
              SizedBox(height: 13),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(child: _SignalFlowStep(number: '1', title: 'Scan', detail: 'ABS checks selected markets.')),
                  SizedBox(width: 8),
                  Expanded(child: _SignalFlowStep(number: '2', title: 'Review', detail: 'Confirm levels, context and risk.')),
                  SizedBox(width: 8),
                  Expanded(child: _SignalFlowStep(number: '3', title: 'Act', detail: 'Execute only when ready.')),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Row(
          children: [
            Expanded(child: MetricCard(label: 'Active Signals', value: '$activeCount', detail: '${JsonTools.integer(overview['active_pairs'])} markets', icon: Icons.bolt_rounded)),
            const SizedBox(width: 10),
            Expanded(child: MetricCard(label: 'High Confidence', value: '$highConviction', detail: 'Score 80+', icon: Icons.verified_outlined)),
          ],
        ),
        if (session.proMode) ...[
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(child: MetricCard(label: 'Long', value: '${JsonTools.integer(overview['long_count'])}', detail: 'Current signals', icon: Icons.trending_up_rounded, valueColor: AbsColors.green)),
              const SizedBox(width: 10),
              Expanded(child: MetricCard(label: 'Short', value: '${JsonTools.integer(overview['short_count'])}', detail: 'Current signals', icon: Icons.trending_down_rounded, valueColor: AbsColors.red)),
            ],
          ),
        ],
        const SizedBox(height: 16),
        SegmentedButton<String>(
          segments: const [
            ButtonSegment(value: 'active', label: Text('Active')),
            ButtonSegment(value: 'history', label: Text('History')),
            ButtonSegment(value: 'all', label: Text('All')),
          ],
          selected: {status},
          onSelectionChanged: (v) {
            setState(() => status = v.first);
            _load();
          },
        ),
        const SizedBox(height: 20),
        AbsSectionTitle(
          status == 'active' ? 'Current Opportunities' : status == 'history' ? 'Signal History' : 'All Signals',
          subtitle: activeCount > 0 ? 'Review the setup before any execution decision.' : 'Run a market scan whenever you want ABS to search for new qualifying setups.',
        ),
        const SizedBox(height: 10),
        if (signals.isEmpty)
          AbsCard(
            child: Column(
              children: [
                Container(
                  width: 58,
                  height: 58,
                  decoration: BoxDecoration(color: AbsColors.purple.withValues(alpha: .08), borderRadius: BorderRadius.circular(18)),
                  child: const Icon(Icons.radar_rounded, color: AbsColors.purpleSoft, size: 28),
                ),
                const SizedBox(height: 13),
                Text(status == 'active' ? 'No active signals right now' : 'No signals in this view', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900)),
                const SizedBox(height: 5),
                Text(
                  status == 'active'
                      ? 'Start a market scan and ABS will evaluate your selected markets against your configured strategies.'
                      : 'Your signals will appear here as activity is generated.',
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: AbsColors.muted, fontSize: 11.5),
                ),
                if (status == 'active') ...[
                  const SizedBox(height: 13),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: scanning ? null : _handlePrimaryScan,
                      icon: Icon(selectedPairs <= 0 ? Icons.grid_view_rounded : Icons.radar_rounded),
                      label: Text(selectedPairs <= 0 ? 'Select Markets to Scan' : 'Scan Markets Now'),
                    ),
                  ),
                ],
              ],
            ),
          )
        else
          ...signals.map((signal) => _SignalCard(signal: signal, onChanged: _load)),
        const SizedBox(height: 18),
      ],
    );
  }
}

class _SignalFlowStep extends StatelessWidget {
  const _SignalFlowStep({required this.number, required this.title, required this.detail});
  final String number;
  final String title;
  final String detail;

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 26,
            height: 26,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: AbsColors.purple.withValues(alpha: .14), borderRadius: BorderRadius.circular(9), border: Border.all(color: AbsColors.purple.withValues(alpha: .30))),
            child: Text(number, style: const TextStyle(color: AbsColors.purpleSoft, fontSize: 11, fontWeight: FontWeight.w900)),
          ),
          const SizedBox(height: 7),
          Text(title, style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w900)),
          const SizedBox(height: 2),
          Text(detail, style: const TextStyle(color: AbsColors.muted, fontSize: 9.5, height: 1.35)),
        ],
      );
}

class _SignalCard extends StatelessWidget {
  const _SignalCard({required this.signal, required this.onChanged});
  final Map<String, dynamic> signal;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    final direction = JsonTools.text(signal['direction'], '—').toUpperCase();
    final id = JsonTools.integer(signal['id']);
    final action = JsonTools.map(signal['trade_action']);
    final actionLabel = JsonTools.text(action['label'], 'Review Signal');
    final actionState = JsonTools.text(action['state'], 'review');
    final strategies = JsonTools.list(signal['strategies'] ?? signal['strategy_names'])
        .map((e) => e is Map ? JsonTools.text(e['name'] ?? e['slug']) : e.toString())
        .where((e) => e.isNotEmpty)
        .join(' · ');
    final score = JsonTools.number(signal['confidence_score'] ?? signal['score']);
    final base = JsonTools.text(signal['symbol']).replaceAll('USDT', '').replaceAll('/', '');
    final badge = base.length <= 2 ? base : base.substring(0, 2);

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: AbsCard(
        child: Column(
          children: [
            Row(
              children: [
                Container(
                  width: 38,
                  height: 38,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(color: AbsColors.panel2, borderRadius: BorderRadius.circular(12), border: Border.all(color: AbsColors.line)),
                  child: Text(badge, style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w900, color: AbsColors.goldSoft)),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(JsonTools.text(signal['symbol']), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
                      const SizedBox(height: 3),
                      Text('${JsonTools.text(signal['timeframe']).toUpperCase()} · ${strategies.isEmpty ? 'ABS qualified setup' : strategies}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AbsColors.muted, fontSize: 9.5)),
                    ],
                  ),
                ),
                StatusChip(direction, good: direction == 'LONG' ? true : direction == 'SHORT' ? false : null, warning: direction != 'LONG' && direction != 'SHORT'),
              ],
            ),
            const SizedBox(height: 13),
            Row(
              children: [
                Expanded(child: _SignalMetric(label: 'ENTRY', value: money(signal['entry_price']))),
                Expanded(child: _SignalMetric(label: 'TAKE PROFIT', value: money(signal['take_profit']), color: AbsColors.green)),
                Expanded(child: _SignalMetric(label: 'STOP LOSS', value: money(signal['stop_loss']), color: AbsColors.red)),
                Expanded(child: _SignalMetric(label: 'CONFIDENCE', value: '${number(score, digits: 0)}/100', color: score >= 80 ? AbsColors.green : AbsColors.gold)),
              ],
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: id > 0
                    ? () async {
                        await Navigator.of(context).push(MaterialPageRoute(builder: (_) => SignalDetailScreen(signalId: id)));
                        onChanged();
                      }
                    : null,
                icon: const Icon(Icons.visibility_outlined, size: 18),
                label: Text(actionLabel),
              ),
            ),
            if (actionState != 'position_active' && JsonTools.text(signal['status']).toLowerCase() == 'active')
              Align(
                alignment: Alignment.centerRight,
                child: TextButton.icon(
                  onPressed: id <= 0 ? null : () => _dismiss(context, id),
                  icon: const Icon(Icons.close_rounded, size: 16),
                  label: const Text('Dismiss'),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _dismiss(BuildContext context, int id) async {
    try {
      await SessionScope.of(context).api.patch('/pulse/signals/$id/dismiss');
      if (context.mounted) showSnack(context, 'Signal dismissed.');
      onChanged();
    } on ApiException catch (e) {
      if (context.mounted) showSnack(context, e.message, error: true);
    }
  }
}

class _SignalMetric extends StatelessWidget {
  const _SignalMetric({required this.label, required this.value, this.color});
  final String label;
  final String value;
  final Color? color;

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(color: AbsColors.muted, fontSize: 8.3, fontWeight: FontWeight.w800)),
          const SizedBox(height: 3),
          Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 10.5, color: color ?? AbsColors.text)),
        ],
      );
}
