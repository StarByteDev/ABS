import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';
import 'plans_screen.dart';
import 'trade_detail_screen.dart';

class PositionsScreen extends StatefulWidget {
  const PositionsScreen({super.key});
  @override
  State<PositionsScreen> createState() => _PositionsScreenState();
}

class _PositionsScreenState extends State<PositionsScreen> {
  bool loading = true;
  bool syncing = false;
  String? error;
  Map<String, dynamic> payload = {};

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && payload.isEmpty && error == null) _load();
  }

  Future<void> _load() async {
    final session = SessionScope.of(context);
    if (!session.hasPulseAccess) {
      setState(() => loading = false);
      return;
    }
    setState(() {
      loading = true;
      error = null;
    });
    try {
      payload = JsonTools.map(JsonTools.at(await session.api.get('/pulse/positions'), 'data', <String, dynamic>{}));
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _sync() async {
    setState(() => syncing = true);
    try {
      await SessionScope.of(context).api.post('/pulse/trades/sync');
      if (mounted) showSnack(context, 'Exchange state synchronized.');
      await _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => syncing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    if (!session.hasPulseAccess) {
      return AbsPage(
        title: 'Open Positions',
        subtitle: 'ABS + Binance Futures reconciliation',
        child: PulseAccessGate(onViewPlans: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PlansScreen()))),
      );
    }
    return AbsPage(
      title: 'Open Positions',
      subtitle: 'Exchange-confirmed state · guarded closing',
      actions: [IconButton(onPressed: syncing ? null : _sync, tooltip: 'Sync with exchange', icon: syncing ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.sync))],
      child: loading
          ? const LoadingBlock(label: 'Reconciling positions...')
          : error != null
              ? ErrorBlock(message: error!, onRetry: _load)
              : RefreshIndicator(onRefresh: _load, child: _content()),
    );
  }

  Widget _content() {
    final session = SessionScope.of(context);
    final local = JsonTools.mapList(payload['local']);
    final environment = JsonTools.text(payload['environment'], 'testnet').toUpperCase();
    final exchange = JsonTools.map(payload['exchange']);
    final exchangePositions = JsonTools.mapList(exchange['positions'] ?? exchange['open_positions']);
    final totalUnrealized = local.fold<double>(0, (sum, t) => sum + JsonTools.number(t['unrealized_pnl']));
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        PremiumHeroCard(
          eyebrow: 'Portfolio control',
          title: local.isEmpty ? 'No open exposure right now' : '${local.length} position${local.length == 1 ? '' : 's'} under ABS tracking',
          message: session.proMode
              ? 'ABS reconciles local trade state with Binance and keeps closing state separate until the exchange confirms the position is closed.'
              : 'Monitor your position, protection status and P&L here. If you close a trade, ABS waits for Binance confirmation before calling it closed.',
          trailing: Container(
            width: 56,
            height: 56,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: pnlColor(totalUnrealized).withValues(alpha: .09), borderRadius: BorderRadius.circular(18)),
            child: Icon(Icons.candlestick_chart_rounded, color: pnlColor(totalUnrealized), size: 28),
          ),
          footer: Wrap(
            spacing: 7,
            runSpacing: 7,
            children: [
              StatusChip(environment == 'LIVE' ? 'LIVE ACCOUNT' : 'TESTNET', warning: environment == 'LIVE'),
              StatusChip(exchangePositions.isNotEmpty ? 'EXCHANGE SYNC AVAILABLE' : 'ABS TRACKING'),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Row(
          children: [
            Expanded(child: MetricCard(label: 'Open / pending', value: '${local.length}', detail: 'ABS tracked', icon: Icons.layers_outlined)),
            const SizedBox(width: 10),
            Expanded(child: MetricCard(label: 'Unrealized P&L', value: money(totalUnrealized), valueColor: pnlColor(totalUnrealized), detail: 'Current snapshot', icon: Icons.monitor_heart_outlined)),
          ],
        ),
        if (!session.proMode && local.isNotEmpty) ...[
          const SizedBox(height: 12),
          AbsCard(
            accent: AbsColors.cyan,
            child: const Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.shield_outlined, color: AbsColors.cyanSoft, size: 20),
                SizedBox(width: 10),
                Expanded(child: Text('Protection first: confirm TP/SL status before focusing on profit. ABS clearly highlights any position that requires protection review.', style: TextStyle(color: AbsColors.muted, fontSize: 11.5))),
              ],
            ),
          ),
        ],
        if (exchangePositions.isNotEmpty) ...[
          const SizedBox(height: 12),
          AbsCard(
            accent: AbsColors.green,
            child: Row(
              children: [
                const Icon(Icons.verified_outlined, color: AbsColors.green),
                const SizedBox(width: 10),
                Expanded(child: Text('Exchange snapshot available · ${exchangePositions.length} position record(s)', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12))),
              ],
            ),
          ),
        ],
        const SizedBox(height: 20),
        const AbsSectionTitle('Positions', subtitle: 'Submitted → filled → protected → closing → closed'),
        const SizedBox(height: 10),
        if (local.isEmpty)
          const EmptyState(title: 'No open positions', message: 'Executed signals will appear here after ABS receives exchange state.', icon: Icons.candlestick_chart_outlined)
        else
          ...local.map((trade) => _PositionCard(trade: trade, onChanged: _load)),
        const SizedBox(height: 18),
      ],
    );
  }

}

