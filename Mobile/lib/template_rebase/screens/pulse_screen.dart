import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/ads/ad_policy.dart';
import '../../core/ads/banner_ad_slot.dart';
import '../../core/api_client.dart';
import '../../core/json_tools.dart';
import '../data/mock_data.dart';
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../widgets/common.dart';
import '../widgets/sheets.dart';
import '../widgets/tiles.dart';
import '../../screens/alerts_screen.dart';
import '../../screens/plans_screen.dart';
import 'signal_detail_screen.dart';
import '../../screens/calculators_screen.dart';

class PulseScreen extends StatefulWidget {
  const PulseScreen({super.key});

  @override
  State<PulseScreen> createState() => _PulseScreenState();
}

class _PulseScreenState extends State<PulseScreen> {
  static const _filters = ['All', 'Long', 'Short', '15M', '4H'];
  static const _scanStages = <String>[
    'Preparing package market universe',
    'Checking 15M market structure',
    'Checking 4H trend alignment',
    'Running Pulse strategy qualification',
    'Ranking qualifying setups',
    'Selecting the strongest setup',
  ];

  String _filter = 'All';
  bool _requested = false;
  bool _scanContextLoading = false;
  bool _findingBest = false;
  bool _scanCompleted = false;
  int _scanStage = 0;
  String? _scanError;
  Signal? _bestSignal;
  Timer? _scanStageTimer;
  Map<String, dynamic> _scannerOverview = <String, dynamic>{};
  Map<String, dynamic> _scanUsage = <String, dynamic>{};
  Map<String, dynamic> _marketHealth = <String, dynamic>{};
  Map<String, dynamic> _lastScan = <String, dynamic>{};

  Signal? get _effectiveBestSignal {
    final candidates = <Signal>[
      if (_bestSignal != null) _bestSignal!,
      ...MockData.signals.where((s) => s.status == SignalStatus.active),
    ];
    if (candidates.isEmpty) return null;
    candidates.sort((a, b) => b.confidence.compareTo(a.confidence));
    return candidates.first;
  }

  List<Signal> get _allSignals {
    final out = <Signal>[];
    final seen = <String>{};
    void add(Signal signal) {
      final key = signal.backendId != null
          ? 'id:${signal.backendId}'
          : '${signal.symbol}|${signal.timeframe}|${signal.entry}|${signal.confidence}';
      if (seen.add(key)) out.add(signal);
    }

    final best = _effectiveBestSignal;
    if (best != null) add(best);
    for (final signal in MockData.signals) {
      add(signal);
    }
    return out;
  }

