import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';

class SignalDetailScreen extends StatefulWidget {
  const SignalDetailScreen({super.key, required this.signalId});
  final int signalId;

  @override
  State<SignalDetailScreen> createState() => _SignalDetailScreenState();
}

class _SignalDetailScreenState extends State<SignalDetailScreen> {
  bool loading = true;
  bool executing = false;
  String? error;
  Map<String, dynamic> signal = {};
  Map<String, dynamic> ticketData = {};
  Map<String, dynamic> validation = {};

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && signal.isEmpty && error == null) _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    final api = SessionScope.of(context).api;
    try {
      final values = await Future.wait([
        api.get('/pulse/signals/${widget.signalId}'),
        api.get('/pulse/execution/ticket', query: {'signal_id': widget.signalId}),
        api.get('/pulse/signals/${widget.signalId}/validation').catchError((_) => <String, dynamic>{}),
      ]);
      signal = JsonTools.map(JsonTools.at(values[0], 'data', <String, dynamic>{}));
      ticketData = JsonTools.map(JsonTools.at(values[1], 'data', <String, dynamic>{}));
      validation = JsonTools.map(JsonTools.at(values[2], 'data', <String, dynamic>{}));
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AbsPage(
      title: 'Trade Review',
      subtitle: 'Signal #${widget.signalId} · server-authoritative execution checks',
      actions: [IconButton(onPressed: _load, icon: const Icon(Icons.refresh))],
      child: loading
          ? const LoadingBlock(label: 'Preparing execution ticket...')
          : error != null
              ? ErrorBlock(message: error!, onRetry: _load)
              : ListView(children: _content()),
    );
  }

  List<Widget> _content() {
    final session = SessionScope.of(context);
    final selected = JsonTools.map(ticketData['selected_signal']);
    final detail = selected.isNotEmpty ? selected : signal;
    final ticket = JsonTools.map(ticketData['ticket']);
    final calc = JsonTools.map(ticketData['calculation']);
    final account = JsonTools.map(ticketData['account']);
    final settings = JsonTools.map(ticketData['settings']);
    final checks = JsonTools.mapList(ticketData['checks']);
    final ready = JsonTools.boolean(ticketData['execution_ready']);
    final direction = JsonTools.text(detail['direction'], JsonTools.text(signal['direction'])).toUpperCase();
    final environment = JsonTools.text(settings['environment'], 'testnet').toUpperCase();
    final validationStatus = JsonTools.text(validation['status'], JsonTools.text(validation['outcome'], 'waiting_entry'));
    final symbol = JsonTools.text(detail['symbol'], JsonTools.text(signal['symbol']));
    final directionColor = direction == 'LONG' ? AbsColors.green : direction == 'SHORT' ? AbsColors.red : AbsColors.gold;

    return [
      PremiumHeroCard(
        eyebrow: ready ? 'Ready for review' : 'Execution checks',
        title: '$symbol · $direction',
        message: session.proMode
            ? 'Review the server-authoritative ticket, risk calculation and execution checks before submitting to Binance.'
            : ready
                ? 'ABS has prepared the trade ticket. Check entry, take profit, stop loss and estimated risk before you confirm.'
                : 'This signal is not ready to execute yet. ABS will show exactly which safety check still needs attention.',
        trailing: Container(
          width: 58,
          height: 58,
          alignment: Alignment.center,
          decoration: BoxDecoration(color: directionColor.withValues(alpha: .09), borderRadius: BorderRadius.circular(18), border: Border.all(color: directionColor.withValues(alpha: .22))),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(number(detail['score'] ?? signal['score'], digits: 0), style: TextStyle(color: directionColor, fontWeight: FontWeight.w900, fontSize: 18, height: 1)),
              const SizedBox(height: 2),
              const Text('SCORE', style: TextStyle(color: AbsColors.muted, fontSize: 7.5, fontWeight: FontWeight.w900, letterSpacing: .6)),
            ],
          ),
        ),
        footer: Wrap(
          spacing: 7,
          runSpacing: 7,
          children: [
            StatusChip(direction, good: direction == 'LONG' ? true : direction == 'SHORT' ? false : null, warning: direction != 'LONG' && direction != 'SHORT'),
            StatusChip(environment == 'LIVE' ? 'LIVE ACCOUNT' : 'TESTNET', warning: environment == 'LIVE'),
            StatusChip(ready ? 'READY' : 'CHECKS REQUIRED', good: ready),
          ],
        ),
      ),
      const SizedBox(height: 14),
      AbsCard(
        accent: directionColor,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const AbsSectionTitle('Trade plan', subtitle: 'The values that define this setup.'),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(child: _TradePlanMetric(label: 'Current ABS price', value: money(detail['last_price'] ?? signal['last_price']))),
                const SizedBox(width: 8),
                Expanded(child: _TradePlanMetric(label: 'Entry / limit', value: money(ticket['limit_price'] ?? detail['entry_price'] ?? signal['entry_price']))),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(child: _TradePlanMetric(label: 'Take profit', value: money(ticket['take_profit'] ?? detail['take_profit'] ?? signal['take_profit']), valueColor: AbsColors.green)),
                const SizedBox(width: 8),
                Expanded(child: _TradePlanMetric(label: 'Stop loss', value: money(ticket['stop_loss'] ?? detail['stop_loss'] ?? signal['stop_loss']), valueColor: AbsColors.red)),
              ],
            ),
            const SizedBox(height: 10),
            KeyValueRow('Order type', JsonTools.text(ticket['order_type'], 'LIMIT')),
            KeyValueRow('Quantity', number(ticket['quantity'], digits: 6)),
            KeyValueRow('Leverage', '${JsonTools.integer(ticket['leverage'], 1)}x'),
            KeyValueRow('Validation state', validationStatus.replaceAll('_', ' ').toUpperCase()),
          ],
        ),
      ),
      if (!session.proMode) ...[
        const SizedBox(height: 12),
        AbsCard(
          accent: AbsColors.cyan,
          child: const Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.school_outlined, color: AbsColors.cyanSoft, size: 20),
              SizedBox(width: 10),
              Expanded(child: Text('Before you confirm: Entry is where ABS intends the trade to open. Take Profit is the planned profit exit. Stop Loss is the protection exit. Live orders can use real funds, so review all three before execution.', style: TextStyle(color: AbsColors.muted, fontSize: 11.5))),
            ],
          ),
        ),
      ],
      const SizedBox(height: 18),
      const AbsSectionTitle('Pre-trade safeguards', subtitle: 'Every required check must pass on the ABS server.'),
      const SizedBox(height: 10),
      AbsCard(
        child: Column(
          children: checks.isEmpty
              ? [const Text('Execution checks are unavailable.', style: TextStyle(color: AbsColors.muted))]
              : checks.map((check) {
                  final passed = JsonTools.boolean(check['passed']);
                  return Padding(
                    padding: const EdgeInsets.symmetric(vertical: 7),
                    child: Row(
                      children: [
                        Container(
                          width: 28,
                          height: 28,
                          decoration: BoxDecoration(color: (passed ? AbsColors.green : AbsColors.red).withValues(alpha: .09), borderRadius: BorderRadius.circular(9)),
                          child: Icon(passed ? Icons.check_rounded : Icons.close_rounded, color: passed ? AbsColors.green : AbsColors.red, size: 17),
                        ),
                        const SizedBox(width: 10),
                        Expanded(child: Text(JsonTools.text(check['label']), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12.5))),
                        Text(JsonTools.text(check['value']), style: const TextStyle(color: AbsColors.muted, fontSize: 10.5)),
                      ],
                    ),
                  );
                }).toList(),
        ),
      ),
      const SizedBox(height: 18),
      AbsSectionTitle('Risk & margin', subtitle: session.proMode ? 'Full server-calculated account context.' : 'The most important risk numbers first.'),
      const SizedBox(height: 10),
      AbsCard(
        child: Column(
          children: [
            KeyValueRow('Estimated risk', money(calc['estimated_risk']), valueColor: AbsColors.gold),
            KeyValueRow('Potential reward', money(calc['potential_reward']), valueColor: AbsColors.green),
            KeyValueRow('Risk / reward', number(calc['risk_reward'], digits: 2)),
            KeyValueRow('Available balance', money(calc['available_balance'])),
            if (session.proMode) ...[
              KeyValueRow('Notional value', money(calc['notional_value'])),
              KeyValueRow('Required margin', money(calc['required_margin'])),
              KeyValueRow('Account equity', money(account['equity'])),
              KeyValueRow('Current exposure', percent(account['current_exposure'])),
            ] else
              ExpansionTile(
                tilePadding: EdgeInsets.zero,
                childrenPadding: EdgeInsets.zero,
                title: const Text('Show professional details', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 12)),
                children: [
                  KeyValueRow('Notional value', money(calc['notional_value'])),
                  KeyValueRow('Required margin', money(calc['required_margin'])),
                  KeyValueRow('Account equity', money(account['equity'])),
                  KeyValueRow('Current exposure', percent(account['current_exposure'])),
                ],
              ),
          ],
        ),
      ),
      const SizedBox(height: 16),
      if (environment == 'LIVE')
        const AbsCard(
          accent: AbsColors.gold,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.warning_amber_rounded, color: AbsColors.gold),
              SizedBox(width: 10),
              Expanded(child: Text('LIVE account selected. Confirming can place a real Binance Futures order. Review entry, quantity, leverage, TP and SL carefully.', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12))),
            ],
          ),
        ),
      if (environment == 'LIVE') const SizedBox(height: 12),
      SizedBox(
        width: double.infinity,
        child: ElevatedButton.icon(
          onPressed: ready && !executing ? () => _confirmAndExecute(ticket, settings, direction, environment) : null,
          icon: executing ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : Icon(ready ? Icons.verified_user_outlined : Icons.lock_outline_rounded),
          label: Text(executing ? 'Submitting to ABS...' : ready ? 'Review & Confirm Trade' : 'Resolve blocked checks first'),
        ),
      ),
      const SizedBox(height: 10),
      const Text('ABS does not treat a submitted order as an open position until Binance confirms the entry fill and protection state.', textAlign: TextAlign.center, style: TextStyle(color: AbsColors.muted, fontSize: 10.5)),
      const SizedBox(height: 30),
    ];
  }


  Future<void> _confirmAndExecute(Map<String, dynamic> ticket, Map<String, dynamic> settings, String direction, String environment) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(environment == 'LIVE' ? 'Confirm LIVE order' : 'Confirm Testnet order'),
        content: Text(environment == 'LIVE'
            ? 'This can place a real Binance Futures order. ABS will use the server-validated ticket and protection workflow.'
            : 'Submit this trade to your Binance Futures Testnet connection?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          ElevatedButton(onPressed: () => Navigator.pop(context, true), child: Text(environment == 'LIVE' ? 'Execute LIVE' : 'Execute Testnet')),
        ],
      ),
    );
    if (confirmed != true) return;
    setState(() => executing = true);
    try {
      final body = <String, dynamic>{
        'environment': JsonTools.text(settings['environment'], 'testnet'),
        'order_type': JsonTools.text(ticket['order_type'], 'LIMIT').toUpperCase(),
        'leverage': JsonTools.integer(ticket['leverage'], 1),
        'quantity': JsonTools.number(ticket['quantity']) > 0 ? JsonTools.number(ticket['quantity']) : null,
        'notional': null,
        'price': JsonTools.number(ticket['limit_price']) > 0 ? JsonTools.number(ticket['limit_price']) : null,
        'stop_loss': JsonTools.number(ticket['stop_loss']),
        'take_profit': JsonTools.number(ticket['take_profit']),
        'position_side': JsonTools.text(settings['position_mode'], 'BOTH').toUpperCase(),
        'time_in_force': JsonTools.text(ticket['time_in_force'], 'GTC').toUpperCase(),
        'client_reference': JsonTools.text(ticket['client_reference'], ''),
        'confirmed_review': true,
      };
      final response = await SessionScope.of(context).api.post('/pulse/signals/${widget.signalId}/execute', body: body);
      if (!mounted) return;
      showSnack(context, JsonTools.text(JsonTools.map(response)['message'], 'Order request accepted.'));
      await _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => executing = false);
    }
  }
}

class _TradePlanMetric extends StatelessWidget {
  const _TradePlanMetric({required this.label, required this.value, this.valueColor});
  final String label;
  final String value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: AbsColors.panel2.withValues(alpha: .75), borderRadius: BorderRadius.circular(14), border: Border.all(color: AbsColors.lineSoft)),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: const TextStyle(color: AbsColors.muted, fontSize: 9.5, fontWeight: FontWeight.w700)),
            const SizedBox(height: 5),
            Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: valueColor ?? AbsColors.text, fontSize: 13, fontWeight: FontWeight.w900)),
          ],
        ),
      );
}

