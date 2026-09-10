import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api_client.dart';
import '../core/app_config.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';
import 'signals_screen.dart';

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
      showSnack(
        context,
        'Please confirm that you want to watch a rewarded ad.',
        error: true,
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
        await SessionScope.of(context).storage
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
    final unitId = Platform.isIOS
        ? AppConfig.rewardedAdUnitIos
        : AppConfig.rewardedAdUnitAndroid;
    RewardedAd.load(
      adUnitId: unitId,
      request: const AdRequest(),
      rewardedAdLoadCallback: RewardedAdLoadCallback(
        onAdLoaded: (ad) {
          rewardedAd = ad;
          ad.fullScreenContentCallback = FullScreenContentCallback(
            onAdDismissedFullScreenContent: (ad) {
              ad.dispose();
              rewardedAd = null;
              if (mounted && !rewardEarned) {
                setState(() {
                  busy = false;
                  error = 'The ad was closed before the reward completed. No cooldown was applied.';
                });
              }
            },
            onAdFailedToShowFullScreenContent: (ad, failure) {
              ad.dispose();
              rewardedAd = null;
              if (mounted) {
                setState(() {
                  busy = false;
                  error = 'The rewarded ad could not be displayed. Please try again.';
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
              error = 'No rewarded ad is available right now. Please try again shortly.';
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

      // V15.1.6 can persist the reveal against the visitor session. If a
      // deployment returns the reveal from status instead of directly from
      // /claim, recover it before telling the user that no setup was returned.
      if (signal.isEmpty) {
        final recovered = await _tryRecoverAnySignal();
        if (recovered.isNotEmpty) {
          signal = recovered;
          if (JsonTools.boolean(signal['member_fallback_used'])) {
            claimNotice = 'Public free flow returned no dedicated setup, so ABS showed your strongest active package signal.';
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
      signals.sort((a, b) => JsonTools.number(b['confidence_score'] ?? b['score'])
          .compareTo(JsonTools.number(a['confidence_score'] ?? a['score'])));
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
    return _recoverMemberSignal();
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
          final value = _firstValue(map, const ['price', 'value', 'target', 'level']);
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
      text = text.replaceFirst(RegExp(r'0+$'), '').replaceFirst(RegExp(r'\.$'), '');
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
      'ABS Pulse ${JsonTools.boolean(signal['is_qualified_signal'], true) ? 'Free Signal' : 'Entry Watch'} — ${JsonTools.text(signal['symbol'])} ${JsonTools.text(signal['direction'])} (${JsonTools.text(signal['timeframe'])})',
      'Entry ${_priceText(signal['entry_price'])} · SL ${_priceText(signal['stop_loss'])} · ${targets.isEmpty ? 'TP ${_priceText(signal['take_profit'])}' : targets}',
      'Confidence ${number(signal['confidence_score'], digits: 0)}/100',
      'Market intelligence for decision support — not financial advice or a guarantee of profit.',
      '${AppConfig.website}/pulse/free-signal',
    ].join('\n');
    await Share.share(
      text,
      subject: 'ABS Pulse · ${JsonTools.text(signal['symbol'])}',
    );
  }

  @override
  Widget build(BuildContext context) {
    final content = loading
        ? const LoadingBlock(label: 'Checking free-signal availability...')
        : error != null && signal.isEmpty
        ? ListView(
            padding: EdgeInsets.fromLTRB(16, 16, 16, widget.embedded ? 110 : 28),
            children: [
              ErrorBlock(message: error!, onRetry: _loadStatus),
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: () {
                  final session = SessionScope.of(context);
                  if (session.authenticated && session.hasPulseAccess) {
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const SignalsScreen()),
                    );
                  } else {
                    _openWebExperience();
                  }
                },
                child: Text(
                  (SessionScope.of(context).authenticated &&
                          SessionScope.of(context).hasPulseAccess)
                      ? 'Open Trade Signals'
                      : 'Open web Free Signal',
                ),
              ),
            ],
          )
        : ListView(
            padding: EdgeInsets.fromLTRB(16, 12, 16, widget.embedded ? 110 : 32),
            children: signal.isEmpty ? _gateway() : _reveal(),
          );

    if (widget.embedded) {
      return Scaffold(
        backgroundColor: Colors.transparent,
        body: AbsBackground(child: SafeArea(child: content)),
      );
    }
    return AbsPage(
      title: 'Free Signal',
      subtitle: 'One rewarded ad · no account required',
      actions: [
        IconButton(
          onPressed: busy ? null : _loadStatus,
          icon: const Icon(Icons.refresh),
        ),
      ],
      child: content,
    );
  }

  List<Widget> _gateway() {
    final enabled = JsonTools.boolean(status['enabled']);
    final available =
        JsonTools.boolean(status['available']) && cooldownSeconds <= 0;
    final availabilityLabel = available
        ? 'READY'
        : enabled
            ? _clock(cooldownSeconds)
            : 'PAUSED';

    return [
      _RewardedAccessCard(
        consent: consent,
        enabled: enabled,
        available: available,
        busy: busy,
        availabilityLabel: availabilityLabel,
        notice: claimNotice,
        onConsentChanged: (value) => setState(() => consent = value),
        onUnlock: _unlock,
      ),
      const SizedBox(height: 10),
      const Text(
        'Market intelligence only. Not financial advice, a recommendation, or a guarantee of profit.',
        textAlign: TextAlign.center,
        style: TextStyle(color: AbsColors.muted2, fontSize: 9.8, height: 1.35),
      ),
    ];
  }

  List<Widget> _reveal() {
    final qualified = JsonTools.boolean(signal['is_qualified_signal'], true);
    final direction = JsonTools.text(signal['direction']).toUpperCase();
    final directionColor = direction == 'LONG'
        ? AbsColors.green
        : direction == 'SHORT'
            ? AbsColors.red
            : AbsColors.gold;
    final candles = JsonTools.mapList(signal['candles']);
    final targets = JsonTools.list(signal['take_profit_levels'])
        .where((value) => _validPrice(value) > 0)
        .toList();
    final strategies = JsonTools.list(signal['strategies'])
        .map((value) => value.toString())
        .where((value) => value.isNotEmpty)
        .toList();
    final current = _validPrice(signal['current_price']);
    final entry = _validPrice(signal['entry_price']);
    final stop = _validPrice(signal['stop_loss']);
    final singleTarget = _validPrice(signal['take_profit']);
    final firstTarget = targets.isNotEmpty
        ? _validPrice(targets.first)
        : singleTarget;
    final hasTradeLevels = entry > 0 && stop > 0 && firstTarget > 0;
    final score = JsonTools.number(signal['confidence_score']);
    final change24 = JsonTools.number(signal['change_percent_24h'], double.nan);
    final timeframe = JsonTools.text(signal['timeframe']).toUpperCase();

    return [
      PremiumHeroCard(
        eyebrow: qualified ? 'QUALIFIED PULSE SIGNAL' : 'ENTRY WATCH',
        title: '${JsonTools.text(signal['symbol'])} · $direction',
        message: qualified
            ? 'Qualified setup ready for review.'
            : 'Strongest current setup, still below the qualification threshold.',
        trailing: Container(
          width: 62,
          height: 62,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: directionColor.withValues(alpha: .12),
            borderRadius: BorderRadius.circular(19),
            border: Border.all(color: directionColor.withValues(alpha: .28)),
          ),
          child: Text(
            number(score, digits: 0),
            style: TextStyle(
              color: directionColor,
              fontWeight: FontWeight.w900,
              fontSize: 20,
            ),
          ),
        ),
        footer: Wrap(
          spacing: 7,
          runSpacing: 7,
          children: [
            StatusChip(
              direction,
              good: direction == 'LONG'
                  ? true
                  : direction == 'SHORT'
                      ? false
                      : null,
            ),
            if (timeframe.isNotEmpty && timeframe != '—') StatusChip(timeframe),
            StatusChip(
              qualified ? 'QUALIFIED' : 'WATCH ONLY',
              good: qualified,
              warning: !qualified,
            ),
            if (JsonTools.boolean(signal['member_fallback_used']))
              const StatusChip('ACTIVE PACKAGE', good: true),
          ],
        ),
      ),
      const SizedBox(height: 12),
      AbsCard(
        accent: qualified ? AbsColors.green : AbsColors.gold,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: (qualified ? AbsColors.green : AbsColors.gold)
                    .withValues(alpha: .10),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(
                qualified ? Icons.fact_check_outlined : Icons.visibility_outlined,
                color: qualified ? AbsColors.green : AbsColors.goldSoft,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    qualified ? 'REVIEW THE SETUP' : 'WATCH ONLY',
                    style: TextStyle(
                      color: qualified ? AbsColors.green : AbsColors.goldSoft,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w900,
                      letterSpacing: 1,
                    ),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    qualified
                        ? 'Check Entry, Stop Loss and Take Profit below before making any decision.'
                        : 'This is not a qualified signal yet. Monitor it and wait for stronger confirmation.',
                    style: const TextStyle(
                      color: AbsColors.text,
                      fontSize: 12.2,
                      height: 1.4,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      if (candles.length > 1) ...[
        const SizedBox(height: 12),
        AbsCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'MARKET CONTEXT',
                          style: TextStyle(
                            color: AbsColors.muted,
                            fontSize: 10,
                            fontWeight: FontWeight.w900,
                            letterSpacing: 1,
                          ),
                        ),
                        SizedBox(height: 3),
                        Text(
                          'Candles + price line',
                          style: TextStyle(color: AbsColors.muted2, fontSize: 9.5),
                        ),
                      ],
                    ),
                  ),
                  if (current > 0)
                    Text(
                      _priceText(current),
                      style: TextStyle(
                        color: directionColor,
                        fontWeight: FontWeight.w900,
                        fontSize: 13,
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 12),
              SizedBox(
                height: 178,
                child: CustomPaint(
                  painter: _PulseMarketPainter(
                    candles: candles,
                    lineColor: directionColor,
                    entry: entry,
                    stop: stop,
                    target: firstTarget,
                  ),
                  child: const SizedBox.expand(),
                ),
              ),
              if (hasTradeLevels) ...[
                const SizedBox(height: 9),
                const Wrap(
                  spacing: 12,
                  runSpacing: 6,
                  children: [
                    _ChartLegendDot(label: 'Entry', color: AbsColors.gold),
                    _ChartLegendDot(label: 'Stop', color: AbsColors.red),
                    _ChartLegendDot(label: 'Target', color: AbsColors.green),
                  ],
                ),
              ],
            ],
          ),
        ),
      ],
      const SizedBox(height: 12),
      AbsCard(
        accent: directionColor,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              qualified ? 'TRADE PLAN' : 'SETUP SNAPSHOT',
              style: const TextStyle(
                color: AbsColors.muted,
                fontSize: 10,
                fontWeight: FontWeight.w900,
                letterSpacing: .9,
              ),
            ),
            const SizedBox(height: 6),
            KeyValueRow('Current market price', _priceText(current)),
            KeyValueRow('Signal score', '${number(score, digits: 0)}/100'),
            if (timeframe.isNotEmpty && timeframe != '—')
              KeyValueRow('Timeframe', timeframe),
            if (entry > 0) KeyValueRow('Entry', _priceText(entry)),
            if (stop > 0)
              KeyValueRow(
                'Stop Loss',
                _priceText(stop),
                valueColor: AbsColors.red,
              ),
            if (targets.isEmpty && firstTarget > 0)
              KeyValueRow(
                'Take Profit',
                _priceText(firstTarget),
                valueColor: AbsColors.green,
              )
            else
              ...targets.asMap().entries.map(
                    (target) => KeyValueRow(
                      'Take Profit ${target.key + 1}',
                      _priceText(target.value),
                      valueColor: AbsColors.green,
                    ),
                  ),
            if (!hasTradeLevels) ...[
              const SizedBox(height: 6),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(11),
                decoration: BoxDecoration(
                  color: AbsColors.gold.withValues(alpha: .07),
                  borderRadius: BorderRadius.circular(13),
                  border: Border.all(color: AbsColors.gold.withValues(alpha: .18)),
                ),
                child: const Text(
                  'Entry, Stop Loss and Take Profit have not been issued for this setup yet.',
                  style: TextStyle(
                    color: AbsColors.goldSoft,
                    fontSize: 10.8,
                    height: 1.4,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
            if (change24.isFinite && change24 != 0)
              KeyValueRow(
                '24h change',
                percent(change24),
                valueColor: pnlColor(change24),
              ),
          ],
        ),
      ),
      if (strategies.isNotEmpty) ...[
        const SizedBox(height: 12),
        AbsCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'STRATEGY EVIDENCE',
                style: TextStyle(
                  color: AbsColors.muted,
                  fontSize: 10,
                  fontWeight: FontWeight.w900,
                  letterSpacing: .8,
                ),
              ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 7,
                runSpacing: 7,
                children: strategies
                    .map((value) => StatusChip(value.toUpperCase()))
                    .toList(),
              ),
            ],
          ),
        ),
      ],
      const SizedBox(height: 14),
      Row(
        children: [
          Expanded(
            child: ElevatedButton.icon(
              onPressed: _shareSignal,
              icon: const Icon(Icons.ios_share_rounded),
              label: const Text('Share'),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: OutlinedButton.icon(
              onPressed: _loadStatus,
              icon: const Icon(Icons.close_rounded),
              label: const Text('Hide'),
            ),
          ),
        ],
      ),
      const SizedBox(height: 12),
      Text(
        'Next free unlock: ${_clock(cooldownSeconds)}',
        textAlign: TextAlign.center,
        style: const TextStyle(color: AbsColors.muted, fontSize: 10.5),
      ),
    ];
  }

  static String _clock(int seconds) {
    if (seconds <= 0) return '00:00';
    final minutes = seconds ~/ 60;
    final remain = seconds % 60;
    return '${minutes.toString().padLeft(2, '0')}:${remain.toString().padLeft(2, '0')}';
  }
}

