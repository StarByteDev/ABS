import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';
import 'content_screens.dart';

class GuestLandingScreen extends StatefulWidget {
  const GuestLandingScreen({super.key});

  @override
  State<GuestLandingScreen> createState() => _GuestLandingScreenState();
}

class _GuestLandingScreenState extends State<GuestLandingScreen> {
  int index = 0;

  @override
  Widget build(BuildContext context) {
    final pages = <Widget>[
      const _PublicMarketPulsePage(),
      const _PublicExploreMarketsPage(),
      const NewsScreen(),
      const _PublicWatchlistPage(),
      const _GuestMorePage(),
    ];
    return Scaffold(
      backgroundColor: AbsColors.bg,
      body: IndexedStack(index: index, children: pages),
      bottomNavigationBar: SafeArea(
        top: false,
        child: Container(
          decoration: const BoxDecoration(
            color: Color(0xFF090D16),
            border: Border(top: BorderSide(color: AbsColors.lineSoft)),
          ),
          child: NavigationBar(
            selectedIndex: index,
            onDestinationSelected: (value) => setState(() => index = value),
            destinations: const [
              NavigationDestination(icon: Icon(Icons.monitor_heart_outlined), selectedIcon: Icon(Icons.monitor_heart_rounded), label: 'Pulse'),
              NavigationDestination(icon: Icon(Icons.show_chart_rounded), selectedIcon: Icon(Icons.candlestick_chart_rounded), label: 'Markets'),
              NavigationDestination(icon: Icon(Icons.article_outlined), selectedIcon: Icon(Icons.article_rounded), label: 'News'),
              NavigationDestination(icon: Icon(Icons.star_border_rounded), selectedIcon: Icon(Icons.star_rounded), label: 'Watchlist'),
              NavigationDestination(icon: Icon(Icons.more_horiz_rounded), selectedIcon: Icon(Icons.more_horiz_rounded), label: 'More'),
            ],
          ),
        ),
      ),
    );
  }
}

class _PublicMarketPulsePage extends StatefulWidget {
  const _PublicMarketPulsePage();

  @override
  State<_PublicMarketPulsePage> createState() => _PublicMarketPulsePageState();
}

