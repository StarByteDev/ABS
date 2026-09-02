import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';
import 'account_extra_screens.dart';
import 'content_screens.dart';
import 'plans_screen.dart';
import 'positions_screen.dart';
import 'scanner_screen.dart';
import 'signal_detail_screen.dart';
import 'signals_screen.dart';
import 'trade_detail_screen.dart';
import 'trading_setup_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  String period = '30d';
  bool loading = true;
  String? error;
  Map<String, dynamic> data = {};
  Map<String, dynamic> health = {};
  Map<String, dynamic> readiness = {};
  Map<String, dynamic> marketOverview = {};

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && data.isEmpty && error == null) _load();
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
      final results = await Future.wait([
        session.api.get('/pulse/dashboard', query: {'period': period}),
        session.api.get('/pulse/market-data/health'),
        session.api.get('/pulse/execution/readiness'),
        session.api.get('/market/overview'),
      ]);
      data = JsonTools.map(results[0]);
      health = JsonTools.map(JsonTools.at(results[1], 'data', <String, dynamic>{}));
      readiness = JsonTools.map(JsonTools.at(results[2], 'data', <String, dynamic>{}));
      marketOverview = JsonTools.map(JsonTools.at(results[3], 'data', <String, dynamic>{}));
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    final user = session.user ?? {};
    if (!session.hasPulseAccess) {
      return const _BasicAccountDashboard();
    }
    final plan = JsonTools.text(JsonTools.at(data, 'summary.plan_name'), JsonTools.text(JsonTools.at(data, 'access.plan.name'), 'Pulse'));
    return AbsPage(
      title: 'Pulse',
      subtitle: '${JsonTools.text(user['name'], 'ABS Member')} · $plan',
      actions: [
        IconButton(
          onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const AccountNotificationsScreen())),
          icon: const Icon(Icons.notifications_none_rounded),
          tooltip: 'Notifications',
        ),
        IconButton(onPressed: _load, icon: const Icon(Icons.refresh_rounded), tooltip: 'Refresh'),
      ],
      child: loading
          ? const LoadingBlock(label: 'Preparing your Pulse experience...')
          : error != null
              ? ErrorBlock(message: error!, onRetry: _load)
              : RefreshIndicator(onRefresh: _load, child: _content(session)),
    );
  }

  Widget _content(AppSession session) {
    final summary = JsonTools.map(data['summary']);
    final settings = JsonTools.map(data['settings']);
    final access = JsonTools.map(data['access']);
    final recentTrades = JsonTools.mapList(data['recent_trades']);
    final activeSignals = JsonTools.mapList(data['active_signals']);
    final feedHealthy = JsonTools.boolean(health['healthy'], JsonTools.boolean(health['is_healthy']));
    final ready = JsonTools.boolean(readiness['ready']);
    final net = summary.isNotEmpty
        ? JsonTools.number(summary['net_pnl'])
        : JsonTools.number(data['realized_pnl']) + JsonTools.number(data['unrealized_pnl']);
    final openPositions = summary.isNotEmpty ? JsonTools.integer(summary['open_position_count']) : JsonTools.integer(data['open_trades']);
    final realized = summary.isNotEmpty ? summary['realized_pnl'] : data['realized_pnl'];
    final unrealized = summary.isNotEmpty ? summary['unrealized_pnl'] : data['unrealized_pnl'];
    final planName = JsonTools.text(summary['plan_name'], JsonTools.text(JsonTools.at(access, 'plan.name'), 'Pulse'));
    final env = JsonTools.text(settings['environment'], JsonTools.text(readiness['environment'], 'testnet')).toUpperCase();
    final firstName = JsonTools.text(session.user?['name'], 'Trader').trim().split(RegExp(r'\s+')).first;

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('${_greeting()}, $firstName', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900)),
                  const SizedBox(height: 4),
                  Text(session.proMode ? 'Professional mode · advanced market and trading detail' : 'Clear mode · focused actions and essential information', style: const TextStyle(color: AbsColors.muted, fontSize: 11.5)),
                ],
              ),
            ),
            ExperienceModeSwitch(
              proMode: session.proMode,
              onChanged: (value) => session.setTraderExperience(value ? 'pro' : 'simple'),
            ),
          ],
        ),
        const SizedBox(height: 14),
        PremiumHeroCard(
          eyebrow: ready ? 'Trading ready' : 'Trading setup',
          title: ready ? 'Your Pulse account is ready' : 'Complete setup before your first trade',
          message: ready
              ? 'ABS is connected to your selected environment and continues to enforce server-side execution, protection and account checks.'
              : 'ABS will guide you through Binance, risk, market selection and execution settings. Nothing is sent to the exchange until readiness checks pass.',
          trailing: Container(
            width: 58,
            height: 58,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: (ready ? AbsColors.green : AbsColors.gold).withValues(alpha: .09),
              border: Border.all(color: (ready ? AbsColors.green : AbsColors.gold).withValues(alpha: .32)),
            ),
            child: Icon(ready ? Icons.verified_rounded : Icons.route_rounded, color: ready ? AbsColors.green : AbsColors.gold, size: 28),
          ),
          footer: Wrap(
            spacing: 7,
            runSpacing: 7,
            children: [
              StatusChip(planName.toUpperCase(), good: true),
              StatusChip(env == 'LIVE' ? 'LIVE ACCOUNT' : 'TESTNET', warning: env == 'LIVE'),
              StatusChip(feedHealthy ? 'MARKET FEED HEALTHY' : 'MARKET FEED CHECK', good: feedHealthy),
            ],
          ),
        ),
        const SizedBox(height: 14),
        _LoggedMarketPulseStrip(market: marketOverview),
        if (session.updateRecommended) ...[
          const SizedBox(height: 10),
          AbsCard(
            accent: AbsColors.gold,
            child: Row(
              children: [
                const Icon(Icons.system_update_alt_rounded, color: AbsColors.gold),
                const SizedBox(width: 10),
                Expanded(child: Text('ABS Pulse ${session.recommendedMobileVersion} is recommended by the server.', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12))),
              ],
            ),
          ),
        ],
        const SizedBox(height: 20),
        if (session.proMode)
          ..._proWorkspace(summary, net, openPositions, realized, unrealized, activeSignals, recentTrades)
        else
          ..._simpleWorkspace(ready, feedHealthy, net, openPositions, activeSignals, recentTrades),
      ],
    );
  }

  List<Widget> _simpleWorkspace(
    bool ready,
    bool feedHealthy,
    double net,
    int openPositions,
    List<Map<String, dynamic>> activeSignals,
    List<Map<String, dynamic>> recentTrades,
  ) {
    final nextStep = JsonTools.text(readiness['next_step'], 'trading_setup').replaceAll('_', ' ');
    return [
      const AbsSectionTitle('Trading Flow', eyebrow: 'Clear next steps', subtitle: 'Move from setup to market scan, signal review and execution with complete visibility.'),
      const SizedBox(height: 10),
      GuidedStepCard(
        number: 1,
        title: 'Prepare your trading account',
        description: ready ? 'Your current ABS execution readiness checks are complete.' : 'Connect Binance, set risk limits, choose markets and confirm execution settings.',
        actionLabel: ready ? 'Review trading setup' : 'Continue setup · ${nextStep.toUpperCase()}',
        complete: ready,
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const TradingSetupScreen())),
      ),
      const SizedBox(height: 8),
      GuidedStepCard(
        number: 2,
        title: 'Find trading opportunities',
        description: feedHealthy ? 'The ABS central market feed is healthy. Scan your selected pairs for qualifying setups.' : 'The market feed needs to be fresh before ABS allows new scan decisions.',
        actionLabel: 'Scan Markets',
        complete: false,
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ScannerScreen())),
      ),
      const SizedBox(height: 8),
      GuidedStepCard(
        number: 3,
        title: 'Review signals before execution',
        description: '${activeSignals.length} active signal${activeSignals.length == 1 ? '' : 's'} currently available. ABS shows entry, protection and action state before you submit anything.',
        actionLabel: 'Review active signals',
        complete: false,
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const SignalsScreen())),
      ),
      const SizedBox(height: 20),
      const AbsSectionTitle('Your account at a glance', subtitle: 'Only the numbers you need first.'),
      const SizedBox(height: 10),
      Row(
        children: [
          Expanded(child: MetricCard(label: 'Net P&L', value: money(net), valueColor: pnlColor(net), detail: period.toUpperCase(), icon: Icons.account_balance_wallet_outlined)),
          const SizedBox(width: 10),
          Expanded(child: MetricCard(label: 'Open Positions', value: '$openPositions', detail: 'ABS + exchange tracked', icon: Icons.candlestick_chart_rounded)),
        ],
      ),
      const SizedBox(height: 18),
      const AbsSectionTitle('Quick actions', subtitle: 'Go straight to what you need.'),
      const SizedBox(height: 10),
      QuickActionCard(
        icon: Icons.radar_rounded,
        title: 'Market Scan',
        subtitle: 'Find qualified setups across your selected markets',
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ScannerScreen())),
        accent: AbsColors.purple,
      ),
      const SizedBox(height: 8),
      QuickActionCard(
        icon: Icons.tune_rounded,
        title: 'Trading Setup',
        subtitle: 'Binance, risk, markets and execution',
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const TradingSetupScreen())),
      ),
      const SizedBox(height: 8),
      QuickActionCard(
        icon: Icons.candlestick_chart_rounded,
        title: 'Open Positions',
        subtitle: 'Monitor P&L, protection and close state',
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PositionsScreen())),
        accent: AbsColors.gold,
      ),
      const SizedBox(height: 20),
      AbsSectionTitle('Best opportunities', subtitle: activeSignals.isEmpty ? 'No active setups need your attention.' : 'Prioritized signals ready for review.'),
      const SizedBox(height: 10),
      if (activeSignals.isEmpty)
        const AbsCard(child: Text('No active signals right now. Run Market Scan when you want ABS to search for new opportunities.', style: TextStyle(color: AbsColors.muted, fontSize: 12)))
      else
        ...activeSignals.take(3).map((s) => _SignalMiniCard(signal: s)),
      const SizedBox(height: 20),
      const AbsSectionTitle('Recent activity', subtitle: 'Your latest completed or tracked trades.'),
      const SizedBox(height: 10),
      if (recentTrades.isEmpty)
        const AbsCard(child: Text('No trades recorded yet.', style: TextStyle(color: AbsColors.muted, fontSize: 12)))
      else
        ...recentTrades.take(3).map(_tradeCard),
      const SizedBox(height: 20),
    ];
  }

  List<Widget> _proWorkspace(
    Map<String, dynamic> summary,
    double net,
    int openPositions,
    dynamic realized,
    dynamic unrealized,
    List<Map<String, dynamic>> activeSignals,
    List<Map<String, dynamic>> recentTrades,
  ) {
    return [
      AbsSectionTitle(
        'Portfolio Pulse',
        eyebrow: 'Professional view',
        subtitle: 'Dense account intelligence and execution context.',
        trailing: DropdownButton<String>(
          value: period,
          underline: const SizedBox.shrink(),
          items: const [
            DropdownMenuItem(value: '24h', child: Text('24H')),
            DropdownMenuItem(value: '7d', child: Text('7D')),
            DropdownMenuItem(value: '30d', child: Text('30D')),
            DropdownMenuItem(value: 'all', child: Text('ALL')),
          ],
          onChanged: (v) {
            if (v == null) return;
            setState(() => period = v);
            _load();
          },
        ),
      ),
      const SizedBox(height: 10),
      GridView.count(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        crossAxisCount: 2,
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
        childAspectRatio: 1.42,
        children: [
          MetricCard(label: 'Net P&L', value: money(net), valueColor: pnlColor(net), detail: period.toUpperCase(), icon: Icons.account_balance_wallet_outlined),
          MetricCard(label: 'Open Positions', value: '$openPositions', detail: 'Exchange + ABS', icon: Icons.candlestick_chart_rounded),
          MetricCard(label: 'Realized P&L', value: money(realized), valueColor: pnlColor(realized), detail: 'Closed trades', icon: Icons.done_all_rounded),
          MetricCard(label: 'Unrealized P&L', value: money(unrealized), valueColor: pnlColor(unrealized), detail: 'Open exposure', icon: Icons.timeline_rounded),
        ],
      ),
      const SizedBox(height: 18),
      AbsCard(
        child: Column(
          children: [
            KeyValueRow('Win rate', percent(summary['win_rate'])),
            KeyValueRow('Executed trades', '${JsonTools.integer(summary['executed_trades'])}'),
            KeyValueRow('Signals received', '${JsonTools.integer(summary['signals_received'])}'),
            KeyValueRow('Signal engagement', percent(summary['signal_engagement'])),
            KeyValueRow('Average setup score', number(summary['average_setup_score'], digits: 1)),
            KeyValueRow('Portfolio exposure', JsonTools.text(summary['portfolio_exposure'])),
            KeyValueRow('Risk utilization', percent(summary['risk_utilization'])),
          ],
        ),
      ),
      const SizedBox(height: 18),
      QuickActionCard(
        icon: Icons.radar_rounded,
        title: 'Market Scan',
        subtitle: 'Evaluate selected markets across 15M + 4H',
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ScannerScreen())),
        accent: AbsColors.purple,
      ),
      const SizedBox(height: 20),
      const AbsSectionTitle('Active signals', subtitle: 'Latest opportunities waiting for review.'),
      const SizedBox(height: 10),
      if (activeSignals.isEmpty)
        const AbsCard(child: Text('No active signals right now.', style: TextStyle(color: AbsColors.muted, fontSize: 12)))
      else
        ...activeSignals.take(5).map((s) => _SignalMiniCard(signal: s)),
      const SizedBox(height: 20),
      const AbsSectionTitle('Recent trades', subtitle: 'Latest execution records and realized outcomes.'),
      const SizedBox(height: 10),
      if (recentTrades.isEmpty)
        const AbsCard(child: Text('No trades recorded yet.', style: TextStyle(color: AbsColors.muted, fontSize: 12)))
      else
        ...recentTrades.take(6).map(_tradeCard),
      const SizedBox(height: 20),
    ];
  }

  Widget _tradeCard(Map<String, dynamic> trade) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => TradeDetailScreen(tradeId: JsonTools.integer(trade['id'])))),
        child: AbsCard(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              Container(
                width: 38,
                height: 38,
                alignment: Alignment.center,
                decoration: BoxDecoration(color: AbsColors.panel2, borderRadius: BorderRadius.circular(12)),
                child: const Icon(Icons.candlestick_chart_rounded, color: AbsColors.cyanSoft, size: 19),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(JsonTools.text(trade['symbol']), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 13.5)),
                    const SizedBox(height: 3),
                    Text('${JsonTools.text(trade['side'], JsonTools.text(trade['direction']))} · ${JsonTools.text(trade['status'])}', style: const TextStyle(color: AbsColors.muted, fontSize: 10.5)),
                  ],
                ),
              ),
              Text(money(trade['realized_pnl']), style: TextStyle(fontWeight: FontWeight.w900, fontSize: 13, color: pnlColor(trade['realized_pnl']))),
              const SizedBox(width: 5),
              const Icon(Icons.chevron_right_rounded, color: AbsColors.muted2),
            ],
          ),
        ),
      ),
    );
  }

  String _greeting() {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
  }
}

