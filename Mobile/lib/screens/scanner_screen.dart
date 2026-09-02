import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';
import 'plans_screen.dart';
import 'signal_detail_screen.dart';

class ScannerScreen extends StatefulWidget {
  const ScannerScreen({super.key});

  @override
  State<ScannerScreen> createState() => _ScannerScreenState();
}

class _ScannerScreenState extends State<ScannerScreen> {
  bool loading = true;
  bool scanning = false;
  String timeframe = 'all';
  String? error;
  Map<String, dynamic> overview = {};
  Map<String, dynamic> usage = {};
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
        session.api.get('/pulse/scanner/overview'),
        session.api.get('/pulse/usage'),
        session.api.get('/pulse/market-data/health'),
      ]);
      overview = JsonTools.map(JsonTools.at(values[0], 'data', <String, dynamic>{}));
      usage = JsonTools.map(JsonTools.at(values[0], 'usage', JsonTools.at(values[1], 'data', <String, dynamic>{})));
      feed = JsonTools.map(JsonTools.at(values[2], 'data', <String, dynamic>{}));
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _run() async {
    if (scanning) return;
    setState(() => scanning = true);
    try {
      final response = await SessionScope.of(context).api.post('/pulse/scanner/run', body: {'timeframe': timeframe});
      usage = JsonTools.map(JsonTools.at(response, 'usage', usage));
      final run = JsonTools.map(JsonTools.at(response, 'data', <String, dynamic>{}));
      final created = JsonTools.integer(run['signals_created']);
      final pairs = JsonTools.integer(run['pairs_scanned']);
      if (mounted) {
        showSnack(
          context,
          created > 0
              ? 'Scan complete · $created new signal${created == 1 ? '' : 's'} from $pairs selected markets.'
              : 'Scan complete · no new setup met your current signal criteria.',
        );
      }
      await _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => scanning = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    if (!session.hasPulseAccess) {
      return AbsPage(
        title: 'Market Scan',
        subtitle: 'Find qualified opportunities',
        child: PulseAccessGate(onViewPlans: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PlansScreen()))),
      );
    }
    return AbsPage(
      title: 'Market Scan',
      subtitle: 'Selected markets · ABS intelligence',
      actions: [IconButton(onPressed: loading || scanning ? null : _load, icon: const Icon(Icons.refresh_rounded), tooltip: 'Refresh')],
      child: loading
          ? const LoadingBlock(label: 'Preparing market scan...')
          : error != null
              ? ErrorBlock(message: error!, onRetry: _load)
              : RefreshIndicator(onRefresh: _load, child: _content()),
    );
  }

  Widget _content() {
    final session = SessionScope.of(context);
    final results = JsonTools.mapList(overview['results']);
    final healthy = JsonTools.boolean(feed['healthy'], JsonTools.boolean(feed['is_healthy']));
    final scanUnlimited = JsonTools.boolean(JsonTools.at(usage, 'scans.unlimited'));
    final scanRemaining = JsonTools.at(
      usage,
      'scans.remaining',
      JsonTools.at(usage, 'scanner.remaining', JsonTools.at(usage, 'scanner_runs_remaining', '—')),
    );
    final signalsRemaining = JsonTools.at(usage, 'signals.remaining', JsonTools.at(usage, 'signals_remaining', '—'));
    final selectedPairs = JsonTools.integer(overview['selected_pairs']);
    final setupCount = JsonTools.integer(overview['setups_identified']);

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        AbsCard(
          padding: const EdgeInsets.all(18),
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFF15152C), Color(0xFF0C1724), Color(0xFF091019)],
          ),
          accent: healthy ? AbsColors.purple : AbsColors.gold,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('MARKET INTELLIGENCE', style: TextStyle(color: healthy ? AbsColors.purpleSoft : AbsColors.goldSoft, fontSize: 10.5, fontWeight: FontWeight.w900, letterSpacing: 1.2)),
                        const SizedBox(height: 7),
                        const Text('Find your next opportunity', style: TextStyle(fontSize: 21, fontWeight: FontWeight.w900, letterSpacing: -.3)),
                        const SizedBox(height: 7),
                        Text(
                          session.proMode
                              ? 'ABS evaluates your saved market selection using your plan strategies, signal threshold and centralized 15M / 4H market data.'
                              : 'Choose a timeframe, tap Scan Markets, and ABS will rank only the setups that meet your configured requirements.',
                          style: const TextStyle(color: AbsColors.muted, fontSize: 11.5, height: 1.45),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 12),
                  Container(
                    width: 56,
                    height: 56,
                    decoration: BoxDecoration(
                      color: (healthy ? AbsColors.green : AbsColors.gold).withValues(alpha: .09),
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: (healthy ? AbsColors.green : AbsColors.gold).withValues(alpha: .28)),
                    ),
                    child: Icon(healthy ? Icons.radar_rounded : Icons.sync_problem_rounded, color: healthy ? AbsColors.green : AbsColors.gold, size: 28),
                  ),
                ],
              ),
              const SizedBox(height: 15),
              Wrap(
                spacing: 7,
                runSpacing: 7,
                children: [
                  StatusChip(healthy ? 'MARKET DATA READY' : 'MARKET DATA DELAYED', good: healthy),
                  StatusChip('$selectedPairs SELECTED MARKETS'),
                  StatusChip(scanUnlimited ? 'UNLIMITED SCANS' : '${JsonTools.text(scanRemaining)} SCANS LEFT'),
                ],
              ),
              const SizedBox(height: 17),
              const Text('TIMEFRAME', style: TextStyle(color: AbsColors.muted, fontSize: 9.5, fontWeight: FontWeight.w900, letterSpacing: 1.0)),
              const SizedBox(height: 8),
              SegmentedButton<String>(
                segments: const [
                  ButtonSegment(value: 'all', icon: Icon(Icons.auto_graph_rounded, size: 17), label: Text('15M + 4H')),
                  ButtonSegment(value: '15m', label: Text('15M')),
                  ButtonSegment(value: '4h', label: Text('4H')),
                ],
                selected: {timeframe},
                onSelectionChanged: scanning ? null : (value) => setState(() => timeframe = value.first),
              ),
              const SizedBox(height: 13),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: scanning || !healthy || selectedPairs <= 0 ? null : _run,
                  icon: scanning
                      ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Icon(Icons.radar_rounded),
                  label: Text(
                    scanning
                        ? 'Scanning selected markets...'
                        : !healthy
                            ? 'Waiting for fresh market data'
                            : selectedPairs <= 0
                                ? 'Select markets before scanning'
                                : 'Scan $selectedPairs Markets',
                  ),
                ),
              ),
              const SizedBox(height: 8),
              Text('Latest ABS market update · ${compactDate(feed['last_price_at'] ?? feed['last_success_at'] ?? feed['latest_observed_at'])}', style: const TextStyle(color: AbsColors.muted2, fontSize: 10.2)),
            ],
          ),
        ),
        const SizedBox(height: 14),
        AbsCard(
          accent: AbsColors.cyan,
          child: const Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('HOW A MARKET SCAN WORKS', style: TextStyle(color: AbsColors.cyanSoft, fontSize: 10, fontWeight: FontWeight.w900, letterSpacing: 1.1)),
              SizedBox(height: 12),
              _ScanProcessRow(icon: Icons.grid_view_rounded, title: 'Selected markets', detail: 'ABS scans only the markets saved in your trading setup and allowed by your plan.'),
              SizedBox(height: 10),
              _ScanProcessRow(icon: Icons.psychology_alt_outlined, title: 'Strategy qualification', detail: 'Your enabled strategies and minimum signal score determine which setups qualify.'),
              SizedBox(height: 10),
              _ScanProcessRow(icon: Icons.verified_outlined, title: 'Signals only after qualification', detail: 'A scan never places a trade. Qualified setups become signals for your review.'),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Row(
          children: [
            Expanded(child: MetricCard(label: 'Selected Markets', value: '$selectedPairs', detail: 'Current selection', icon: Icons.grid_view_rounded)),
            const SizedBox(width: 10),
            Expanded(child: MetricCard(label: 'Qualified Setups', value: '$setupCount', detail: 'Latest scan', icon: Icons.bolt_rounded)),
          ],
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            Expanded(child: MetricCard(label: 'Scans Available', value: scanUnlimited ? '∞' : JsonTools.text(scanRemaining), detail: 'Current allowance', icon: Icons.radar_rounded)),
            const SizedBox(width: 10),
            Expanded(child: MetricCard(label: 'Signals Available', value: JsonTools.text(signalsRemaining), detail: 'Current allowance', icon: Icons.verified_outlined)),
          ],
        ),
        const SizedBox(height: 20),
        AbsSectionTitle(
          'Scan Results',
          subtitle: results.isEmpty
              ? 'Run a market scan to evaluate your selected markets.'
              : '${JsonTools.text(overview['market_bias'], 'Balanced')} market bias · ${results.length} evaluated setup${results.length == 1 ? '' : 's'}',
        ),
        const SizedBox(height: 10),
        if (results.isEmpty)
          const EmptyState(
            title: 'Ready when you are',
            message: 'Select a timeframe above and tap Scan Markets. Results that meet your signal requirements will appear here.',
            icon: Icons.radar_rounded,
          )
        else
          ...results.map((row) => _ScannerRow(row: row)),
        const SizedBox(height: 18),
      ],
    );
  }
}