class _PublicMarketPulsePageState extends State<_PublicMarketPulsePage> {
  Future<dynamic>? _future;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= SessionScope.of(context).api.get('/market/overview');
  }

  Future<void> _refresh() async {
    setState(() => _future = SessionScope.of(context).api.get('/market/overview', query: {'refresh': 1}));
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.transparent,
      body: AbsBackground(
        child: SafeArea(
          child: FutureBuilder<dynamic>(
            future: _future,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const Center(child: LoadingBlock(label: 'Loading live market intelligence...'));
              }
              if (snapshot.hasError) {
                return Padding(
                  padding: const EdgeInsets.all(18),
                  child: ErrorBlock(message: 'Public market intelligence is temporarily unavailable.', onRetry: _refresh),
                );
              }
              final data = JsonTools.map(JsonTools.at(snapshot.data, 'data', <String, dynamic>{}));
              final global = JsonTools.map(data['global']);
              final pulse = JsonTools.map(data['pulse']);
              final sentiment = JsonTools.map(data['sentiment']);
              final insights = JsonTools.map(data['insights']);
              final core = JsonTools.mapList(data['core']);
              final score = JsonTools.integer(pulse['score']);
              final live = JsonTools.boolean(data['is_live']);
              final bullish = JsonTools.integer(sentiment['bullish']);
              final neutral = JsonTools.integer(sentiment['neutral']);
              final bearish = JsonTools.integer(sentiment['bearish']);
              return RefreshIndicator(
                onRefresh: _refresh,
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                  children: [
                    _PublicHeader(
                      title: 'Market Pulse',
                      subtitle: live ? 'LIVE · ${compactDate(data['updated_at'])}' : 'PUBLIC MARKET INTELLIGENCE',
                    ),
                    const SizedBox(height: 12),
                    _AlertStrip(text: JsonTools.text(insights['daily_insight'], 'ABS market intelligence is syncing.')),
                    const SizedBox(height: 12),
                    _FourMetricStrip(global: global),
                    const SizedBox(height: 12),
                    AbsCard(
                      gradient: const LinearGradient(colors: [Color(0xFF11172B), Color(0xFF101322)]),
                      accent: AbsColors.purple,
                      child: Row(
                        children: [
                          SizedBox(
                            width: 92,
                            height: 92,
                            child: Stack(
                              alignment: Alignment.center,
                              children: [
                                SizedBox(
                                  width: 82,
                                  height: 82,
                                  child: CircularProgressIndicator(
                                    value: score > 0 ? score / 100 : 0,
                                    strokeWidth: 8,
                                    backgroundColor: AbsColors.lineSoft,
                                    valueColor: const AlwaysStoppedAnimation(AbsColors.purpleSoft),
                                  ),
                                ),
                                Column(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Text(score > 0 ? '$score' : '—', style: const TextStyle(fontSize: 27, fontWeight: FontWeight.w900, height: 1)),
                                    const SizedBox(height: 2),
                                    const Text('/ 100', style: TextStyle(color: AbsColors.muted, fontSize: 9)),
                                  ],
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('${JsonTools.text(pulse['label'], 'Market')} · ${JsonTools.text(insights['market_bias'], 'Balanced')}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 17)),
                                const SizedBox(height: 6),
                                Text(JsonTools.text(insights['market_bias_detail'], 'Live breadth is being calculated.'), style: const TextStyle(color: AbsColors.muted, fontSize: 10.5)),
                                const SizedBox(height: 12),
                                _BreadthBar(bullish: bullish, neutral: neutral, bearish: bearish),
                                const SizedBox(height: 7),
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    Text('$bullish% Bull', style: const TextStyle(color: AbsColors.green, fontSize: 9.5, fontWeight: FontWeight.w800)),
                                    Text('$neutral% Neutral', style: const TextStyle(color: AbsColors.muted, fontSize: 9.5, fontWeight: FontWeight.w800)),
                                    Text('$bearish% Bear', style: const TextStyle(color: AbsColors.red, fontSize: 9.5, fontWeight: FontWeight.w800)),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 18),
                    const _CompactSectionLabel('FUTURES INSIGHTS', trailing: 'BTC USD-M'),
                    const SizedBox(height: 9),
                    GridView.count(
                      crossAxisCount: 2,
                      crossAxisSpacing: 10,
                      mainAxisSpacing: 10,
                      childAspectRatio: 1.55,
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      children: [
                        _InsightMetric(label: 'OPEN INTEREST', value: _compactUsd(global['open_interest_usd']), detail: 'ABS derivatives context'),
                        _InsightMetric(label: 'FUNDING RATE', value: _rate(global['funding_rate']), detail: 'Current funding'),
                        _InsightMetric(label: 'LONG / SHORT', value: number(global['long_short_ratio'], digits: 2), detail: 'Global accounts'),
                        _InsightMetric(label: 'PERP BASIS', value: _rate(global['perp_premium_basis']), detail: 'Mark vs index'),
                      ],
                    ),
                    const SizedBox(height: 10),
                    AbsCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              const Expanded(child: Text('LIQUIDATIONS 24H', style: TextStyle(color: AbsColors.muted, fontSize: 10, fontWeight: FontWeight.w900, letterSpacing: 1))),
                              Text(_compactUsd(global['liquidation_24h_usd']), style: const TextStyle(color: AbsColors.muted, fontSize: 11, fontWeight: FontWeight.w800)),
                            ],
                          ),
                          const SizedBox(height: 14),
                          _LiquidationBar(label: 'Longs', value: JsonTools.number(global['liquidation_long_24h_usd']), total: JsonTools.number(global['liquidation_24h_usd']), color: const Color(0xFFD38A78)),
                          const SizedBox(height: 9),
                          _LiquidationBar(label: 'Shorts', value: JsonTools.number(global['liquidation_short_24h_usd']), total: JsonTools.number(global['liquidation_24h_usd']), color: const Color(0xFF7DCE9A)),
                        ],
                      ),
                    ),
                    const SizedBox(height: 18),
                    const _CompactSectionLabel('CORE ASSETS', trailing: 'Public watchlist'),
                    const SizedBox(height: 8),
                    ...core.take(6).map((row) => _PublicAssetRow(row: row)),
                    const SizedBox(height: 18),
                    OutlinedButton.icon(
                      onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LoginScreen())),
                      icon: const Icon(Icons.lock_open_rounded),
                      label: const Text('Sign in to unlock Signals & Trading'),
                    ),
                  ],
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}

class _PublicExploreMarketsPage extends StatefulWidget {
  const _PublicExploreMarketsPage();
  @override
  State<_PublicExploreMarketsPage> createState() => _PublicExploreMarketsPageState();
}

class _PublicExploreMarketsPageState extends State<_PublicExploreMarketsPage> {
  Future<List<dynamic>>? _future;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= Future.wait([
      SessionScope.of(context).api.get('/market/overview'),
      SessionScope.of(context).api.get('/market/movers', query: {'limit': 6}),
    ]);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.transparent,
      body: AbsBackground(
        child: SafeArea(
          child: FutureBuilder<List<dynamic>>(
            future: _future,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) return const Center(child: LoadingBlock(label: 'Exploring markets...'));
              if (snapshot.hasError || !snapshot.hasData) return const Padding(padding: EdgeInsets.all(18), child: ErrorBlock(message: 'Market explorer is temporarily unavailable.'));
              final overview = JsonTools.map(JsonTools.at(snapshot.data![0], 'data', <String, dynamic>{}));
              final movers = JsonTools.map(JsonTools.at(snapshot.data![1], 'data', <String, dynamic>{}));
              final core = JsonTools.mapList(overview['core']);
              final gainers = JsonTools.mapList(movers['gainers']);
              final losers = JsonTools.mapList(movers['losers']);
              return ListView(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                children: [
                  const _PublicHeader(title: 'Explore Markets', subtitle: 'Public market discovery · No login required'),
                  const SizedBox(height: 14),
                  const _CompactSectionLabel('TOP MOVERS 24H', trailing: 'Live'),
                  const SizedBox(height: 9),
                  SizedBox(
                    height: 102,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: gainers.take(6).length,
                      separatorBuilder: (_, __) => const SizedBox(width: 8),
                      itemBuilder: (_, i) => _MoverCard(row: gainers[i]),
                    ),
                  ),
                  const SizedBox(height: 18),
                  const _CompactSectionLabel('MARKET HEATMAP 24H', trailing: 'Core assets'),
                  const SizedBox(height: 9),
                  AbsCard(
                    padding: const EdgeInsets.all(8),
                    child: GridView.count(
                      crossAxisCount: 3,
                      crossAxisSpacing: 7,
                      mainAxisSpacing: 7,
                      childAspectRatio: 1.25,
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      children: core.take(6).map((row) {
                        final change = JsonTools.number(row['change_percent']);
                        final color = change >= 0 ? AbsColors.green : AbsColors.red;
                        return Container(
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(color: color.withValues(alpha: .16), borderRadius: BorderRadius.circular(13), border: Border.all(color: color.withValues(alpha: .28))),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(JsonTools.text(row['base'], JsonTools.text(row['symbol']).replaceAll('USDT', '')), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 15)),
                              const SizedBox(height: 4),
                              Text(percent(change), style: TextStyle(color: color, fontWeight: FontWeight.w900, fontSize: 11)),
                            ],
                          ),
                        );
                      }).toList(),
                    ),
                  ),
                  const SizedBox(height: 18),
                  const _CompactSectionLabel('WATCHLIST PREVIEW', trailing: 'Core'),
                  const SizedBox(height: 8),
                  ...core.take(5).map((row) => _PublicAssetRow(row: row)),
                  if (losers.isNotEmpty) ...[
                    const SizedBox(height: 18),
                    const _CompactSectionLabel('UNDER PRESSURE', trailing: '24H'),
                    const SizedBox(height: 8),
                    ...losers.take(4).map((row) => _PublicAssetRow(row: row)),
                  ],
                  const SizedBox(height: 18),
                  AbsCard(
                    gradient: const LinearGradient(colors: [Color(0xFF1B1632), Color(0xFF111528)]),
                    accent: AbsColors.purple,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Unlock Easy Signals & Pro Tools', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16)),
                        const SizedBox(height: 5),
                        const Text('Sign in to generate and review trading signals, manage risk and access your complete trading account.', style: TextStyle(color: AbsColors.muted, fontSize: 11.5)),
                        const SizedBox(height: 12),
                        SizedBox(width: double.infinity, child: ElevatedButton(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LoginScreen())), child: const Text('Sign In / Get Started'))),
                      ],
                    ),
                  ),
                ],
              );
            },
          ),
        ),
      ),
    );
  }
}

