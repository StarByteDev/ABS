import 'dart:async';

import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/app_config.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';
import 'market_extra_screens.dart';

class MarketScreen extends StatefulWidget {
  const MarketScreen({super.key});
  @override
  State<MarketScreen> createState() => _MarketScreenState();
}

class _MarketScreenState extends State<MarketScreen> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> prices = [];
  Map<String, dynamic> health = {};
  Timer? timer;
  String search = '';

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && prices.isEmpty) {
      _load();
      timer ??= Timer.periodic(AppConfig.visiblePriceRefresh, (_) => _load(silent: true));
    }
  }

  @override
  void dispose() {
    timer?.cancel();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    if (!silent) setState(() { loading = true; error = null; });
    try {
      final api = SessionScope.of(context).api;
      final values = await Future.wait([
        api.get('/pulse/market-data/prices'),
        api.get('/pulse/market-data/health'),
      ]);
      prices = JsonTools.mapList(JsonTools.at(values[0], 'data', <dynamic>[]));
      health = JsonTools.map(JsonTools.at(values[1], 'data', <String, dynamic>{}));
      error = null;
    } on ApiException catch (e) {
      if (!silent) error = e.message;
    } finally {
      if (mounted && !silent) setState(() => loading = false);
      if (mounted && silent) setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) => AbsPage(
        title: 'ABS Market',
        subtitle: 'Central Binance Futures snapshot · users never query Binance directly',
        actions: [IconButton(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const WatchlistScreen())), icon: const Icon(Icons.star_outline), tooltip: 'Watchlist'), IconButton(onPressed: _load, icon: const Icon(Icons.refresh))],
        child: loading
            ? const LoadingBlock(label: 'Loading central prices...')
            : error != null
                ? ErrorBlock(message: error!, onRetry: _load)
                : RefreshIndicator(onRefresh: _load, child: _content()),
      );

  Widget _content() {
    final healthy = JsonTools.boolean(health['healthy'], JsonTools.boolean(health['is_healthy']));
    final filtered = prices.where((p) => JsonTools.text(p['symbol']).toLowerCase().contains(search.toLowerCase())).toList();
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        Row(
          children: [
            StatusChip(healthy ? 'FEED HEALTHY' : 'FEED DELAYED', good: healthy),
            const SizedBox(width: 8),
            const StatusChip('1 MINUTE ABS FEED', good: true),
          ],
        ),
        const SizedBox(height: 12),
        TextField(
          onChanged: (v) => setState(() => search = v),
          decoration: const InputDecoration(prefixIcon: Icon(Icons.search), hintText: 'Search BTCUSDT, ETHUSDT...'),
        ),
        const SizedBox(height: 14),
        ...filtered.map((row) {
          final change = JsonTools.number(row['change_percent_24h']);
          return Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: InkWell(
              borderRadius: BorderRadius.circular(18),
              onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => MarketDetailScreen(symbol: JsonTools.text(row['symbol'])))),
              child: AbsCard(
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(JsonTools.text(row['symbol']), style: const TextStyle(fontWeight: FontWeight.w900)),
                        const SizedBox(height: 4),
                        Text('Observed ${compactDate(row['observed_at'])} · ${JsonTools.text(row['source'], 'ABS')}', style: const TextStyle(color: AbsColors.muted, fontSize: 10)),
                      ],
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(money(row['price']), style: const TextStyle(fontWeight: FontWeight.w900)),
                      const SizedBox(height: 4),
                      Text(percent(change), style: TextStyle(color: pnlColor(change), fontWeight: FontWeight.w700, fontSize: 12)),
                    ],
                  ),
                ],
              ),
            )),
          );
        }),
        if (filtered.isEmpty) const EmptyState(title: 'No matching market', message: 'Try a different symbol.'),
      ],
    );
  }
}
