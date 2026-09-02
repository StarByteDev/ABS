import 'package:flutter/material.dart';
import '../core/api_client.dart';
import '../core/brand.dart';
import '../widgets/pulse_widgets.dart';
import 'login_screen.dart';
import 'plans_screen.dart';
import 'profile_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  final ApiClient api = ApiClient();
  Map<String, dynamic> data = <String, dynamic>{};
  String? error;
  bool loading = true;
  bool scanning = false;
  String displayName = 'Trader';
  List<String> selectedPairs = <String>[];

  @override
  void initState() {
    super.initState();
    loadLocalName();
    load();
  }

  Future<void> loadLocalName() async {
    final name = await api.displayName();
    if (!mounted) return;
    setState(() => displayName = name);
  }

  Future<void> load() async {
    final Map<String, dynamic> res = await api.get('/dashboard');
    if (!mounted) return;
    setState(() {
      data = res;
      final userData = _asMap(res['user']);
      if (userData.isNotEmpty) { api.saveUser(userData); }
      final settings = _asMap(res['settings'] ?? res['trading_settings']);
      selectedPairs = _extractPairs(settings['selected_pairs'] ?? settings['pairs'] ?? res['selected_pairs']);
      error = res['success'] == false ? res['message']?.toString() : null;
      loading = false;
    });
    await loadLocalName();
  }

  Future<void> scan() async {
    setState(() { scanning = true; error = null; });

    final pairs = selectedPairs.isNotEmpty
        ? selectedPairs
        : <String>['BTCUSDT', 'ETHUSDT', 'BNBUSDT', 'SOLUSDT', 'XRPUSDT'];

    final Map<String, dynamic> res = await api.post('/scan-next-batch', <String, dynamic>{
      'selected_pairs': pairs,
      'pairs': pairs,
    });

    if (!mounted) return;

    setState(() {
      final dynamic dashboard = res['dashboard'];
      final Map<String, dynamic> merged = dashboard is Map
          ? Map<String, dynamic>.from(dashboard)
          : <String, dynamic>{...data, ...res};

      // Some Laravel builds return scanner fields at the root level.
      // The mobile UI expects a scanner object, so we normalize it here.
      final scannedSymbols = _asList(res['scanned_symbols']);
      merged['scanner'] = <String, dynamic>{
        ..._asMap(merged['scanner']),
        'progress': res['message'] ?? merged['scanner']?['progress'] ?? 'Batch scan completed.',
        'current_batch': 'Scanned ${scannedSymbols.length} pair(s) from selected market list',
        'last_batch': scannedSymbols.isEmpty ? 'No symbols returned by API' : scannedSymbols.join(', '),
      };

      if (res['stats'] != null) merged['stats'] = res['stats'];
      if (res['signals'] != null) merged['signals'] = res['signals'];
      if (res['latest_signals'] != null) merged['latest_signals'] = res['latest_signals'];

      data = merged;
      error = res['success'] == false ? res['message']?.toString() : null;
      scanning = false;
    });
  }

  Future<void> logout() async {
    await api.post('/logout', <String, dynamic>{});
    await api.clearToken();
    if (!mounted) return;
    Navigator.pushReplacement(context, MaterialPageRoute<void>(builder: (_) => const LoginScreen()));
  }

  @override
  Widget build(BuildContext context) {
    final Map<String, dynamic> user = _asMap(data['user']);
    final Map<String, dynamic> stats = _asMap(data['stats']);
    final Map<String, dynamic> scanner = _asMap(data['scanner']);
    final List<dynamic> signals = _asList(data['latest_signals'] ?? data['signals']);
    final String name = _cleanName(user['name']) ?? displayName;

    return PulseShell(
      child: Column(
        children: <Widget>[
          AppTopBar(
            title: Brand.appName,
            logoOnly: true,
            actions: <Widget>[
              IconButton(tooltip: 'Plans', icon: const Icon(Icons.workspace_premium_outlined, color: Brand.text), onPressed: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => const PlansScreen()))),
              IconButton(tooltip: 'Profile & Settings', icon: const Icon(Icons.settings, color: Brand.text), onPressed: () => Navigator.push(context, MaterialPageRoute<void>(builder: (_) => const ProfileScreen()))),
              IconButton(tooltip: 'Logout', icon: const Icon(Icons.logout, color: Brand.text), onPressed: logout),
            ],
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: load,
              child: loading
                  ? const Center(child: CircularProgressIndicator(color: Brand.green))
                  : ListView(
                      padding: const EdgeInsets.all(16),
                      children: <Widget>[
                        Text('Welcome, $name', style: const TextStyle(color: Brand.text, fontSize: 21, fontWeight: FontWeight.w900)),
                        const SizedBox(height: 4),
                        const Text('Review opportunities, manage selected pairs, and scan the market from your mobile workspace.', style: TextStyle(color: Brand.muted, height: 1.35)),
                        const SizedBox(height: 16),
                        GridView.count(
                          crossAxisCount: 2,
                          shrinkWrap: true,
                          crossAxisSpacing: 10,
                          mainAxisSpacing: 10,
                          childAspectRatio: 1.18,
                          physics: const NeverScrollableScrollPhysics(),
                          children: <Widget>[
                            StatTile(label: 'Signals', value: '${stats['signals'] ?? signals.length}', icon: Icons.show_chart),
                            StatTile(label: 'Active', value: '${stats['active'] ?? stats['active_signals'] ?? 0}', icon: Icons.bolt),
                            StatTile(label: 'Trades', value: '${stats['trades'] ?? 0}', icon: Icons.receipt_long),
                            StatTile(label: 'PnL', value: '${stats['pnl'] ?? stats['local_pnl'] ?? '0.00'}', icon: Icons.account_balance_wallet),
                          ],
                        ),
                        const SizedBox(height: 16),
                        PulseCard(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: <Widget>[
                            const Text('Pulse Scanner', style: TextStyle(color: Brand.text, fontSize: 20, fontWeight: FontWeight.w900)),
                            const SizedBox(height: 10),
                            _line('Progress', '${scanner['progress'] ?? 'waiting'}'),
                            _line('Current batch', '${scanner['current_batch'] ?? 'waiting to start'}', color: Brand.cyan),
                            _line('Last batch', '${scanner['last_batch'] ?? 'none yet'}', color: Brand.green),
                            const SizedBox(height: 10),
                            Text('Selected pairs: ${selectedPairs.isEmpty ? 'Default market list' : selectedPairs.take(8).join(', ')}${selectedPairs.length > 8 ? ' +${selectedPairs.length - 8}' : ''}', style: const TextStyle(color: Brand.muted, height: 1.35)),
                            const SizedBox(height: 16),
                            PulseButton(text: scanning ? 'Scanning...' : 'Start Pulse Scan', icon: Icons.play_arrow, onPressed: scanning ? null : scan),
                            if (error != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(error!, style: const TextStyle(color: Brand.danger, height: 1.35))),
                          ]),
                        ),
                        const SizedBox(height: 22),
                        const SectionTitle('Latest Signals'),
                        const SizedBox(height: 12),
                        if (signals.isEmpty)
                          const PulseCard(child: Text('No signals yet. Start Pulse Scan to search selected markets.', style: TextStyle(color: Brand.text)))
                        else
                          ...signals.map((dynamic item) => _signalCard(_asMap(item))),
                      ],
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _line(String label, String value, {Color color = Brand.muted}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Text('$label: $value', style: TextStyle(color: color, height: 1.3)),
    );
  }

  Widget _signalCard(Map<String, dynamic> signal) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: PulseCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: <Widget>[
          Row(children: <Widget>[
            Expanded(child: Text('${signal['symbol'] ?? '-'}', style: const TextStyle(color: Brand.text, fontSize: 18, fontWeight: FontWeight.w900))),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
              decoration: BoxDecoration(color: Brand.green.withOpacity(.14), borderRadius: BorderRadius.circular(99), border: Border.all(color: Brand.green.withOpacity(.4))),
              child: Text('${signal['side'] ?? signal['signal'] ?? '-'}', style: const TextStyle(color: Brand.green, fontWeight: FontWeight.w900)),
            ),
          ]),
          const SizedBox(height: 8),
          Text('Strategy: ${signal['strategy'] ?? signal['reason'] ?? 'Pulse scanner'}', style: const TextStyle(color: Brand.muted)),
          const SizedBox(height: 6),
          Text('Entry: ${signal['entry'] ?? signal['entry_price'] ?? '-'}   SL: ${signal['sl'] ?? signal['stop_loss'] ?? '-'}   TP: ${signal['tp'] ?? signal['take_profit'] ?? '-'}', style: const TextStyle(color: Brand.text)),
          const SizedBox(height: 6),
          Text('Confidence: ${signal['confidence_score'] ?? signal['confidence'] ?? '-'}', style: const TextStyle(color: Brand.cyan)),
        ]),
      ),
    );
  }

  String? _cleanName(dynamic value) {
    final text = value?.toString().trim();
    if (text == null || text.isEmpty || text.toLowerCase() == 'user') return null;
    return text;
  }

  List<String> _extractPairs(dynamic value) {
    if (value is List) return value.map((e) => e.toString()).where((e) => e.trim().isNotEmpty).toList();
    if (value is String && value.trim().isNotEmpty) return value.split(',').map((e) => e.trim()).where((e) => e.isNotEmpty).toList();
    return <String>[];
  }

  Map<String, dynamic> _asMap(dynamic value) => value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
  List<dynamic> _asList(dynamic value) => value is List ? value : <dynamic>[];
}