class _LoggedMarketPulseStrip extends StatelessWidget {
  const _LoggedMarketPulseStrip({required this.market});
  final Map<String, dynamic> market;

  @override
  Widget build(BuildContext context) {
    final global = JsonTools.map(market['global']);
    final pulse = JsonTools.map(market['pulse']);
    final core = JsonTools.mapList(market['core']);
    Map<String, dynamic>? btc;
    Map<String, dynamic>? eth;
    for (final row in core) {
      final symbol = JsonTools.text(row['symbol']).toUpperCase();
      if (symbol == 'BTCUSDT') btc = row;
      if (symbol == 'ETHUSDT') eth = row;
    }
    final score = JsonTools.integer(pulse['score']);
    return AbsCard(
      gradient: const LinearGradient(colors: [Color(0xFF15152B), Color(0xFF0C1422)]),
      accent: AbsColors.purple,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(child: Text('MARKET PULSE', style: TextStyle(color: AbsColors.muted, fontSize: 10, fontWeight: FontWeight.w900, letterSpacing: 1))),
              StatusChip(JsonTools.text(pulse['label'], 'LIVE').toUpperCase()),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Container(width: 48, height: 48, alignment: Alignment.center, decoration: BoxDecoration(color: AbsColors.purple.withValues(alpha: .12), shape: BoxShape.circle, border: Border.all(color: AbsColors.purple.withValues(alpha: .35))), child: Text(score > 0 ? '$score' : '—', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900))),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('${JsonTools.text(global['fear_greed_label'], 'Market')} · BTC Dom ${number(global['btc_dominance'], digits: 1)}%', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 13)), const SizedBox(height: 4), Text('24H Volume ${_compactDashboardUsd(global['total_volume'])} · Open Interest ${_compactDashboardUsd(global['open_interest_usd'])}', style: const TextStyle(color: AbsColors.muted, fontSize: 9.5))])),
            ],
          ),
          if (btc != null || eth != null) ...[
            const SizedBox(height: 12),
            Row(children: [
              if (btc != null) Expanded(child: _MiniMarket(row: btc)),
              if (btc != null && eth != null) const SizedBox(width: 8),
              if (eth != null) Expanded(child: _MiniMarket(row: eth)),
            ]),
          ],
        ],
      ),
    );
  }
}

