import 'package:flutter/material.dart';

import '../../core/json_tools.dart';
import '../theme/app_theme.dart';

class Coin {
  const Coin(
    this.symbol,
    this.name,
    this.price,
    this.change,
    this.volume,
    this.color, {
    this.highValue,
    this.lowValue,
    this.sparkValues = const <double>[],
  });

  final String symbol;
  final String name;
  final double price;
  final double change;
  final double volume;
  final Color color;
  final double? highValue;
  final double? lowValue;
  final List<double> sparkValues;

  String get pair => '$symbol/USDT';
  double get high => highValue ?? price;
  double get low => lowValue ?? price;
  List<double> get spark => sparkValues.length >= 2 ? sparkValues : <double>[price, price];
  List<double> get chart => spark;
}

enum Side { long, short }
enum SignalStatus { active, watching, closed }

class Signal {
  const Signal({
    required this.id,
    required this.symbol,
    required this.side,
    required this.timeframe,
    required this.entry,
    required this.stop,
    required this.targets,
    required this.confidence,
    required this.agree,
    required this.status,
    required this.minutesAgo,
    required this.note,
    this.result,
    this.backendId,
    this.raw = const <String, dynamic>{},
  });

  final String id;
  final String symbol;
  final Side side;
  final String timeframe;
  final double entry;
  final double stop;
  final List<double> targets;
  final int confidence;
  final int agree;
  final SignalStatus status;
  final int minutesAgo;
  final String note;
  final String? result;
  final int? backendId;
  final Map<String, dynamic> raw;

  String get pair => symbol.contains('/') ? symbol : '$symbol/USDT';
  double get riskReward {
    if (targets.isEmpty) return 0;
    final risk = (entry - stop).abs();
    if (risk <= 0) return 0;
    return (targets.first - entry).abs() / risk;
  }

  List<int> votes(String tf) {
    final direct = JsonTools.list(raw[tf == '4H' ? 'votes_4h' : 'votes_15m']);
    if (direct.isNotEmpty) {
      return List<int>.generate(15, (i) {
        if (i >= direct.length) return 0;
        final value = direct[i];
        if (value is num) return value > 0 ? 1 : value < 0 ? -1 : 0;
        final text = value.toString().toLowerCase();
        if (['agree', 'bullish', 'long', 'buy', 'positive', '1'].contains(text)) return 1;
        if (['disagree', 'bearish', 'short', 'sell', 'negative', '-1'].contains(text)) return -1;
        return 0;
      });
    }
    // Do not invent per-strategy votes when the API only supplies an aggregate
    // agree count. Neutral placeholders make that limitation explicit.
    return List<int>.filled(15, 0);
  }
}

class NewsItem {
  const NewsItem({
    required this.id,
    required this.title,
    required this.source,
    required this.category,
    required this.minutesAgo,
    required this.summary,
    required this.body,
    required this.keyPoints,
    this.slug = '',
    this.sourceUrl = '',
    this.raw = const <String, dynamic>{},
  });

  final String id;
  final String title;
  final String source;
  final String category;
  final int minutesAgo;
  final String summary;
  final List<String> body;
  final List<String> keyPoints;
  final String slug;
  final String sourceUrl;
  final Map<String, dynamic> raw;
}

class CalendarEvent {
  const CalendarEvent({
    required this.id,
    required this.title,
    required this.at,
    required this.impact,
    required this.currency,
    required this.previous,
    required this.forecast,
    required this.actual,
    required this.context,
  });
  final String id;
  final String title;
  final DateTime? at;
  final String impact;
  final String currency;
  final String previous;
  final String forecast;
  final String actual;
  final String context;
}

class Plan {
  const Plan({
    required this.id,
    required this.name,
    required this.tagline,
    required this.prices,
    required this.features,
    this.featured = false,
  });
  final String id;
  final String name;
  final String tagline;
  final Map<int, double> prices;
  final List<String> features;
  final bool featured;
}

class Sector {
  const Sector(this.name, this.icon, this.change);
  final String name;
  final IconData icon;
  final double change;
}

class Mover {
  const Mover(this.symbol, this.name, this.change, {this.price = 0});
  final String symbol;
  final String name;
  final double change;
  final double price;
}

