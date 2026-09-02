import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';

class WatchlistScreen extends StatefulWidget {
  const WatchlistScreen({super.key});

  @override
  State<WatchlistScreen> createState() => _WatchlistScreenState();
}

class _WatchlistScreenState extends State<WatchlistScreen> {
  bool loading = true;
  String? error;
  List<Map<String, dynamic>> items = [];
  final symbol = TextEditingController();

  @override
  void dispose() {
    symbol.dispose();
    super.dispose();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && items.isEmpty) _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      items = JsonTools.mapList(
        JsonTools.at(
          await SessionScope.of(context).api.get('/watchlist'),
          'data',
          <dynamic>[],
        ),
      );
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _add() async {
    final value = symbol.text.trim().toUpperCase();
    if (value.isEmpty) return;
    try {
      await SessionScope.of(context).api.post(
        '/watchlist',
        body: {'symbol': value},
      );
      symbol.clear();
      await _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    }
  }

  Future<void> _remove(String value) async {
    try {
      await SessionScope.of(context).api.delete('/watchlist/$value');
      await _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AbsPage(
      title: 'Watchlist',
      subtitle: 'Your saved ABS markets',
      actions: [IconButton(onPressed: _load, icon: const Icon(Icons.refresh))],
      child: loading
          ? const LoadingBlock()
          : error != null
              ? ErrorBlock(message: error!, onRetry: _load)
              : ListView(
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: TextField(
                            controller: symbol,
                            textCapitalization: TextCapitalization.characters,
                            decoration: const InputDecoration(
                              prefixIcon: Icon(Icons.add),
                              hintText: 'BTCUSDT',
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        ElevatedButton(onPressed: _add, child: const Text('Add')),
                      ],
                    ),
                    const SizedBox(height: 12),
                    ...items.map((item) {
                      final value = JsonTools.text(item['symbol']);
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(18),
                          onTap: () => Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => MarketDetailScreen(symbol: value),
                            ),
                          ),
                          child: AbsCard(
                            child: Row(
                              children: [
                                const Icon(Icons.star, color: AbsColors.gold),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        value,
                                        style: const TextStyle(fontWeight: FontWeight.w900),
                                      ),
                                      if (JsonTools.text(item['display_name'], '').isNotEmpty)
                                        Text(
                                          JsonTools.text(item['display_name']),
                                          style: const TextStyle(
                                            color: AbsColors.muted,
                                            fontSize: 11,
                                          ),
                                        ),
                                    ],
                                  ),
                                ),
                                IconButton(
                                  onPressed: () => _remove(value),
                                  icon: const Icon(Icons.close, color: AbsColors.muted),
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    }),
                    if (items.isEmpty)
                      const EmptyState(
                        title: 'Watchlist is empty',
                        message: 'Add symbols such as BTCUSDT to keep them close.',
                        icon: Icons.star_border,
                      ),
                  ],
                ),
    );
  }
}

class MarketDetailScreen extends StatefulWidget {
  const MarketDetailScreen({super.key, required this.symbol});

  final String symbol;

  @override
  State<MarketDetailScreen> createState() => _MarketDetailScreenState();
}