class _MiniMarket extends StatelessWidget {
  const _MiniMarket({required this.row});
  final Map<String, dynamic> row;
  @override
  Widget build(BuildContext context) {
    final change = JsonTools.number(row['change_percent']);
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(color: AbsColors.panel2.withValues(alpha: .72), borderRadius: BorderRadius.circular(13), border: Border.all(color: AbsColors.lineSoft)),
      child: Row(children: [Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(JsonTools.text(row['pair'], JsonTools.text(row['symbol'])), style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(money(row['price']), style: const TextStyle(fontSize: 10, color: AbsColors.muted))])), Text(percent(change), style: TextStyle(color: pnlColor(change), fontSize: 9.5, fontWeight: FontWeight.w900))]),
    );
  }
}

String _compactDashboardUsd(dynamic value) {
  final n = JsonTools.number(value);
  if (n == 0) return '—';
  if (n.abs() >= 1e12) return '\$${(n / 1e12).toStringAsFixed(2)}T';
  if (n.abs() >= 1e9) return '\$${(n / 1e9).toStringAsFixed(1)}B';
  if (n.abs() >= 1e6) return '\$${(n / 1e6).toStringAsFixed(1)}M';
  return money(n);
}

class _BasicAccountDashboard extends StatefulWidget {
  const _BasicAccountDashboard();