/// Runtime data used by the exact supplied template UI.  The class keeps the
/// template's original API surface so the visual code remains almost
/// unchanged, but every mutable value is filled from the ABS V15.7.4 API.
class MockData {
  static List<Coin> coins = <Coin>[
    const Coin('BTC', 'Bitcoin', 0, 0, 0, Color(0xFFF7931A)),
  ];
  static const timeframes = <String>['1H', '1D', '1W', '1M', '1Y', 'ALL'];
  static final Map<String, List<double>> _btcCache = <String, List<double>>{};

  static int pulseScore = 0;
  static String pulseLabel = 'Syncing';
  static String dailyInsight = 'ABS market intelligence is syncing.';
  static int sentimentScore = 0;
  static int bullish = 0;
  static int neutral = 0;
  static int bearish = 0;
  static double liqLongs = double.nan;
  static double liqShorts = double.nan;
  static double openInterest = double.nan;
  static double fundingRate = double.nan;
  static double longShort = double.nan;
  static double basis = double.nan;
  static double totalMcap = double.nan;
  static double mcapChange = double.nan;
  static double volume24h = double.nan;
  static double btcDominance = double.nan;
  static double fearGreed = double.nan;
  static double stablecoinFlow = double.nan;
  static double volatilityScore = double.nan;

  static List<Sector> sectors = <Sector>[];
  static List<Mover> gainers = <Mover>[];
  static List<Mover> losers = <Mover>[];
  static List<Signal> signals = <Signal>[];
  static Signal? freeSignal;
  static List<NewsItem> news = <NewsItem>[];
  static List<NewsItem> liveNews = <NewsItem>[];
  static List<CalendarEvent> calendar = <CalendarEvent>[];
  static List<String> newsCategories = <String>['All'];

  static const strategies = <String>[
    'EMA trend stack',
    'RSI momentum',
    'MACD cross',
    'Bollinger squeeze',
    'VWAP reclaim',
    'Supertrend',
    'Ichimoku cloud',
    'Volume profile',
    'Order block',
    'Fair value gap',
    'Stochastic RSI',
    'ADX strength',
    'Funding skew',
    'Open interest divergence',
    'Liquidity sweep',
  ];

  static List<Plan> plans = <Plan>[
    const Plan(
      id: 'pulse',
      name: 'Pulse Intelligence',
      tagline: 'Market scanning, strategy scoring, signal review and alerts.',
      prices: <int, double>{},
      features: <String>[
        '15-strategy analysis on 15M and 4H',
        'Server-authoritative signal validation',
        'Free Signal and market intelligence',
      ],
    ),
    const Plan(
      id: 'professional',
      name: 'Pulse Professional',
      tagline: 'Expanded market selection, advanced execution and intelligence.',
      prices: <int, double>{},
      features: <String>[
        'Expanded market selection',
        'Guarded execution and position monitoring',
        'Advanced reports and strategy intelligence',
      ],
      featured: true,
    ),
  ];

  static Coin coin(String symbol) {
    final normalized = symbol.replaceAll('USDT', '').replaceAll('/', '').toUpperCase();
    for (final c in coins) {
      if (c.symbol.toUpperCase() == normalized) return c;
    }
    return Coin(normalized.isEmpty ? 'BTC' : normalized, normalized, 0, 0, 0, _coinColor(normalized));
  }

  static List<double> btcSeries(String tf) {
    final values = _btcCache[tf];
    if (values != null && values.length >= 2) return values;
    final price = coin('BTC').price;
    return <double>[price, price];
  }

  static void setBtcSeries(String tf, List<double> values) {
    final clean = values.where((v) => v.isFinite && v > 0).toList();
    if (clean.length >= 2) _btcCache[tf] = clean;
  }

  static Plan plan(String id) {
    for (final p in plans) {
      if (p.id == id) return p;
    }
    return plans.first;
  }

  static (IconData, Color) categoryStyle(String c) => switch (c.toLowerCase()) {
        'bitcoin' => (Icons.currency_bitcoin, const Color(0xFFF7931A)),
        'etfs' || 'etf' => (Icons.account_balance_outlined, AppColors.accent),
        'regulation' => (Icons.gavel_rounded, const Color(0xFFB08CFF)),
        'defi' => (Icons.hub_outlined, AppColors.up),
        'ai' || 'ai & data' => (Icons.memory_rounded, const Color(0xFF2FD4E0)),
        'macro' || 'economy' => (Icons.public_rounded, AppColors.amber),
        _ => (Icons.show_chart_rounded, AppColors.amber),
      };

