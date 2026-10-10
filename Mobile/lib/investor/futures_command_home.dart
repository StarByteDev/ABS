import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/ads/ad_policy.dart';
import '../core/ads/banner_ad_slot.dart';
import '../screens/alerts_screen.dart';
import '../template_rebase/data/mock_data.dart';
import '../template_rebase/state/app_state.dart';
import 'investor_ui.dart';
import 'market_command_ui.dart';
import 'premium_ui.dart';

/// A futures-first Home, distinct from the broad-market analysis tab.
/// All numbers originate in the ABS backend and display — when unavailable.
class FuturesCommandHome extends StatefulWidget {
  const FuturesCommandHome({super.key, required this.onTab, required this.onNews});
  final ValueChanged<int> onTab;
  final VoidCallback onNews;
  @override State<FuturesCommandHome> createState() => _FuturesCommandHomeState();
}

class _FuturesCommandHomeState extends State<FuturesCommandHome> {
  bool _requested = false;
  @override void didChangeDependencies() {
    super.didChangeDependencies();
    if (_requested) return;
    _requested = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final app = AppScope.read(context);
      app.ensureHome();
      app.ensureNews();
    });
  }

  Future<void> _refresh() async {
    final app = AppScope.read(context);
    await Future.wait([app.refreshHome(), app.refreshNews()]);
  }

  /// Show the publisher's own table without copying unlicensed flow numbers
  /// or exposing a CoinGlass API credential in the mobile binary.
  Future<void> _openPublishedEtfFlows() async {
    final uri = Uri.parse('https://farside.co.uk/btc/');
    try {
      final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!opened && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not open the ETF source in your browser.')));
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not open the ETF source in your browser.')));
      }
    }
  }

  String _etfStatusMessage() => switch (MockData.btcEtfStatus) {
    'provider_not_configured' =>
      'ABS has not enabled the ETF data provider. Open published figures below.',
    'endpoint_not_installed' || 'endpoint_missing' =>
      'ETF data endpoint is not installed on ABS. Open published figures below.',
    'provider_unavailable' =>
      'The ETF data provider is temporarily unavailable. Try again later.',
    'no_recent_trading_session' =>
      'No recent completed trading session was returned by the provider.',
    _ => 'No verified daily ETF flow is currently available.',
  };

  @override Widget build(BuildContext context) {
    final app = AppScope.of(context);
    final events = MockData.calendar.where((e) =>
      e.at != null && e.at!.isAfter(DateTime.now()) &&
      (e.impact.contains('high') || e.impact.contains('medium'))).toList()
      ..sort((a,b) => a.at!.compareTo(b.at!));
    final longs = MockData.liqLongs, shorts = MockData.liqShorts;
    final totalLiq = longs.isFinite && shorts.isFinite ? longs + shorts : double.nan;
    final score = MockData.pulseScore;
    final scoreLabel = score <= 0 ? 'Awaiting market data' : MockData.pulseLabel;
    return PremiumBackdrop(child: SafeArea(bottom: false, child: RefreshIndicator(
      color: invGold, onRefresh: _refresh,
      child: ListView(physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 25), children: [
        Row(children: [
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Good ${DateTime.now().hour < 12 ? 'morning' : DateTime.now().hour < 17 ? 'afternoon' : 'evening'},',
              style: const TextStyle(color: invMuted, fontSize: 11.5)),
            Text(app.userName, maxLines: 1, overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Colors.white, fontSize: 19, fontWeight: FontWeight.w800)),
          ])),
          const PremiumChip('PULSE', icon: Icons.monitor_heart_rounded),
          IconButton(tooltip: 'Free Signal', icon: const Icon(Icons.card_giftcard_rounded, color: invGold),
            onPressed: () => widget.onTab(2)),
          IconButton(tooltip: 'Alerts', icon: const Icon(Icons.notifications_none_rounded, color: invMuted),
            onPressed: () => app.emailVerified ? Navigator.push(context,
              MaterialPageRoute<void>(builder: (_) => const AlertsScreen())) : widget.onTab(4)),
        ]),
        const SizedBox(height: 12),
        PremiumCard(highlight: true, padding: const EdgeInsets.all(16), child: Column(
          crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            const Icon(Icons.monitor_heart_rounded, color: invGold, size: 22),
            const SizedBox(width: 8),
            const Expanded(child: Text('Market Pulse', style: TextStyle(color: Colors.white,
              fontSize: 19, fontWeight: FontWeight.w800))),
            Text('15M + 4H context', style: TextStyle(color: invMuted, fontSize: 10.5)),
          ]),
          const SizedBox(height: 13),
          Row(children: [
            SizedBox(width: 85, height: 85, child: Stack(alignment: Alignment.center, children: [
              SizedBox(width: 83, height: 83, child: CircularProgressIndicator(
                value: score <= 0 ? 0 : (score/100).clamp(0,1).toDouble(),
                strokeWidth: 8, backgroundColor: invBorder,
                color: score > 65 ? invGreen : score < 40 ? pulseRed : invGold)),
              Text(score > 0 ? '$score' : '—',
                style: const TextStyle(color: Colors.white, fontSize: 29, fontWeight: FontWeight.w900)),
            ])),
            const SizedBox(width: 15),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(scoreLabel, style: const TextStyle(color: invGreen, fontSize: 17, fontWeight: FontWeight.w800)),
              const SizedBox(height: 5),
              Text(MockData.dailyInsight, maxLines: 4, overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: invMuted, fontSize: 11.5, height: 1.42)),
            ])),
          ]),
        ])),
        if (app.homeLoading && MockData.totalMcap.isNaN) const Padding(
          padding: EdgeInsets.all(17), child: Center(child: CircularProgressIndicator(color: invGold))),
        if (app.homeError != null) Padding(padding: const EdgeInsets.only(top: 8),
          child: Text('Market feed: ${app.homeError}',
            style: const TextStyle(color: invMuted, fontSize: 11))),
        const MarketSectionHeading('Futures & risk snapshot'),
        MetricGrid(children: [
          MarketMetric(label: '24h liquidations', icon: Icons.local_fire_department_outlined,
            value: pulseMoney(totalLiq), detail: 'Long ${pulseMoney(longs)} · Short ${pulseMoney(shorts)}'),
          MarketMetric(label: 'Long / short ratio', icon: Icons.balance_outlined,
            value: pulseNumber(MockData.longShort), detail: 'Position ratio · source: ABS'),
          MarketMetric(label: 'Open interest', icon: Icons.layers_outlined,
            value: pulseMoney(MockData.openInterest), detail: 'Aggregate futures exposure'),
          MarketMetric(label: 'Funding rate', icon: Icons.percent_rounded,
            value: pulsePercent(MockData.fundingRate, decimals: 4),
            detail: 'Exchange-reported rate'),
          MarketMetric(label: 'Volatility index', icon: Icons.show_chart_rounded,
            value: MockData.volatilityScore.isFinite ? pulseNumber(MockData.volatilityScore, decimals: 1) : '—',
            detail: 'Provider volatility reading'),
        ]),
        const SizedBox(height: 10),
        PremiumCard(padding: const EdgeInsets.all(13), child: Column(
          crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Futures positioning', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13)),
          const SizedBox(height: 8),
          PulseDirectionBar(ratio: MockData.longShort),
          if (totalLiq.isFinite && totalLiq > 0) ...[
            const SizedBox(height: 12),
            const Text('24h liquidation composition', style: TextStyle(color: invMuted, fontSize: 11)),
            const SizedBox(height: 6),
            ClipRRect(borderRadius: BorderRadius.circular(7), child: Row(children: [
              Expanded(flex: (longs/totalLiq*100).round().clamp(1,99).toInt(),
                child: Container(height: 7, color: pulseRed)),
              Expanded(flex: (shorts/totalLiq*100).round().clamp(1,99).toInt(),
                child: Container(height: 7, color: invGreen)),
            ])),
            const SizedBox(height: 5),
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              Text('Longs ${pulsePercent(longs/totalLiq*100, decimals: 1)}',
                style: const TextStyle(color: pulseRed, fontSize: 10.5)),
              Text('Shorts ${pulsePercent(shorts/totalLiq*100, decimals: 1)}',
                style: const TextStyle(color: invGreen, fontSize: 10.5)),
            ]),
          ],
        ])),
        const InlineBannerAdSlot(placement: BannerPlacement.home),
        // Daily fund-flow data is distinct from 24h crypto market volume.
        MarketSectionHeading('Spot Bitcoin ETF flows', action: 'Refresh',
          onTap: () => AppScope.read(context).refreshHome()),
        PremiumCard(padding: const EdgeInsets.all(14), child: Column(
          crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            const Icon(Icons.account_balance_rounded, color: invGold, size: 28),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(MockData.btcEtfFlow.isFinite
                ? pulseMoney(MockData.btcEtfFlow, signed: true) : 'ETF flow not connected',
                style: TextStyle(color: MockData.btcEtfFlow.isFinite
                  ? pulseChangeColor(MockData.btcEtfFlow) : Colors.white,
                  fontWeight: FontWeight.w900, fontSize: 18)),
              const SizedBox(height: 4),
              Text(MockData.btcEtfFlow.isFinite
                ? 'US spot BTC ETFs · last published session ${MockData.btcEtfDate}'
                : _etfStatusMessage(),
                style: const TextStyle(color: invMuted, fontSize: 11.5, height: 1.4)),
              if (MockData.btcEtfFlow.isFinite && MockData.btcEtfSource.isNotEmpty)
                Text('Source: ${MockData.btcEtfSource}',
                  style: const TextStyle(color: invMuted, fontSize: 10)),
            ])),
          ]),
          const SizedBox(height: 12),
          SizedBox(width: double.infinity, child: OutlinedButton.icon(
            key: const Key('view-published-etf-flows'),
            onPressed: _openPublishedEtfFlows,
            icon: const Icon(Icons.open_in_new_rounded, size: 17),
            label: const Text('View published BTC ETF flows'),
            style: OutlinedButton.styleFrom(foregroundColor: invGold,
              side: BorderSide(color: invGold.withAlpha(125)),
              minimumSize: const Size.fromHeight(43)),
          )),
        ])),
        MarketSectionHeading('Key economic events', action: 'Full calendar', onTap: widget.onNews),
        PremiumCard(child: events.isEmpty
          ? const Text('No upcoming high- or medium-impact releases returned by the calendar feed.',
            style: TextStyle(color: invMuted, fontSize: 12))
          : Column(children: events.take(4).map((e) => ListTile(
              contentPadding: EdgeInsets.zero, dense: true,
              title: Text(e.title, maxLines: 2, overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Colors.white, fontSize: 12.5, fontWeight: FontWeight.w700)),
              subtitle: Text('${e.currency} · ${e.at!.toLocal()} · Forecast: ${e.forecast.isEmpty ? 'Not published' : e.forecast}',
                maxLines: 2, overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: invMuted, fontSize: 10.5)),
              trailing: PremiumChip(e.impact.toUpperCase(),
                color: e.impact.contains('high') ? pulseRed : invGold),
              onTap: widget.onNews,
            )).toList())),
        const SizedBox(height: 18),
        PremiumCard(highlight: true, child: Row(children: [
          const Icon(Icons.card_giftcard_rounded, color: invGold, size: 35),
          const SizedBox(width: 10),
          const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Free Signal', style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w900)),
            SizedBox(height: 4),
            Text('Watch a rewarded ad to reveal a qualified signal, Entry Watch, or BTC context.',
              style: TextStyle(color: invMuted, fontSize: 11.2, height: 1.35)),
          ])),
          IconButton(tooltip: 'Open Free Signal', onPressed: () => widget.onTab(2),
            icon: const Icon(Icons.arrow_forward_rounded, color: invGold)),
        ])),
        const PulseNotice('Information and simulated signals are not a guarantee of profits. Futures trading carries liquidation risk.'),
      ]),
    )));
  }
}