  @override
  State<_BasicAccountDashboard> createState() => _BasicAccountDashboardState();
}

class _BasicAccountDashboardState extends State<_BasicAccountDashboard> {
  bool loading = true;
  String? error;
  Map<String, dynamic> data = {};

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && data.isEmpty) _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      data = JsonTools.map(JsonTools.at(await SessionScope.of(context).api.get('/dashboard'), 'data', <String, dynamic>{}));
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = SessionScope.of(context).user ?? {};
    return AbsPage(
      title: 'ABS Home',
      subtitle: JsonTools.text(user['name'], 'ABS Member'),
      actions: [
        IconButton(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const AccountNotificationsScreen())), icon: const Icon(Icons.notifications_none_rounded)),
        IconButton(onPressed: _load, icon: const Icon(Icons.refresh_rounded)),
      ],
      child: loading
          ? const LoadingBlock()
          : error != null
              ? ErrorBlock(message: error!, onRetry: _load)
              : RefreshIndicator(onRefresh: _load, child: _content()),
    );
  }

  Widget _content() {
    final market = JsonTools.map(data['market']);
    final core = JsonTools.mapList(market['core']);
    final watchlist = JsonTools.mapList(data['watchlist']);
    final news = JsonTools.mapList(data['news']);
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        PremiumHeroCard(
          eyebrow: 'ABS account active',
          title: 'Move from market watching to a structured trading process',
          message: 'Pulse brings market intelligence, signal review, Binance Futures setup, execution safeguards, open positions and performance tracking into one professional experience.',
          trailing: const Icon(Icons.workspace_premium_rounded, color: AbsColors.gold, size: 36),
          footer: SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PlansScreen())),
              icon: const Icon(Icons.bolt_rounded),
              label: const Text('Compare Pulse plans'),
            ),
          ),
        ),
        const SizedBox(height: 20),
        const AbsSectionTitle('Market snapshot', subtitle: 'Public market context from ABS.'),
        const SizedBox(height: 10),
        if (core.isEmpty)
          const AbsCard(child: Text('Market snapshot is temporarily unavailable.', style: TextStyle(color: AbsColors.muted)))
        else
          ...core.take(4).map((row) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: AbsCard(
                  padding: const EdgeInsets.all(14),
                  child: Row(
                    children: [
                      Expanded(child: Text(JsonTools.text(row['pair'], JsonTools.text(row['symbol'])), style: const TextStyle(fontWeight: FontWeight.w900))),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text(money(row['price']), style: const TextStyle(fontWeight: FontWeight.w900)),
                          Text(percent(row['change_percent']), style: TextStyle(color: pnlColor(row['change_percent']), fontSize: 10.5, fontWeight: FontWeight.w800)),
                        ],
                      ),
                    ],
                  ),
                ),
              )),
        const SizedBox(height: 8),
        QuickActionCard(
          icon: Icons.explore_outlined,
          title: 'Explore Alpha Block Solutions',
          subtitle: 'Research, learning, services and market context',
          onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ExploreAbsScreen())),
        ),
        const SizedBox(height: 20),
        AbsSectionTitle('Watchlist', subtitle: watchlist.isEmpty ? 'Save markets you want to follow.' : '${watchlist.length} saved markets'),
        const SizedBox(height: 10),
        if (watchlist.isEmpty)
          const AbsCard(child: Text('Your watchlist is empty.', style: TextStyle(color: AbsColors.muted)))
        else
          AbsCard(child: Wrap(spacing: 7, runSpacing: 7, children: watchlist.map((item) => StatusChip(JsonTools.text(item['symbol']))).toList())),
        const SizedBox(height: 20),
        const AbsSectionTitle('Latest from ABS'),
        const SizedBox(height: 10),
        ...news.take(4).map((article) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: AbsCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(JsonTools.text(article['title']), style: const TextStyle(fontWeight: FontWeight.w900)),
                    const SizedBox(height: 5),
                    Text(JsonTools.plain(article['excerpt']), maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AbsColors.muted, fontSize: 11.5)),
                  ],
                ),
              ),
            )),
        const SizedBox(height: 20),
      ],
    );
  }
}