  static void applyMarketOverview(Map<String, dynamic> data) {
    // V15.x has evolved this public payload over time. Merge the common
    // containers so the mobile UI consumes whichever server shape is live,
    // without inventing values when a metric is genuinely unavailable.
    final global = <String, dynamic>{
      ...data,
      ...JsonTools.map(data['market']),
      ...JsonTools.map(data['metrics']),
      ...JsonTools.map(data['global']),
    };
    final pulse = <String, dynamic>{
      ...JsonTools.map(data['market_pulse']),
      ...JsonTools.map(data['pulse']),
    };
    final sentiment = <String, dynamic>{
      ...JsonTools.map(data['breadth']),
      ...JsonTools.map(data['sentiment']),
    };
    final insights = <String, dynamic>{
      ...JsonTools.map(data['context']),
      ...JsonTools.map(data['insights']),
    };
    final core = <Map<String, dynamic>>[
      ...JsonTools.mapList(data['core']),
      ...JsonTools.mapList(data['prices']),
      ...JsonTools.mapList(data['assets']),
    ];

    final mapped = <Coin>[];
    final seenCore = <String>{};
    for (final row in core) {
      final rawSymbol = JsonTools.text(row['base'], JsonTools.text(row['symbol'] ?? row['pair'], '')).toUpperCase();
      final symbol = rawSymbol.replaceAll('USDT', '').replaceAll('/', '').trim();
      if (symbol.isEmpty || !seenCore.add(symbol)) continue;
      final price = JsonTools.number(row['price'] ?? row['last_price']);
      final change = JsonTools.number(row['change_percent'] ?? row['change_percent_24h'] ?? row['price_change_percent']);
      final volume = JsonTools.number(row['quote_volume'] ?? row['volume_24h'] ?? row['volume']);
      final high = JsonTools.number(row['high_24h'] ?? row['high'], price);
      final low = JsonTools.number(row['low_24h'] ?? row['low'], price);
      mapped.add(Coin(
        symbol,
        JsonTools.text(row['name'], _coinName(symbol)),
        price,
        change,
        volume,
        _coinColor(symbol),
        highValue: high > 0 ? high : null,
        lowValue: low > 0 ? low : null,
      ));
    }
    if (mapped.isNotEmpty) {
      mapped.sort((a, b) {
        const order = <String>['BTC', 'ETH', 'SOL', 'BNB', 'XRP', 'ADA', 'DOGE', 'AVAX', 'LINK'];
        final ai = order.indexOf(a.symbol);
        final bi = order.indexOf(b.symbol);
        if (ai == -1 && bi == -1) return b.volume.compareTo(a.volume);
        if (ai == -1) return 1;
        if (bi == -1) return -1;
        return ai.compareTo(bi);
      });
      coins = mapped;
    }

    pulseScore = JsonTools.integer(pulse['score']).clamp(0, 100).toInt();
    pulseLabel = JsonTools.text(pulse['label'], pulseScore > 0 ? 'Market' : 'Syncing');
    dailyInsight = JsonTools.text(insights['daily_insight'], 'ABS market intelligence is syncing.');
    bullish = JsonTools.integer(sentiment['bullish']).clamp(0, 100).toInt();
    neutral = JsonTools.integer(sentiment['neutral']).clamp(0, 100).toInt();
    bearish = JsonTools.integer(sentiment['bearish']).clamp(0, 100).toInt();
    final sentimentTotal = bullish + neutral + bearish;
    sentimentScore = sentimentTotal <= 0 ? 0 : ((bullish * 100 + neutral * 50) / sentimentTotal).round();

    openInterest = _maybeNumber(global, <String>['open_interest_usd', 'open_interest']);
    fundingRate = _maybeNumber(global, <String>['funding_rate']);
    longShort = _maybeNumber(global, <String>['long_short_ratio']);
    basis = _maybeNumber(global, <String>['perp_premium_basis', 'basis']);
    liqLongs = _maybeNumber(global, <String>['liquidation_long_24h_usd', 'liquidations_long']);
    liqShorts = _maybeNumber(global, <String>['liquidation_short_24h_usd', 'liquidations_short']);
    totalMcap = _maybeNumber(global, <String>[
      'total_market_cap', 'total_market_cap_usd', 'market_cap_usd', 'market_cap', 'totalMarketCap',
    ]);
    mcapChange = _maybeNumber(global, <String>[
      'market_cap_change_24h', 'market_cap_change_percent', 'total_market_cap_change_24h',
      'total_market_cap_change_percentage_24h', 'market_cap_24h_change_percent',
    ]);
    volume24h = _maybeNumber(global, <String>[
      'volume_24h_usd', 'total_volume_24h', 'total_volume_24h_usd', 'volume_24h',
      'total_volume', 'volume24h',
    ]);
    btcDominance = _maybeNumber(global, <String>[
      'btc_dominance', 'bitcoin_dominance', 'btc_dominance_percentage', 'btc_market_share',
    ]);
    fearGreed = _maybeNestedNumber(global, <String>[
      'fear_greed_score', 'fear_greed', 'fear_greed_index', 'fear_and_greed', 'fearGreed',
    ]);
    stablecoinFlow = _maybeNumber(global, <String>[
      'stablecoin_flow', 'stablecoin_netflow', 'stablecoin_net_flow', 'stablecoin_flow_usd',
    ]);
    volatilityScore = _maybeNumber(global, <String>[
      'volatility_score', 'volatility', 'market_volatility_score',
    ]);

    final sectorRows = JsonTools.mapList(data['sectors'] ?? insights['sectors']);
    sectors = sectorRows.take(8).map((row) {
      final name = JsonTools.text(row['name'] ?? row['sector'], 'Market');
      return Sector(name, _sectorIcon(name), JsonTools.number(row['change_percent'] ?? row['change']));
    }).toList();
  }

