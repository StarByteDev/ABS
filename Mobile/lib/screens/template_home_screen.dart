import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../template_ui/charts.dart';
import '../template_ui/common.dart';
import '../widgets/abs_ui.dart';
import 'alerts_screen.dart';
import 'market_extra_screens.dart';
import 'plans_screen.dart';
import 'scanner_screen.dart';

class TemplateHomeScreen extends StatefulWidget {
  const TemplateHomeScreen({super.key, required this.onTab});
  final ValueChanged<int> onTab;

  @override
  State<TemplateHomeScreen> createState() => _TemplateHomeScreenState();
}

class _TemplateHomeScreenState extends State<TemplateHomeScreen> {
  bool loading = true;
  String? error;
  Map<String, dynamic> overview = {};
  Map<String, dynamic> movers = {};
  Map<String, dynamic> chart = {};
  List<Map<String, dynamic>> headlines = [];
  String interval = '1h';
  int? hover;

  static const intervals = <String, String>{
    '1H': '5m',
    '1D': '1h',
    '1W': '4h',
    '1M': '1d',
  };

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && overview.isEmpty && error == null) _load();
  }

  Future<void> _load({bool force = false}) async {
    if (!mounted) return;
    setState(() {
      loading = true;
      error = null;
    });
    final api = SessionScope.of(context).api;
    try {
      final results = await Future.wait<dynamic>([
        api.get('/market/overview', query: {if (force) 'refresh': 1}),
        api.get('/market/movers'),
        api.get('/market/chart/BTCUSDT', query: {'interval': interval, 'limit': 80}),
        api.get('/news'),
      ]);
      overview = JsonTools.map(JsonTools.at(results[0], 'data', <String, dynamic>{}));
      movers = JsonTools.map(JsonTools.at(results[1], 'data', <String, dynamic>{}));
      chart = JsonTools.map(JsonTools.at(results[2], 'data', <String, dynamic>{}));
      headlines = JsonTools.collectionItems(results[3]);
    } on ApiException catch (e) {
      error = e.message;
    } catch (_) {
      error = 'ABS market intelligence is temporarily unavailable.';
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _loadChart(String value) async {
    setState(() {
      interval = value;
      hover = null;
    });
    try {
      final response = await SessionScope.of(context).api.get(
        '/market/chart/BTCUSDT',
        query: {'interval': value, 'limit': 80},
      );
      chart = JsonTools.map(JsonTools.at(response, 'data', <String, dynamic>{}));
      if (mounted) setState(() {});
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    }
  }

  String get greeting {
    final h = DateTime.now().hour;
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
  }

  String _displayName(AppSession session) {
    final user = session.user ?? <String, dynamic>{};
    final direct = JsonTools.text(user['name'], '');
    if (direct.isNotEmpty) return direct.split(' ').first;
    final first = JsonTools.text(user['first_name'], '');
    return first.isNotEmpty ? first : 'Trader';
  }

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    return Scaffold(
      backgroundColor: AbsColors.bg,
      body: SafeArea(
        bottom: false,
        child: loading && overview.isEmpty
            ? const Center(child: LoadingBlock(label: 'Loading Pulse market intelligence...'))
            : error != null && overview.isEmpty
                ? Padding(
                    padding: const EdgeInsets.all(18),
                    child: ErrorBlock(message: error!, onRetry: _load),
                  )
                : RefreshIndicator(
                    onRefresh: () => _load(force: true),
                    color: AbsColors.cyan,
                    backgroundColor: AbsColors.panel,
                    child: ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
                      children: [
                        _topBar(session),
                        if (session.limitedAccount) ...[
                          const SizedBox(height: 12),
                          _activationBanner(),
                        ],
                        const SizedBox(height: 14),
                        _btcHero(),
                        const TemplateSectionTitle('Market pulse'),
                        _marketPulseCard(),
                        const SizedBox(height: 10),
                        _sentimentCard(),
                        const TemplateSectionTitle('Futures'),
                        _futuresGrid(),
                        const SizedBox(height: 10),
                        _liquidationCard(),
                        TemplateSectionTitle(
                          'Majors',
                          action: 'All markets',
                          onAction: () => Navigator.of(context).push(
                            MaterialPageRoute(builder: (_) => const PublicMarketOverviewScreen()),
                          ),
                        ),
                        _majors(),
                        const TemplateSectionTitle('Top movers'),
                        _movers(),
                        if (session.authenticated && session.emailVerified && !session.hasPulseAccess) ...[
                          const SizedBox(height: 22),
                          _membershipPromo(),
                        ],
                        TemplateSectionTitle(
                          'Latest headlines',
                          action: 'Intelligence',
                          onAction: () => widget.onTab(3),
                        ),
                        _headlineCards(),
                        const TemplateRiskNotice(),
                      ],
                    ),
                  ),
      ),
    );
  }

  Widget _topBar(AppSession session) => Row(
        children: [
          const PulseLogo(size: 40),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(greeting, style: const TextStyle(color: AbsColors.muted, fontSize: 11.5)),
                const SizedBox(height: 1),
                Text(
                  _displayName(session),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800),
                ),
              ],
            ),
          ),
          if (session.hasPulseAccess)
            const Padding(
              padding: EdgeInsets.only(right: 4),
              child: TemplatePill('PULSE ACTIVE', color: AbsColors.green),
            )
          else if (session.authenticated)
            const Padding(
              padding: EdgeInsets.only(right: 4),
              child: TemplatePill('FREE', color: AbsColors.gold),
            ),
          IconButton(
            tooltip: 'Price alerts',
            onPressed: session.emailVerified
                ? () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const AlertsScreen()))
                : null,
            icon: const Icon(Icons.notifications_none_rounded),
          ),
        ],
      );

  Widget _activationBanner() => TemplateCard(
        borderColor: templateFade(AbsColors.gold, .45),
        onTap: () => widget.onTab(4),
        padding: const EdgeInsets.all(13),
        child: const Row(
          children: [
            Icon(Icons.mark_email_unread_outlined, color: AbsColors.gold, size: 21),
            SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Activate your account', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13)),
                  SizedBox(height: 2),
                  Text('Basic access is available now. Activate from Profile to unlock trading features.', style: TextStyle(color: AbsColors.muted, fontSize: 10.5)),
                ],
              ),
            ),
            Icon(Icons.chevron_right_rounded, color: AbsColors.muted2),
          ],
        ),
      );

  Map<String, dynamic> get _global => JsonTools.map(overview['global']);
  Map<String, dynamic> get _pulse => JsonTools.map(overview['pulse']);
  Map<String, dynamic> get _sentiment => JsonTools.map(overview['sentiment']);
  Map<String, dynamic> get _insights => JsonTools.map(overview['insights']);
  List<Map<String, dynamic>> get _core => JsonTools.mapList(overview['core']);

  Map<String, dynamic> get _btc {
    for (final row in _core) {
      final symbol = JsonTools.text(row['symbol'], '').toUpperCase();
      final base = JsonTools.text(row['base'], '').toUpperCase();
      if (symbol.startsWith('BTC') || base == 'BTC') return row;
    }
    return _core.isNotEmpty ? _core.first : <String, dynamic>{};
  }

  Widget _btcHero() {
    final candles = JsonTools.mapList(chart['candles']);
    final closes = candles
        .map((row) => JsonTools.number(row['close']))
        .where((value) => value > 0)
        .toList();
    final row = _btc;
    final livePrice = JsonTools.number(row['price'], closes.isNotEmpty ? closes.last : 0);
    final shown = hover != null && hover! >= 0 && hover! < closes.length ? closes[hover!] : livePrice;
    final change = JsonTools.number(row['change_percent'] ?? row['change_percent_24h']);
    final color = change >= 0 ? AbsColors.green : AbsColors.red;
    return TemplateCard(
      padding: const EdgeInsets.fromLTRB(16, 15, 16, 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: templateFade(AbsColors.gold, .12),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.currency_bitcoin_rounded, color: AbsColors.gold),
              ),
              const SizedBox(width: 10),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('BTC/USDT', style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w800)),
                    Text('Bitcoin · ABS market feed', style: TextStyle(color: AbsColors.muted, fontSize: 10.5)),
                  ],
                ),
              ),
              TemplatePill(JsonTools.boolean(chart['is_live'], true) ? 'LIVE' : 'MARKET'),
            ],
          ),
          const SizedBox(height: 11),
          Text(
            money(shown),
            style: const TextStyle(fontSize: 32, fontWeight: FontWeight.w900, letterSpacing: -.8),
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              TemplateChangeBadge(change),
              const SizedBox(width: 8),
              const Text('24h', style: TextStyle(color: AbsColors.muted, fontSize: 11)),
            ],
          ),
          const SizedBox(height: 12),
          SizedBox(
            height: 152,
            child: closes.length >= 2
                ? InteractiveLineChart(
                    values: closes,
                    color: color,
                    onHover: (index) => setState(() => hover = index),
                  )
                : const Center(child: Text('Chart is syncing', style: TextStyle(color: AbsColors.muted))),
          ),
          const SizedBox(height: 10),
          Row(
            children: intervals.entries.map((entry) {
              final selected = interval == entry.value;
              return Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 2),
                  child: InkWell(
                    onTap: () => _loadChart(entry.value),
                    borderRadius: BorderRadius.circular(9),
                    child: Container(
                      alignment: Alignment.center,
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      decoration: BoxDecoration(
                        color: selected ? templateFade(AbsColors.cyan, .12) : Colors.transparent,
                        borderRadius: BorderRadius.circular(9),
                        border: Border.all(color: selected ? templateFade(AbsColors.cyan, .45) : AbsColors.lineSoft),
                      ),
                      child: Text(
                        entry.key,
                        style: TextStyle(
                          color: selected ? AbsColors.cyanSoft : AbsColors.muted,
                          fontSize: 10.5,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ),
                ),
              );
            }).toList(),
          ),
          const Divider(height: 26),
          Row(
            children: [
              Expanded(child: TemplateMiniStat(label: '24h high', value: money(row['high'] ?? row['high_24h']))),
              Expanded(child: TemplateMiniStat(label: '24h low', value: money(row['low'] ?? row['low_24h']))),
              Expanded(child: TemplateMiniStat(label: '24h volume', value: _compactUsd(row['volume'] ?? row['quote_volume']))),
            ],
          ),
        ],
      ),
    );
  }

  Widget _marketPulseCard() {
    final score = JsonTools.integer(_pulse['score']);
    final label = JsonTools.text(_pulse['label'], 'Market');
    final insight = JsonTools.text(_insights['daily_insight'], 'ABS market intelligence is syncing.');
    return TemplateCard(
      child: Column(
        children: [
          Row(
            children: [
              SizedBox(width: 126, height: 76, child: PulseGauge(score: score.clamp(0, 100).toInt())),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Current reading', style: TextStyle(color: AbsColors.muted, fontSize: 10.5)),
                    const SizedBox(height: 2),
                    Text(label, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: AbsColors.gold)),
                    const SizedBox(height: 2),
                    Text('Score $score of 100', style: const TextStyle(color: AbsColors.muted, fontSize: 10.5)),
                  ],
                ),
              ),
            ],
          ),
          const Divider(height: 26),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(Icons.auto_awesome_outlined, color: AbsColors.gold, size: 19),
              const SizedBox(width: 9),
              Expanded(child: Text(insight, style: const TextStyle(fontSize: 12.5, height: 1.45))),
            ],
          ),
        ],
      ),
    );
  }

  Widget _sentimentCard() {
    final bullish = JsonTools.integer(_sentiment['bullish']);
    final neutral = JsonTools.integer(_sentiment['neutral']);
    final bearish = JsonTools.integer(_sentiment['bearish']);
    final total = bullish + neutral + bearish;
    final score = total <= 0 ? 0 : ((bullish * 100 + neutral * 50) / total).round();
    return TemplateCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(child: Text('Market sentiment', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5))),
              Text('$score', style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w900)),
              const Text(' / 100', style: TextStyle(color: AbsColors.muted, fontSize: 11)),
            ],
          ),
          const SizedBox(height: 12),
          SplitBar(parts: [
            (bullish.toDouble(), AbsColors.green),
            (neutral.toDouble(), AbsColors.muted),
            (bearish.toDouble(), AbsColors.red),
          ]),
          const SizedBox(height: 10),
          Wrap(
            spacing: 16,
            runSpacing: 6,
            children: [
              _legend(AbsColors.green, 'Bullish $bullish%'),
              _legend(AbsColors.muted, 'Neutral $neutral%'),
              _legend(AbsColors.red, 'Bearish $bearish%'),
            ],
          ),
        ],
      ),
    );
  }

  Widget _legend(Color color, String text) => Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(width: 7, height: 7, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
          const SizedBox(width: 5),
          Text(text, style: const TextStyle(color: AbsColors.muted, fontSize: 10.5)),
        ],
      );

  Widget _futuresGrid() {
    final g = _global;
    final values = <(String, String)>[
      ('Open interest', _compactUsd(g['open_interest_usd'])),
      ('Funding rate', _rate(g['funding_rate'])),
      ('Long / Short', number(g['long_short_ratio'], digits: 2)),
      ('Perp basis', _rate(g['perp_premium_basis'])),
    ];
    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      padding: EdgeInsets.zero,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 9,
      crossAxisSpacing: 9,
      childAspectRatio: 1.7,
      children: values.map((item) {
        return TemplateCard(
          padding: const EdgeInsets.all(13),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(item.$1, style: const TextStyle(color: AbsColors.muted, fontSize: 10.5)),
              const SizedBox(height: 5),
              Text(item.$2, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900)),
            ],
          ),
        );
      }).toList(),
    );
  }

  Widget _liquidationCard() {
    final total = JsonTools.number(_global['liquidation_24h_usd']);
    final longs = JsonTools.number(_global['liquidation_long_24h_usd']);
    final shorts = JsonTools.number(_global['liquidation_short_24h_usd']);
    return TemplateCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(child: Text('24h liquidations', style: TextStyle(fontWeight: FontWeight.w800))),
              Text(_compactUsd(total), style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w900)),
            ],
          ),
          const SizedBox(height: 11),
          SplitBar(parts: [
            (longs, AbsColors.red),
            (shorts, AbsColors.green),
          ]),
          const SizedBox(height: 9),
          Row(
            children: [
              Expanded(child: TemplateMiniStat(label: 'Longs', value: _compactUsd(longs))),
              Expanded(child: TemplateMiniStat(label: 'Shorts', value: _compactUsd(shorts))),
            ],
          ),
        ],
      ),
    );
  }

  Widget _majors() {
    if (_core.isEmpty) {
      return const TemplateCard(child: Text('Core market data is syncing.', style: TextStyle(color: AbsColors.muted)));
    }
    return TemplateCard(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Column(
        children: _core.take(6).map((row) {
          final symbol = JsonTools.text(row['symbol'], JsonTools.text(row['pair']));
          final base = JsonTools.text(row['base'], symbol.replaceAll('USDT', ''));
          final change = JsonTools.number(row['change_percent'] ?? row['change_percent_24h']);
          return Column(
            children: [
              ListTile(
                dense: true,
                contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 2),
                onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => MarketDetailScreen(symbol: symbol.isEmpty ? '${base}USDT' : symbol))),
                leading: CircleAvatar(
                  radius: 17,
                  backgroundColor: templateFade(AbsColors.cyan, .10),
                  child: Text(base.isNotEmpty ? base.substring(0, 1) : '?', style: const TextStyle(color: AbsColors.cyanSoft, fontWeight: FontWeight.w900)),
                ),
                title: Text(base, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5)),
                subtitle: Text(symbol, style: const TextStyle(color: AbsColors.muted, fontSize: 9.5)),
                trailing: SizedBox(
                  width: 132,
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      Flexible(child: Text(money(row['price']), overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12))),
                      const SizedBox(width: 8),
                      TemplateChangeBadge(change),
                    ],
                  ),
                ),
              ),
              if (row != _core.take(6).last) const Divider(height: 1),
            ],
          );
        }).toList(),
      ),
    );
  }

  Widget _movers() {
    final gainers = JsonTools.mapList(movers['gainers']);
    final losers = JsonTools.mapList(movers['losers']);
    final rows = <Map<String, dynamic>>[...gainers.take(3), ...losers.take(3)];
    if (rows.isEmpty) {
      return const TemplateCard(child: Text('Mover data is syncing.', style: TextStyle(color: AbsColors.muted)));
    }
    return SizedBox(
      height: 100,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: rows.length,
        separatorBuilder: (_, __) => const SizedBox(width: 9),
        itemBuilder: (_, i) {
          final row = rows[i];
          final change = JsonTools.number(row['change_percent'] ?? row['change_percent_24h']);
          final symbol = JsonTools.text(row['symbol'], JsonTools.text(row['pair']));
          return SizedBox(
            width: 146,
            child: TemplateCard(
              padding: const EdgeInsets.all(12),
              onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => MarketDetailScreen(symbol: symbol))),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(symbol.replaceAll('USDT', ''), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 14)),
                  const SizedBox(height: 6),
                  Text(money(row['price']), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 5),
                  Text('${change >= 0 ? '+' : ''}${change.toStringAsFixed(2)}%', style: TextStyle(color: change >= 0 ? AbsColors.green : AbsColors.red, fontWeight: FontWeight.w900, fontSize: 11.5)),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _membershipPromo() => Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: templateFade(AbsColors.gold, .38)),
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [templateFade(AbsColors.gold, .12), AbsColors.panel],
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const TemplatePill('PULSE MEMBERSHIP', color: AbsColors.gold),
            const SizedBox(height: 10),
            const Text('Ready to scan for qualified setups?', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900)),
            const SizedBox(height: 6),
            const Text('Activate Pulse for scanner access, complete signal reasoning, guarded execution, positions and performance intelligence.', style: TextStyle(color: AbsColors.muted, fontSize: 12.5, height: 1.45)),
            const SizedBox(height: 13),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PlansScreen())),
                    child: const Text('View Plans'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ScannerScreen())),
                    child: const Text('Open Scanner'),
                  ),
                ),
              ],
            ),
          ],
        ),
      );

  Widget _headlineCards() {
    if (headlines.isEmpty) {
      return TemplateCard(
        onTap: () => widget.onTab(3),
        child: const Row(
          children: [
            Icon(Icons.article_outlined, color: AbsColors.cyan),
            SizedBox(width: 10),
            Expanded(child: Text('Open ABS Intelligence for live news and the economic calendar.')),
            Icon(Icons.chevron_right_rounded, color: AbsColors.muted2),
          ],
        ),
      );
    }
    return Column(
      children: headlines.take(3).map((item) {
        final title = JsonTools.text(item['title'] ?? item['headline'], 'ABS market update');
        final summary = JsonTools.plain(item['summary'] ?? item['excerpt'] ?? item['description']);
        return Padding(
          padding: const EdgeInsets.only(bottom: 9),
          child: TemplateCard(
            onTap: () => widget.onTab(3),
            padding: const EdgeInsets.all(13),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(color: templateFade(AbsColors.cyan, .09), borderRadius: BorderRadius.circular(10)),
                  child: const Icon(Icons.article_outlined, color: AbsColors.cyanSoft, size: 19),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(title, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12.5)),
                      if (summary.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(summary, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AbsColors.muted, fontSize: 10.5)),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: 5),
                const Icon(Icons.chevron_right_rounded, color: AbsColors.muted2, size: 19),
              ],
            ),
          ),
        );
      }).toList(),
    );
  }

  String _compactUsd(dynamic value) {
    final n = JsonTools.number(value);
    if (n.abs() >= 1e12) return '\$${(n / 1e12).toStringAsFixed(2)}T';
    if (n.abs() >= 1e9) return '\$${(n / 1e9).toStringAsFixed(2)}B';
    if (n.abs() >= 1e6) return '\$${(n / 1e6).toStringAsFixed(2)}M';
    if (n.abs() >= 1e3) return '\$${(n / 1e3).toStringAsFixed(1)}K';
    return money(n);
  }

  String _rate(dynamic value) {
    final n = JsonTools.number(value);
    if (n.abs() <= 1) return '${(n * 100).toStringAsFixed(3)}%';
    return '${n.toStringAsFixed(3)}%';
  }
}
