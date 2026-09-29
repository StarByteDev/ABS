import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/ads/ad_config.dart';
import '../../core/ads/ad_service.dart';
import '../../core/api_client.dart';
import '../../core/app_config.dart';
import '../../core/json_tools.dart';
import '../../core/session.dart';
import '../theme/app_theme.dart';
import '../widgets/common.dart';

class FreeSignalScreen extends StatefulWidget {
  const FreeSignalScreen({super.key, this.embedded = false});

  final bool embedded;

  @override
  State<FreeSignalScreen> createState() => _FreeSignalScreenState();
}

class _FreeSignalScreenState extends State<FreeSignalScreen> {
  static const _visitorKey = 'abs_public_signal_visitor';

  bool loading = true;
  bool busy = false;
  bool consent = false;
  bool rewardEarned = false;
  String? error;
  String? claimNotice;
  String visitorToken = '';
  String claimToken = '';
  int cooldownSeconds = 0;
  Map<String, dynamic> status = {};
  Map<String, dynamic> signal = {};
  RewardedAd? rewardedAd;
  Timer? timer;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && status.isEmpty) _loadStatus();
  }

  @override
  void dispose() {
    timer?.cancel();
    rewardedAd?.dispose();
    super.dispose();
  }

  Future<void> _loadStatus() async {
    setState(() {
      loading = true;
      error = null;
      claimNotice = null;
      signal = {};
    });
    try {
      final session = SessionScope.of(context);
      visitorToken = await session.storage.read(key: _visitorKey) ?? '';
      final response = await session.api.get(
        '/pulse/free-signal/status',
        query: {if (visitorToken.isNotEmpty) 'visitor_token': visitorToken},
      );
      status = JsonTools.map(
        JsonTools.at(response, 'data', <String, dynamic>{}),
      );
      visitorToken = JsonTools.text(status['visitor_token'], visitorToken);
      if (visitorToken.isNotEmpty) {
        await session.storage.write(key: _visitorKey, value: visitorToken);
      }
      _startCooldown(JsonTools.integer(status['cooldown_seconds']));
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  void _startCooldown(int seconds) {
    timer?.cancel();
    cooldownSeconds = seconds < 0 ? 0 : seconds;
    if (cooldownSeconds <= 0) return;
    timer = Timer.periodic(const Duration(seconds: 1), (value) {
      if (!mounted) return value.cancel();
      setState(() => cooldownSeconds--);
      if (cooldownSeconds <= 0) value.cancel();
    });
  }

  Future<void> _unlock() async {
    if (!consent) {
      snack(
        context,
        'Please confirm that you want to watch a rewarded ad.',
      );
      return;
    }
    if (!Platform.isAndroid && !Platform.isIOS) {
      await _openWebExperience();
      return;
    }
    setState(() {
      busy = true;
      error = null;
      claimNotice = null;
      rewardEarned = false;
      signal = {};
    });
    try {
      final response = await SessionScope.of(context).api.post(
        '/pulse/free-signal/session',
        body: {'visitor_token': visitorToken},
      );
      final data = JsonTools.map(
        JsonTools.at(response, 'data', <String, dynamic>{}),
      );
      visitorToken = JsonTools.text(data['visitor_token'], visitorToken);
      claimToken = JsonTools.text(data['claim_token'], '');
      if (visitorToken.isNotEmpty) {
        await SessionScope.of(context)
            .storage
            .write(key: _visitorKey, value: visitorToken);
      }
      if (claimToken.isEmpty)
        throw const ApiException('ABS could not prepare the reward session.');
      await _loadAndShowAd();
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          busy = false;
          error = e.message;
        });
      }
    }
  }

  Future<void> _loadAndShowAd() async {
    final completer = Completer<void>();
    final unitId = AdConfig.rewardedUnitId;
    RewardedAd.load(
      adUnitId: unitId,
      request: const AdRequest(),
      rewardedAdLoadCallback: RewardedAdLoadCallback(
        onAdLoaded: (ad) {
          rewardedAd = ad;
          ad.fullScreenContentCallback = FullScreenContentCallback(
            onAdShowedFullScreenContent: (_) =>
                AdService.instance.rewardedShowing(),
            onAdDismissedFullScreenContent: (ad) {
              AdService.instance.rewardedDismissed();
              ad.dispose();
              rewardedAd = null;
              if (mounted && !rewardEarned) {
                setState(() {
                  busy = false;
                  error =
                      'The ad was closed before the reward completed. No cooldown was applied.';
                });
              }
            },
            onAdFailedToShowFullScreenContent: (ad, failure) {
              AdService.instance.rewardedFailed();
              ad.dispose();
              rewardedAd = null;
              if (mounted) {
                setState(() {
                  busy = false;
                  error =
                      'The rewarded ad could not be displayed. Please try again.';
                });
              }
            },
          );
          ad.show(
            onUserEarnedReward: (_, reward) async {
              rewardEarned = true;
              await _claim(reward);
            },
          );
          if (!completer.isCompleted) completer.complete();
        },
        onAdFailedToLoad: (failure) {
          if (mounted) {
            setState(() {
              busy = false;
              error =
                  'No rewarded ad is available right now. Please try again shortly.';
            });
          }
          if (!completer.isCompleted) completer.complete();
        },
      ),
    );
    return completer.future;
  }

  Future<void> _claim(RewardItem reward) async {
    try {
      final session = SessionScope.of(context);
      final response = await session.api.post(
        '/pulse/free-signal/claim',
        body: {
          'visitor_token': visitorToken,
          'claim_token': claimToken,
          'reward_type': reward.type,
          'reward_amount': reward.amount,
        },
      );
      final data = JsonTools.map(
        JsonTools.at(response, 'data', <String, dynamic>{}),
      );
      final responseCooldown = JsonTools.integer(
        data['cooldown_seconds'],
        JsonTools.integer(JsonTools.at(response, 'cooldown_seconds')),
      );

      signal = _extractSignalPayload(response);
      if (signal.isNotEmpty) signal = await _hydrateSignalDetails(signal);
      if (responseCooldown > 0) _startCooldown(responseCooldown);

      // ABS can persist the reveal against the visitor session. If a
      // deployment returns the reveal from status instead of directly from
      // /claim, recover it before telling the user that no setup was returned.
      if (signal.isEmpty) {
        final recovered = await _tryRecoverAnySignal();
        if (recovered.isNotEmpty) {
          signal = recovered;
          if (JsonTools.boolean(signal['member_fallback_used'])) {
            claimNotice =
                'Public free flow returned no dedicated setup, so ABS showed your strongest active package signal.';
          } else if (JsonTools.boolean(signal['btc_context_fallback_used'])) {
            claimNotice =
                'No qualified Free Signal or Entry Watch is available, so ABS is showing the latest BTC 4H market context instead.';
          }
        }
      }

      if (signal.isEmpty) {
        claimNotice = 'No public setup is available right now.';
      }

      if (mounted) setState(() => busy = false);
    } on ApiException catch (e) {
      final recovered = await _tryRecoverAnySignal();
      if (mounted) {
        setState(() {
          busy = false;
          if (recovered.isNotEmpty) {
            signal = recovered;
            claimNotice = JsonTools.boolean(recovered['member_fallback_used'])
                ? 'Public free flow returned no dedicated setup, so ABS showed your strongest active package signal.'
                : JsonTools.boolean(recovered['btc_context_fallback_used'])
                    ? 'No qualified Free Signal or Entry Watch is available, so ABS is showing the latest BTC 4H market context instead.'
                    : null;
            error = null;
          } else {
            error = _friendlyFreeSignalMessage(e.message);
          }
        });
      }
    }
  }

  Future<Map<String, dynamic>> _recoverRevealFromStatus() async {
    try {
      final session = SessionScope.of(context);
      final response = await session.api.get(
        '/pulse/free-signal/status',
        query: {if (visitorToken.isNotEmpty) 'visitor_token': visitorToken},
      );
      final data = JsonTools.map(
        JsonTools.at(response, 'data', <String, dynamic>{}),
      );
      if (data.isNotEmpty) status = data;
      visitorToken = JsonTools.text(data['visitor_token'], visitorToken);
      if (visitorToken.isNotEmpty) {
        await session.storage.write(key: _visitorKey, value: visitorToken);
      }
      final seconds = JsonTools.integer(data['cooldown_seconds']);
      if (seconds > 0) _startCooldown(seconds);
      return _extractSignalPayload(response);
    } on ApiException {
      return <String, dynamic>{};
    }
  }

  Future<Map<String, dynamic>> _recoverMemberSignal() async {
    final session = SessionScope.of(context);
    if (!session.authenticated || !session.hasPulseAccess) {
      return <String, dynamic>{};
    }
    try {
      final response = await session.api.get(
        '/pulse/signals/overview',
        query: const {'status': 'active'},
      );
      final data = JsonTools.map(
        JsonTools.at(response, 'data', <String, dynamic>{}),
      );
      final signals = JsonTools.mapList(data['signals']);
      if (signals.isEmpty) return <String, dynamic>{};
      signals.sort((a, b) =>
          JsonTools.number(b['confidence_score'] ?? b['score']).compareTo(
              JsonTools.number(a['confidence_score'] ?? a['score'])));
      var best = _normalizeSignal(signals.first, forceEntryWatch: false);
      best = await _hydrateSignalDetails(best);
      best['member_fallback_used'] = true;
      best['setup_summary'] = JsonTools.text(
        best['setup_summary'],
        'Shown from your active Pulse package because the public Free Signal flow returned no dedicated setup.',
      );
      return best;
    } on ApiException {
      return <String, dynamic>{};
    }
  }

  Future<Map<String, dynamic>> _tryRecoverAnySignal() async {
    final recovered = await _recoverRevealFromStatus();
    if (recovered.isNotEmpty) return recovered;
    final member = await _recoverMemberSignal();
    if (member.isNotEmpty) return member;
    return _recoverBtcContext();
  }

  Future<Map<String, dynamic>> _recoverBtcContext() async {
    try {
      final response = await SessionScope.of(context).api.get(
        '/market/chart/BTCUSDT',
        query: const {'interval': '4h', 'limit': 30},
      );
      final chart = JsonTools.map(
        JsonTools.at(response, 'data', <String, dynamic>{}),
      );
      final candles = JsonTools.mapList(chart['candles']);
      if (candles.isEmpty) return <String, dynamic>{};
      final last = candles.last;
      final first =
          candles.length > 6 ? candles[candles.length - 7] : candles.first;
      final lastClose = JsonTools.number(last['close']);
      final firstClose = JsonTools.number(first['close']);
      if (lastClose <= 0) return <String, dynamic>{};
      final change =
          firstClose > 0 ? ((lastClose - firstClose) / firstClose) * 100 : 0.0;
      final bias = change > .75
          ? 'BULLISH WATCH'
          : change < -.75
              ? 'BEARISH WATCH'
              : 'NEUTRAL WATCH';
      return _normalizeSignal(<String, dynamic>{
        'symbol': 'BTCUSDT',
        'direction': 'WATCH',
        'timeframe': '4H',
        'is_qualified_signal': false,
        'qualified': false,
        'score': 0,
        'current_price': lastClose,
        'candles': candles,
        'entry_price': 0,
        'stop_loss': 0,
        'take_profit': 0,
        'change_percent_24h': change,
        'setup_summary':
            'No qualified Free Signal or Entry Watch is available. ABS is showing BTC 4H market context ($bias) without issuing trade levels.',
        'btc_context_fallback_used': true,
        'market_context_only': true,
        'source': JsonTools.text(chart['source'], 'ABS'),
        'generated_at': chart['updated_at'],
      }, forceEntryWatch: true);
    } on ApiException {
      return <String, dynamic>{};
    }
  }

  String _friendlyFreeSignalMessage(String message) {
    final lower = message.toLowerCase();
    if (lower.contains('no new qualified pulse opportunity') ||
        lower.contains('no qualified pulse opportunity') ||
        lower.contains('no setup qualifies') ||
        lower.contains('no signal available')) {
      return 'No public setup is available right now.';
    }
    return message;
  }

  Map<String, dynamic> _extractSignalPayload(dynamic response) {
    final root = JsonTools.map(response);
    final data = JsonTools.map(root['data']);

    final candidates = <({dynamic value, bool watch})>[
      (value: data['signal'], watch: false),
      (value: data['free_signal'], watch: false),
      (value: data['setup'], watch: false),
      (value: data['best_signal'], watch: false),
      (value: data['best_setup'], watch: false),
      (value: data['entry_watch'], watch: true),
      (value: JsonTools.at(data, 'result.signal'), watch: false),
      (value: JsonTools.at(data, 'result.entry_watch'), watch: true),
      (value: JsonTools.at(data, 'payload.signal'), watch: false),
      (value: JsonTools.at(data, 'payload.entry_watch'), watch: true),
      (value: root['signal'], watch: false),
      (value: root['free_signal'], watch: false),
      (value: root['setup'], watch: false),
      (value: root['best_signal'], watch: false),
      (value: root['best_setup'], watch: false),
      (value: root['entry_watch'], watch: true),
      (value: JsonTools.at(root, 'result.signal'), watch: false),
      (value: JsonTools.at(root, 'result.entry_watch'), watch: true),
      (value: data['result'], watch: false),
      (value: data, watch: false),
      (value: root, watch: false),
    ];

    for (final candidate in candidates) {
      final map = JsonTools.map(candidate.value);
      if (_looksLikeSignal(map)) {
        return _normalizeSignal(map, forceEntryWatch: candidate.watch);
      }
    }
    return <String, dynamic>{};
  }

  bool _looksLikeSignal(Map<String, dynamic> value) {
    if (value.isEmpty) return false;
    final symbol = JsonTools.text(
      value['symbol'],
      JsonTools.text(
        value['pair'],
        JsonTools.text(value['market'], JsonTools.text(value['ticker'], '')),
      ),
    );
    if (symbol.isEmpty || symbol == '—') return false;
    return value.containsKey('direction') ||
        value.containsKey('side') ||
        value.containsKey('entry_price') ||
        value.containsKey('entry') ||
        value.containsKey('confidence_score') ||
        value.containsKey('score');
  }

  dynamic _firstValue(Map<String, dynamic> source, List<String> paths) {
    for (final path in paths) {
      final value = JsonTools.at(source, path);
      if (value == null) continue;
      if (value is String && value.trim().isEmpty) continue;
      return value;
    }
    return null;
  }

  dynamic _priceValue(Map<String, dynamic> source, List<String> paths) {
    final value = _firstValue(source, paths);
    if (value is Map) {
      final map = JsonTools.map(value);
      return _firstValue(map, const ['price', 'value', 'amount', 'level']);
    }
    return value;
  }

  List<dynamic> _targetValues(Map<String, dynamic> raw) {
    final sources = <dynamic>[
      raw['take_profit_levels'],
      raw['targets'],
      raw['take_profits'],
      raw['tp_levels'],
      JsonTools.at(raw, 'levels.take_profit_levels'),
      JsonTools.at(raw, 'levels.targets'),
      JsonTools.at(raw, 'trade_levels.targets'),
    ];
    for (final source in sources) {
      final list = JsonTools.list(source);
      if (list.isEmpty) continue;
      final cleaned = <dynamic>[];
      for (final item in list) {
        if (item is Map) {
          final map = JsonTools.map(item);
          final value =
              _firstValue(map, const ['price', 'value', 'target', 'level']);
          if (JsonTools.number(value) > 0) cleaned.add(value);
        } else if (JsonTools.number(item) > 0) {
          cleaned.add(item);
        }
      }
      if (cleaned.isNotEmpty) return cleaned;
    }
    return <dynamic>[];
  }

  List<Map<String, dynamic>> _normalizedCandles(Map<String, dynamic> raw) {
    final sources = <dynamic>[
      raw['candles'],
      raw['recent_candles'],
      raw['price_context'],
      JsonTools.at(raw, 'chart.candles'),
      JsonTools.at(raw, 'market.candles'),
      JsonTools.at(raw, 'context.candles'),
    ];
    for (final source in sources) {
      final rows = JsonTools.mapList(source);
      if (rows.length < 2) continue;
      final result = <Map<String, dynamic>>[];
      for (final row in rows) {
        final close = JsonTools.number(
          _firstValue(row, const ['close', 'c', 'close_price', 'price']),
        );
        if (close <= 0) continue;
        final open = JsonTools.number(
          _firstValue(row, const ['open', 'o', 'open_price']),
          close,
        );
        final high = JsonTools.number(
          _firstValue(row, const ['high', 'h', 'high_price']),
          open > close ? open : close,
        );
        final low = JsonTools.number(
          _firstValue(row, const ['low', 'l', 'low_price']),
          open < close ? open : close,
        );
        result.add({
          ...row,
          'open': open > 0 ? open : close,
          'high': high > 0 ? high : (open > close ? open : close),
          'low': low > 0 ? low : (open < close ? open : close),
          'close': close,
        });
      }
      if (result.length >= 2) return result;
    }
    return <Map<String, dynamic>>[];
  }

  Map<String, dynamic> _normalizeSignal(
    Map<String, dynamic> raw, {
    required bool forceEntryWatch,
  }) {
    final value = Map<String, dynamic>.from(raw);
    value['symbol'] = JsonTools.text(
      _firstValue(raw, const ['symbol', 'pair', 'market', 'ticker']),
      '',
    );
    value['direction'] = JsonTools.text(
      _firstValue(raw, const ['direction', 'side', 'action', 'bias']),
      'WATCH',
    );
    value['timeframe'] = JsonTools.text(
      _firstValue(raw, const ['timeframe', 'interval', 'tf']),
      '',
    );
    value['confidence_score'] = _firstValue(
      raw,
      const ['confidence_score', 'score', 'confidence', 'signal_score'],
    );

    final candles = _normalizedCandles(raw);
    value['candles'] = candles;

    value['entry_price'] = _priceValue(raw, const [
      'entry_price',
      'entry',
      'entry_reference',
      'entry_ref',
      'levels.entry',
      'levels.entry_price',
      'trade_levels.entry',
      'trade_levels.entry_price',
      'setup.entry',
      'setup.entry_price',
    ]);
    value['current_price'] = _priceValue(raw, const [
      'current_price',
      'price',
      'last_price',
      'mark_price',
      'market_price',
      'ticker_price',
      'market.current_price',
      'market.price',
    ]);
    if (JsonTools.number(value['current_price']) <= 0 && candles.isNotEmpty) {
      value['current_price'] = candles.last['close'];
    }
    value['stop_loss'] = _priceValue(raw, const [
      'stop_loss',
      'sl',
      'protective_stop',
      'stop',
      'levels.stop_loss',
      'levels.sl',
      'trade_levels.stop_loss',
      'trade_levels.sl',
      'risk.stop_loss',
    ]);
    value['take_profit'] = _priceValue(raw, const [
      'take_profit',
      'tp',
      'target',
      'tp1',
      'levels.take_profit',
      'levels.tp',
      'trade_levels.take_profit',
      'trade_levels.tp',
    ]);
    final targets = _targetValues(raw);
    value['take_profit_levels'] = targets;
    if (JsonTools.number(value['take_profit']) <= 0 && targets.isNotEmpty) {
      value['take_profit'] = targets.first;
    }
    value['change_percent_24h'] = _firstValue(raw, const [
      'change_percent_24h',
      'change_24h',
      'price_change_percent',
      'percent_change_24h',
    ]);
    value['is_qualified_signal'] = forceEntryWatch
        ? false
        : JsonTools.boolean(
            raw['is_qualified_signal'],
            JsonTools.boolean(
              raw['qualified'],
              JsonTools.boolean(raw['is_qualified'], true),
            ),
          );
    return value;
  }

  Future<Map<String, dynamic>> _hydrateSignalDetails(
    Map<String, dynamic> base,
  ) async {
    final session = SessionScope.of(context);
    if (!session.authenticated || !session.hasPulseAccess) return base;
    final id = JsonTools.integer(
      _firstValue(base, const ['id', 'signal_id', 'source_signal_id']),
    );
    if (id <= 0) return base;
    try {
      final response = await session.api.get('/pulse/signals/$id');
      final detail = JsonTools.map(
        JsonTools.at(response, 'data', <String, dynamic>{}),
      );
      if (detail.isEmpty) return base;
      final merged = <String, dynamic>{...base, ...detail};
      final forceWatch = !JsonTools.boolean(base['is_qualified_signal'], true);
      final hydrated = _normalizeSignal(merged, forceEntryWatch: forceWatch);
      if (JsonTools.boolean(base['member_fallback_used'])) {
        hydrated['member_fallback_used'] = true;
      }
      if (JsonTools.boolean(base['btc_context_fallback_used'])) {
        hydrated['btc_context_fallback_used'] = true;
      }
      return hydrated;
    } on ApiException {
      return base;
    }
  }

  double _validPrice(dynamic value) {
    final n = JsonTools.number(value, double.nan);
    return n.isFinite && n > 0 ? n : 0;
  }

  String _priceText(dynamic value) {
    final n = _validPrice(value);
    if (n <= 0) return '—';
    final abs = n.abs();
    final digits = abs >= 1000
        ? 2
        : abs >= 1
            ? 4
            : abs >= .01
                ? 6
                : abs >= .0001
                    ? 8
                    : 10;
    var text = n.toStringAsFixed(digits);
    if (text.contains('.')) {
      text = text
          .replaceFirst(RegExp(r'0+$'), '')
          .replaceFirst(RegExp(r'\.$'), '');
    }
    return '\$$text';
  }

  Future<void> _openWebExperience() async {
    final value = JsonTools.text(
      status['free_signal_page_url'],
      '${AppConfig.website}/pulse/free-signal',
    );
    await launchUrl(Uri.parse(value), mode: LaunchMode.externalApplication);
  }

  Future<void> _shareSignal() async {
    if (signal.isEmpty) return;
    final targets = JsonTools.list(signal['take_profit_levels'])
        .asMap()
        .entries
        .map((entry) => 'TP${entry.key + 1} ${entry.value}')
        .join(' · ');
    final text = <String>[
      'Pulse ${JsonTools.boolean(signal['is_qualified_signal'], true) ? 'Free Signal' : 'Entry Watch'} — ${JsonTools.text(signal['symbol'])} ${JsonTools.text(signal['direction'])} (${JsonTools.text(signal['timeframe'])})',
      'Entry ${_priceText(signal['entry_price'])} · SL ${_priceText(signal['stop_loss'])} · ${targets.isEmpty ? 'TP ${_priceText(signal['take_profit'])}' : targets}',
      'Confidence ${JsonTools.integer(signal['confidence_score'])}/100',
      'Market intelligence for decision support — not financial advice or a guarantee of profit.',
      '${AppConfig.website}/pulse/free-signal',
    ].join('\n');
    await Share.share(
      text,
      subject: 'Pulse · ${JsonTools.text(signal['symbol'])}',
    );
  }

  @override
  Widget build(BuildContext context) {
    final body = loading
        ? const Center(
            child: Padding(
            padding: EdgeInsets.all(36),
            child: CircularProgressIndicator(),
          ))
        : RefreshIndicator(
            color: AppColors.accent,
            backgroundColor: AppColors.surface,
            onRefresh: _loadStatus,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
              children: signal.isEmpty ? _gatewayTemplate() : _revealTemplate(),
            ),
          );

    return Scaffold(
      appBar: AppBar(
        title: const Row(
          children: <Widget>[
            AbsLogo(size: 34),
            SizedBox(width: 10),
            Text('Free Signal'),
          ],
        ),
        actions: <Widget>[
          IconButton(
            tooltip: 'Refresh',
            onPressed: busy ? null : _loadStatus,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: body,
    );
  }

  List<Widget> _gatewayTemplate() {
    final enabled = JsonTools.boolean(status['enabled'], true);
    final available =
        JsonTools.boolean(status['available'], cooldownSeconds <= 0) &&
            cooldownSeconds <= 0;
    final statusText = !enabled
        ? 'PAUSED'
        : available
            ? 'READY'
            : 'AVAILABLE IN ${_clock(cooldownSeconds)}';
    final statusColor = available
        ? AppColors.up
        : enabled
            ? AppColors.amber
            : AppColors.muted;
    return <Widget>[
      Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: fade(AppColors.gold, .36)),
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: <Color>[fade(AppColors.gold, .17), AppColors.surface],
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                Container(
                  width: 50,
                  height: 50,
                  decoration: BoxDecoration(
                    color: fade(AppColors.gold, .12),
                    borderRadius: BorderRadius.circular(15),
                    border: Border.all(color: fade(AppColors.gold, .35)),
                  ),
                  child: const Icon(Icons.play_circle_fill_rounded,
                      color: AppColors.gold, size: 30),
                ),
                const SizedBox(width: 13),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text('ABS PULSE · REWARDED ACCESS', style: AppText.label),
                      SizedBox(height: 4),
                      Text('Reveal today\'s best available public setup',
                          style: AppText.h2),
                    ],
                  ),
                ),
                Pill(statusText, color: statusColor),
              ],
            ),
            const SizedBox(height: 14),
            const Text(
              'ABS first checks for a qualified Free Signal. If none is available it may show Entry Watch, an eligible active-package setup, or BTC 4H market context without inventing trade levels.',
              style: TextStyle(color: AppColors.muted, height: 1.48),
            ),
            if (claimNotice != null && claimNotice!.isNotEmpty) ...<Widget>[
              const SizedBox(height: 12),
              _notice(claimNotice!, AppColors.amber),
            ],
            if (error != null && error!.isNotEmpty) ...<Widget>[
              const SizedBox(height: 12),
              _notice(error!, AppColors.down),
            ],
            const SizedBox(height: 14),
            InkWell(
              onTap: busy ? null : () => setState(() => consent = !consent),
              borderRadius: BorderRadius.circular(12),
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 3),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Checkbox(
                      value: consent,
                      onChanged: busy
                          ? null
                          : (value) => setState(() => consent = value ?? false),
                      activeColor: AppColors.accent,
                    ),
                    const SizedBox(width: 2),
                    const Expanded(
                      child: Padding(
                        padding: EdgeInsets.only(top: 11),
                        child: Text(
                          'I want to watch a rewarded ad to reveal the Free Signal. No purchase is required.',
                          style: TextStyle(
                              color: AppColors.muted,
                              fontSize: 12.5,
                              height: 1.4),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 10),
            PrimaryButton(
              label: busy
                  ? 'Preparing rewarded ad…'
                  : available
                      ? 'Watch ad & reveal'
                      : statusText,
              icon: Icons.play_arrow_rounded,
              onPressed: enabled && available && !busy ? _unlock : null,
            ),
          ],
        ),
      ),
      const SizedBox(height: 16),
      const SectionTitle('How Free Signal works'),
      AbsCard(
        child: Column(
          children: const <Widget>[
            _FlowRow(
                number: '1',
                title: 'ABS checks current setups',
                text:
                    'The live backend evaluates available Pulse opportunities.'),
            Divider(height: 22),
            _FlowRow(
                number: '2',
                title: 'Watch one rewarded ad',
                text:
                    'Reward verification is handled through the production ad flow.'),
            Divider(height: 22),
            _FlowRow(
                number: '3',
                title: 'Review, don\'t blindly follow',
                text:
                    'Check signal quality, levels and context before any decision.'),
          ],
        ),
      ),
      const SizedBox(height: 18),
      const RiskNotice(),
    ];
  }

  List<Widget> _revealTemplate() {
    final qualified = JsonTools.boolean(signal['is_qualified_signal'], true);
    final contextOnly = JsonTools.boolean(signal['market_context_only']) ||
        JsonTools.boolean(signal['btc_context_fallback_used']);
    final symbol = JsonTools.text(signal['symbol'], 'BTCUSDT').toUpperCase();
    final pair = symbol.contains('/')
        ? symbol
        : symbol.replaceFirst(RegExp(r'USDT$'), '/USDT');
    final direction =
        JsonTools.text(signal['direction'], contextOnly ? 'WATCH' : '—')
            .toUpperCase();
    final timeframe = JsonTools.text(signal['timeframe'], '—').toUpperCase();
    final score =
        JsonTools.integer(signal['confidence_score'] ?? signal['score'])
            .clamp(0, 100)
            .toInt();
    final current = _validPrice(signal['current_price']);
    final entry = _validPrice(signal['entry_price']);
    final stop = _validPrice(signal['stop_loss']);
    final targets = JsonTools.list(signal['take_profit_levels'])
        .map(_validPrice)
        .where((v) => v > 0)
        .toList();
    final singleTarget = _validPrice(signal['take_profit']);
    if (targets.isEmpty && singleTarget > 0) targets.add(singleTarget);
    final hasTradeLevels = entry > 0 && stop > 0 && targets.isNotEmpty;
    final directionColor =
        direction.contains('LONG') || direction.contains('BULL')
            ? AppColors.up
            : direction.contains('SHORT') || direction.contains('BEAR')
                ? AppColors.down
                : AppColors.amber;
    final summary = JsonTools.plain(
      signal['setup_summary'] ??
          signal['reasoning'] ??
          signal['summary'] ??
          signal['analysis'],
      contextOnly
          ? 'Market context only. ABS has not issued Entry, Stop Loss or Take Profit levels.'
          : qualified
              ? 'Review the setup and risk levels before making any decision.'
              : 'Entry Watch only. Wait for stronger confirmation before treating this as a trade setup.',
    );

    return <Widget>[
      Row(
        children: <Widget>[
          Expanded(
              child: Text('Your Pulse reveal',
                  style: const TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                      color: AppColors.text))),
          Pill(
              contextOnly
                  ? 'CONTEXT'
                  : qualified
                      ? 'QUALIFIED'
                      : 'ENTRY WATCH',
              color: contextOnly
                  ? AppColors.amber
                  : qualified
                      ? AppColors.up
                      : AppColors.gold),
        ],
      ),
      const SizedBox(height: 12),
      AbsCard(
        borderColor: fade(directionColor, .45),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                      color: fade(directionColor, .13),
                      borderRadius: BorderRadius.circular(14)),
                  child: Icon(
                      contextOnly
                          ? Icons.visibility_outlined
                          : Icons.bolt_rounded,
                      color: directionColor),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(pair,
                          style: const TextStyle(
                              fontWeight: FontWeight.w800, fontSize: 18)),
                      const SizedBox(height: 3),
                      Text(
                          '$timeframe · ${contextOnly ? 'Market context' : direction}',
                          style: AppText.muted),
                    ],
                  ),
                ),
                if (!contextOnly) _Confidence(value: score),
              ],
            ),
            const SizedBox(height: 14),
            Text(summary,
                style: const TextStyle(color: AppColors.muted, height: 1.48)),
            if (claimNotice != null && claimNotice!.isNotEmpty) ...<Widget>[
              const SizedBox(height: 12),
              _notice(claimNotice!, AppColors.amber),
            ],
            const Divider(height: 28),
            Row(
              children: <Widget>[
                Expanded(
                    child: MiniStat(
                        label: 'Market price', value: _priceText(current))),
                Expanded(
                    child: MiniStat(
                        label: 'Direction',
                        value: direction,
                        valueColor: directionColor)),
                Expanded(child: MiniStat(label: 'Timeframe', value: timeframe)),
              ],
            ),
          ],
        ),
      ),
      const SizedBox(height: 12),
      if (hasTradeLevels)
        AbsCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              const Text('Trade levels', style: AppText.h2),
              const SizedBox(height: 12),
              Row(
                children: <Widget>[
                  Expanded(
                      child:
                          MiniStat(label: 'Entry', value: _priceText(entry))),
                  Expanded(
                      child: MiniStat(
                          label: 'Stop',
                          value: _priceText(stop),
                          valueColor: AppColors.down)),
                  Expanded(
                      child: MiniStat(
                          label: 'Target 1',
                          value: _priceText(targets.first),
                          valueColor: AppColors.up)),
                ],
              ),
              if (targets.length > 1) ...<Widget>[
                const SizedBox(height: 14),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: <Widget>[
                    for (int i = 0; i < targets.length; i++)
                      Pill('TP${i + 1} ${_priceText(targets[i])}',
                          color: AppColors.up),
                  ],
                ),
              ],
            ],
          ),
        )
      else
        AbsCard(
          borderColor: fade(AppColors.amber, .35),
          child: const Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Icon(Icons.info_outline_rounded, color: AppColors.amber),
              SizedBox(width: 11),
              Expanded(
                child: Text(
                  'No Entry, Stop Loss or Take Profit levels were issued for this result. ABS will not fabricate them. Treat this as market context/watch information only.',
                  style: TextStyle(color: AppColors.muted, height: 1.45),
                ),
              ),
            ],
          ),
        ),
      const SizedBox(height: 14),
      Row(
        children: <Widget>[
          Expanded(
              child: FilledButton.icon(
                  onPressed: contextOnly ? null : _shareSignal,
                  icon: const Icon(Icons.ios_share_rounded),
                  label: const Text('Share'))),
          const SizedBox(width: 10),
          Expanded(
              child: OutlinedButton.icon(
                  onPressed: _loadStatus,
                  icon: const Icon(Icons.close_rounded),
                  label: const Text('Hide'))),
        ],
      ),
      const SizedBox(height: 12),
      Text(
        cooldownSeconds > 0
            ? 'Next free unlock: ${_clock(cooldownSeconds)}'
            : 'Refresh to check the next available Free Signal.',
        textAlign: TextAlign.center,
        style: AppText.muted,
      ),
      const SizedBox(height: 18),
      const RiskNotice(),
    ];
  }

  Widget _notice(String text, Color color) => Container(
        width: double.infinity,
        padding: const EdgeInsets.all(11),
        decoration: BoxDecoration(
          color: fade(color, .08),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: fade(color, .22)),
        ),
        child: Text(text,
            style: TextStyle(color: color, fontSize: 12, height: 1.4)),
      );

  static String _clock(int seconds) {
    if (seconds <= 0) return '00:00';
    final minutes = seconds ~/ 60;
    final remain = seconds % 60;
    return '${minutes.toString().padLeft(2, '0')}:${remain.toString().padLeft(2, '0')}';
  }
}

class _FlowRow extends StatelessWidget {
  const _FlowRow(
      {required this.number, required this.title, required this.text});
  final String number;
  final String title;
  final String text;

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            width: 30,
            height: 30,
            alignment: Alignment.center,
            decoration: BoxDecoration(
                color: fade(AppColors.accent, .14), shape: BoxShape.circle),
            child: Text(number,
                style: const TextStyle(
                    color: AppColors.accent, fontWeight: FontWeight.w800)),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(title,
                    style: const TextStyle(fontWeight: FontWeight.w700)),
                const SizedBox(height: 3),
                Text(text, style: AppText.muted.copyWith(height: 1.4)),
              ],
            ),
          ),
        ],
      );
}

class _Confidence extends StatelessWidget {
  const _Confidence({required this.value});
  final int value;

  @override
  Widget build(BuildContext context) => Container(
        width: 48,
        height: 48,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(color: fade(AppColors.accent, .55), width: 3),
        ),
        child: Text('$value',
            style: const TextStyle(
                fontWeight: FontWeight.w800, color: AppColors.accent)),
      );
}