  static void applyMovers(Map<String, dynamic> data) {
    Mover convert(Map<String, dynamic> row) {
      final raw = JsonTools.text(row['base'], JsonTools.text(row['symbol'] ?? row['pair'], '')).toUpperCase();
      final symbol = raw.replaceAll('USDT', '').replaceAll('/', '');
      return Mover(
        symbol,
        JsonTools.text(row['name'], _coinName(symbol)),
        JsonTools.number(row['change_percent'] ?? row['change_percent_24h'] ?? row['price_change_percent']),
        price: JsonTools.number(row['price'] ?? row['last_price']),
      );
    }
    gainers = JsonTools.mapList(data['gainers']).map(convert).where((m) => m.symbol.isNotEmpty).take(8).toList();
    losers = JsonTools.mapList(data['losers']).map(convert).where((m) => m.symbol.isNotEmpty).take(8).toList();
  }

  static void applySignals(dynamic payload) {
    final root = JsonTools.map(JsonTools.at(payload, 'data', payload));
    final rows = <Map<String, dynamic>>[
      ...JsonTools.collectionItems(
        root,
        keys: const <String>['signals', 'qualified_signals', 'qualified', 'items', 'results', 'data'],
      ),
    ];

    // V15.x endpoints are not all shaped the same way. In particular the web
    // Find Best Signal flow may return one persisted signal instead of a list.
    // Accept those server shapes without manufacturing a signal client-side.
    for (final key in const <String>['best_signal', 'bestSignal', 'signal', 'setup']) {
      final single = JsonTools.map(root[key]);
      if (single.isNotEmpty) rows.add(single);
    }
    final overview = JsonTools.map(root['overview']);
    for (final key in const <String>['best_signal', 'bestSignal', 'signal']) {
      final single = JsonTools.map(overview[key]);
      if (single.isNotEmpty) rows.add(single);
    }

    final seen = <String>{};
    final mapped = <Signal>[];
    for (final row in rows) {
      final signal = signalFromMap(row);
      if (signal.symbol.isEmpty) continue;
      final key = signal.backendId != null
          ? 'id:${signal.backendId}'
          : '${signal.symbol}|${signal.timeframe}|${signal.entry}|${signal.confidence}';
      if (seen.add(key)) mapped.add(signal);
    }
    signals = mapped;
  }