  List<Signal> get _signals => _allSignals.where((s) {
        switch (_filter) {
          case 'Long':
            return s.side == Side.long;
          case 'Short':
            return s.side == Side.short;
          case '15M':
          case '4H':
            return s.timeframe == _filter;
          default:
            return true;
        }
      }).toList();

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_requested) {
      _requested = true;
      WidgetsBinding.instance.addPostFrameCallback((_) async {
        if (!mounted) return;
        final app = AppScope.read(context);
        await app.ensurePulse();
        if (!mounted) return;
        await _loadScanContext(app);
      });
    }
  }

  @override
  void dispose() {
    _scanStageTimer?.cancel();
    super.dispose();
  }

  Future<void> _refreshAll(AppState app) async {
    await Future.wait<void>([
      app.refreshPulse(),
      _loadScanContext(app),
    ]);
  }

  Future<void> _loadScanContext(AppState app) async {
    if (!app.hasPulse || _scanContextLoading) {
      _syncBestSignal();
      return;
    }
    if (mounted) setState(() => _scanContextLoading = true);
    try {
      final values = await Future.wait<dynamic>([
        app.session.api.get('/pulse/scanner/overview'),
        app.session.api.get('/pulse/usage'),
        app.session.api.get('/pulse/market-data/health'),
      ]);
      _scannerOverview = JsonTools.map(
        JsonTools.at(values[0], 'data', <String, dynamic>{}),
      );
      _scanUsage = JsonTools.map(
        JsonTools.at(
          values[0],
          'usage',
          JsonTools.at(values[1], 'data', <String, dynamic>{}),
        ),
      );
      _marketHealth = JsonTools.map(
        JsonTools.at(values[2], 'data', <String, dynamic>{}),
      );
      _syncBestSignal();
    } on ApiException catch (e) {
      _scanError = e.message;
    } finally {
      if (mounted) setState(() => _scanContextLoading = false);
    }
  }

  void _syncBestSignal({List<Map<String, dynamic>> extra = const []}) {
    final candidates = <Signal>[];

    for (final row in extra) {
      final signal = _qualifiedSignalFromRow(row);
      if (signal != null) candidates.add(signal);
    }

    for (final row in JsonTools.mapList(_scannerOverview['results'])) {
      final signal = _qualifiedSignalFromRow(row);
      if (signal != null) candidates.add(signal);
    }

    candidates
        .addAll(MockData.signals.where((s) => s.status == SignalStatus.active));
    if (candidates.isEmpty) return;

    candidates.sort((a, b) {
      final persisted =
          (b.backendId != null ? 1 : 0).compareTo(a.backendId != null ? 1 : 0);
      if (persisted != 0) return persisted;
      return b.confidence.compareTo(a.confidence);
    });
    _bestSignal = candidates.first;
  }

  Signal? _qualifiedSignalFromRow(Map<String, dynamic> row) {
    if (row.isEmpty) return null;
    final status = JsonTools.text(row['status']).toLowerCase();
    final qualified = JsonTools.boolean(row['qualified']) ||
        JsonTools.boolean(row['is_qualified']) ||
        JsonTools.boolean(row['signal_created']) ||
        <String>{'active', 'qualified', 'ready', 'signal'}.contains(status);
    final id = JsonTools.integer(row['id'] ?? row['signal_id']);
    final tradeAction = JsonTools.map(row['trade_action']);
    final actionSignalId =
        JsonTools.integer(tradeAction['signal_id'] ?? tradeAction['id']);

    // Scanner overview may include evaluated but unqualified rows. Only surface
    // a result as the Best Signal when the backend marks it qualified or gives
    // it a persisted signal identity that can be reviewed/executed.
    if (!qualified && id <= 0 && actionSignalId <= 0) return null;

    final normalized = <String, dynamic>{...row};
    if (id <= 0 && actionSignalId > 0) normalized['id'] = actionSignalId;
    if (normalized['confidence_score'] == null && normalized['score'] != null) {
      normalized['confidence_score'] = normalized['score'];
    }
    if (normalized['take_profit'] == null && normalized['tp'] != null) {
      normalized['take_profit'] = normalized['tp'];
    }
    return MockData.signalFromMap(normalized);
  }

  List<Map<String, dynamic>> _scanResponseCandidates(dynamic payload) {
    final root = JsonTools.map(payload);
    final data = JsonTools.map(root['data']);
    final out = <Map<String, dynamic>>[];

    void takeMap(dynamic value) {
      final map = JsonTools.map(value);
      if (map.isNotEmpty) out.add(map);
    }

    for (final container in <Map<String, dynamic>>[root, data]) {
      for (final key in const <String>[
        'best_signal',
        'bestSignal',
        'signal',
        'setup'
      ]) {
        takeMap(container[key]);
      }
      out.addAll(JsonTools.mapList(container['signals']));
      out.addAll(JsonTools.mapList(container['qualified_signals']));
      out.addAll(JsonTools.mapList(container['results']));
    }
    return out;
  }

  Future<void> _findBestSignal(AppState app) async {
    if (_findingBest) return;
    if (!app.hasPulse) {
      await push(context, const PlansScreen());
      return;
    }

    _scanStageTimer?.cancel();
    setState(() {
      _findingBest = true;
      _scanCompleted = false;
      _scanStage = 0;
      _scanError = null;
    });
    _scanStageTimer =
        Timer.periodic(const Duration(milliseconds: 850), (timer) {
      if (!mounted) return;
      if (_scanStage < _scanStages.length - 1) {
        setState(() => _scanStage++);
      }
    });

    final started = DateTime.now();
    try {
      final response = await app.session.api.post(
        '/pulse/scanner/run',
        body: const <String, dynamic>{'timeframe': 'all'},
      );
      _lastScan =
          JsonTools.map(JsonTools.at(response, 'data', <String, dynamic>{}));
      final direct = _scanResponseCandidates(response);

      // Keep the scan animation visible long enough to communicate the real
      // server workflow without claiming a fabricated percentage complete.
      final elapsed = DateTime.now().difference(started);
      const minimumVisual = Duration(milliseconds: 1900);
      if (elapsed < minimumVisual) {
        await Future<void>.delayed(minimumVisual - elapsed);
      }

      await app.refreshPulse();
      await _loadScanContext(app);
      _syncBestSignal(extra: direct);

      if (!mounted) return;
      setState(() => _scanCompleted = true);
      if (_effectiveBestSignal != null) {
        final best = _effectiveBestSignal!;
        snack(context,
            'Best Signal ready · ${best.pair} ${best.side == Side.long ? 'LONG' : 'SHORT'}');
      } else {
        snack(context,
            'Scan complete · no setup currently meets the ABS qualification rules.');
      }
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _scanError = e.message;
        _scanCompleted = true;
      });
    } finally {
      _scanStageTimer?.cancel();
      if (mounted) {
        setState(() {
          _findingBest = false;
          _scanStage = _scanStages.length - 1;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Pulse'),
          actions: [
            IconButton(
              tooltip: 'Refresh Pulse',
              onPressed: app.pulseLoading || _findingBest
                  ? null
                  : () => _refreshAll(app),
              icon: const Icon(Icons.refresh_rounded),
            ),
            IconButton(
              tooltip: 'Calculators',
              onPressed: () => push(context, const CalculatorsScreen()),
              icon: const Icon(Icons.calculate_outlined),
            ),
            IconButton(
              tooltip: 'Price alerts',
              onPressed: app.emailVerified
                  ? () => push(context, const AlertsScreen())
                  : null,
              icon: const Icon(Icons.notifications_none_rounded),
            ),
          ],
          bottom: const TabBar(
            labelColor: AppColors.text,
            unselectedLabelColor: AppColors.muted,
            indicatorColor: AppColors.accent,
            dividerColor: AppColors.line,
            labelStyle: TextStyle(fontWeight: FontWeight.w700),
            tabs: [Tab(text: 'Signals'), Tab(text: 'Watchlist')],
          ),
        ),
        body: TabBarView(children: [_signalsTab(app), _watchlistTab(app)]),
      ),
    );
  }

  Widget _signalsTab(AppState app) {
    final list = _signals;
    // In-feed: after the 3rd signal (or after a shorter list); a second slot
    // only on long feeds, 7 cards later.
    final adSlots = AdPolicy.feedBannerPositions(
      list.length,
      first: 3,
      spacing: 7,
      trailingWhenShort: true,
    );
    return RefreshIndicator(
      onRefresh: () => _refreshAll(app),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          if (app.pulseLoading) ...[
            const LinearProgressIndicator(minHeight: 2),
            const SizedBox(height: 12),
          ],
          if (app.pulseError != null) ...[
            AbsCard(
              borderColor: fade(AppColors.amber, .4),
              child: Row(children: [
                const Icon(Icons.info_outline_rounded, color: AppColors.amber),
                const SizedBox(width: 10),
                Expanded(child: Text(app.pulseError!, style: AppText.muted)),
                IconButton(
                    onPressed: app.refreshPulse,
                    icon: const Icon(Icons.refresh_rounded)),
              ]),
            ),
            const SizedBox(height: 12),
          ],
          _statusBanner(app),
          const SizedBox(height: 14),
          _bestSignalScanner(app),
          const SizedBox(height: 18),
          Row(
            children: [
              const Expanded(
                child: Text('Qualified Signals',
                    style:
                        TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
              ),
              Text('${_allSignals.length}', style: AppText.muted),
            ],
          ),
          const SizedBox(height: 10),
          SizedBox(
            height: 38,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: _filters.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (_, i) {
                final f = _filters[i];
                final sel = f == _filter;
                return ChoiceChip(
                  label: Text(f),
                  selected: sel,
                  showCheckmark: false,
                  onSelected: (_) => setState(() => _filter = f),
                  selectedColor: fade(AppColors.accent, .2),
                  backgroundColor: AppColors.surface,
                  side: BorderSide(
                      color: sel ? fade(AppColors.accent, .5) : AppColors.line),
                  labelStyle: TextStyle(
                    color: sel ? AppColors.accent : AppColors.muted,
                    fontWeight: FontWeight.w600,
                  ),
                );
              },
            ),
          ),
          const SizedBox(height: 14),
          if (list.isEmpty)
            EmptyState(
              icon: app.hasPulse
                  ? Icons.radar_rounded
                  : Icons.lock_outline_rounded,
              title: app.hasPulse
                  ? 'No qualified signal in this view'
                  : 'Pulse signals are locked',
              message: app.hasPulse
                  ? 'Use Find Best Signal above. ABS will scan the package universe across 15M + 4H and reveal the strongest setup only when one qualifies.'
                  : app.limitedAccount
                      ? 'Activate your email, then choose a Pulse package to unlock the scanner and full signal list.'
                      : 'Choose a Pulse package to unlock the scanner and full signal list.',
            ),
          for (int i = 0; i < list.length; i++) ...[
            if (adSlots.contains(i))
              const InlineBannerAdSlot(
                placement: BannerPlacement.pulse,
                margin: EdgeInsets.only(top: 6, bottom: 18),
              ),
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: (app.hasPulse || i == 0)
                  ? SignalCard(
                      signal: list[i],
                      preview: !app.hasPulse,
                      onTap: () => pushContentDetail(
                          context,
                          SignalDetailScreen(signal: list[i]),
                          InterstitialMoment.signalDetailClosed),
                    )
                  : LockedOverlay(
                      label: 'Unlock with Pulse',
                      onUnlock: () => push(context, const PlansScreen()),
                      child: SignalCard(signal: list[i]),
                    ),
            ),
          ],
          if (adSlots.contains(list.length))
            const InlineBannerAdSlot(placement: BannerPlacement.pulse),
          const RiskNotice(),
        ],
      ),
    );
  }

  Widget _bestSignalScanner(AppState app) {
    if (!app.hasPulse) {
      return AbsCard(
        onTap: () => push(context, const PlansScreen()),
        borderColor: fade(AppColors.gold, .35),
        child: Row(
          children: [
            Container(
              width: 54,
              height: 54,
              decoration: BoxDecoration(
                color: fade(AppColors.gold, .1),
                borderRadius: BorderRadius.circular(16),
              ),
              child: const Icon(Icons.radar_rounded,
                  color: AppColors.gold, size: 27),
            ),
            const SizedBox(width: 12),
            const Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Find Best Signal',
                      style:
                          TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                  SizedBox(height: 3),
                  Text(
                      'Activate Pulse access to scan the package universe across 15M + 4H.',
                      style: AppText.muted),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: AppColors.muted),
          ],
        ),
      );
    }

    final selectedPairs = JsonTools.integer(
      _scannerOverview['selected_pairs'] ??
          _scannerOverview['package_universe'] ??
          _lastScan['pairs_scanned'],
    );
    final setupCount = JsonTools.integer(
      _scannerOverview['setups_identified'] ??
          _lastScan['signals_created'] ??
          (_effectiveBestSignal == null ? 0 : 1),
    );
    final healthy = JsonTools.boolean(
      _marketHealth['healthy'],
      JsonTools.boolean(_marketHealth['is_healthy'], true),
    );
    final scanUnlimited =
        JsonTools.boolean(JsonTools.at(_scanUsage, 'scans.unlimited'));
    final scanRemaining = JsonTools.at(
      _scanUsage,
      'scans.remaining',
      JsonTools.at(_scanUsage, 'scanner.remaining',
          JsonTools.at(_scanUsage, 'scanner_runs_remaining', '—')),
    );

    return AbsCard(
      borderColor: fade(_findingBest ? AppColors.accent : AppColors.gold, .42),
      color: const Color(0xFF0B1424),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _ScanningOrb(active: _findingBest),
              const SizedBox(width: 13),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('FIND BEST SIGNAL',
                        style: TextStyle(
                            color: AppColors.gold,
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 1.1)),
                    SizedBox(height: 5),
                    Text('One strongest qualifying setup',
                        style: TextStyle(
                            fontWeight: FontWeight.w800, fontSize: 18)),
                    SizedBox(height: 5),
                    Text(
                      'ABS evaluates your package market universe across 15M and 4H, applies the existing Pulse strategy rules, then surfaces only the strongest qualifying setup.',
                      style: AppText.muted,
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 7,
            runSpacing: 7,
            children: [
              Pill(
                  selectedPairs > 0
                      ? '$selectedPairs markets'
                      : 'Package universe',
                  color: AppColors.accent),
              const Pill('15M + 4H', color: AppColors.accent),
              Pill(healthy ? 'Market data ready' : 'Data updating',
                  color: healthy ? AppColors.up : AppColors.amber),
              Pill(
                  scanUnlimited
                      ? 'Unlimited scans'
                      : '$scanRemaining scans left',
                  color: AppColors.muted),
            ],
          ),
          if (_findingBest) ...[
            const SizedBox(height: 16),
            const LinearProgressIndicator(minHeight: 3),
            const SizedBox(height: 10),
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 250),
              child: Row(
                key: ValueKey(_scanStage),
                children: [
                  const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
                  const SizedBox(width: 9),
                  Expanded(
                    child: Text(
                      _scanStages[_scanStage],
                      style: const TextStyle(
                          fontWeight: FontWeight.w700, fontSize: 12.5),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 10),
            _ScanStageRail(stage: _scanStage),
          ],
          if (_scanError != null && !_findingBest) ...[
            const SizedBox(height: 12),
            Text(_scanError!,
                style: const TextStyle(color: AppColors.down, fontSize: 12.5)),
          ],
          if (!_findingBest && _effectiveBestSignal != null) ...[
            const SizedBox(height: 16),
            Row(
              children: [
                const LegendDot(
                    color: AppColors.up, label: 'Best Signal ready'),
                const Spacer(),
                Text('Score ${_effectiveBestSignal!.confidence}',
                    style: const TextStyle(
                        color: AppColors.accent, fontWeight: FontWeight.w800)),
              ],
            ),
            const SizedBox(height: 10),
            SignalCard(
              signal: _effectiveBestSignal!,
              onTap: () => pushContentDetail(
                  context,
                  SignalDetailScreen(signal: _effectiveBestSignal!),
                  InterstitialMoment.signalDetailClosed),
            ),
          ] else if (!_findingBest &&
              _scanCompleted &&
              _effectiveBestSignal == null) ...[
            const SizedBox(height: 14),
            AbsCard(
              color: AppColors.bg,
              child: const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.check_circle_outline_rounded,
                      color: AppColors.muted),
                  SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Full scan completed. No setup currently meets the active ABS qualification rules, so no signal was created.',
                      style: AppText.muted,
                    ),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                  child: _ScanMetric(
                      label: 'Market universe',
                      value: selectedPairs > 0 ? '$selectedPairs' : '—')),
              const SizedBox(width: 8),
              Expanded(
                  child: _ScanMetric(
                      label: 'Qualified',
                      value: setupCount > 0
                          ? '$setupCount'
                          : (_effectiveBestSignal == null ? '0' : '1'))),
              const SizedBox(width: 8),
              Expanded(
                  child: _ScanMetric(label: 'Coverage', value: '15M + 4H')),
            ],
          ),
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed:
                  _findingBest || !healthy ? null : () => _findBestSignal(app),
              icon: _findingBest
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2))
                  : const Icon(Icons.radar_rounded),
              label: Text(
                _findingBest
                    ? 'Finding best signal...'
                    : !healthy
                        ? 'Waiting for fresh market data'
                        : 'Find Best Signal',
              ),
            ),
          ),
          const SizedBox(height: 7),
          const Text(
            'A scan does not place a trade. It only creates or reveals a qualified signal for review.',
            style:
                TextStyle(color: AppColors.faint, fontSize: 10.5, height: 1.35),
          ),
        ],
      ),
    );
  }

  Widget _statusBanner(AppState app) {
    if (app.hasPulse) {
      final access = JsonTools.map(app.membership['access']);
      final currentPlan = JsonTools.map(access['plan']);
      final fallbackPlan = MockData.plan(app.activePlanId ?? 'pulse');
      final planName = JsonTools.text(currentPlan['name'], fallbackPlan.name);
      return AbsCard(
        borderColor: fade(AppColors.up, .4),
        child: Row(children: [
          const Icon(Icons.verified_rounded, color: AppColors.up),
          const SizedBox(width: 12),
          Expanded(
            child:
                Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('$planName is active',
                  style: const TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 2),
              Text(
                '${app.daysLeft} ${app.daysLeft == 1 ? 'day' : 'days'} left. Best Signal and qualified Pulse signals are available.',
                style: AppText.muted,
              ),
            ]),
          ),
        ]),
      );
    }
    if (app.pendingPlanId != null) {
      return AbsCard(
        borderColor: fade(AppColors.amber, .4),
        child: const Row(children: [
          Icon(Icons.hourglass_top_rounded, color: AppColors.amber),
          SizedBox(width: 12),
          Expanded(
            child:
                Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Payment under review',
                  style: TextStyle(fontWeight: FontWeight.w700)),
              SizedBox(height: 2),
              Text('Access starts once an admin verifies your transaction.',
                  style: AppText.muted),
            ]),
          ),
        ]),
      );
    }
    return AbsCard(
      onTap: () => push(context, const PlansScreen()),
      child: Row(children: [
        Icon(
            app.limitedAccount
                ? Icons.mark_email_unread_outlined
                : Icons.lock_outline_rounded,
            color: AppColors.gold),
        const SizedBox(width: 12),
        Expanded(
          child:
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(
                app.limitedAccount
                    ? 'Activate to unlock Pulse'
                    : 'Pulse trading tools are locked',
                style: const TextStyle(fontWeight: FontWeight.w700)),
            const SizedBox(height: 2),
            Text(
              app.limitedAccount
                  ? 'Basic market intelligence and Free Signal remain available while activation is pending.'
                  : 'View the current Pulse plans to unlock Best Signal, scanner, positions and performance intelligence.',
              style: AppText.muted,
            ),
          ]),
        ),
        const Icon(Icons.chevron_right_rounded, color: AppColors.muted),
      ]),
    );
  }

  Widget _watchlistTab(AppState app) {
    final watched =
        MockData.coins.where((c) => app.watchlist.contains(c.symbol)).toList();
    final rest =
        MockData.coins.where((c) => !app.watchlist.contains(c.symbol)).toList();
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
      children: [
        Text(
          watched.isEmpty
              ? 'Tap the star on any market to add it to your watchlist.'
              : '${watched.length} starred ${watched.length == 1 ? 'market' : 'markets'}, shown first.',
          style: AppText.muted,
        ),
        const SizedBox(height: 4),
        for (final c in [...watched, ...rest]) ...[
          CoinRow(coin: c, onTap: () => showCoinSheet(context, c)),
          const Divider(height: 1),
        ],
      ],
    );
  }
}