class _MarketDetailScreenState extends State<MarketDetailScreen> {
  bool loading = true;
  String? error;
  Map<String, dynamic> chart = {};
  String interval = '1h';

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && chart.isEmpty) _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      chart = JsonTools.map(
        JsonTools.at(
          await SessionScope.of(context).api.get(
            '/market/chart/${widget.symbol}',
            query: {'interval': interval, 'limit': 80},
          ),
          'data',
          <String, dynamic>{},
        ),
      );
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final candles = JsonTools.mapList(chart['candles']);
    final closes = candles
        .map((item) => JsonTools.number(item['close']))
        .where((value) => value > 0)
        .toList();

    return AbsPage(
      title: widget.symbol,
      subtitle: 'ABS-served market chart',
      actions: [IconButton(onPressed: _load, icon: const Icon(Icons.refresh))],
      child: loading
          ? const LoadingBlock()
          : error != null
              ? ErrorBlock(message: error!, onRetry: _load)
              : ListView(
                  children: [
                    Row(
                      children: [
                        StatusChip(
                          JsonTools.text(chart['status'], 'live').toUpperCase(),
                          good: JsonTools.boolean(chart['is_live'], true),
                        ),
                        const Spacer(),
                        DropdownButton<String>(
                          value: interval,
                          items: const [
                            DropdownMenuItem(value: '5m', child: Text('5M')),
                            DropdownMenuItem(value: '15m', child: Text('15M')),
                            DropdownMenuItem(value: '1h', child: Text('1H')),
                            DropdownMenuItem(value: '4h', child: Text('4H')),
                            DropdownMenuItem(value: '1d', child: Text('1D')),
                          ],
                          onChanged: (value) {
                            if (value == null) return;
                            setState(() => interval = value);
                            _load();
                          },
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    AbsCard(
                      child: SizedBox(
                        height: 220,
                        child: closes.length < 2
                            ? const Center(
                                child: Text(
                                  'Chart data is unavailable.',
                                  style: TextStyle(color: AbsColors.muted),
                                ),
                              )
                            : CustomPaint(
                                painter: _PriceLinePainter(closes: closes),
                                child: const SizedBox.expand(),
                              ),
                      ),
                    ),
                    const SizedBox(height: 12),
                    if (candles.isNotEmpty)
                      AbsCard(
                        child: Column(
                          children: [
                            KeyValueRow('Open', money(candles.last['open'])),
                            KeyValueRow('High', money(candles.last['high'])),
                            KeyValueRow('Low', money(candles.last['low'])),
                            KeyValueRow('Close', money(candles.last['close'])),
                            KeyValueRow('Volume', number(candles.last['volume'])),
                          ],
                        ),
                      ),
                    const SizedBox(height: 10),
                    Text(
                      'Source: ${JsonTools.text(chart['source'], 'ABS')} · Updated ${compactDate(chart['updated_at'])}',
                      style: const TextStyle(color: AbsColors.muted, fontSize: 10),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
    );
  }
}

class _PriceLinePainter extends CustomPainter {
  _PriceLinePainter({required this.closes});

  final List<double> closes;

  @override
  void paint(Canvas canvas, Size size) {
    if (closes.length < 2) return;
    final minValue = closes.reduce(math.min);
    final maxValue = closes.reduce(math.max);
    final span = (maxValue - minValue).abs() < 0.0000001 ? 1.0 : maxValue - minValue;
    final path = Path();

    for (var i = 0; i < closes.length; i++) {
      final x = size.width * i / (closes.length - 1);
      final y = size.height - ((closes[i] - minValue) / span * size.height);
      if (i == 0) {
        path.moveTo(x, y);
      } else {
        path.lineTo(x, y);
      }
    }

    final grid = Paint()
      ..color = AbsColors.line
      ..strokeWidth = .7;
    for (var i = 1; i < 4; i++) {
      final y = size.height * i / 4;
      canvas.drawLine(Offset(0, y), Offset(size.width, y), grid);
    }

    final paint = Paint()
      ..color = AbsColors.cyan
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.2
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant _PriceLinePainter oldDelegate) => oldDelegate.closes != closes;
}

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  bool loading = true;
  String? error;
  Map<String, dynamic> snapshot = {};
  String environment = '';

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && snapshot.isEmpty) _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final response = await SessionScope.of(context).api.get(
        '/pulse/orders',
        query: {if (environment.isNotEmpty) 'environment': environment},
      );
      snapshot = JsonTools.map(
        JsonTools.at(response, 'data', <String, dynamic>{}),
      );
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final orders = _extractOrders(snapshot);
    return AbsPage(
      title: 'Orders',
      subtitle: 'Exchange order snapshot reported by ABS',
      actions: [IconButton(onPressed: _load, icon: const Icon(Icons.sync))],
      child: loading
          ? const LoadingBlock()
          : error != null
              ? ErrorBlock(message: error!, onRetry: _load)
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    children: [
                      Row(
                        children: [
                          const StatusChip('SERVER / EXCHANGE STATE', good: true),
                          const Spacer(),
                          DropdownButton<String>(
                            value: environment,
                            items: const [
                              DropdownMenuItem(value: '', child: Text('Active')),
                              DropdownMenuItem(value: 'testnet', child: Text('Testnet')),
                              DropdownMenuItem(value: 'live', child: Text('LIVE')),
                            ],
                            onChanged: (value) {
                              setState(() => environment = value ?? '');
                              _load();
                            },
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      ...orders.map(
                        (order) => Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: AbsCard(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    StatusChip(
                                      JsonTools.text(order['side'] ?? order['direction']).toUpperCase(),
                                      good: JsonTools.text(order['side']).toUpperCase() == 'BUY',
                                    ),
                                    const SizedBox(width: 8),
                                    Expanded(
                                      child: Text(
                                        JsonTools.text(order['symbol']),
                                        style: const TextStyle(fontWeight: FontWeight.w900),
                                      ),
                                    ),
                                    StatusChip(
                                      JsonTools.text(order['status']).toUpperCase(),
                                      warning: JsonTools.text(order['status']).toUpperCase().contains('NEW'),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 8),
                                KeyValueRow('Type', JsonTools.text(order['type'] ?? order['order_type'])),
                                KeyValueRow(
                                  'Quantity',
                                  number(order['origQty'] ?? order['orig_qty'] ?? order['quantity']),
                                ),
                                KeyValueRow('Price', money(order['price'])),
                                if (order['stopPrice'] != null || order['stop_price'] != null)
                                  KeyValueRow(
                                    'Stop',
                                    money(order['stopPrice'] ?? order['stop_price']),
                                  ),
                              ],
                            ),
                          ),
                        ),
                      ),
                      if (orders.isEmpty)
                        const EmptyState(
                          title: 'No open exchange orders',
                          message: 'Pending entry, TP and SL orders will appear when reported by Binance through ABS.',
                          icon: Icons.receipt_long_outlined,
                        ),
                    ],
                  ),
                ),
    );
  }

  List<Map<String, dynamic>> _extractOrders(Map<String, dynamic> source) {
    for (final key in ['orders', 'open_orders', 'openOrders']) {
      final rows = JsonTools.mapList(source[key]);
      if (rows.isNotEmpty) return rows;
    }
    final nested = JsonTools.map(source['data']);
    for (final key in ['orders', 'open_orders', 'openOrders']) {
      final rows = JsonTools.mapList(nested[key]);
      if (rows.isNotEmpty) return rows;
    }
    return [];
  }
}