  static Signal signalFromMap(Map<String, dynamic> row) {
    final rawPair = JsonTools.text(row['pair'] ?? row['symbol'] ?? row['market'], '').toUpperCase();
    final symbol = rawPair.replaceAll('USDT', '').replaceAll('/', '').replaceAll('-', '');
    final direction = JsonTools.text(row['direction'] ?? row['side'] ?? row['signal'], 'LONG').toUpperCase();
    final targetsRaw = JsonTools.list(row['targets'] ?? row['take_profits'] ?? row['tp_levels']);
    final targets = <double>[];
    for (final t in targetsRaw) {
      if (t is Map) {
        final m = JsonTools.map(t);
        final n = JsonTools.number(m['price'] ?? m['value'] ?? m['target']);
        if (n > 0) targets.add(n);
      } else {
        final n = JsonTools.number(t);
        if (n > 0) targets.add(n);
      }
    }
    for (final key in <String>['tp1', 'take_profit', 'target_1']) {
      final n = JsonTools.number(row[key]);
      if (n > 0 && !targets.contains(n)) targets.add(n);
    }
    final created = _parseDate(row['created_at'] ?? row['generated_at'] ?? row['signal_at'] ?? row['updated_at']);
    final minutesAgo = created == null ? 0 : DateTime.now().difference(created.toLocal()).inMinutes.clamp(0, 999999).toInt();
    final statusText = JsonTools.text(row['status'], 'active').toLowerCase();
    final status = statusText.contains('closed') || statusText.contains('hit') || statusText.contains('expired')
        ? SignalStatus.closed
        : statusText.contains('watch') || statusText.contains('entry')
            ? SignalStatus.watching
            : SignalStatus.active;
    final idInt = JsonTools.integer(row['id']);
    return Signal(
      id: idInt > 0 ? '$idInt' : JsonTools.text(row['uuid'] ?? row['signal_id'], '${symbol}_${created?.millisecondsSinceEpoch ?? 0}'),
      backendId: idInt > 0 ? idInt : null,
      symbol: symbol,
      side: direction.contains('SHORT') || direction.contains('SELL') ? Side.short : Side.long,
      timeframe: JsonTools.text(row['timeframe'] ?? row['primary_timeframe'], '4H').toUpperCase(),
      entry: JsonTools.number(row['entry_price'] ?? row['entry'] ?? row['price']),
      stop: JsonTools.number(row['stop_loss'] ?? row['sl'] ?? row['stop']),
      targets: targets,
      confidence: JsonTools.integer(row['confidence_score'] ?? row['confidence'] ?? row['score']).clamp(0, 100).toInt(),
      agree: JsonTools.integer(row['strategy_agree_count'] ?? row['agree_count'] ?? row['strategies_agree']).clamp(0, 15).toInt(),
      status: status,
      minutesAgo: minutesAgo,
      note: JsonTools.plain(row['reasoning'] ?? row['summary'] ?? row['note'] ?? row['analysis'], 'Open the signal for the latest ABS strategy reasoning.'),
      result: JsonTools.text(row['result'] ?? row['outcome'], '').isEmpty ? null : JsonTools.text(row['result'] ?? row['outcome']),
      raw: row,
    );
  }

  static void applyNews(dynamic payload, {bool live = false}) {
    final rows = JsonTools.collectionItems(payload, keys: live
        ? const <String>['headlines', 'news', 'articles', 'items', 'results', 'feed', 'data']
        : const <String>['news', 'articles', 'items', 'results', 'data']);
    final mapped = rows.map(newsFromMap).where((n) => n.title.isNotEmpty).toList();
    if (live) {
      liveNews = mapped;
    } else {
      news = mapped;
      final cats = <String>{'All'};
      for (final item in mapped) {
        if (item.category.trim().isNotEmpty) cats.add(item.category);
      }
      newsCategories = cats.toList();
    }
  }

