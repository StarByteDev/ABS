import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';

class TradingSetupScreen extends StatefulWidget {
  const TradingSetupScreen({super.key, this.initialSection});
  final String? initialSection;
  @override
  State<TradingSetupScreen> createState() => _TradingSetupScreenState();
}

class _TradingSetupScreenState extends State<TradingSetupScreen> with SingleTickerProviderStateMixin {
  late TabController tabs;
  bool loading = true;
  bool saving = false;
  String? error;
  Map<String, dynamic> settings = {};
  Map<String, dynamic> risk = {};
  Map<String, dynamic> pairCatalog = {};
  Map<String, dynamic> readiness = {};
  List<Map<String, dynamic>> connections = [];
  Set<String> selectedPairs = {};

  final riskPerTrade = TextEditingController();
  final dailyLoss = TextEditingController();
  final tpPercent = TextEditingController();
  final slPercent = TextEditingController();
  final maxOpen = TextEditingController();

  String environment = 'testnet';
  String executionMode = 'signal_only';
  String marginType = 'ISOLATED';
  String positionMode = 'BOTH';
  String sizingMode = 'fixed_notional';
  String orderType = 'MARKET';
  bool autoTrade = false;
  bool emergencyStop = false;
  double leverage = 3;
  double minScore = 0;
  final fixedNotional = TextEditingController();
  final fixedQuantity = TextEditingController();