class _SignalMiniCard extends StatelessWidget {
  const _SignalMiniCard({required this.signal});
  final Map<String, dynamic> signal;

  @override
  Widget build(BuildContext context) {
    final direction = JsonTools.text(signal['direction']).toUpperCase();
    final score = JsonTools.number(signal['score']);
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: JsonTools.integer(signal['id']) > 0
            ? () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => SignalDetailScreen(signalId: JsonTools.integer(signal['id']))))
            : null,
        child: AbsCard(
          padding: const EdgeInsets.all(14),
          accent: direction == 'LONG' ? AbsColors.green : direction == 'SHORT' ? AbsColors.red : AbsColors.gold,
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                alignment: Alignment.center,
                decoration: BoxDecoration(color: AbsColors.panel2, borderRadius: BorderRadius.circular(13)),
                child: Text(score.toStringAsFixed(0), style: const TextStyle(fontWeight: FontWeight.w900, color: AbsColors.cyanSoft, fontSize: 15)),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(child: Text(JsonTools.text(signal['symbol']), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 14))),
                        StatusChip(direction, good: direction == 'LONG', warning: direction != 'LONG' && direction != 'SHORT'),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text('${JsonTools.text(signal['timeframe'])} · Entry ${money(signal['entry_price'])}', style: const TextStyle(color: AbsColors.muted, fontSize: 10.5)),
                  ],
                ),
              ),
              const SizedBox(width: 6),
              const Icon(Icons.chevron_right_rounded, color: AbsColors.muted2),
            ],
          ),
        ),
      ),
    );
  }
}