  static NewsItem newsFromMap(Map<String, dynamic> row) {
    final published = _parseDate(row['published_at'] ?? row['publishedAt'] ?? row['created_at'] ?? row['timestamp']);
    final mins = published == null ? 0 : DateTime.now().difference(published.toLocal()).inMinutes.clamp(0, 999999).toInt();
    final content = JsonTools.plain(row['body'] ?? row['content'] ?? row['description'], '');
    final paragraphs = content.isEmpty
        ? <String>[]
        : content.split(RegExp(r'\n\s*\n|\r?\n')).map((e) => e.trim()).where((e) => e.isNotEmpty).toList();
    final rawKeys = JsonTools.list(row['key_points'] ?? row['highlights']);
    return NewsItem(
      id: JsonTools.text(row['id'] ?? row['slug'] ?? row['uuid'], '${row.hashCode}'),
      slug: JsonTools.text(row['slug'], ''),
      title: JsonTools.text(row['title'] ?? row['headline'], ''),
      source: JsonTools.text(row['source_name'] ?? row['source'], 'ABS News'),
      category: JsonTools.text(row['category'] ?? row['topic'], 'Markets'),
      minutesAgo: mins,
      summary: JsonTools.plain(row['summary'] ?? row['excerpt'] ?? row['description'], ''),
      body: paragraphs,
      keyPoints: rawKeys.map((e) => JsonTools.plain(e)).where((e) => e.isNotEmpty).toList(),
      sourceUrl: JsonTools.text(row['source_url'] ?? row['url'], ''),
      raw: row,
    );
  }

  static void applyCalendar(List<Map<String, dynamic>> rows) {
    calendar = rows.map((row) {
      final at = _calendarAt(row);
      return CalendarEvent(
        id: JsonTools.text(row['id'] ?? row['event_id'] ?? row['uuid'], '${row.hashCode}'),
        title: JsonTools.text(row['title'] ?? row['event'] ?? row['name'] ?? row['event_name'] ?? row['eventName'], 'Economic event'),
        at: at,
        impact: JsonTools.text(row['impact'] ?? row['importance'] ?? row['priority'] ?? row['impact_level'] ?? row['impactLevel'], 'medium').toLowerCase(),
        currency: _calendarCurrency(row),
        previous: _calendarValue(row, const <String>[
          'previous_value', 'previous', 'prev', 'previousValue', 'previousValueText',
          'prior', 'prior_value', 'priorValue', 'previous_release', 'previousRelease',
          'last_value', 'lastValue', 'previous_result', 'previousResult', 'previous_text', 'previousText',
        ], fallback: ''),
        forecast: _calendarValue(row, const <String>[
          'forecast_value', 'forecast', 'consensus', 'forecastValue', 'forecastValueText',
          'expected', 'expected_value', 'expectedValue', 'estimate', 'estimated',
          'consensus_value', 'consensusValue', 'projection', 'projected', 'forecast_result', 'forecastResult', 'forecast_text', 'forecastText',
        ], fallback: ''),
        // Keep a missing server value empty. The card decides whether that means
        // Pending (future) or simply unavailable/not reported (past).
        actual: _calendarValue(row, const <String>[
          'actual_value', 'actual', 'actualValue', 'actualValueText', 'reported',
          'reported_value', 'reportedValue', 'released_value', 'releasedValue',
          'release_value', 'releaseValue', 'result_value', 'resultValue', 'outcome', 'actual_result', 'actualResult', 'actual_text', 'actualText',
        ], fallback: ''),
        context: JsonTools.plain(
          row['crypto_context'] ?? row['market_context'] ?? row['context'] ?? row['description'] ?? row['explanation'],
          '',
        ),
      );
    }).toList()
      ..sort((a, b) {
        if (a.at == null && b.at == null) return 0;
        if (a.at == null) return 1;
        if (b.at == null) return -1;
        return a.at!.compareTo(b.at!);
      });
  }

  static DateTime? _calendarAt(Map<String, dynamic> row) {
    final raw = row['event_at'] ??
        row['scheduled_at'] ??
        row['release_at'] ??
        row['scheduled_for'] ??
        row['datetime'] ??
        row['date_time'] ??
        row['dateTime'] ??
        row['event_datetime'] ??
        row['event_date_time'] ??
        row['timestamp'];
    final direct = _parseDate(raw);
    if (direct != null) return direct;
    final date = JsonTools.text(row['date'] ?? row['event_date'] ?? row['release_date'], '');
    final time = JsonTools.text(row['time'] ?? row['event_time'] ?? row['release_time'], '');
    if (date.isEmpty) return null;
    return _parseDate(time.isEmpty ? date : '$date $time');
  }