class _ScanningOrb extends StatefulWidget {
  const _ScanningOrb({required this.active});
  final bool active;

  @override
  State<_ScanningOrb> createState() => _ScanningOrbState();
}

class _ScanningOrbState extends State<_ScanningOrb>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1500),
  );

  @override
  void initState() {
    super.initState();
    if (widget.active) _controller.repeat();
  }

  @override
  void didUpdateWidget(covariant _ScanningOrb oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !oldWidget.active) {
      _controller.repeat();
    } else if (!widget.active && oldWidget.active) {
      _controller.stop();
      _controller.value = 0;
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => SizedBox(
        width: 62,
        height: 62,
        child: Stack(
          alignment: Alignment.center,
          children: [
            Container(
              width: 60,
              height: 60,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(
                    color: fade(
                        widget.active ? AppColors.accent : AppColors.gold,
                        .34)),
                color: fade(
                    widget.active ? AppColors.accent : AppColors.gold, .06),
              ),
            ),
            RotationTransition(
              turns: _controller,
              child: CustomPaint(
                size: const Size.square(52),
                painter: _RadarArcPainter(
                    color: widget.active ? AppColors.accent : AppColors.gold),
              ),
            ),
            Icon(Icons.my_location_rounded,
                color: widget.active ? AppColors.accent : AppColors.gold,
                size: 24),
          ],
        ),
      );
}