class _PublicWatchlistPage extends StatelessWidget {
  const _PublicWatchlistPage();
  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: Colors.transparent,
        body: AbsBackground(
          child: SafeArea(
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
              children: [
                const _PublicHeader(title: 'Watchlist', subtitle: 'Preview public markets · Sign in to save your own list'),
                const SizedBox(height: 16),
                const EmptyState(title: 'Your personal watchlist lives inside your ABS account', message: 'Public market intelligence remains available without login. Sign in when you want to save pairs, alerts and trading preferences.', icon: Icons.star_outline_rounded),
                const SizedBox(height: 14),
                ElevatedButton(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LoginScreen())), child: const Text('Sign in to use Watchlist')),
              ],
            ),
          ),
        ),
      );
}

class _GuestMorePage extends StatelessWidget {
  const _GuestMorePage();
  @override
  Widget build(BuildContext context) {
    final items = <({IconData icon, String title, Widget page})>[
      (icon: Icons.insights_outlined, title: 'Research', page: const ResearchScreen()),
      (icon: Icons.school_outlined, title: 'Learning', page: const LearningScreen()),
      (icon: Icons.event_note_outlined, title: 'Economic Calendar', page: const EconomicCalendarScreen()),
      (icon: Icons.grid_view_rounded, title: 'ABS Services', page: const ServicesScreen()),
      (icon: Icons.search_rounded, title: 'Search ABS', page: const GlobalSearchScreen()),
      (icon: Icons.gavel_outlined, title: 'Legal & Risk', page: const LegalHubScreen()),
      (icon: Icons.mail_outline_rounded, title: 'Contact ABS', page: const ContactScreen()),
      (icon: Icons.explore_outlined, title: 'Explore ABS', page: const ExploreAbsScreen()),
    ];
    return Scaffold(
      backgroundColor: Colors.transparent,
      body: AbsBackground(
        child: SafeArea(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
            children: [
              const _PublicHeader(title: 'More', subtitle: 'Research, learning, services and account access'),
              const SizedBox(height: 18),
              GridView.builder(
                itemCount: items.length,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 2, crossAxisSpacing: 10, mainAxisSpacing: 10, childAspectRatio: 1.28),
                itemBuilder: (_, i) {
                  final item = items[i];
                  return InkWell(
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => item.page)),
                    borderRadius: BorderRadius.circular(18),
                    child: AbsCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(item.icon, color: AbsColors.purpleSoft, size: 24),
                          const SizedBox(height: 12),
                          Text(item.title, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 13)),
                        ],
                      ),
                    ),
                  );
                },
              ),
              const SizedBox(height: 16),
              ElevatedButton.icon(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LoginScreen())), icon: const Icon(Icons.login_rounded), label: const Text('Sign in to ABS Pulse')),
              const SizedBox(height: 9),
              OutlinedButton(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const RegisterScreen())), child: const Text('Create account')),
            ],
          ),
        ),
      ),
    );
  }
}

