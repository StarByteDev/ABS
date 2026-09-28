import 'package:flutter/material.dart';

import '../data/mock_data.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../utils/format.dart';
import '../widgets/charts.dart';
import '../widgets/common.dart';
import '../widgets/sheets.dart';
import '../widgets/tiles.dart';
import '../../screens/alerts_screen.dart';
import 'news_detail_screen.dart';
import '../../screens/plans_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key, required this.onTab});
  final ValueChanged<int> onTab;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  String _tf = '1D';
  int? _hover;
  bool _gainers = true;

  String get _greeting {
    final h = DateTime.now().hour;
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
  }

  String get _tfLabel => const {
        '1H': 'the last hour',
        '1D': 'the last day',
        '1W': 'the last week',
        '1M': 'the last month',
        '1Y': 'the last year',
        'ALL': 'all time',
      }[_tf]!;

  bool _requested = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_requested) {
      _requested = true;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) AppScope.read(context).ensureHome();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator(
          color: AppColors.accent,
          backgroundColor: AppColors.surface,
          onRefresh: app.refreshHome,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
            children: [
              _topBar(app),
              if (app.limitedAccount) ...[
                const SizedBox(height: 12),
                _activationBanner(),
              ],
              if (app.homeLoading && MockData.coins.first.price <= 0) ...[
                const SizedBox(height: 28),
                const Center(child: CircularProgressIndicator()),
                const SizedBox(height: 12),
                const Center(child: Text('Loading Pulse market intelligence…', style: AppText.muted)),
              ] else if (app.homeError != null && MockData.coins.first.price <= 0) ...[
                const SizedBox(height: 18),
                _errorCard(app),
              ] else ...[
              const SizedBox(height: 16),
              _btcHero(app),
              const SectionTitle('Market pulse'),
              _pulseCard(),
              const SizedBox(height: 12),
              _sentimentCard(),
              const SectionTitle('Futures'),
              _futuresGrid(),
              const SizedBox(height: 12),
              _liquidationsCard(),
              SectionTitle('Majors', action: 'Watchlist', onAction: () => widget.onTab(1)),
              _majorsStrip(),
              const SectionTitle('Market overview'),
              _overviewGrid(),
              if (MockData.sectors.isNotEmpty) ...[
                const SectionTitle('Sectors today'),
                _sectorsGrid(),
              ],
              const SectionTitle('Movers in the last 24h'),
              _moversCard(),
              if (!app.hasPulse) ...[const SizedBox(height: 22), _pulsePromo()],
              SectionTitle('Latest headlines', action: 'All news', onAction: () => widget.onTab(3)),
              for (final n in MockData.news.take(3))
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: NewsTile(
                      item: n, onTap: () => push(context, NewsDetailScreen(item: n))),
                ),
              ],
              const RiskNotice(),
            ],
          ),
        ),
      ),
    );
  }

  Widget _activationBanner() => AbsCard(
        borderColor: fade(AppColors.amber, .45),
        onTap: () => widget.onTab(4),
        padding: const EdgeInsets.all(13),
        child: const Row(
          children: [
            Icon(Icons.mark_email_unread_outlined, color: AppColors.amber, size: 21),
            SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Activate your ABS account', style: TextStyle(fontWeight: FontWeight.w700)),
                  SizedBox(height: 2),
                  Text('Basic access is available now. Activate from Account to unlock Pulse trading features.', style: AppText.muted),
                ],
              ),
            ),
            Icon(Icons.chevron_right_rounded, color: AppColors.faint),
          ],
        ),
      );

  Widget _errorCard(AppState app) => AbsCard(
        child: Column(
          children: [
            const Icon(Icons.cloud_off_outlined, color: AppColors.amber, size: 30),
            const SizedBox(height: 8),
            Text(app.homeError ?? 'Market intelligence is unavailable.', textAlign: TextAlign.center),
            const SizedBox(height: 10),
            OutlinedButton(onPressed: app.refreshHome, child: const Text('Try again')),
          ],
        ),
      );

  Widget _topBar(AppState app) => Row(
        children: [
          const AbsLogo(size: 38),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(_greeting, style: AppText.muted),
                Text(app.userName,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
          if (app.hasPulse)
            const Pill('PULSE', color: AppColors.up)
          else if (app.limitedAccount)
            const Pill('ACTIVATE', color: AppColors.amber)
          else
            const Pill('FREE', color: AppColors.gold),
          IconButton(
            tooltip: 'Price alerts',
            onPressed: app.emailVerified ? () => push(context, const AlertsScreen()) : null,
            icon: const Icon(Icons.notifications_none_rounded),
          ),
        ],
      );

  Widget _btcHero(AppState app) {
    final btc = MockData.coins.first;
    final series = MockData.btcSeries(_tf);
    final shown = _hover == null ? series.last : series[_hover!];
    final change = series.first > 0 ? (shown - series.first) / series.first * 100 : btc.change;
    final color = change >= 0 ? AppColors.up : AppColors.down;
    final watched = app.watchlist.contains(btc.symbol);
    return AbsCard(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CoinAvatar(symbol: btc.symbol, color: btc.color),
              const SizedBox(width: 10),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('BTC/USDT', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                    Text('ABS central market feed', style: AppText.muted),
                  ],
                ),
              ),
              IconButton(
                tooltip: watched ? 'Remove from watchlist' : 'Add to watchlist',
                onPressed: app.emailVerified ? () => app.toggleWatch(btc.symbol) : null,
                icon: Icon(watched ? Icons.star_rounded : Icons.star_outline_rounded,
                    color: watched ? AppColors.gold : AppColors.muted),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(fmtNum(shown),
              style: AppText.figure.copyWith(fontSize: 34, letterSpacing: -0.8)),
          const SizedBox(height: 6),
          Row(children: [
            ChangeBadge(change),
            const SizedBox(width: 8),
            Text(_hover == null ? 'over $_tfLabel' : 'from start of range', style: AppText.muted),
          ]),
          const SizedBox(height: 14),
          SizedBox(
            height: 170,
            child: InteractiveLineChart(
              values: series,
              color: color,
              onHover: (i) => setState(() => _hover = i),
            ),
          ),
          const SizedBox(height: 4),
          Text('Touch and drag the chart to inspect prices',
              style: AppText.muted.copyWith(fontSize: 11, color: AppColors.faint)),
          const SizedBox(height: 12),
          SegmentToggle(
            options: MockData.timeframes,
            index: MockData.timeframes.indexOf(_tf),
            onChanged: (i) {
              final tf = MockData.timeframes[i];
              setState(() {
                _tf = tf;
                _hover = null;
              });
              app.loadBtcSeries(tf);
            },
          ),
          const Divider(height: 28),
          Row(children: [
            Expanded(child: MiniStat(label: '24h high', value: fmtNum(btc.high))),
            Expanded(child: MiniStat(label: '24h low', value: fmtNum(btc.low))),
            Expanded(child: MiniStat(label: '24h volume', value: '${fmtCompact(btc.volume)} USDT')),
          ]),
        ],
      ),
    );
  }

  Widget _pulseCard() => AbsCard(
        child: Column(
          children: [
            Row(
              children: [
                SizedBox(width: 128, height: 76, child: PulseGauge(score: MockData.pulseScore)),
                SizedBox(width: 18),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Current reading', style: AppText.muted),
                      SizedBox(height: 2),
                      Text(MockData.pulseLabel,
                          style: TextStyle(
                              fontSize: 24, fontWeight: FontWeight.w800, color: AppColors.amber)),
                      SizedBox(height: 2),
                      Text('Score ${MockData.pulseScore} of 100', style: AppText.muted),
                    ],
                  ),
                ),
              ],
            ),
            Divider(height: 28),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.wb_sunny_outlined, color: AppColors.gold, size: 20),
                SizedBox(width: 10),
                Expanded(
                  child: Text(MockData.dailyInsight,
                      style: TextStyle(fontSize: 13.5, height: 1.45)),
                ),
              ],
            ),
          ],
        ),
      );

  Widget _sentimentCard() => AbsCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(children: [
              Expanded(child: Text('Market sentiment', style: AppText.h2.copyWith(fontSize: 15))),
              Text('${MockData.sentimentScore}', style: AppText.figure.copyWith(fontSize: 20)),
              const Text(' / 100', style: AppText.muted),
            ]),
            const SizedBox(height: 12),
            SplitBar(parts: [
              (MockData.bullish.toDouble(), AppColors.up),
              (MockData.neutral.toDouble(), AppColors.muted),
              (MockData.bearish.toDouble(), AppColors.down),
            ]),
            const SizedBox(height: 12),
            Wrap(
              spacing: 16,
              runSpacing: 6,
              children: [
                LegendDot(color: AppColors.up, label: 'Bullish ${MockData.bullish}%'),
                LegendDot(color: AppColors.muted, label: 'Neutral ${MockData.neutral}%'),
                LegendDot(color: AppColors.down, label: 'Bearish ${MockData.bearish}%'),
              ],
            ),
          ],
        ),
      );

  Widget _grid(List<Widget> children, {double ratio = 1.6}) => GridView.count(
        crossAxisCount: 2,
        shrinkWrap: true,
        padding: EdgeInsets.zero,
        physics: const NeverScrollableScrollPhysics(),
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        childAspectRatio: ratio,
        children: children,
      );

  Widget _futuresGrid() => _grid([
        StatTile(
            label: 'Open interest',
            value: fmtCompact(MockData.openInterest, prefix: '\$'),
            note: 'BTC USD-M'),
        StatTile(
            label: 'Funding rate',
            value: MockData.fundingRate.isFinite ? '${MockData.fundingRate.toStringAsFixed(3)}%' : '—',
            note: 'Negative',
            noteColor: AppColors.down),
        StatTile(
            label: 'Long / short ratio',
            value: MockData.longShort.isFinite ? MockData.longShort.toStringAsFixed(2) : '—',
            note: 'Long-heavy',
            noteColor: AppColors.up),
        StatTile(
            label: 'Perp basis',
            value: MockData.basis.isFinite ? '${MockData.basis.toStringAsFixed(3)}%' : '—',
            note: 'Below index',
            noteColor: AppColors.down),
      ]);

  Widget _liquidationsCard() {
    final longs = MockData.liqLongs.isFinite ? MockData.liqLongs : 0.0;
    final shorts = MockData.liqShorts.isFinite ? MockData.liqShorts : 0.0;
    final total = longs + shorts;
    final longPct = total > 0 ? longs / total * 100 : 0.0;
    return AbsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Liquidations in the last 24h', style: AppText.h2.copyWith(fontSize: 15)),
          const SizedBox(height: 12),
          SplitBar(height: 10, parts: [
            (longs, AppColors.down),
            (shorts, AppColors.up),
          ]),
          const SizedBox(height: 12),
          Row(children: [
            Expanded(
              child: MiniStat(
                label: 'Longs, ${longPct.toStringAsFixed(1)}%',
                value: total > 0 ? fmtCompact(longs, prefix: '\$') : '—',
                valueColor: AppColors.down,
              ),
            ),
            Expanded(
              child: MiniStat(
                label: 'Shorts, ${(100 - longPct).toStringAsFixed(1)}%',
                value: total > 0 ? fmtCompact(shorts, prefix: '\$') : '—',
                valueColor: AppColors.up,
              ),
            ),
          ]),
        ],
      ),
    );
  }

  Widget _majorsStrip() => SizedBox(
        height: 140,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          itemCount: MockData.coins.length - 1,
          separatorBuilder: (_, __) => const SizedBox(width: 10),
          itemBuilder: (context, i) {
            final c = MockData.coins[i + 1];
            return SizedBox(
              width: 150,
              child: AbsCard(
                padding: const EdgeInsets.all(12),
                onTap: () => showCoinSheet(context, c),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(children: [
                      CoinAvatar(symbol: c.symbol, color: c.color, size: 26),
                      const SizedBox(width: 8),
                      Text(c.symbol, style: const TextStyle(fontWeight: FontWeight.w700)),
                      const Spacer(),
                      ChangeText(c.change, size: 11.5),
                    ]),
                    const Spacer(),
                    Text(fmtPrice(c.price), style: AppText.figure.copyWith(fontSize: 16)),
                    const SizedBox(height: 6),
                    SizedBox(
                      height: 30,
                      child: Sparkline(
                          values: c.spark,
                          color: c.change >= 0 ? AppColors.up : AppColors.down),
                    ),
                  ],
                ),
              ),
            );
          },
        ),
      );

  Widget _overviewGrid() {
    final tiles = <Widget>[];

    if (MockData.totalMcap.isFinite) {
      tiles.add(StatTile(
        label: 'Total market cap',
        value: fmtCompact(MockData.totalMcap, prefix: '\$'),
        note: MockData.mcapChange.isFinite ? fmtPct(MockData.mcapChange) : 'Global crypto market',
        noteColor: MockData.mcapChange.isFinite
            ? (MockData.mcapChange >= 0 ? AppColors.up : AppColors.down)
            : AppColors.muted,
      ));
    }
    if (MockData.volume24h.isFinite) {
      tiles.add(StatTile(
        label: '24h trading volume',
        value: fmtCompact(MockData.volume24h, prefix: '\$'),
        note: 'Global reported volume',
      ));
    }
    if (MockData.btcDominance.isFinite) {
      tiles.add(StatTile(
        label: 'BTC dominance',
        value: '${MockData.btcDominance.toStringAsFixed(1)}%',
        note: 'Market share',
        noteColor: AppColors.muted,
      ));
    }
    if (MockData.fearGreed.isFinite) {
      tiles.add(StatTile(
        label: 'Fear and greed',
        value: MockData.fearGreed.toStringAsFixed(0),
        note: 'Fear & Greed index',
        noteColor: AppColors.amber,
      ));
    }
    if (MockData.stablecoinFlow.isFinite) {
      tiles.add(StatTile(
        label: 'Stablecoin flow',
        value: fmtCompact(MockData.stablecoinFlow, prefix: '\$'),
        note: 'Net flow',
        noteColor: MockData.stablecoinFlow >= 0 ? AppColors.up : AppColors.down,
      ));
    }
    if (MockData.volatilityScore.isFinite) {
      tiles.add(StatTile(
        label: 'Volatility score',
        value: MockData.volatilityScore.toStringAsFixed(1),
        note: 'ABS market measure',
        noteColor: AppColors.amber,
      ));
    }

    // If the public overview endpoint does not publish one of the optional
    // macro fields, fill the grid only with other REAL values already returned
    // by ABS instead of showing meaningless dash / "Not supplied" cards.
    if (tiles.length < 4 && MockData.openInterest.isFinite) {
      tiles.add(StatTile(
        label: 'Open interest',
        value: fmtCompact(MockData.openInterest, prefix: '\$'),
        note: 'Futures positioning',
      ));
    }
    if (tiles.length < 4 && MockData.fundingRate.isFinite) {
      tiles.add(StatTile(
        label: 'Funding rate',
        value: '${MockData.fundingRate.toStringAsFixed(3)}%',
        note: 'Perpetual futures',
        noteColor: MockData.fundingRate >= 0 ? AppColors.up : AppColors.down,
      ));
    }
    if (tiles.length < 4 && MockData.pulseScore > 0) {
      tiles.add(StatTile(
        label: 'Pulse score',
        value: '${MockData.pulseScore}',
        note: MockData.pulseLabel,
        noteColor: AppColors.accent,
      ));
    }
    if (tiles.length < 4 && MockData.sentimentScore > 0) {
      tiles.add(StatTile(
        label: 'Market sentiment',
        value: '${MockData.sentimentScore}',
        note: '${MockData.bullish}% bullish · ${MockData.bearish}% bearish',
        noteColor: AppColors.muted,
      ));
    }

    if (tiles.isEmpty) {
      return const AbsCard(
        child: Text(
          'Market overview is refreshing from ABS. Pull down to try again.',
          style: AppText.muted,
        ),
      );
    }
    return _grid(tiles);
  }

  Widget _sectorsGrid() => GridView.count(
        crossAxisCount: 4,
        shrinkWrap: true,
        padding: EdgeInsets.zero,
        physics: const NeverScrollableScrollPhysics(),
        mainAxisSpacing: 8,
        crossAxisSpacing: 8,
        childAspectRatio: 0.78,
        children: [
          for (final s in MockData.sectors)
            AbsCard(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 10),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(s.icon, color: AppColors.accent, size: 22),
                  const SizedBox(height: 6),
                  Text(s.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      textAlign: TextAlign.center,
                      style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600)),
                  const SizedBox(height: 4),
                  FittedBox(child: ChangeText(s.change, size: 11.5)),
                ],
              ),
            ),
        ],
      );

  Widget _moversCard() {
    final list = _gainers ? MockData.gainers : MockData.losers;
    return AbsCard(
      padding: const EdgeInsets.fromLTRB(10, 10, 10, 6),
      child: Column(
        children: [
          SegmentToggle(
            options: const ['Top gainers', 'Top losers'],
            index: _gainers ? 0 : 1,
            onChanged: (i) => setState(() => _gainers = i == 0),
          ),
          const SizedBox(height: 4),
          for (final m in list)
            ListTile(
              dense: true,
              contentPadding: const EdgeInsets.symmetric(horizontal: 6),
              leading: CoinAvatar(symbol: m.symbol, size: 32),
              title: Text(m.symbol, style: const TextStyle(fontWeight: FontWeight.w700)),
              subtitle: Text(m.name, style: AppText.muted),
              trailing: ChangeBadge(m.change),
            ),
        ],
      ),
    );
  }

  Widget _pulsePromo() => AbsCard(
        color: AppColors.surfaceHi,
        borderColor: fade(AppColors.accent, .45),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(children: [
              Icon(Icons.bolt_rounded, color: AppColors.gold),
              SizedBox(width: 8),
              Text('Pulse intelligence', style: AppText.h2),
            ]),
            const SizedBox(height: 8),
            const Text(
              'Strategy scoring on the 15M and 4H charts, validated signals, guarded trading tools and performance intelligence. View the current server plan options.',
              style: TextStyle(color: AppColors.muted, height: 1.45),
            ),
            const SizedBox(height: 14),
            Row(children: [
              FilledButton(
                style: FilledButton.styleFrom(minimumSize: const Size(0, 44)),
                onPressed: () => push(context, const PlansScreen()),
                child: const Text('See plans'),
              ),
              const SizedBox(width: 8),
              TextButton(
                onPressed: () => widget.onTab(2),
                child: const Text('Try the free signal'),
              ),
            ]),
          ],
        ),
      );
}
