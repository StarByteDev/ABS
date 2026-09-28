import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../template_ui/common.dart';
import '../widgets/abs_ui.dart';
import 'alerts_screen.dart';
import 'market_extra_screens.dart';
import 'plans_screen.dart';
import 'positions_screen.dart';
import 'profile_screen.dart';
import 'reports_screen.dart';
import 'scanner_screen.dart';
import 'signal_detail_screen.dart';
import 'signals_screen.dart';
import 'strategies_screen.dart';
import 'trading_setup_screen.dart';

class TemplatePulseScreen extends StatefulWidget {
  const TemplatePulseScreen({super.key});

  @override
  State<TemplatePulseScreen> createState() => _TemplatePulseScreenState();
}

class _TemplatePulseScreenState extends State<TemplatePulseScreen>
    with SingleTickerProviderStateMixin {
  late final TabController tabs;
  bool loading = true;
  bool scanning = false;
  String? error;
  Map<String, dynamic> overview = {};
  Map<String, dynamic> scanner = {};
  Map<String, dynamic> feed = {};
  List<Map<String, dynamic>> watchlist = [];
  String filter = 'All';

  static const filters = ['All', 'Long', 'Short', '15M', '4H'];

  @override
  void initState() {
    super.initState();
    tabs = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    tabs.dispose();
    super.dispose();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && overview.isEmpty && error == null) _load();
  }

  Future<void> _load() async {
    final session = SessionScope.of(context);
    if (!session.authenticated || !session.emailVerified || !session.hasPulseAccess) {
      if (mounted) setState(() => loading = false);
      return;
    }
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final values = await Future.wait<dynamic>([
        session.api.get('/pulse/signals/overview', query: const {'status': 'active'}),
        session.api.get('/pulse/scanner/overview'),
        session.api.get('/pulse/market-data/health'),
        session.api.get('/watchlist'),
      ]);
      overview = JsonTools.map(JsonTools.at(values[0], 'data', <String, dynamic>{}));
      scanner = JsonTools.map(JsonTools.at(values[1], 'data', <String, dynamic>{}));
      feed = JsonTools.map(JsonTools.at(values[2], 'data', <String, dynamic>{}));
      watchlist = JsonTools.mapList(JsonTools.at(values[3], 'data', <dynamic>[]));
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _scan() async {
    if (scanning) return;
    final selected = JsonTools.integer(scanner['selected_pairs']);
    final healthy = JsonTools.boolean(feed['healthy'], JsonTools.boolean(feed['is_healthy']));
    if (selected <= 0) {
      await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const TradingSetupScreen(initialSection: 'markets')));
      if (mounted) _load();
      return;
    }
    if (!healthy) {
      showSnack(context, 'ABS market data is updating. Scans resume when the central feed is fresh.', error: true);
      return;
    }
    setState(() => scanning = true);
    try {
      final response = await SessionScope.of(context).api.post('/pulse/scanner/run', body: const {'timeframe': 'all'});
      final run = JsonTools.map(JsonTools.at(response, 'data', <String, dynamic>{}));
      final created = JsonTools.integer(run['signals_created']);
      if (mounted) {
        showSnack(context, created > 0 ? 'Scan complete · $created new signal${created == 1 ? '' : 's'}.' : 'Scan complete · no setup met your current requirements.');
      }
      await _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => scanning = false);
    }
  }

  List<Map<String, dynamic>> get filteredSignals {
    final signals = JsonTools.mapList(overview['signals']);
    return signals.where((s) {
      final direction = JsonTools.text(s['direction']).toUpperCase();
      final timeframe = JsonTools.text(s['timeframe']).toUpperCase();
      switch (filter) {
        case 'Long':
          return direction == 'LONG' || direction == 'BUY';
        case 'Short':
          return direction == 'SHORT' || direction == 'SELL';
        case '15M':
        case '4H':
          return timeframe == filter;
        default:
          return true;
      }
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    return Scaffold(
      backgroundColor: AbsColors.bg,
      appBar: AppBar(
        titleSpacing: 16,
        title: const Row(
          children: [
            PulseLogo(size: 36),
            SizedBox(width: 10),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Pulse', style: TextStyle(fontSize: 19, fontWeight: FontWeight.w900)),
                Text('Signals · scan · review · act', style: TextStyle(color: AbsColors.muted, fontSize: 10.5, fontWeight: FontWeight.w500)),
              ],
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Price alerts',
            onPressed: session.emailVerified ? () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const AlertsScreen())) : null,
            icon: const Icon(Icons.notifications_none_rounded),
          ),
          IconButton(onPressed: _load, icon: const Icon(Icons.refresh_rounded)),
        ],
        bottom: TabBar(
          controller: tabs,
          labelColor: AbsColors.text,
          unselectedLabelColor: AbsColors.muted,
          indicatorColor: AbsColors.cyan,
          dividerColor: AbsColors.line,
          labelStyle: const TextStyle(fontWeight: FontWeight.w800),
          tabs: const [Tab(text: 'Signals'), Tab(text: 'Watchlist')],
        ),
      ),
      body: !session.authenticated || !session.emailVerified || !session.hasPulseAccess
          ? _locked(session)
          : loading && overview.isEmpty
              ? const Center(child: LoadingBlock(label: 'Loading Pulse signals...'))
              : error != null && overview.isEmpty
                  ? Padding(padding: const EdgeInsets.all(16), child: ErrorBlock(message: error!, onRetry: _load))
                  : TabBarView(controller: tabs, children: [_signalsTab(), _watchlistTab()]),
    );
  }

  Widget _locked(AppSession session) {
    final activation = session.authenticated && !session.emailVerified;
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 30),
      children: [
        TemplateCard(
          borderColor: templateFade(activation ? AbsColors.gold : AbsColors.cyan, .42),
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [templateFade(activation ? AbsColors.gold : AbsColors.cyan, .12), AbsColors.panel],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(activation ? Icons.mark_email_unread_outlined : Icons.bolt_rounded, color: activation ? AbsColors.gold : AbsColors.cyan, size: 30),
              const SizedBox(height: 14),
              Text(activation ? 'Activate to unlock Pulse' : 'Pulse trading tools', style: const TextStyle(fontSize: 21, fontWeight: FontWeight.w900)),
              const SizedBox(height: 7),
              Text(
                activation
                    ? 'You can use market intelligence, Free Signal and ABS News now. Activate your email to unlock membership, scanner, full signals, execution and positions.'
                    : 'Sign in and activate a Pulse package to scan selected markets, review qualified setups and manage positions with ABS safeguards.',
                style: const TextStyle(color: AbsColors.muted, fontSize: 12.5, height: 1.5),
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: activation
                      ? () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ProfileScreen()))
                      : () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PlansScreen())),
                  child: Text(activation ? 'Activate from Account' : 'View Pulse Plans'),
                ),
              ),
            ],
          ),
        ),
        const TemplateSectionTitle('What Pulse unlocks'),
        _feature(Icons.radar_rounded, 'Market Scanner', 'Run the ABS strategy engine across your selected pairs.'),
        _feature(Icons.bolt_rounded, 'Qualified Signals', 'Entry, stop, targets, confidence and strategy reasoning.'),
        _feature(Icons.candlestick_chart_rounded, 'Positions', 'Exchange-confirmed state, protection status and P&L.'),
        _feature(Icons.analytics_outlined, 'Performance Intelligence', 'Signal outcomes, strategy performance and learning reports.'),
      ],
    );
  }

  Widget _feature(IconData icon, String title, String detail) => Padding(
        padding: const EdgeInsets.only(bottom: 9),
        child: TemplateCard(
          padding: const EdgeInsets.all(13),
          child: Row(
            children: [
              Container(width: 40, height: 40, decoration: BoxDecoration(color: templateFade(AbsColors.cyan, .09), borderRadius: BorderRadius.circular(11)), child: Icon(icon, color: AbsColors.cyanSoft, size: 20)),
              const SizedBox(width: 11),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13)), const SizedBox(height: 3), Text(detail, style: const TextStyle(color: AbsColors.muted, fontSize: 10.5))])),
            ],
          ),
        ),
      );

  Widget _signalsTab() {
    final signals = filteredSignals;
    final activeCount = JsonTools.integer(overview['active_count']);
    final highConviction = JsonTools.integer(overview['high_conviction_count']);
    final selected = JsonTools.integer(scanner['selected_pairs']);
    final healthy = JsonTools.boolean(feed['healthy'], JsonTools.boolean(feed['is_healthy']));
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 30),
        children: [
          _scanCard(selected, healthy),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: _metric('Active signals', '$activeCount', Icons.bolt_rounded)),
              const SizedBox(width: 9),
              Expanded(child: _metric('High confidence', '$highConviction', Icons.verified_outlined)),
            ],
          ),
          const TemplateSectionTitle('Signal filters'),
          SizedBox(
            height: 38,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: filters.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (_, i) {
                final value = filters[i];
                final selectedFilter = filter == value;
                return ChoiceChip(
                  label: Text(value),
                  selected: selectedFilter,
                  showCheckmark: false,
                  onSelected: (_) => setState(() => filter = value),
                  selectedColor: templateFade(AbsColors.cyan, .15),
                  backgroundColor: AbsColors.panel,
                  side: BorderSide(color: selectedFilter ? templateFade(AbsColors.cyan, .45) : AbsColors.line),
                  labelStyle: TextStyle(color: selectedFilter ? AbsColors.cyanSoft : AbsColors.muted, fontWeight: FontWeight.w700, fontSize: 11),
                );
              },
            ),
          ),
          TemplateSectionTitle('Current opportunities', action: 'Full view', onAction: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const SignalsScreen()))),
          if (signals.isEmpty)
            TemplateCard(
              child: Column(
                children: [
                  const Icon(Icons.radar_rounded, color: AbsColors.cyanSoft, size: 34),
                  const SizedBox(height: 10),
                  const Text('No signals match this view', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                  const SizedBox(height: 5),
                  const Text('Run a market scan or choose another filter.', textAlign: TextAlign.center, style: TextStyle(color: AbsColors.muted, fontSize: 11.5)),
                  const SizedBox(height: 12),
                  SizedBox(width: double.infinity, child: ElevatedButton(onPressed: _scan, child: const Text('Scan Markets'))),
                ],
              ),
            )
          else
            ...signals.map(_signalCard),
          const TemplateSectionTitle('Pulse tools'),
          _toolGrid(),
          const TemplateRiskNotice(),
        ],
      ),
    );
  }

  Widget _scanCard(int selected, bool healthy) => TemplateCard(
        borderColor: templateFade(AbsColors.gold, .4),
        gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [templateFade(AbsColors.gold, .08), AbsColors.panel]),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(width: 42, height: 42, decoration: BoxDecoration(color: templateFade(AbsColors.gold, .10), borderRadius: BorderRadius.circular(12)), child: const Icon(Icons.radar_rounded, color: AbsColors.gold, size: 21)),
                const SizedBox(width: 11),
                const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('MARKET SCAN', style: TextStyle(color: AbsColors.goldSoft, fontSize: 9.5, fontWeight: FontWeight.w900, letterSpacing: 1)), SizedBox(height: 2), Text('Find fresh opportunities', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18))])),
              ],
            ),
            const SizedBox(height: 10),
            Wrap(spacing: 7, runSpacing: 7, children: [TemplatePill('$selected MARKETS'), TemplatePill(healthy ? 'DATA READY' : 'DATA UPDATING', color: healthy ? AbsColors.green : AbsColors.red)]),
            const SizedBox(height: 10),
            const Text('One tap evaluates your selected markets and shows only setups that pass your ABS strategy and signal requirements.', style: TextStyle(color: AbsColors.muted, fontSize: 11.5, height: 1.45)),
            const SizedBox(height: 13),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: scanning ? null : _scan,
                icon: scanning ? const SizedBox(width: 17, height: 17, child: CircularProgressIndicator(strokeWidth: 2)) : Icon(selected <= 0 ? Icons.grid_view_rounded : Icons.radar_rounded),
                label: Text(scanning ? 'Scanning...' : selected <= 0 ? 'Select Markets to Scan' : 'Scan $selected Markets Now'),
              ),
            ),
          ],
        ),
      );

  Widget _metric(String label, String value, IconData icon) => TemplateCard(
        padding: const EdgeInsets.all(13),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: AbsColors.cyanSoft, size: 20),
            const SizedBox(height: 10),
            Text(value, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900)),
            const SizedBox(height: 3),
            Text(label, style: const TextStyle(color: AbsColors.muted, fontSize: 10.5)),
          ],
        ),
      );

  Widget _signalCard(Map<String, dynamic> signal) {
    final id = JsonTools.integer(signal['id']);
    final direction = JsonTools.text(signal['direction'], 'WATCH').toUpperCase();
    final score = JsonTools.number(signal['confidence_score'] ?? signal['score']);
    final sideColor = direction == 'LONG' || direction == 'BUY' ? AbsColors.green : direction == 'SHORT' || direction == 'SELL' ? AbsColors.red : AbsColors.gold;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: TemplateCard(
        onTap: id > 0 ? () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => SignalDetailScreen(signalId: id))).then((_) => _load()) : null,
        child: Column(
          children: [
            Row(
              children: [
                Container(width: 40, height: 40, alignment: Alignment.center, decoration: BoxDecoration(color: templateFade(sideColor, .08), borderRadius: BorderRadius.circular(11)), child: Icon(direction == 'SHORT' || direction == 'SELL' ? Icons.south_east_rounded : Icons.north_east_rounded, color: sideColor)),
                const SizedBox(width: 10),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(JsonTools.text(signal['symbol']), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 15)), const SizedBox(height: 2), Text('${JsonTools.text(signal['timeframe']).toUpperCase()} · ABS qualified setup', style: const TextStyle(color: AbsColors.muted, fontSize: 10))])),
                TemplatePill(direction, color: sideColor),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(child: TemplateMiniStat(label: 'Entry', value: money(signal['entry_price']))),
                Expanded(child: TemplateMiniStat(label: 'Take profit', value: money(signal['take_profit']))),
                Expanded(child: TemplateMiniStat(label: 'Stop loss', value: money(signal['stop_loss']))),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                const Text('Confidence', style: TextStyle(color: AbsColors.muted, fontSize: 10.5)),
                const Spacer(),
                Text('${score.toStringAsFixed(0)}/100', style: TextStyle(color: score >= 80 ? AbsColors.green : AbsColors.gold, fontWeight: FontWeight.w900, fontSize: 12)),
                const SizedBox(width: 4),
                const Icon(Icons.chevron_right_rounded, color: AbsColors.muted2, size: 18),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _watchlistTab() {
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 30),
        children: [
          Row(
            children: [
              Expanded(child: Text('${watchlist.length} saved ${watchlist.length == 1 ? 'market' : 'markets'}', style: const TextStyle(color: AbsColors.muted, fontSize: 11.5))),
              TextButton(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const WatchlistScreen())).then((_) => _load()), child: const Text('Manage')),
            ],
          ),
          const SizedBox(height: 6),
          if (watchlist.isEmpty)
            TemplateCard(
              child: Column(
                children: [
                  const Icon(Icons.star_border_rounded, color: AbsColors.gold, size: 34),
                  const SizedBox(height: 10),
                  const Text('Your watchlist is empty', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                  const SizedBox(height: 5),
                  const Text('Save the markets you want to keep close.', style: TextStyle(color: AbsColors.muted, fontSize: 11.5)),
                  const SizedBox(height: 12),
                  OutlinedButton(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const WatchlistScreen())).then((_) => _load()), child: const Text('Add markets')),
                ],
              ),
            )
          else
            TemplateCard(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Column(
                children: watchlist.map((item) {
                  final symbol = JsonTools.text(item['symbol']);
                  return ListTile(
                    leading: const Icon(Icons.star_rounded, color: AbsColors.gold),
                    title: Text(symbol, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5)),
                    subtitle: JsonTools.text(item['display_name'], '').isEmpty ? null : Text(JsonTools.text(item['display_name']), style: const TextStyle(color: AbsColors.muted, fontSize: 10)),
                    trailing: const Icon(Icons.chevron_right_rounded, color: AbsColors.muted2),
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => MarketDetailScreen(symbol: symbol))),
                  );
                }).toList(),
              ),
            ),
          const TemplateSectionTitle('Pulse tools'),
          _toolGrid(),
        ],
      ),
    );
  }

  Widget _toolGrid() {
    final tools = <(IconData, String, Widget)>[
      (Icons.radar_rounded, 'Scanner', const ScannerScreen()),
      (Icons.candlestick_chart_rounded, 'Positions', const PositionsScreen()),
      (Icons.analytics_outlined, 'Reports', const ReportsScreen()),
      (Icons.auto_graph_rounded, 'Strategies', const StrategiesScreen()),
    ];
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: tools.length,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 2, crossAxisSpacing: 9, mainAxisSpacing: 9, childAspectRatio: 1.65),
      itemBuilder: (_, i) {
        final item = tools[i];
        return TemplateCard(
          onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => item.$3)),
          padding: const EdgeInsets.all(13),
          child: Row(
            children: [
              Icon(item.$1, color: AbsColors.cyanSoft, size: 21),
              const SizedBox(width: 9),
              Expanded(child: Text(item.$2, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12.5))),
            ],
          ),
        );
      },
    );
  }
}