class _PublicHeader extends StatelessWidget {
  const _PublicHeader({required this.title, required this.subtitle});
  final String title;
  final String subtitle;
  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(width: 34, height: 34, padding: const EdgeInsets.all(4), decoration: BoxDecoration(color: AbsColors.panel2, borderRadius: BorderRadius.circular(10), border: Border.all(color: AbsColors.line)), child: Image.asset('assets/brand/abs-logo-master.png')),
              const SizedBox(width: 9),
              const Expanded(child: Text('ABS', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 13, letterSpacing: .4))),
              IconButton(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const GlobalSearchScreen())), icon: const Icon(Icons.search_rounded, color: AbsColors.muted)),
              IconButton(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const LoginScreen())), icon: const Icon(Icons.person_outline_rounded, color: AbsColors.muted)),
            ],
          ),
          const SizedBox(height: 10),
          Text(subtitle.toUpperCase(), style: const TextStyle(color: AbsColors.muted, fontSize: 9.5, fontWeight: FontWeight.w800, letterSpacing: .9)),
          const SizedBox(height: 4),
          Text(title, style: const TextStyle(fontSize: 27, fontWeight: FontWeight.w900, letterSpacing: -.8)),
        ],
      );
}

class _AlertStrip extends StatelessWidget {
  const _AlertStrip({required this.text});
  final String text;
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(color: AbsColors.purple.withValues(alpha: .10), borderRadius: BorderRadius.circular(12), border: Border.all(color: AbsColors.purple.withValues(alpha: .34))),
        child: Row(children: [const Icon(Icons.notifications_active_outlined, color: AbsColors.purpleSoft, size: 17), const SizedBox(width: 8), Expanded(child: Text(text, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700))), const Text('LIVE', style: TextStyle(color: AbsColors.muted, fontSize: 9, fontWeight: FontWeight.w800))]),
      );
}