class _ScanProcessRow extends StatelessWidget {
  const _ScanProcessRow({required this.icon, required this.title, required this.detail});
  final IconData icon;
  final String title;
  final String detail;

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(color: AbsColors.cyan.withValues(alpha: .08), borderRadius: BorderRadius.circular(11)),
            child: Icon(icon, color: AbsColors.cyanSoft, size: 18),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w900)),
                const SizedBox(height: 2),
                Text(detail, style: const TextStyle(color: AbsColors.muted, fontSize: 10.2, height: 1.4)),
              ],
            ),
          ),
        ],
      );
}

class _ScannerRow extends StatelessWidget {
  const _ScannerRow({required this.row});
  final Map<String, dynamic> row;

  @override
  Widget build(BuildContext context) {
    final direction = JsonTools.text(row['direction'], 'WAIT').toUpperCase();
    final signalId = JsonTools.integer(row['id']);
    final action = JsonTools.map(row['trade_action']);
    final actionLabel = JsonTools.text(action['label'], signalId > 0 ? 'Review Signal' : 'Monitor');
    final score = JsonTools.number(row['score']);
    return Padding(
      padding: const EdgeInsets.only(bottom: 9),
      child: AbsCard(
        child: Column(
          children: [
            Row(
              children: [
                StatusChip(direction, good: direction == 'LONG' ? true : direction == 'SHORT' ? false : null, warning: direction == 'WAIT' || direction == 'NEUTRAL'),
                const SizedBox(width: 10),
                Expanded(child: Text(JsonTools.text(row['symbol']), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16))),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(number(score, digits: 0), style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18, color: score >= 80 ? AbsColors.green : AbsColors.cyan)),
                    const Text('SCORE', style: TextStyle(color: AbsColors.muted2, fontSize: 8.5, fontWeight: FontWeight.w800)),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 11),
            Row(
              children: [
                Expanded(child: _Mini(label: 'MARKET PRICE', value: money(row['last_price'] ?? row['price']))),
                Expanded(child: _Mini(label: 'ENTRY', value: money(row['entry_price']))),
                Expanded(child: _Mini(label: 'TIMEFRAME', value: JsonTools.text(row['timeframe']).toUpperCase())),
              ],
            ),
            const SizedBox(height: 11),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: signalId <= 0 ? null : () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => SignalDetailScreen(signalId: signalId))),
                icon: const Icon(Icons.visibility_outlined, size: 18),
                label: Text(actionLabel),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Mini extends StatelessWidget {
  const _Mini({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(fontSize: 8.5, color: AbsColors.muted, fontWeight: FontWeight.w800)),
          const SizedBox(height: 3),
          Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 11)),
        ],
      );
}