  static String _calendarValue(
    Map<String, dynamic> row,
    List<String> keys, {
    String fallback = '—',
  }) {
    final wanted = keys.map(_normaliseCalendarKey).toSet();

    String clean(dynamic value) {
      if (value == null) return '';
      if (value is Map) {
        final map = JsonTools.map(value);
        // Common provider representation: {value: 4.25, unit: "%"} or
        // {display: "4.25%", raw: 4.25}.
        for (final displayKey in const <String>[
          'display', 'formatted', 'text', 'label_value', 'labelValue',
          'formatted_value', 'formattedValue', 'value', 'raw', 'result',
        ]) {
          if (!map.containsKey(displayKey)) continue;
          final candidate = clean(map[displayKey]);
          if (candidate.isEmpty) continue;
          final unit = JsonTools.text(map['unit'] ?? map['suffix'], '');
          if (unit.isNotEmpty && !candidate.endsWith(unit)) return '$candidate$unit';
          return candidate;
        }
        return '';
      }
      if (value is List) return '';
      final text = JsonTools.plain(value, '').trim();
      if (text.isEmpty) return '';
      final lower = text.toLowerCase();
      if (<String>{
        'null', 'n/a', 'na', '-', '—', 'pending', 'not available',
        'not supplied', 'tbd', 'none',
      }.contains(lower)) return '';
      return text;
    }

    String scan(dynamic value, int depth) {
      if (value == null || depth > 8) return '';
      if (value is List) {
        // Some calendar providers send figures as rows such as
        // [{name: "Previous", value: "4.25%"}, ...].
        for (final item in value) {
          if (item is Map) {
            final itemMap = JsonTools.map(item);
            final label = _normaliseCalendarKey(JsonTools.text(
              itemMap['name'] ?? itemMap['label'] ?? itemMap['type'] ?? itemMap['key'] ?? itemMap['field'],
              '',
            ));
            if (wanted.contains(label)) {
              for (final valueKey in const <String>['value', 'text', 'display', 'formatted', 'result', 'amount']) {
                final candidate = clean(itemMap[valueKey]);
                if (candidate.isNotEmpty) return candidate;
              }
            }
          }
          final nested = scan(item, depth + 1);
          if (nested.isNotEmpty) return nested;
        }
        return '';
      }
      if (value is! Map) return '';
      final map = JsonTools.map(value);

      // First pass: exact/case-insensitive aliases at this level.
      for (final entry in map.entries) {
        if (!wanted.contains(_normaliseCalendarKey(entry.key))) continue;
        final candidate = clean(entry.value);
        if (candidate.isNotEmpty) return candidate;
      }

      // Second pass: nested provider blobs such as values/release/figures/data.
      for (final entry in map.entries) {
        final nested = scan(entry.value, depth + 1);
        if (nested.isNotEmpty) return nested;
      }
      return '';
    }

    final structured = scan(row, 0);
    if (structured.isNotEmpty) return structured;

    // Last-resort extraction for providers that only include release figures
    // in a human-readable summary. Requiring a colon/equal sign prevents prose
    // such as "markets compare actual with forecast" from becoming a value.
    final labels = keys.map((key) => key.replaceAll(RegExp(r'[_-]'), ' ')).toList();
    for (final textKey in const <String>[
      'values_text', 'valuesText', 'release_summary', 'releaseSummary',
      'figures_text', 'figuresText', 'details', 'summary', 'description',
    ]) {
      final text = JsonTools.plain(row[textKey], '');
      if (text.isEmpty) continue;
      for (final label in labels) {
        final match = RegExp(
          '${RegExp.escape(label)}\\s*[:=]\\s*([^|;\\n]{1,80})',
          caseSensitive: false,
        ).firstMatch(text);
        if (match == null) continue;
        final candidate = clean(match.group(1));
        if (candidate.isNotEmpty) return candidate;
      }
    }
    return fallback;
  }