class _FourMetricStrip extends StatelessWidget {
  const _FourMetricStrip({required this.global});
  final Map<String, dynamic> global;
  @override
  Widget build(BuildContext context) {
    final items = [
      ('M. CAP', _compactUsd(global['total_market_cap']), percent(global['market_cap_change_24h'])),
      ('24H VOL', _compactUsd(global['total_volume']), 'Live'),
      ('BTC DOM', '${number(global['btc_dominance'], digits: 1)}%', percent(global['btc_dominance_change_24h'])),
      ('F & G', number(global['fear_greed_score'], digits: 0), JsonTools.text(global['fear_greed_label'], '—')),
    ];
    return AbsCard(
      padding: EdgeInsets.zero,
      child: Row(
        children: List.generate(items.length, (i) => Expanded(
          child: Container(
            padding: const EdgeInsets.fromLTRB(10, 12, 8, 12),
            decoration: BoxDecoration(border: i == 0 ? null : const Border(left: BorderSide(color: AbsColors.lineSoft))),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(items[i].$1, style: const TextStyle(color: AbsColors.muted, fontSize: 8.5, fontWeight: FontWeight.w900, letterSpacing: .75)), const SizedBox(height: 6), Text(items[i].$2, maxLines: 1, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 14)), const SizedBox(height: 4), Text(items[i].$3, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AbsColors.muted, fontSize: 8.5, fontWeight: FontWeight.w700))]),
          ),
        )),
      ),
    );
  }
}

class _BreadthBar extends StatelessWidget {
  const _BreadthBar({required this.bullish, required this.neutral, required this.bearish});
  final int bullish;
  final int neutral;
  final int bearish;
  @override
  Widget build(BuildContext context) => ClipRRect(
        borderRadius: BorderRadius.circular(999),
        child: SizedBox(
          height: 8,
          child: Row(children: [
            Expanded(flex: bullish > 0 ? bullish : 1, child: Container(color: AbsColors.green)),
            Expanded(flex: neutral > 0 ? neutral : 1, child: Container(color: const Color(0xFF777D8D))),
            Expanded(flex: bearish > 0 ? bearish : 1, child: Container(color: const Color(0xFFB77E70))),
          ]),
        ),
      );
}

class _CompactSectionLabel extends StatelessWidget {
  const _CompactSectionLabel(this.label, {this.trailing});
  final String label;
  final String? trailing;
  @override
  Widget build(BuildContext context) => Row(children: [Expanded(child: Text(label, style: const TextStyle(color: AbsColors.muted, fontSize: 10, fontWeight: FontWeight.w900, letterSpacing: 1.05))), if (trailing != null) Text(trailing!, style: const TextStyle(color: AbsColors.purpleSoft, fontSize: 9.5, fontWeight: FontWeight.w800))]);
}

class _InsightMetric extends StatelessWidget {
  const _InsightMetric({required this.label, required this.value, required this.detail});
  final String label;
  final String value;
  final String detail;
  @override
  Widget build(BuildContext context) => AbsCard(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(label, style: const TextStyle(color: AbsColors.muted, fontSize: 9, fontWeight: FontWeight.w900, letterSpacing: .8)), const SizedBox(height: 10), Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w900)), const SizedBox(height: 5), Text(detail, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AbsColors.muted2, fontSize: 9.5))]),
      );
}