class _RadarArcPainter extends CustomPainter {
  const _RadarArcPainter({required this.color});
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = fade(color, .85)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.4
      ..strokeCap = StrokeCap.round;
    final rect = Offset.zero & size;
    canvas.drawArc(rect.deflate(4), -.6, 1.7, false, paint);
    paint.color = fade(color, .24);
    canvas.drawCircle(size.center(Offset.zero), size.width * .29, paint);
  }

  @override
  bool shouldRepaint(covariant _RadarArcPainter oldDelegate) =>
      oldDelegate.color != color;
}

class _ScanStageRail extends StatelessWidget {
  const _ScanStageRail({required this.stage});
  final int stage;

  @override
  Widget build(BuildContext context) {
    const labels = <String>['Universe', '15M', '4H', 'Rank'];
    final thresholds = <int>[0, 1, 2, 4];
    return Row(
      children: [
        for (int i = 0; i < labels.length; i++) ...[
          Expanded(
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 220),
              padding: const EdgeInsets.symmetric(vertical: 7),
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: fade(
                    stage >= thresholds[i] ? AppColors.accent : AppColors.muted,
                    stage >= thresholds[i] ? .13 : .05),
                borderRadius: BorderRadius.circular(9),
                border: Border.all(
                    color: fade(
                        stage >= thresholds[i]
                            ? AppColors.accent
                            : AppColors.line,
                        .45)),
              ),
              child: Text(
                labels[i],
                style: TextStyle(
                  color: stage >= thresholds[i]
                      ? AppColors.accent
                      : AppColors.muted,
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
          if (i != labels.length - 1) const SizedBox(width: 6),
        ],
      ],
    );
  }
}

class _ScanMetric extends StatelessWidget {
  const _ScanMetric({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: AppColors.bg,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.line),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(value,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style:
                    const TextStyle(fontWeight: FontWeight.w800, fontSize: 13)),
            const SizedBox(height: 2),
            Text(label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppText.muted.copyWith(fontSize: 9.5)),
          ],
        ),
      );
}
