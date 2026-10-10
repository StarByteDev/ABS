import 'package:flutter/material.dart';

import '../template_rebase/data/mock_data.dart';
import '../template_rebase/state/app_state.dart';
import 'investor_ui.dart';
import 'market_command_ui.dart';
import 'premium_ui.dart';

/// Markets is broader cross-market research, not a duplicate of the futures
/// risk dashboard. Charts, movers and sector data come from the ABS API.
class MarketsAnalysisScreen extends StatefulWidget {
  const MarketsAnalysisScreen({super.key, required this.onNews});
  final VoidCallback onNews;
  @override State<MarketsAnalysisScreen> createState() => _MarketsAnalysisScreenState();
}

class _MarketsAnalysisScreenState extends State<MarketsAnalysisScreen> {
  String timeframe = '1D';
  bool _requested = false;
  @override void didChangeDependencies() {
    super.didChangeDependencies();
    if (_requested) return;
    _requested = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final app = AppScope.read(context);
      app.ensureHome();
      app.loadBtcSeries(timeframe);
    });
  }
  @override Widget build(BuildContext context) {
    final app = AppScope.of(context);
    final chart = MockData.btcSeries(timeframe);
    final hasChart = chart.length > 2 && chart.any((v) => v > 0) &&
        chart.any((v) => (v - chart.first).abs() > 0.000001);
    final chartLoading = app.loadingCharts.contains(timeframe);
    final chartError = app.chartErrors[timeframe];
    return PremiumBackdrop(child: SafeArea(bottom: false, child: RefreshIndicator(
      color: invGold, onRefresh: app.refreshHome,
      child: ListView(physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(14, 17, 14, 28), children: [
        Row(children: [
          const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Markets', style: TextStyle(color: Colors.white, fontSize: 25, fontWeight: FontWeight.w900)),
            SizedBox(height: 4),
            Text('Global market intelligence', style: TextStyle(color: invMuted, fontSize: 11.5)),
          ])),
          IconButton(tooltip: 'Refresh market data', onPressed: app.refreshHome,
            icon: const Icon(Icons.refresh_rounded, color: invGold)),
        ]),
        const MarketSectionHeading('Spot and macro market overview'),
        MetricGrid(children: [
          MarketMetric(label: 'Total market cap', icon: Icons.public_rounded,
            value: pulseMoney(MockData.totalMcap),
            detail: '24h ${pulsePercent(MockData.mcapChange, signed: true)}'),
          MarketMetric(label: '24h trading volume', icon: Icons.bar_chart_rounded,
            value: pulseMoney(MockData.volume24h), detail: 'Global trading activity'),
          MarketMetric(label: 'BTC dominance', icon: Icons.currency_bitcoin_rounded,
            value: pulsePercent(MockData.btcDominance), detail: 'Bitcoin market share'),
          MarketMetric(label: 'Stablecoin supply Δ · 24h', icon: Icons.account_balance_wallet_outlined,
            value: pulseMoney(MockData.stablecoinFlow, signed: true),
            tone: pulseChangeColor(MockData.stablecoinFlow),
            detail: MockData.stablecoinMarketCap.isFinite
                ? 'Stablecoin cap ${pulseMoney(MockData.stablecoinMarketCap)}'
                : 'Capitalization change estimate'),
          MarketMetric(label: 'Market breadth', icon: Icons.pie_chart_outline_rounded,
            value: MockData.bullish + MockData.neutral + MockData.bearish > 0
              ? '${MockData.bullish} bullish' : '—', detail: 'Provider breadth reading'),
        ]),
        const MarketSectionHeading('Spot benchmark · BTC price trend'),
        PremiumCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            const Icon(Icons.currency_bitcoin, color: invGold, size: 25),
            const SizedBox(width: 7),
            Expanded(child: Text('BTC / USDT', style: const TextStyle(
              color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15))),
            Text(MockData.coin('BTC').price > 0 ? pulseMoney(MockData.coin('BTC').price) : '—',
              style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800)),
          ]),
          const SizedBox(height: 11),
          SingleChildScrollView(scrollDirection: Axis.horizontal, child: Row(children: [
            for (final tf in ['1H','1D','1W','1M','1Y']) Padding(
              padding: const EdgeInsets.only(right: 5), child: ChoiceChip(
                selected: timeframe == tf, label: Text(tf),
                labelStyle: TextStyle(fontSize: 11.5, color: timeframe == tf ? invBg : invMuted),
                backgroundColor: invCard, selectedColor: invGold,
                side: BorderSide(color: timeframe == tf ? invGold : invBorder),
                onSelected: (_) { setState(() => timeframe = tf); app.loadBtcSeries(tf); },
              )),
          ])),
          const SizedBox(height: 12),
          if (hasChart) PremiumPerformanceChart(values: chart, height: 175, color: pulseCyan)
          else SizedBox(height: 175, width: double.infinity, child: Center(child: chartLoading
            ? const CircularProgressIndicator(color: invGold)
            : Column(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.show_chart_rounded, color: invMuted, size: 28),
                const SizedBox(height: 8),
                Text(chartError ?? 'Price history unavailable for this interval.',
                  style: const TextStyle(color: invMuted, fontSize: 12), textAlign: TextAlign.center),
                TextButton.icon(onPressed: () => app.loadBtcSeries(timeframe),
                  icon: const Icon(Icons.refresh_rounded, size: 16), label: const Text('Retry chart')),
              ]))),
          const SizedBox(height: 6),
          Text('ABS / Binance price history · ${timeframe} view',
            style: const TextStyle(color: invMuted, fontSize: 10.5)),
        ])),
        const MarketSectionHeading('Core markets'),
        PremiumCard(child: MockData.coins.isEmpty
          ? const Text('No current prices available', style: TextStyle(color: invMuted))
          : Column(children: MockData.coins.take(9).map((c) => Column(children: [
              MarketCoinTile(coin: c, onTap: app.emailVerified ? () => app.toggleWatch(c.symbol) : null),
              if (c != MockData.coins.take(9).last) const Divider(height: 1, color: invBorder),
            ])).toList())),
        if (MockData.sectors.isNotEmpty) ...[
          const MarketSectionHeading('Sector performance · 24h'),
          PremiumCard(child: Column(children: MockData.sectors.map((s) => Padding(
            padding: const EdgeInsets.symmetric(vertical: 8), child: Row(children: [
              Icon(s.icon, size: 19, color: invGold), const SizedBox(width: 10),
              Expanded(child: Text(s.name, style: const TextStyle(color: Colors.white,
                fontSize: 12.5, fontWeight: FontWeight.w600))),
              Text(pulsePercent(s.change, signed: true), style: TextStyle(
                color: pulseChangeColor(s.change), fontWeight: FontWeight.w800, fontSize: 12)),
            ]))).toList())),
        ],
        const MarketSectionHeading('Spot market movers · 24h'),
        PremiumCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Gainers', style: TextStyle(color: invGreen, fontWeight: FontWeight.w800)),
          if (MockData.gainers.isEmpty) const Padding(padding: EdgeInsets.symmetric(vertical: 10),
            child: Text('Not available', style: TextStyle(color: invMuted))),
          ...MockData.gainers.take(5).map((m) => _mover(m)),
          const Divider(color: invBorder),
          const Text('Losers', style: TextStyle(color: pulseRed, fontWeight: FontWeight.w800)),
          if (MockData.losers.isEmpty) const Padding(padding: EdgeInsets.symmetric(vertical: 10),
            child: Text('Not available', style: TextStyle(color: invMuted))),
          ...MockData.losers.take(5).map((m) => _mover(m)),
        ])),
        MarketSectionHeading('My spot watchlist', action: 'News & research', onTap: widget.onNews),
        PremiumCard(child: app.watchlist.isEmpty
          ? const Text('Sign in and tap a core-market row to add it to your watchlist.',
              style: TextStyle(color: invMuted, fontSize: 12))
          : Column(children: app.watchlist.map((s) => MarketCoinTile(coin: MockData.coin(s),
            onTap: app.emailVerified ? () => app.toggleWatch(s) : null)).toList())),
        MarketSectionHeading('Market headlines', action: 'View all news', onTap: widget.onNews),
        PremiumCard(child: MockData.news.isEmpty
          ? const Text('No recent verified headlines.', style: TextStyle(color: invMuted))
          : Column(children: MockData.news.take(3).map((n) => ListTile(
              onTap: widget.onNews, contentPadding: EdgeInsets.zero,
              title: Text(n.title, maxLines: 2, overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
              subtitle: Text(n.source, style: const TextStyle(color: invMuted, fontSize: 10)),
              trailing: const Icon(Icons.chevron_right, color: pulseCyan, size: 17),
            )).toList())),
        const PulseNotice('Market figures are supplied by external providers and can be delayed or revised.'),
      ]),
    )));
  }

  Widget _mover(Mover m) => Padding(padding: const EdgeInsets.symmetric(vertical: 8),
    child: Row(children: [
      Expanded(child: Text(m.symbol, style: const TextStyle(color: Colors.white,
        fontWeight: FontWeight.w700, fontSize: 12))),
      Text(m.price > 0 ? pulseMoney(m.price) : '—',
        style: const TextStyle(color: invMuted, fontSize: 11.5)),
      const SizedBox(width: 16),
      SizedBox(width: 66, child: Text(pulsePercent(m.change, signed: true),
        textAlign: TextAlign.right, style: TextStyle(color: pulseChangeColor(m.change),
          fontWeight: FontWeight.w800, fontSize: 11.5))),
    ]));
}