class _LiquidationBar extends StatelessWidget {
  const _LiquidationBar({required this.label, required this.value, required this.total, required this.color});
  final String label;
  final double value;
  final double total;
  final Color color;
  @override
  Widget build(BuildContext context) {
    final share = total > 0 ? (value / total).clamp(0.0, 1.0) : 0.0;
    return Row(children: [SizedBox(width: 48, child: Text(label, style: const TextStyle(color: AbsColors.muted, fontSize: 10.5, fontWeight: FontWeight.w700))), Expanded(child: ClipRRect(borderRadius: BorderRadius.circular(99), child: LinearProgressIndicator(value: share, minHeight: 7, backgroundColor: AbsColors.lineSoft, valueColor: AlwaysStoppedAnimation(color)))), const SizedBox(width: 10), SizedBox(width: 66, child: Text(_compactUsd(value), textAlign: TextAlign.end, style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w800)))]);
  }
}

class _PublicAssetRow extends StatelessWidget {
  const _PublicAssetRow({required this.row});
  final Map<String, dynamic> row;
  @override
  Widget build(BuildContext context) {
    final change = JsonTools.number(row['change_percent'] ?? row['change_percent_24h']);
    return Padding(
      padding: const EdgeInsets.only(bottom: 7),
      child: AbsCard(
        padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 12),
        child: Row(children: [Container(width: 32, height: 32, alignment: Alignment.center, decoration: BoxDecoration(color: AbsColors.purple.withValues(alpha: .10), shape: BoxShape.circle, border: Border.all(color: AbsColors.purple.withValues(alpha: .22))), child: Text(JsonTools.text(row['base'], JsonTools.text(row['symbol']).substring(0, 1)), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 11))), const SizedBox(width: 10), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(JsonTools.text(row['pair'], JsonTools.text(row['symbol'])), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 12.5)), const SizedBox(height: 3), Text(_compactUsd(row['volume']), style: const TextStyle(color: AbsColors.muted, fontSize: 9.5))])), const Icon(Icons.show_chart_rounded, size: 30, color: Color(0xFF7EC9A0)), const SizedBox(width: 12), Column(crossAxisAlignment: CrossAxisAlignment.end, children: [Text(money(row['price']), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 12)), const SizedBox(height: 3), Text(percent(change), style: TextStyle(color: pnlColor(change), fontSize: 9.5, fontWeight: FontWeight.w900))])]),
      ),
    );
  }
}

class _MoverCard extends StatelessWidget {
  const _MoverCard({required this.row});
  final Map<String, dynamic> row;
  @override
  Widget build(BuildContext context) {
    final change = JsonTools.number(row['change_percent']);
    return Container(
      width: 132,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AbsColors.panel, borderRadius: BorderRadius.circular(17), border: Border.all(color: AbsColors.lineSoft)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(JsonTools.text(row['pair'], JsonTools.text(row['symbol'])), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 11)), const Spacer(), Text(percent(change), style: TextStyle(color: pnlColor(change), fontSize: 14, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(money(row['price']), style: const TextStyle(color: AbsColors.muted, fontSize: 9.5))]),
    );
  }
}

String _compactUsd(dynamic value) {
  final n = JsonTools.number(value);
  if (n == 0) return '—';
  if (n.abs() >= 1e12) return '\$${(n / 1e12).toStringAsFixed(2)}T';
  if (n.abs() >= 1e9) return '\$${(n / 1e9).toStringAsFixed(1)}B';
  if (n.abs() >= 1e6) return '\$${(n / 1e6).toStringAsFixed(1)}M';
  if (n.abs() >= 1e3) return '\$${(n / 1e3).toStringAsFixed(1)}K';
  return money(n);
}