class PublicMarketOverviewScreen extends StatefulWidget {
  const PublicMarketOverviewScreen({super.key});

  @override
  State<PublicMarketOverviewScreen> createState() => _PublicMarketOverviewScreenState();
}

class _PublicMarketOverviewScreenState extends State<PublicMarketOverviewScreen> {
  bool loading = true;
  String? error;
  Map<String, dynamic> overview = {};
  Map<String, dynamic> movers = {};

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && overview.isEmpty) _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final api = SessionScope.of(context).api;
      final results = await Future.wait([
        api.get('/market/overview'),
        api.get('/market/movers', query: {'limit': 5}),
      ]);
      overview = JsonTools.map(
        JsonTools.at(results[0], 'data', <String, dynamic>{}),
      );
      movers = JsonTools.map(
        JsonTools.at(results[1], 'data', <String, dynamic>{}),
      );
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final core = JsonTools.mapList(overview['core']);
    final global = JsonTools.map(overview['global']);
    final gainers = JsonTools.mapList(movers['gainers']);
    final losers = JsonTools.mapList(movers['losers']);

    return AbsPage(
      title: 'Market Overview',
      subtitle: 'Public ABS market intelligence',
      actions: [IconButton(onPressed: _load, icon: const Icon(Icons.refresh))],
      child: loading
          ? const LoadingBlock()
          : error != null
              ? ErrorBlock(message: error!, onRetry: _load)
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    children: [
                      Row(
                        children: [
                          StatusChip(
                            JsonTools.text(overview['status'], 'live').toUpperCase(),
                            good: JsonTools.boolean(overview['is_live'], true),
                          ),
                          const SizedBox(width: 7),
                          Expanded(
                            child: Text(
                              JsonTools.text(overview['source'], 'ABS'),
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(color: AbsColors.muted, fontSize: 10),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      GridView.count(
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        crossAxisCount: 2,
                        crossAxisSpacing: 8,
                        mainAxisSpacing: 8,
                        childAspectRatio: 1.6,
                        children: [
                          MetricCard(
                            label: 'Market Cap',
                            value: _compactUsd(global['total_market_cap']),
                          ),
                          MetricCard(
                            label: '24H Volume',
                            value: _compactUsd(global['total_volume']),
                          ),
                          MetricCard(
                            label: 'BTC Dominance',
                            value: percent(global['btc_dominance']),
                          ),
                          MetricCard(
                            label: 'Fear & Greed',
                            value: JsonTools.text(global['fear_greed_score']),
                            detail: JsonTools.text(global['fear_greed_label']),
                          ),
                        ],
                      ),
                      const SizedBox(height: 18),
                      const AbsSectionTitle('Core markets'),
                      const SizedBox(height: 9),
                      ...core.map(
                        (row) => Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: InkWell(
                            borderRadius: BorderRadius.circular(18),
                            onTap: () => Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => MarketDetailScreen(
                                  symbol: JsonTools.text(row['symbol']),
                                ),
                              ),
                            ),
                            child: AbsCard(
                              child: Row(
                                children: [
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          JsonTools.text(
                                            row['pair'],
                                            JsonTools.text(row['symbol']),
                                          ),
                                          style: const TextStyle(fontWeight: FontWeight.w900),
                                        ),
                                        const SizedBox(height: 3),
                                        Text(
                                          '24H volume ${_compactUsd(row['volume'])}',
                                          style: const TextStyle(
                                            color: AbsColors.muted,
                                            fontSize: 10,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  Column(
                                    crossAxisAlignment: CrossAxisAlignment.end,
                                    children: [
                                      Text(
                                        money(row['price']),
                                        style: const TextStyle(fontWeight: FontWeight.w900),
                                      ),
                                      const SizedBox(height: 3),
                                      Text(
                                        percent(row['change_percent']),
                                        style: TextStyle(
                                          color: pnlColor(row['change_percent']),
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),
                      const AbsSectionTitle('Top movers'),
                      const SizedBox(height: 9),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(child: _MoverColumn(title: 'Gainers', items: gainers)),
                          const SizedBox(width: 8),
                          Expanded(child: _MoverColumn(title: 'Losers', items: losers)),
                        ],
                      ),
                      const SizedBox(height: 20),
                    ],
                  ),
                ),
    );
  }

  String _compactUsd(dynamic value) {
    final amount = JsonTools.number(value);
    if (amount >= 1e12) return '\$${number(amount / 1e12, digits: 2)}T';
    if (amount >= 1e9) return '\$${number(amount / 1e9, digits: 2)}B';
    if (amount >= 1e6) return '\$${number(amount / 1e6, digits: 1)}M';
    return money(amount);
  }
}

class _MoverColumn extends StatelessWidget {
  const _MoverColumn({required this.title, required this.items});

  final String title;
  final List<Map<String, dynamic>> items;

  @override
  Widget build(BuildContext context) {
    return AbsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
          const SizedBox(height: 8),
          ...items.take(5).map(
            (row) => Padding(
              padding: const EdgeInsets.symmetric(vertical: 5),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      JsonTools.text(row['pair'], JsonTools.text(row['symbol'])),
                      style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
                    ),
                  ),
                  Text(
                    percent(row['change_percent']),
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      color: pnlColor(row['change_percent']),
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (items.isEmpty) const Text('—', style: TextStyle(color: AbsColors.muted)),
        ],
      ),
    );
  }
}