  static String _normaliseCalendarKey(String key) =>
      key.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]'), '');

  static String _calendarCurrency(Map<String, dynamic> row) {
    final raw = JsonTools.text(
      row['currency'] ?? row['currency_code'] ?? row['country_code'] ?? row['country'] ?? row['region'],
      'Global',
    ).toUpperCase();
    const aliases = <String, String>{
      'US': 'USD', 'USA': 'USD', 'GB': 'GBP', 'UK': 'GBP', 'EU': 'EUR', 'EMU': 'EUR',
      'JP': 'JPY', 'JAPAN': 'JPY', 'CA': 'CAD', 'CANADA': 'CAD', 'AU': 'AUD',
      'AUSTRALIA': 'AUD', 'NZ': 'NZD', 'NEW ZEALAND': 'NZD', 'CH': 'CHF', 'SWITZERLAND': 'CHF',
    };
    return aliases[raw] ?? raw;
  }

  static void applyPlans(Map<String, dynamic> membership) {
    final rows = JsonTools.mapList(membership['plans']);
    if (rows.isEmpty) return;
    plans = rows.map((row) {
      final id = JsonTools.text(row['slug'] ?? row['id'] ?? row['code'], 'pulse');
      final features = JsonTools.list(row['features']).map((e) => JsonTools.plain(e)).where((e) => e.isNotEmpty).toList();
      final amount = JsonTools.number(row['price'] ?? row['monthly_price'] ?? row['amount'], double.nan);
      return Plan(
        id: id,
        name: JsonTools.text(row['name'], 'Pulse'),
        tagline: JsonTools.text(row['description'], 'Pulse Trading Intelligence membership'),
        prices: amount.isFinite ? <int, double>{30: amount} : <int, double>{},
        features: features.isEmpty ? <String>['Pulse Trading Intelligence'] : features,
        featured: JsonTools.boolean(row['is_next_upgrade'] ?? row['featured']),
      );
    }).toList();
  }

  static double _maybeNumber(Map<String, dynamic> map, List<String> keys) {
    for (final key in keys) {
      if (map.containsKey(key) && map[key] != null && map[key].toString().trim().isNotEmpty) {
        return JsonTools.number(map[key], double.nan);
      }
    }
    return double.nan;
  }

  static double _maybeNestedNumber(Map<String, dynamic> map, List<String> keys) {
    for (final key in keys) {
      if (!map.containsKey(key) || map[key] == null) continue;
      final value = map[key];
      if (value is Map) {
        final nested = JsonTools.map(value);
        final number = _maybeNumber(nested, const <String>['value', 'score', 'index', 'current']);
        if (number.isFinite) return number;
      } else {
        final number = JsonTools.number(value, double.nan);
        if (number.isFinite) return number;
      }
    }
    return double.nan;
  }

  static DateTime? _parseDate(dynamic value) {
    final text = JsonTools.text(value, '');
    if (text.isEmpty) return null;
    return DateTime.tryParse(text.replaceFirst(' ', 'T'));
  }

  static Color _coinColor(String symbol) => switch (symbol.toUpperCase()) {
        'BTC' => const Color(0xFFF7931A),
        'ETH' => const Color(0xFF7B8CFF),
        'SOL' => const Color(0xFF14F195),
        'BNB' => const Color(0xFFF3BA2F),
        'XRP' => const Color(0xFF9AB4D6),
        'ADA' => const Color(0xFF3C7BFF),
        'DOGE' => const Color(0xFFC9A94A),
        'AVAX' => const Color(0xFFE84142),
        'LINK' => const Color(0xFF3A6FF8),
        _ => AppColors.accent,
      };

  static String _coinName(String symbol) => switch (symbol.toUpperCase()) {
        'BTC' => 'Bitcoin',
        'ETH' => 'Ethereum',
        'SOL' => 'Solana',
        'BNB' => 'BNB',
        'XRP' => 'XRP',
        'ADA' => 'Cardano',
        'DOGE' => 'Dogecoin',
        'AVAX' => 'Avalanche',
        'LINK' => 'Chainlink',
        _ => symbol,
      };

  static IconData _sectorIcon(String name) {
    final n = name.toLowerCase();
    if (n.contains('defi')) return Icons.account_balance_outlined;
    if (n.contains('layer')) return Icons.layers_outlined;
    if (n.contains('game')) return Icons.sports_esports_outlined;
    if (n.contains('ai') || n.contains('data')) return Icons.memory_rounded;
    if (n.contains('payment')) return Icons.payments_outlined;
    if (n.contains('nft')) return Icons.image_outlined;
    return Icons.hub_outlined;
  }
}