String _rate(dynamic value) {
  if (value == null) return '—';
  final n = JsonTools.number(value);
  if (n.abs() < 1 && n != 0) return '${(n * 100).toStringAsFixed(4)}%';
  return '${n.toStringAsFixed(4)}%';
}

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _form = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _hide = true;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    return AbsPage(
      title: 'ABS Pulse',
      subtitle: 'Secure access to your trading intelligence',
      child: ListView(
        children: [
          const SizedBox(height: 8),
          AbsCard(
            child: Form(
              key: _form,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Welcome back', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 17)),
                  const SizedBox(height: 5),
                  const Text('Use your ABS account to continue.', style: TextStyle(color: AbsColors.muted, fontSize: 11.5)),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _email,
                    keyboardType: TextInputType.emailAddress,
                    autofillHints: const [AutofillHints.email],
                    decoration: const InputDecoration(labelText: 'Email address', hintText: 'name@example.com', prefixIcon: Icon(Icons.alternate_email_rounded)),
                    validator: (v) => (v == null || !v.contains('@')) ? 'Enter your email.' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _password,
                    obscureText: _hide,
                    autofillHints: const [AutofillHints.password],
                    decoration: InputDecoration(
                      labelText: 'Password',
                      hintText: 'Enter your password',
                      prefixIcon: const Icon(Icons.lock_outline_rounded),
                      suffixIcon: IconButton(onPressed: () => setState(() => _hide = !_hide), icon: Icon(_hide ? Icons.visibility_outlined : Icons.visibility_off_outlined)),
                    ),
                    validator: (v) => (v == null || v.isEmpty) ? 'Enter your password.' : null,
                  ),
                  const SizedBox(height: 6),
                  Align(
                    alignment: Alignment.centerRight,
                    child: TextButton(
                      onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ForgotPasswordScreen())),
                      child: const Text('Forgot password?'),
                    ),
                  ),
                  const SizedBox(height: 4),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: session.busy ? null : () => _submit(session),
                      icon: session.busy ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.login_rounded),
                      label: Text(session.busy ? 'Signing in...' : 'Sign in securely'),
                    ),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: const [
                      _TrustChip(icon: Icons.shield_outlined, text: 'Protected by ABS'),
                      _TrustChip(icon: Icons.insights_outlined, text: '1-minute market feed'),
                      _TrustChip(icon: Icons.verified_user_outlined, text: 'Server-guarded execution'),
                      _TrustChip(icon: Icons.swap_horiz_rounded, text: 'Testnet & Live ready'),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const RegisterScreen())),
                  icon: const Icon(Icons.person_add_alt_1_rounded),
                  label: const Text('Create account'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: TextButton.icon(
                  onPressed: () => Navigator.of(context).popUntil((r) => r.isFirst),
                  icon: const Icon(Icons.public_rounded),
                  label: const Text('Continue with public market access'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(
              color: AbsColors.panel.withValues(alpha: .72),
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AbsColors.lineSoft),
            ),
            child: const Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.lock_person_outlined, size: 16, color: AbsColors.green),
                SizedBox(width: 8),
                Expanded(child: Text('Your Binance permissions, trading limits and order safety checks remain enforced by the ABS server.', style: TextStyle(color: AbsColors.muted, fontSize: 10.8, height: 1.45))),
              ],
            ),
          ),
          const SizedBox(height: 18),
        ],
      ),
    );
  }

  Future<void> _submit(AppSession session) async {
    if (!_form.currentState!.validate()) return;
    try {
      await session.login(_email.text, _password.text);
      if (!mounted) return;
      Navigator.of(context).popUntil((r) => r.isFirst);
    } on ApiException catch (e) {
      if (!mounted) return;
      final body = JsonTools.map(e.body);
      if (e.statusCode == 403 && JsonTools.boolean(body['activation_required'])) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) => ActivationRequiredScreen(email: _email.text.trim()),
          ),
        );
        return;
      }
      showSnack(context, e.message, error: true);
    }
  }
}