class _RewardedAccessCard extends StatelessWidget {
  const _RewardedAccessCard({
    required this.consent,
    required this.enabled,
    required this.available,
    required this.busy,
    required this.availabilityLabel,
    required this.notice,
    required this.onConsentChanged,
    required this.onUnlock,
  });

  final bool consent;
  final bool enabled;
  final bool available;
  final bool busy;
  final String availabilityLabel;
  final String? notice;
  final ValueChanged<bool> onConsentChanged;
  final VoidCallback onUnlock;

  @override
  Widget build(BuildContext context) {
    final canUnlock = enabled && available && !busy;
    final statusColor = available
        ? AbsColors.green
        : enabled
            ? AbsColors.gold
            : AbsColors.muted;
    final buttonLabel = busy
        ? 'Preparing Rewarded Ad...'
        : available
            ? 'Watch Ad & Reveal Signal'
            : enabled
                ? 'Available in $availabilityLabel'
                : 'Free Signal Paused';

    return Container(
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [Color(0xFF111827), Color(0xFF0D1521), Color(0xFF0A111B)],
        ),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AbsColors.gold.withValues(alpha: .52), width: 1.1),
        boxShadow: const [
          BoxShadow(color: Color(0x38000000), blurRadius: 34, offset: Offset(0, 18)),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(24),
        child: Stack(
          children: [
            const Positioned.fill(child: _GatewayGlow()),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 18, 20, 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Expanded(
                        child: Text(
                          'ABS PULSE · REWARDED ACCESS',
                          style: TextStyle(
                            color: AbsColors.goldSoft,
                            fontSize: 10.2,
                            fontWeight: FontWeight.w900,
                            letterSpacing: 1.55,
                          ),
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                        decoration: BoxDecoration(
                          color: statusColor.withValues(alpha: .08),
                          borderRadius: BorderRadius.circular(999),
                          border: Border.all(color: statusColor.withValues(alpha: .26)),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              width: 6,
                              height: 6,
                              decoration: BoxDecoration(color: statusColor, shape: BoxShape.circle),
                            ),
                            const SizedBox(width: 5),
                            Text(
                              availabilityLabel,
                              style: TextStyle(
                                color: statusColor,
                                fontSize: 8.5,
                                fontWeight: FontWeight.w900,
                                letterSpacing: .4,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Center(
                    child: _RewardPulseVisual(
                      active: canUnlock,
                      busy: busy,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Watch one ad. Unlock one Pulse setup.',
                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                          fontSize: 28,
                          fontWeight: FontWeight.w900,
                          letterSpacing: -.85,
                          height: 1.16,
                        ),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Complete one rewarded ad to reveal the strongest available setup. If nothing qualifies, ABS can show the best Entry Watch instead.',
                    style: TextStyle(
                      color: Color(0xFFAAB8CB),
                      fontSize: 13.2,
                      height: 1.48,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                  const SizedBox(height: 18),
                  InkWell(
                    borderRadius: BorderRadius.circular(17),
                    onTap: busy ? null : () => onConsentChanged(!consent),
                    child: Container(
                      padding: const EdgeInsets.fromLTRB(14, 13, 14, 13),
                      decoration: BoxDecoration(
                        color: const Color(0xFF111B28).withValues(alpha: .92),
                        borderRadius: BorderRadius.circular(17),
                        border: Border.all(
                          color: consent
                              ? AbsColors.gold.withValues(alpha: .50)
                              : const Color(0xFF36506A).withValues(alpha: .70),
                        ),
                      ),
                      child: Row(
                        children: [
                          SizedBox(
                            width: 42,
                            height: 42,
                            child: Checkbox(
                              value: consent,
                              onChanged: busy ? null : (value) => onConsentChanged(value ?? false),
                              activeColor: AbsColors.gold,
                              checkColor: const Color(0xFF10151D),
                              side: const BorderSide(color: Color(0xFF7890A8), width: 1.5),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7)),
                            ),
                          ),
                          const SizedBox(width: 8),
                          const Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Risk confirmation',
                                  style: TextStyle(fontSize: 12.6, fontWeight: FontWeight.w900),
                                ),
                                SizedBox(height: 4),
                                Text(
                                  'I agree to the Risk Disclosure.',
                                  style: TextStyle(
                                    color: Color(0xFFB0C0D2),
                                    fontSize: 11.1,
                                    height: 1.35,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  if (notice != null && notice!.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AbsColors.gold.withValues(alpha: .07),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: AbsColors.gold.withValues(alpha: .22)),
                      ),
                      child: Text(
                        notice!,
                        style: const TextStyle(
                          color: AbsColors.goldSoft,
                          fontSize: 10.7,
                          height: 1.4,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: 16),
                  _GoldUnlockButton(
                    enabled: canUnlock && consent,
                    busy: busy,
                    label: buttonLabel,
                    onPressed: onUnlock,
                  ),
                  const SizedBox(height: 10),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.lock_outline_rounded, color: AbsColors.muted2, size: 12),
                      const SizedBox(width: 5),
                      Flexible(
                        child: Text(
                          available
                              ? '1 ad · 30-minute reset'
                              : enabled
                                  ? 'Next free reveal unlocks after the server cooldown.'
                                  : 'Rewarded Free Signal is currently paused by ABS.',
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: AbsColors.muted2,
                            fontSize: 9.5,
                            height: 1.3,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _GatewayGlow extends StatelessWidget {
  const _GatewayGlow();

  @override
  Widget build(BuildContext context) => IgnorePointer(
        child: DecoratedBox(
          decoration: BoxDecoration(
            gradient: RadialGradient(
              center: const Alignment(0, -.46),
              radius: .68,
              colors: [
                AbsColors.gold.withValues(alpha: .075),
                AbsColors.cyan.withValues(alpha: .025),
                Colors.transparent,
              ],
              stops: const [0, .48, 1],
            ),
          ),
        ),
      );
}

class _RewardPulseVisual extends StatefulWidget {
  const _RewardPulseVisual({required this.active, required this.busy});

  final bool active;
  final bool busy;

  @override
  State<_RewardPulseVisual> createState() => _RewardPulseVisualState();
}

class _RewardPulseVisualState extends State<_RewardPulseVisual>
    with SingleTickerProviderStateMixin {
  late final AnimationController controller;

  @override
  void initState() {
    super.initState();
    controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 5200),
    )..repeat();
  }

  @override
  void dispose() {
    controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => SizedBox(
        width: 230,
        height: 220,
        child: AnimatedBuilder(
          animation: controller,
          builder: (context, child) {
            final pulse = 1 - ((controller.value * 2) - 1).abs();
            return Stack(
              alignment: Alignment.center,
              children: [
                Transform.rotate(
                  angle: controller.value * 6.283185307179586,
                  child: _OrbitRing(
                    width: 132,
                    height: 205,
                    color: AbsColors.gold.withValues(alpha: .26),
                  ),
                ),
                Transform.rotate(
                  angle: -controller.value * 4.71238898038469,
                  child: _OrbitRing(
                    width: 184,
                    height: 122,
                    color: AbsColors.cyanSoft.withValues(alpha: .18),
                  ),
                ),
                Transform.rotate(
                  angle: controller.value * 3.141592653589793,
                  child: _OrbitRing(
                    width: 164,
                    height: 146,
                    color: const Color(0xFF6E9DC3).withValues(alpha: .19),
                  ),
                ),
                Transform.scale(
                  scale: 1 + (pulse * .035),
                  child: Container(
                    width: 112,
                    height: 112,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: const Color(0xFF152033),
                      border: Border.all(color: AbsColors.goldSoft, width: 3),
                      boxShadow: [
                        BoxShadow(
                          color: AbsColors.gold.withValues(alpha: .10 + pulse * .12),
                          blurRadius: 24 + pulse * 20,
                          spreadRadius: 2 + pulse * 3,
                        ),
                      ],
                    ),
                    child: widget.busy
                        ? const Padding(
                            padding: EdgeInsets.all(38),
                            child: CircularProgressIndicator(
                              strokeWidth: 2.4,
                              color: AbsColors.goldSoft,
                            ),
                          )
                        : Icon(
                            Icons.play_arrow_rounded,
                            color: widget.active ? AbsColors.goldSoft : AbsColors.muted,
                            size: 52,
                          ),
                  ),
                ),
                Positioned(
                  bottom: 8,
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: List.generate(9, (index) {
                      final phase = (controller.value + index * .105) % 1;
                      final wave = 1 - ((phase * 2) - 1).abs();
                      final height = 7 + wave * (index.isEven ? 23 : 15);
                      return Container(
                        width: 4,
                        height: height,
                        margin: const EdgeInsets.symmetric(horizontal: 3),
                        decoration: BoxDecoration(
                          color: index < 2
                              ? AbsColors.gold.withValues(alpha: .75)
                              : const Color(0xFF7693A8).withValues(alpha: .58),
                          borderRadius: BorderRadius.circular(99),
                        ),
                      );
                    }),
                  ),
                ),
              ],
            );
          },
        ),
      );
}

class _OrbitRing extends StatelessWidget {
  const _OrbitRing({
    required this.width,
    required this.height,
    required this.color,
  });

  final double width;
  final double height;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
        width: width,
        height: height,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: color, width: 1.45),
        ),
      );
}

class _GoldUnlockButton extends StatelessWidget {
  const _GoldUnlockButton({
    required this.enabled,
    required this.busy,
    required this.label,
    required this.onPressed,
  });

  final bool enabled;
  final bool busy;
  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final active = enabled || busy;
    return AnimatedContainer(
      duration: const Duration(milliseconds: 220),
      width: double.infinity,
      height: 58,
      decoration: BoxDecoration(
        gradient: active
            ? const LinearGradient(
                begin: Alignment.centerLeft,
                end: Alignment.centerRight,
                colors: [Color(0xFFE0B644), Color(0xFFF8D86A)],
              )
            : null,
        color: active ? null : const Color(0xFF273141),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: active
              ? AbsColors.goldSoft.withValues(alpha: .45)
              : AbsColors.line,
        ),
        boxShadow: active
            ? const [
                BoxShadow(color: Color(0x2EF7BC55), blurRadius: 24, offset: Offset(0, 10)),
              ]
            : null,
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: enabled ? onPressed : null,
          borderRadius: BorderRadius.circular(16),
          child: Center(
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (busy)
                  const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.2,
                      color: Color(0xFF0C1119),
                    ),
                  )
                else
                  Icon(
                    availableIcon(active),
                    color: active ? const Color(0xFF0B1119) : AbsColors.muted,
                    size: 22,
                  ),
                const SizedBox(width: 8),
                Flexible(
                  child: Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: active ? const Color(0xFF0B1119) : AbsColors.muted,
                      fontSize: 14,
                      fontWeight: FontWeight.w900,
                      letterSpacing: -.1,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  static IconData availableIcon(bool active) =>
      active ? Icons.play_arrow_rounded : Icons.schedule_rounded;
}

class _ChartLegendDot extends StatelessWidget {
  const _ChartLegendDot({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 7,
            height: 7,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 5),
          Text(
            label,
            style: const TextStyle(
              color: AbsColors.muted2,
              fontSize: 9.5,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      );
}

class _PulseMarketPainter extends CustomPainter {
  const _PulseMarketPainter({
    required this.candles,
    required this.lineColor,
    required this.entry,
    required this.stop,
    required this.target,
  });

  final List<Map<String, dynamic>> candles;
  final Color lineColor;
  final double entry;
  final double stop;
  final double target;

  double _n(Map<String, dynamic> row, String key, double fallback) {
    final value = JsonTools.number(row[key], double.nan);
    return value.isFinite && value > 0 ? value : fallback;
  }

  @override
  void paint(Canvas canvas, Size size) {
    if (candles.length < 2 || size.width <= 24 || size.height <= 30) return;

    final rows = <Map<String, double>>[];
    for (final row in candles) {
      final close = JsonTools.number(row['close'], double.nan);
      if (!close.isFinite || close <= 0) continue;
      final open = _n(row, 'open', close);
      var high = _n(row, 'high', open > close ? open : close);
      var low = _n(row, 'low', open < close ? open : close);
      if (high < open) high = open;
      if (high < close) high = close;
      if (low > open) low = open;
      if (low > close) low = close;
      rows.add({'open': open, 'high': high, 'low': low, 'close': close});
    }
    if (rows.length < 2) return;

    var low = rows.first['low']!;
    var high = rows.first['high']!;
    for (final row in rows) {
      if (row['low']! < low) low = row['low']!;
      if (row['high']! > high) high = row['high']!;
    }
    for (final level in [entry, stop, target]) {
      if (level <= 0) continue;
      final rawRange = high - low;
      final allowance = rawRange <= 0 ? high.abs() * .08 : rawRange * .45;
      if (level >= low - allowance && level <= high + allowance) {
        if (level < low) low = level;
        if (level > high) high = level;
      }
    }
    var range = high - low;
    if (range <= 0) range = high.abs() > 0 ? high.abs() * .02 : 1;
    low -= range * .08;
    high += range * .08;
    range = high - low;

    const left = 5.0;
    const right = 5.0;
    const top = 8.0;
    const bottom = 8.0;
    final chartWidth = size.width - left - right;
    final chartHeight = size.height - top - bottom;

    double y(double value) => top + (high - value) / range * chartHeight;

    final grid = Paint()
      ..color = const Color(0xFF26374A).withValues(alpha: .38)
      ..strokeWidth = .7;
    for (var i = 0; i <= 4; i++) {
      final yy = top + chartHeight * i / 4;
      canvas.drawLine(Offset(left, yy), Offset(size.width - right, yy), grid);
    }

    final step = chartWidth / rows.length;
    final bodyWidth = (step * .48).clamp(2.0, 8.0).toDouble();
    final closePath = Path();

    for (var i = 0; i < rows.length; i++) {
      final row = rows[i];
      final x = left + step * (i + .5);
      final open = row['open']!;
      final close = row['close']!;
      final candleColor = close >= open ? AbsColors.green : AbsColors.red;
      final wick = Paint()
        ..color = candleColor.withValues(alpha: .72)
        ..strokeWidth = 1;
      canvas.drawLine(
        Offset(x, y(row['high']!)),
        Offset(x, y(row['low']!)),
        wick,
      );
      final yOpen = y(open);
      final yClose = y(close);
      final rectTop = yOpen < yClose ? yOpen : yClose;
      final rectHeight = (yOpen - yClose).abs().clamp(1.5, chartHeight).toDouble();
      canvas.drawRRect(
        RRect.fromRectAndRadius(
          Rect.fromLTWH(x - bodyWidth / 2, rectTop, bodyWidth, rectHeight),
          const Radius.circular(1.2),
        ),
        Paint()..color = candleColor.withValues(alpha: .72),
      );
      if (i == 0) {
        closePath.moveTo(x, y(close));
      } else {
        closePath.lineTo(x, y(close));
      }
    }

    canvas.drawPath(
      closePath,
      Paint()
        ..color = lineColor
        ..strokeWidth = 2.0
        ..style = PaintingStyle.stroke
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round,
    );

    void levelLine(double value, Color color) {
      if (value <= 0 || value < low || value > high) return;
      final yy = y(value);
      canvas.drawLine(
        Offset(left, yy),
        Offset(size.width - right, yy),
        Paint()
          ..color = color.withValues(alpha: .60)
          ..strokeWidth = .9,
      );
    }

    levelLine(entry, AbsColors.gold);
    levelLine(stop, AbsColors.red);
    levelLine(target, AbsColors.green);
  }

  @override
  bool shouldRepaint(covariant _PulseMarketPainter oldDelegate) =>
      oldDelegate.candles != candles ||
      oldDelegate.lineColor != lineColor ||
      oldDelegate.entry != entry ||
      oldDelegate.stop != stop ||
      oldDelegate.target != target;
}