class _PositionCard extends StatelessWidget {
  const _PositionCard({required this.trade, required this.onChanged});
  final Map<String, dynamic> trade;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    final id = JsonTools.integer(trade['id']);
    final status = JsonTools.text(trade['status']).toLowerCase();
    final protection = JsonTools.text(trade['protection_status'], 'pending');
    final canClose = ['open', 'protection_failed'].contains(status);
    final side = JsonTools.text(trade['side'], JsonTools.text(trade['direction'])).toUpperCase();
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: AbsCard(
        child: Column(
          children: [
            Row(
              children: [
                StatusChip(side, good: side.contains('LONG') || side == 'BUY' ? true : side.contains('SHORT') || side == 'SELL' ? false : null, warning: !(side.contains('LONG') || side == 'BUY' || side.contains('SHORT') || side == 'SELL')),
                const SizedBox(width: 9),
                Expanded(child: Text(JsonTools.text(trade['symbol']), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 17))),
                StatusChip(status.toUpperCase(), good: status == 'open', warning: status == 'closing' || status == 'pending'),
              ],
            ),
            const SizedBox(height: 12),
            KeyValueRow('Entry', money(trade['entry_price'] ?? trade['average_entry_price'])),
            KeyValueRow('Current', money(trade['current_price'] ?? trade['mark_price'])),
            KeyValueRow('Quantity', number(trade['quantity'], digits: 6)),
            KeyValueRow('Unrealized P&L', money(trade['unrealized_pnl']), valueColor: pnlColor(trade['unrealized_pnl'])),
            KeyValueRow('Protection', protection.replaceAll('_', ' ').toUpperCase(), valueColor: protection == 'failed' || protection == 'review_required' ? AbsColors.red : null),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(child: OutlinedButton(onPressed: id > 0 ? () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => TradeDetailScreen(tradeId: id))) : null, child: const Text('View Trade'))),
                if (canClose) ...[
                  const SizedBox(width: 8),
                  Expanded(child: ElevatedButton(onPressed: id > 0 ? () => _close(context, id) : null, child: const Text('Close Position'))),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _close(BuildContext context, int id) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Close this position?'),
        content: const Text('ABS will submit a guarded reduce-only close request to Binance. The trade will not be marked closed until exchange state confirms it.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Keep open')),
          ElevatedButton(onPressed: () => Navigator.pop(context, true), child: const Text('Request close')),
        ],
      ),
    );
    if (confirm != true) return;
    try {
      await SessionScope.of(context).api.post('/pulse/trades/$id/close');
      if (context.mounted) showSnack(context, 'Close request processed.');
      onChanged();
    } on ApiException catch (e) {
      if (context.mounted) showSnack(context, e.message, error: true);
    }
  }
}