class _TrustChip extends StatelessWidget {
  const _TrustChip({required this.icon, required this.text});
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: AbsColors.panel2.withValues(alpha: .9),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: AbsColors.lineSoft),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: AbsColors.cyan),
          const SizedBox(width: 6),
          Text(text, style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w800, color: AbsColors.muted2)),
        ],
      ),
    );
  }
}

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});
  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _country = TextEditingController();
  final _countryCode = TextEditingController(text: '+971');
  final _phone = TextEditingController();
  final _password = TextEditingController();

  @override
  void dispose() {
    for (final c in [_name, _email, _country, _countryCode, _phone, _password]) {
      c.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    return AbsPage(
      title: 'Create account',
      subtitle: 'Start with ABS, then activate from email',
      child: Form(
        key: _form,
        child: ListView(
          children: [
            TextFormField(controller: _name, decoration: const InputDecoration(labelText: 'Full name'), validator: (v) => (v?.trim().isEmpty ?? true) ? 'Enter your name.' : null),
            const SizedBox(height: 10),
            TextFormField(controller: _email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email'), validator: (v) => (v == null || !v.contains('@')) ? 'Enter a valid email.' : null),
            const SizedBox(height: 10),
            TextFormField(controller: _country, decoration: const InputDecoration(labelText: 'Country (optional)')),
            const SizedBox(height: 10),
            Row(
              children: [
                SizedBox(width: 105, child: TextFormField(controller: _countryCode, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Code'))),
                const SizedBox(width: 10),
                Expanded(child: TextFormField(controller: _phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Phone (optional)'))),
              ],
            ),
            const SizedBox(height: 10),
            TextFormField(
              controller: _password,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Password', helperText: '8+ characters with upper/lower case and a number.'),
              validator: (v) {
                final value = v ?? '';
                if (value.length < 8) return 'Use at least 8 characters.';
                if (!RegExp(r'[A-Z]').hasMatch(value) || !RegExp(r'[a-z]').hasMatch(value) || !RegExp(r'[0-9]').hasMatch(value)) {
                  return 'Use upper/lower case letters and a number.';
                }
                return null;
              },
            ),
            const SizedBox(height: 18),
            ElevatedButton(onPressed: session.busy ? null : () => _submit(session), child: const Text('Create ABS account')),
            const SizedBox(height: 12),
            OutlinedButton(onPressed: () => Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const LoginScreen())), child: const Text('I already have an account')),
          ],
        ),
      ),
    );
  }

  Future<void> _submit(AppSession session) async {
    if (!_form.currentState!.validate()) return;
    try {
      await session.register({
        'name': _name.text.trim(),
        'email': _email.text.trim(),
        'country': _country.text.trim().isEmpty ? null : _country.text.trim(),
        'country_code': _phone.text.trim().isEmpty ? null : _countryCode.text.trim(),
        'phone': _phone.text.trim().isEmpty ? null : _phone.text.trim(),
        'password': _password.text,
      });
      if (!mounted) return;
      Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => ActivationRequiredScreen(email: _email.text.trim())));
    } on ApiException catch (e) {
      if (!mounted) return;
      showSnack(context, e.message, error: true);
    }
  }
}

class ActivationRequiredScreen extends StatefulWidget {
  const ActivationRequiredScreen({super.key, required this.email});
  final String email;

  @override
  State<ActivationRequiredScreen> createState() => _ActivationRequiredScreenState();
}

class _ActivationRequiredScreenState extends State<ActivationRequiredScreen> {
  bool sending = false;

  @override
  Widget build(BuildContext context) {
    return AbsPage(
      title: 'Activate account',
      child: Center(
        child: AbsCard(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.mark_email_read_outlined, size: 48, color: AbsColors.gold),
              const SizedBox(height: 14),
              const Text('Check your email', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 20)),
              const SizedBox(height: 8),
              Text('ABS sent a secure activation link to ${widget.email}. Activate the account, then sign in.', textAlign: TextAlign.center, style: const TextStyle(color: AbsColors.muted)),
              const SizedBox(height: 18),
              ElevatedButton(onPressed: () => Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const LoginScreen())), child: const Text('Go to sign in')),
              const SizedBox(height: 8),
              TextButton(onPressed: sending ? null : _resend, child: Text(sending ? 'Sending...' : 'Resend activation email')),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _resend() async {
    setState(() => sending = true);
    try {
      await SessionScope.of(context).api.post('/auth/activation/resend', body: {'email': widget.email});
      if (mounted) showSnack(context, 'Activation email sent.');
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => sending = false);
    }
  }
}

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});
  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final email = TextEditingController();
  bool busy = false;

  @override
  void dispose() {
    email.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AbsPage(
        title: 'Reset password',
        subtitle: 'Secure ABS account recovery',
        child: ListView(
          children: [
            const SizedBox(height: 30),
            const Icon(Icons.lock_reset, size: 54, color: AbsColors.cyan),
            const SizedBox(height: 18),
            TextField(controller: email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Account email')),
            const SizedBox(height: 14),
            ElevatedButton(onPressed: busy ? null : _send, child: const Text('Send reset instructions')),
          ],
        ),
      );

  Future<void> _send() async {
    if (!email.text.contains('@')) return showSnack(context, 'Enter your account email.', error: true);
    setState(() => busy = true);
    try {
      final response = await SessionScope.of(context).api.post('/auth/forgot-password', body: {'email': email.text.trim()});
      if (mounted) showSnack(context, JsonTools.text(JsonTools.map(response)['message'], 'Reset instructions sent.'));
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }
}