  @override
  void initState() {
    super.initState();
    final section = (widget.initialSection ?? '').toLowerCase();
    final initialIndex = section == 'risk' ? 1 : section == 'markets' ? 2 : section == 'execution' ? 3 : 0;
    tabs = TabController(length: 4, vsync: this, initialIndex: initialIndex);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && settings.isEmpty) _load();
  }

  @override
  void dispose() {
    tabs.dispose();
    for (final c in [riskPerTrade, dailyLoss, tpPercent, slPercent, maxOpen, fixedNotional, fixedQuantity]) { c.dispose(); }
    super.dispose();
  }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final api = SessionScope.of(context).api;
      final values = await Future.wait([
        api.get('/pulse/settings'),
        api.get('/pulse/risk-controls'),
        api.get('/pulse/pairs'),
        api.get('/pulse/binance/connections'),
        api.get('/pulse/execution/readiness'),
      ]);
      final settingsResponse = JsonTools.map(values[0]);
      settings = JsonTools.map(settingsResponse['data']);
      risk = JsonTools.map(JsonTools.at(values[1], 'data', <String, dynamic>{}));
      pairCatalog = JsonTools.map(JsonTools.at(values[2], 'data', <String, dynamic>{}));
      connections = JsonTools.mapList(JsonTools.at(values[3], 'data', <dynamic>[]));
      readiness = JsonTools.map(JsonTools.at(values[4], 'data', <String, dynamic>{}));
      selectedPairs = JsonTools.list(settings['selected_pairs']).map((e) => e.toString().toUpperCase()).toSet();
      _hydrateFields(settings, JsonTools.map(risk['settings']));
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  void _hydrateFields(Map<String, dynamic> s, Map<String, dynamic> riskSettings) {
    final r = riskSettings.isNotEmpty ? riskSettings : s;
    riskPerTrade.text = JsonTools.number(r['risk_per_trade_percent'], 1).toString();
    dailyLoss.text = JsonTools.number(r['daily_loss_limit']).toString();
    tpPercent.text = JsonTools.number(r['take_profit_percent'], 2).toString();
    slPercent.text = JsonTools.number(r['stop_loss_percent'], 1).toString();
    maxOpen.text = '${JsonTools.integer(r['max_open_positions'], 1)}';
    environment = JsonTools.text(s['environment'], 'testnet');
    executionMode = JsonTools.text(s['execution_mode'], 'signal_only');
    marginType = JsonTools.text(s['margin_type'], 'ISOLATED');
    positionMode = JsonTools.text(s['position_mode'], 'BOTH');
    sizingMode = JsonTools.text(s['sizing_mode'], 'fixed_notional');
    orderType = JsonTools.text(s['default_order_type'], 'MARKET');
    autoTrade = JsonTools.boolean(s['auto_trade_enabled']);
    emergencyStop = JsonTools.boolean(s['emergency_stop']);
    leverage = JsonTools.number(s['default_leverage'], 3).clamp(1, 20);
    minScore = JsonTools.number(s['minimum_signal_score'], 0).clamp(0, 100);
    fixedNotional.text = JsonTools.number(s['fixed_notional'], 25).toString();
    fixedQuantity.text = s['fixed_quantity'] == null ? '' : JsonTools.number(s['fixed_quantity']).toString();
  }

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    return Scaffold(
      backgroundColor: Colors.transparent,
      appBar: AppBar(
        toolbarHeight: 72,
        title: Row(
          children: [
            Container(width: 34, height: 34, padding: const EdgeInsets.all(4), decoration: BoxDecoration(color: AbsColors.panel2, borderRadius: BorderRadius.circular(11), border: Border.all(color: AbsColors.line)), child: Image.asset('assets/brand/abs-logo-master.png')),
            const SizedBox(width: 10),
            const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Trading Setup', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18)), SizedBox(height: 2), Text('Binance · Risk · Markets · Execution', style: TextStyle(fontSize: 10.5, color: AbsColors.muted))])),
          ],
        ),
        actions: [IconButton(onPressed: _load, icon: const Icon(Icons.refresh_rounded))],
        bottom: TabBar(
          controller: tabs,
          isScrollable: true,
          tabAlignment: TabAlignment.start,
          tabs: const [Tab(icon: Icon(Icons.link_rounded, size: 17), text: 'Binance'), Tab(icon: Icon(Icons.shield_outlined, size: 17), text: 'Risk'), Tab(icon: Icon(Icons.grid_view_rounded, size: 17), text: 'Markets'), Tab(icon: Icon(Icons.tune_rounded, size: 17), text: 'Execution')],
        ),
      ),
      body: AbsBackground(
        child: SafeArea(
          top: false,
          child: loading
              ? const LoadingBlock(label: 'Loading trading configuration...')
              : error != null
                  ? Padding(padding: const EdgeInsets.all(18), child: ErrorBlock(message: error!, onRetry: _load))
                  : Column(
                      children: [
                        Padding(
                          padding: const EdgeInsets.fromLTRB(18, 12, 18, 0),
                          child: Row(
                            children: [
                              Expanded(child: Text(session.proMode ? 'Professional controls' : 'Guided setup mode', style: const TextStyle(color: AbsColors.muted, fontSize: 11.5, fontWeight: FontWeight.w700))),
                              ExperienceModeSwitch(proMode: session.proMode, onChanged: (value) => session.setTraderExperience(value ? 'pro' : 'simple')),
                            ],
                          ),
                        ),
                        Expanded(child: TabBarView(controller: tabs, children: [_binanceTab(), _riskTab(), _pairsTab(), _executionTab()])),
                      ],
                    ),
        ),
      ),
    );
  }

  Widget _readinessCard() {
    final ready = JsonTools.boolean(readiness['ready']);
    final env = JsonTools.text(readiness['environment'], environment).toUpperCase();
    final next = JsonTools.text(readiness['next_step'], ready ? 'ready' : 'setup').replaceAll('_', ' ');
    return AbsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(ready ? Icons.verified_rounded : Icons.route_rounded, color: ready ? AbsColors.green : AbsColors.gold),
              const SizedBox(width: 10),
              Expanded(child: Text(ready ? 'Trading setup ready' : 'Complete trading setup', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 17))),
              StatusChip(env == 'LIVE' ? 'LIVE' : 'TESTNET', warning: env == 'LIVE'),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            ready
                ? 'ABS reports that your current environment, Binance connection and server-side trading permissions are ready.'
                : 'Next recommended step: ${next.toUpperCase()}. ABS will still enforce every server-side readiness check before execution.',
            style: const TextStyle(color: AbsColors.muted, fontSize: 12),
          ),
        ],
      ),
    );
  }

  Widget _binanceTab() {
    return ListView(
      padding: const EdgeInsets.all(18),
      children: [
        _readinessCard(),
        const SizedBox(height: 14),
        const AbsSectionTitle('Binance Futures', subtitle: 'Credentials are encrypted by ABS and never returned to the app.'),
        const SizedBox(height: 12),
        if (connections.isEmpty)
          const AbsCard(child: Text('No Binance Futures connection saved.', style: TextStyle(color: AbsColors.muted)))
        else
          ...connections.map((c) => Padding(
                padding: const EdgeInsets.only(bottom: 9),
                child: AbsCard(
                  child: Column(
                    children: [
                      Row(
                        children: [
                          StatusChip(JsonTools.text(c['environment']).toUpperCase(), warning: JsonTools.text(c['environment']) == 'live'),
                          const SizedBox(width: 8),
                          Expanded(child: Text(JsonTools.text(c['label']), style: const TextStyle(fontWeight: FontWeight.w900))),
                          if (JsonTools.boolean(c['is_active'])) const StatusChip('ACTIVE', good: true),
                        ],
                      ),
                      const SizedBox(height: 9),
                      KeyValueRow('API Key', JsonTools.text(c['masked_key'])),
                      KeyValueRow('Last tested', compactDate(c['last_tested_at'])),
                      if (c['last_error'] != null) KeyValueRow('Last error', JsonTools.text(c['last_error']), valueColor: AbsColors.red),
                      const SizedBox(height: 8),
                      Row(children: [
                        Expanded(child: OutlinedButton(onPressed: () => _testConnection(c), child: const Text('Test'))),
                        const SizedBox(width: 8),
                        Expanded(child: ElevatedButton(onPressed: JsonTools.boolean(c['is_active']) ? null : () => _activateConnection(c), child: const Text('Activate'))),
                        const SizedBox(width: 4),
                        IconButton(onPressed: () => _deleteConnection(c), icon: const Icon(Icons.delete_outline)),
                      ]),
                    ],
                  ),
                ),
              )),
        const SizedBox(height: 12),
        ElevatedButton.icon(onPressed: _addConnection, icon: const Icon(Icons.add_link), label: const Text('Add Binance connection')),
        const SizedBox(height: 12),
        const AbsCard(
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Icon(Icons.security, color: AbsColors.green),
            SizedBox(width: 10),
            Expanded(child: Text('Use Futures trading permissions only. ABS does not need and should not be given withdrawal permission.')),
          ]),
        ),
      ],
    );
  }

  Widget _riskTab() {
    return ListView(
      padding: const EdgeInsets.all(18),
      children: [
        const AbsSectionTitle('Risk Controls', subtitle: 'Simple limits for new traders; exact values remain available for professionals.'),
        const SizedBox(height: 12),
        TextField(controller: riskPerTrade, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: const InputDecoration(labelText: 'Risk per trade (%)')),
        const SizedBox(height: 10),
        TextField(controller: dailyLoss, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: const InputDecoration(labelText: 'Daily loss limit (account currency, 0 = disabled)')),
        const SizedBox(height: 10),
        Row(children: [
          Expanded(child: TextField(controller: tpPercent, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: const InputDecoration(labelText: 'Default TP %'))),
          const SizedBox(width: 10),
          Expanded(child: TextField(controller: slPercent, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: const InputDecoration(labelText: 'Default SL %'))),
        ]),
        const SizedBox(height: 10),
        TextField(controller: maxOpen, keyboardType: TextInputType.number, decoration: InputDecoration(labelText: 'Max open positions', helperText: 'Plan maximum: ${JsonTools.integer(risk['plan_max_open'])}')),
        const SizedBox(height: 16),
        ElevatedButton(onPressed: saving ? null : _saveRisk, child: Text(saving ? 'Saving...' : 'Save risk controls')),
        const SizedBox(height: 14),
        AbsCard(
          child: Column(children: [
            KeyValueRow('Open now', '${JsonTools.integer(risk['open_count'])} / ${JsonTools.integer(risk['max_open'])}'),
            KeyValueRow('Risk utilization', percent(risk['risk_utilization'])),
            KeyValueRow('Today realized P&L', money(risk['today_realized_pnl']), valueColor: pnlColor(risk['today_realized_pnl'])),
            KeyValueRow('Protection issues', '${JsonTools.integer(risk['protection_issues'])}', valueColor: JsonTools.integer(risk['protection_issues']) > 0 ? AbsColors.red : null),
          ]),
        ),
      ],
    );
  }

  Widget _pairsTab() {
    final pairs = JsonTools.mapList(pairCatalog['pairs']);
    final limit = JsonTools.integer(pairCatalog['limit'], 1);
    return ListView(
      padding: const EdgeInsets.all(18),
      children: [
        AbsSectionTitle('Market Selection', subtitle: '${selectedPairs.length} selected · plan limit $limit'),
        const SizedBox(height: 10),
        ...pairs.map((p) {
          final symbol = JsonTools.text(p['symbol']);
          final selected = selectedPairs.contains(symbol);
          return CheckboxListTile(
            contentPadding: const EdgeInsets.symmetric(horizontal: 8),
            value: selected,
            title: Text(symbol, style: const TextStyle(fontWeight: FontWeight.w800)),
            subtitle: Text('${JsonTools.text(p['base_asset'])} / ${JsonTools.text(p['quote_asset'])}', style: const TextStyle(color: AbsColors.muted, fontSize: 11)),
            onChanged: (value) {
              setState(() {
                if (value == true) {
                  if (selectedPairs.length >= limit) {
                    showSnack(context, 'Your current plan allows $limit selected pairs.', error: true);
                    return;
                  }
                  selectedPairs.add(symbol);
                } else {
                  selectedPairs.remove(symbol);
                }
              });
            },
          );
        }),
        const SizedBox(height: 14),
        ElevatedButton(onPressed: saving ? null : _saveSettings, child: Text(saving ? 'Saving...' : 'Save selected markets')),
        const SizedBox(height: 10),
        if (JsonTools.boolean(JsonTools.at(settings, 'pair_selection_lock.locked')))
          const Text('Your pair set is currently under the server-controlled selection cooldown.', style: TextStyle(color: AbsColors.gold, fontSize: 12)),
      ],
    );
  }

  Widget _executionTab() {
    return ListView(
      padding: const EdgeInsets.all(18),
      children: [
        const AbsSectionTitle('Execution Setup', subtitle: 'Practice first. Live is visually and technically separated.'),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(value: environment, decoration: const InputDecoration(labelText: 'Trading environment'), items: const [DropdownMenuItem(value: 'testnet', child: Text('Binance Testnet')), DropdownMenuItem(value: 'live', child: Text('Binance LIVE'))], onChanged: (v) => setState(() => environment = v ?? 'testnet')),
        const SizedBox(height: 10),
        DropdownButtonFormField<String>(value: executionMode, decoration: const InputDecoration(labelText: 'Execution mode'), items: const [DropdownMenuItem(value: 'signal_only', child: Text('Signal only')), DropdownMenuItem(value: 'manual', child: Text('Manual execution')), DropdownMenuItem(value: 'automatic', child: Text('Automatic (if permitted)'))], onChanged: (v) => setState(() => executionMode = v ?? 'signal_only')),
        const SizedBox(height: 14),
        Text('Default leverage · ${leverage.round()}x', style: const TextStyle(fontWeight: FontWeight.w700)),
        Slider(value: leverage, min: 1, max: 20, divisions: 19, label: '${leverage.round()}x', onChanged: (v) => setState(() => leverage = v)),
        DropdownButtonFormField<String>(value: marginType, decoration: const InputDecoration(labelText: 'Margin type'), items: const [DropdownMenuItem(value: 'ISOLATED', child: Text('Isolated')), DropdownMenuItem(value: 'CROSSED', child: Text('Crossed'))], onChanged: (v) => setState(() => marginType = v ?? 'ISOLATED')),
        const SizedBox(height: 10),
        DropdownButtonFormField<String>(value: positionMode, decoration: const InputDecoration(labelText: 'Position side'), items: const [DropdownMenuItem(value: 'BOTH', child: Text('One-way / BOTH')), DropdownMenuItem(value: 'LONG', child: Text('LONG')), DropdownMenuItem(value: 'SHORT', child: Text('SHORT'))], onChanged: (v) => setState(() => positionMode = v ?? 'BOTH')),
        const SizedBox(height: 10),
        DropdownButtonFormField<String>(value: orderType, decoration: const InputDecoration(labelText: 'Default order type'), items: const [DropdownMenuItem(value: 'MARKET', child: Text('Market')), DropdownMenuItem(value: 'LIMIT', child: Text('Limit'))], onChanged: (v) => setState(() => orderType = v ?? 'MARKET')),
        const SizedBox(height: 14),
        Text('Minimum signal score · ${minScore.round()}', style: const TextStyle(fontWeight: FontWeight.w700)),
        Slider(value: minScore, min: 0, max: 100, divisions: 20, label: '${minScore.round()}', onChanged: (v) => setState(() => minScore = v)),
        SwitchListTile(contentPadding: EdgeInsets.zero, value: autoTrade, onChanged: executionMode == 'automatic' ? (v) => setState(() => autoTrade = v) : null, title: const Text('Automatic trading'), subtitle: const Text('Only works if server, plan and user permissions allow it.')),
        const SizedBox(height: 10),
        if (environment == 'live')
          const AbsCard(child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(Icons.warning_amber_rounded, color: AbsColors.gold), SizedBox(width: 10), Expanded(child: Text('LIVE selected. ABS still requires an active tested Live connection and server-side Live permission before any order can be submitted.'))])),
        if (environment == 'live') const SizedBox(height: 10),
        ElevatedButton(onPressed: saving ? null : _saveSettings, child: Text(saving ? 'Saving...' : 'Save execution setup')),
        const SizedBox(height: 18),
        OutlinedButton.icon(onPressed: _emergencyStop, icon: const Icon(Icons.emergency_outlined, color: AbsColors.red), label: const Text('Enable Emergency Stop')),
      ],
    );
  }

  Map<String, dynamic> _settingsBody() {
    return {
      'environment': environment,
      'execution_mode': executionMode,
      'auto_trade_enabled': executionMode == 'automatic' && autoTrade,
      'emergency_stop': emergencyStop,
      'default_leverage': leverage.round(),
      'margin_type': marginType,
      'position_mode': positionMode,
      'risk_per_trade_percent': double.tryParse(riskPerTrade.text) ?? JsonTools.number(settings['risk_per_trade_percent'], 1),
      'sizing_mode': sizingMode,
      'fixed_notional': double.tryParse(fixedNotional.text) ?? JsonTools.number(settings['fixed_notional'], 25),
      'fixed_quantity': fixedQuantity.text.trim().isEmpty ? settings['fixed_quantity'] : double.tryParse(fixedQuantity.text),
      'minimum_signal_score': minScore,
      'default_order_type': orderType,
      'take_profit_percent': double.tryParse(tpPercent.text) ?? JsonTools.number(settings['take_profit_percent'], 2),
      'stop_loss_percent': double.tryParse(slPercent.text) ?? JsonTools.number(settings['stop_loss_percent'], 1),
      'daily_loss_limit': double.tryParse(dailyLoss.text) ?? 0,
      'max_open_positions': int.tryParse(maxOpen.text) ?? JsonTools.integer(settings['max_open_positions'], 1),
      'selected_pairs': selectedPairs.toList(),
      'notification_preferences': JsonTools.map(settings['notification_preferences']),
    };
  }

  Future<void> _saveSettings() async {
    setState(() => saving = true);
    try {
      final response = await SessionScope.of(context).api.put('/pulse/settings', body: _settingsBody());
      settings = JsonTools.map(JsonTools.at(response, 'data', settings));
      if (mounted) showSnack(context, JsonTools.text(JsonTools.map(response)['message'], 'Pulse settings updated.'));
      await _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Future<void> _saveRisk() async {
    setState(() => saving = true);
    try {
      final response = await SessionScope.of(context).api.put('/pulse/risk-controls', body: {
        'risk_per_trade_percent': double.tryParse(riskPerTrade.text) ?? 1,
        'daily_loss_limit': double.tryParse(dailyLoss.text) ?? 0,
        'take_profit_percent': double.tryParse(tpPercent.text) ?? 2,
        'stop_loss_percent': double.tryParse(slPercent.text) ?? 1,
        'max_open_positions': int.tryParse(maxOpen.text) ?? 1,
      });
      if (mounted) showSnack(context, JsonTools.text(JsonTools.map(response)['message'], 'Risk controls updated.'));
      await _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Future<void> _addConnection() async {
    final result = await showDialog<bool>(context: context, builder: (_) => const _ConnectionDialog());
    if (result == true) await _load();
  }

  Future<void> _testConnection(Map<String, dynamic> c) async {
    try {
      await SessionScope.of(context).api.post('/pulse/binance/connections/${JsonTools.integer(c['id'])}/test');
      if (mounted) showSnack(context, 'Connection verified.');
      await _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    }
  }

  Future<void> _activateConnection(Map<String, dynamic> c) async {
    try {
      await SessionScope.of(context).api.post('/pulse/binance/connections/${JsonTools.integer(c['id'])}/activate');
      if (mounted) showSnack(context, '${JsonTools.text(c['environment']).toUpperCase()} connection activated.');
      await _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    }
  }

  Future<void> _deleteConnection(Map<String, dynamic> c) async {
    final yes = await showDialog<bool>(context: context, builder: (context) => AlertDialog(
      title: const Text('Delete Binance connection?'),
      content: const Text('The encrypted credentials will be removed from ABS. Existing trade history is not deleted.'),
      actions: [TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')), ElevatedButton(onPressed: () => Navigator.pop(context, true), child: const Text('Delete'))],
    ));
    if (yes != true) return;
    try {
      await SessionScope.of(context).api.delete('/pulse/binance/connections/${JsonTools.integer(c['id'])}');
      await _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    }
  }

  Future<void> _emergencyStop() async {
    final yes = await showDialog<bool>(context: context, builder: (context) => AlertDialog(
      title: const Text('Enable Emergency Stop?'),
      content: const Text('This blocks new trade execution for your account. Existing exchange positions still require appropriate management.'),
      actions: [TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')), ElevatedButton(onPressed: () => Navigator.pop(context, true), child: const Text('Enable Stop'))],
    ));
    if (yes != true) return;
    try {
      await SessionScope.of(context).api.post('/pulse/emergency-stop');
      if (mounted) showSnack(context, 'Emergency stop enabled.');
      await _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    }
  }
}

class _ConnectionDialog extends StatefulWidget {
  const _ConnectionDialog();
  @override
  State<_ConnectionDialog> createState() => _ConnectionDialogState();
}

class _ConnectionDialogState extends State<_ConnectionDialog> {
  String environment = 'testnet';
  final label = TextEditingController(text: 'My Binance Futures');
  final keyController = TextEditingController();
  final secret = TextEditingController();
  bool saving = false;

  @override
  void dispose() { label.dispose(); keyController.dispose(); secret.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) => AlertDialog(
        title: const Text('Add Binance Futures'),
        content: SingleChildScrollView(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            DropdownButtonFormField<String>(value: environment, decoration: const InputDecoration(labelText: 'Environment'), items: const [DropdownMenuItem(value: 'testnet', child: Text('Testnet')), DropdownMenuItem(value: 'live', child: Text('LIVE'))], onChanged: (v) => setState(() => environment = v ?? 'testnet')),
            const SizedBox(height: 10),
            TextField(controller: label, decoration: const InputDecoration(labelText: 'Connection label')),
            const SizedBox(height: 10),
            TextField(controller: keyController, autocorrect: false, decoration: const InputDecoration(labelText: 'API Key')),
            const SizedBox(height: 10),
            TextField(controller: secret, obscureText: true, autocorrect: false, decoration: const InputDecoration(labelText: 'API Secret')),
            const SizedBox(height: 8),
            const Text('Secrets are sent to ABS over HTTPS and encrypted server-side. They are never returned by the API.', style: TextStyle(color: AbsColors.muted, fontSize: 10)),
          ]),
        ),
        actions: [
          TextButton(onPressed: saving ? null : () => Navigator.pop(context, false), child: const Text('Cancel')),
          ElevatedButton(onPressed: saving ? null : _save, child: Text(saving ? 'Saving...' : 'Save')),
        ],
      );

  Future<void> _save() async {
    if (keyController.text.trim().isEmpty || secret.text.trim().isEmpty) return showSnack(context, 'Enter API key and secret.', error: true);
    setState(() => saving = true);
    try {
      await SessionScope.of(context).api.post('/pulse/binance/connections', body: {
        'environment': environment,
        'label': label.text.trim().isEmpty ? 'Binance Futures' : label.text.trim(),
        'api_key': keyController.text.trim(),
        'api_secret': secret.text.trim(),
      });
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }
}
