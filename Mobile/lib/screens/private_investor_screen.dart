import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/api_client.dart';
import '../core/app_config.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';

class PrivateInvestorHubScreen extends StatefulWidget {
  const PrivateInvestorHubScreen({super.key});

  @override
  State<PrivateInvestorHubScreen> createState() => _PrivateInvestorHubScreenState();
}

class _PrivateInvestorHubScreenState extends State<PrivateInvestorHubScreen> {
  bool loading = true;
  String? error;
  int section = 0;
  Map<String, dynamic> account = {};

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && account.isEmpty) _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final response = await SessionScope.of(context).api.get('/private/account');
      account = JsonTools.map(JsonTools.at(response, 'data', <String, dynamic>{}));
    } on ApiException catch (e) {
      error = e.statusCode == 403
          ? 'Private Investor access is not enabled for this ABS account.'
          : e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  dynamic _first(List<String> paths, [dynamic fallback]) {
    for (final path in paths) {
      final value = JsonTools.at(account, path);
      if (value == null) continue;
      if (value is String && value.trim().isEmpty) continue;
      return value;
    }
    return fallback;
  }

  List<Map<String, dynamic>> _list(List<String> paths) {
    for (final path in paths) {
      final rows = JsonTools.mapList(JsonTools.at(account, path, const <dynamic>[]));
      if (rows.isNotEmpty) return rows;
    }
    return const <Map<String, dynamic>>[];
  }

  @override
  Widget build(BuildContext context) {
    return AbsPage(
      title: 'Private Investor',
      subtitle: 'Investment terms, performance, statements and requests',
      actions: [
        IconButton(onPressed: _load, icon: const Icon(Icons.refresh_rounded), tooltip: 'Refresh'),
      ],
      child: loading
          ? const LoadingBlock()
          : error != null
              ? _AccessError(message: error!, onRetry: _load)
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    children: [
                      _hero(),
                      const SizedBox(height: 14),
                      _sectionPicker(),
                      const SizedBox(height: 16),
                      if (section == 0) ..._overview(),
                      if (section == 1) ..._performance(),
                      if (section == 2) ..._statements(),
                      if (section == 3) ..._requests(),
                      const SizedBox(height: 28),
                    ],
                  ),
                ),
    );
  }

  Widget _hero() {
    final currency = JsonTools.text(_first([
      'principal.currency',
      'investment.currency',
      'original_currency',
      'currency',
    ], 'USD'));
    final principal = _first([
      'principal.amount',
      'investment.principal_amount',
      'original_principal_amount',
      'principal_amount',
      'net_contributions',
      'current_value',
    ]);
    final usdEquivalent = _first([
      'principal.usd_equivalent',
      'investment.usd_equivalent',
      'principal_usd_equivalent',
      'usd_equivalent',
    ]);
    final status = JsonTools.text(_first([
      'agreement.status',
      'investment.status',
      'status',
    ], JsonTools.boolean(account['is_active'], true) ? 'active' : 'inactive')).toUpperCase();
    final totalPaid = _first([
      'summary.profit_paid',
      'performance.total_profit_paid',
      'profit_paid',
      'total_profit_paid',
      'total_profit',
    ]);

    return PremiumHeroCard(
      eyebrow: 'V15.7.4 INVESTOR ACCOUNT',
      title: JsonTools.text(_first(['account_name', 'investment.name']), 'Private Investor Account'),
      message: 'Your principal, accrued performance, paid profit and statements are shown separately so the account remains easy to understand.',
      trailing: Container(
        width: 58,
        height: 58,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: AbsColors.gold.withOpacity(.10),
          border: Border.all(color: AbsColors.gold.withOpacity(.30)),
        ),
        child: const Icon(Icons.account_balance_rounded, color: AbsColors.gold, size: 29),
      ),
      footer: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 7,
            runSpacing: 7,
            children: [
              StatusChip(status, good: status == 'ACTIVE'),
              StatusChip('$currency ${number(principal)} PRINCIPAL', warning: true),
              if (usdEquivalent != null) StatusChip('USD ${number(usdEquivalent)} AT START'),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: _HeroMetric(label: 'Principal', value: '$currency ${number(principal)}')),
              const SizedBox(width: 8),
              Expanded(child: _HeroMetric(label: 'Profit paid', value: '$currency ${number(totalPaid)}', valueColor: AbsColors.green)),
            ],
          ),
        ],
      ),
    );
  }

  Widget _sectionPicker() {
    const labels = ['Overview', 'Performance', 'Statements', 'Requests'];
    const icons = [Icons.dashboard_outlined, Icons.query_stats_rounded, Icons.receipt_long_outlined, Icons.swap_horiz_rounded];
    return SizedBox(
      height: 42,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: labels.length,
        separatorBuilder: (_, __) => const SizedBox(width: 7),
        itemBuilder: (_, index) {
          final selected = section == index;
          return InkWell(
            borderRadius: BorderRadius.circular(13),
            onTap: () => setState(() => section = index),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 160),
              padding: const EdgeInsets.symmetric(horizontal: 12),
              decoration: BoxDecoration(
                color: selected ? AbsColors.panel3 : AbsColors.panel,
                borderRadius: BorderRadius.circular(13),
                border: Border.all(color: selected ? AbsColors.cyan.withOpacity(.45) : AbsColors.lineSoft),
              ),
              child: Row(
                children: [
                  Icon(icons[index], size: 16, color: selected ? AbsColors.cyan : AbsColors.muted),
                  const SizedBox(width: 6),
                  Text(labels[index], style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w900, color: selected ? AbsColors.text : AbsColors.muted)),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  List<Widget> _overview() {
    final currency = JsonTools.text(_first(['principal.currency', 'investment.currency', 'original_currency', 'currency'], 'USD'));
    final principal = _first(['principal.amount', 'investment.principal_amount', 'original_principal_amount', 'principal_amount', 'net_contributions', 'current_value']);
    final rate = JsonTools.number(_first(['agreement.monthly_rate', 'agreement.monthly_performance_rate', 'monthly_rate', 'monthly_performance_rate']));
    final ratePct = rate > 0 && rate <= 1 ? rate * 100 : rate;
    final start = _first(['agreement.effective_date', 'investment.effective_date', 'performance_start_date', 'start_date']);
    final payoutDay = JsonTools.integer(_first(['agreement.payout_day', 'settings.payout_day', 'payout_day'], 0));
    final totalPaid = _first(['summary.profit_paid', 'performance.total_profit_paid', 'profit_paid', 'total_profit_paid', 'total_profit']);
    final withdrawn = _first(['summary.capital_withdrawn', 'capital_withdrawn', 'capital_withdrawals'], 0);

    return [
      const AbsSectionTitle('Investment agreement', eyebrow: 'Your terms', subtitle: 'The values ABS uses for automatic performance tracking.'),
      const SizedBox(height: 10),
      AbsCard(
        child: Column(
          children: [
            KeyValueRow('Principal', '$currency ${number(principal)}'),
            KeyValueRow('Effective date', compactDate(start)),
            KeyValueRow('Agreed monthly rate', ratePct > 0 ? '${ratePct.toStringAsFixed(2)}%' : 'Set by Admin'),
            KeyValueRow('Profit settlement', payoutDay > 0 ? 'Day $payoutDay of following month' : 'Month-end / Admin schedule'),
            KeyValueRow('Profit paid', '$currency ${number(totalPaid)}', valueColor: AbsColors.green),
            KeyValueRow('Capital withdrawn', '$currency ${number(withdrawn)}', valueColor: JsonTools.number(withdrawn) > 0 ? AbsColors.gold : null),
          ],
        ),
      ),
      const SizedBox(height: 16),
      const AbsSectionTitle('How ABS accounts for your money', eyebrow: 'Simple explanation'),
      const SizedBox(height: 10),
      const _RuleCard(
        icon: Icons.savings_outlined,
        title: 'Principal stays separate',
        message: 'Your investment principal remains the capital amount. Paid monthly profit is not added to or deducted from principal unless a separate capital transaction is recorded.',
      ),
      const SizedBox(height: 8),
      const _RuleCard(
        icon: Icons.payments_outlined,
        title: 'Profit Paid means profit distribution',
        message: 'A Profit Paid entry represents profit distributed outside the portfolio. It has zero investor-capital effect in the V15.7.4 accounting model.',
      ),
      const SizedBox(height: 8),
      const _RuleCard(
        icon: Icons.account_balance_wallet_outlined,
        title: 'Capital Withdrawal means principal return',
        message: 'Capital Withdrawal is used only when part of the investment principal is returned. It is not used for normal monthly profit payouts.',
      ),
      const SizedBox(height: 8),
      const _RuleCard(
        icon: Icons.currency_exchange_rounded,
        title: 'Original currency is preserved',
        message: 'Your principal remains in its original currency and amount. ABS may also keep the USD equivalent captured at the investment effective date for consolidated reporting.',
      ),
    ];
  }

  List<Widget> _performance() {
    final currency = JsonTools.text(_first(['principal.currency', 'investment.currency', 'original_currency', 'currency'], 'USD'));
    final progress = JsonTools.map(_first(['performance.current_month', 'current_month', 'monthly_progress'], <String, dynamic>{}));
    final target = _valueFrom(progress, ['target_profit', 'target', 'monthly_target'], _first(['monthly_target_profit', 'target_profit']));
    final accrued = _valueFrom(progress, ['accrued_profit', 'earned', 'progress_amount'], _first(['accrued_profit', 'monthly_profit']));
    final progressPct = JsonTools.number(_valueFrom(progress, ['progress_percent', 'progress_pct'], 0));
    final months = _list(['performance.months', 'monthly_progress', 'progress_history', 'performance_history']);
    final transactions = _list(['transactions', 'activity', 'ledger']);

    return [
      const AbsSectionTitle('Current month', eyebrow: 'Automatic progress', subtitle: 'Daily progress is derived by the backend from your agreed monthly terms.'),
      const SizedBox(height: 10),
      AbsCard(
        accent: AbsColors.cyan,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(child: MetricCard(label: 'Target profit', value: '$currency ${number(target)}', icon: Icons.flag_outlined)),
                const SizedBox(width: 8),
                Expanded(child: MetricCard(label: 'Accrued', value: '$currency ${number(accrued)}', valueColor: AbsColors.green, icon: Icons.trending_up_rounded)),
              ],
            ),
            const SizedBox(height: 12),
            _ProgressBar(value: progressPct > 1 ? progressPct / 100 : progressPct),
            const SizedBox(height: 7),
            Text(progressPct > 0 ? '${progressPct.toStringAsFixed(1)}% of this month\'s target accrued' : 'Progress updates automatically from the server.', style: const TextStyle(color: AbsColors.muted, fontSize: 10.5)),
          ],
        ),
      ),
      const SizedBox(height: 18),
      const AbsSectionTitle('Monthly performance', subtitle: 'Completed and in-progress performance months.'),
      const SizedBox(height: 10),
      if (months.isEmpty)
        const EmptyState(title: 'No monthly history returned', message: 'Completed performance months will appear here when exposed by the server.', icon: Icons.query_stats_rounded)
      else
        ...months.take(24).map((m) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: AbsCard(
                padding: const EdgeInsets.all(13),
                child: Row(
                  children: [
                    Container(width: 38, height: 38, alignment: Alignment.center, decoration: BoxDecoration(color: AbsColors.panel2, borderRadius: BorderRadius.circular(11)), child: const Icon(Icons.calendar_month_outlined, color: AbsColors.cyan, size: 19)),
                    const SizedBox(width: 10),
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(JsonTools.text(m['month'] ?? m['performance_month'] ?? m['statement_month'], 'Performance month'), style: const TextStyle(fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text('${JsonTools.text(m['status'], 'Tracked').toUpperCase()} · ${number(m['active_days'] ?? m['days_active'])} active days', style: const TextStyle(color: AbsColors.muted, fontSize: 10))])),
                    Text('$currency ${number(m['profit'] ?? m['profit_paid'] ?? m['earned'])}', style: const TextStyle(fontWeight: FontWeight.w900, color: AbsColors.green)),
                  ],
                ),
              ),
            )),
      if (transactions.isNotEmpty) ...[
        const SizedBox(height: 18),
        const AbsSectionTitle('Recent account activity'),
        const SizedBox(height: 10),
        ...transactions.take(20).map((t) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: AbsCard(
                padding: const EdgeInsets.all(13),
                child: Row(
                  children: [
                    Icon(_transactionIcon(t), color: _transactionColor(t), size: 20),
                    const SizedBox(width: 10),
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(_transactionTitle(t), style: const TextStyle(fontWeight: FontWeight.w800)), const SizedBox(height: 3), Text(compactDate(t['transaction_date'] ?? t['date'] ?? t['created_at']), style: const TextStyle(color: AbsColors.muted, fontSize: 10))])),
                    Text('$currency ${number(t['amount'])}', style: TextStyle(fontWeight: FontWeight.w900, color: _transactionColor(t))),
                  ],
                ),
              ),
            )),
      ],
    ];
  }

  List<Widget> _statements() {
    final statements = _list(['statements', 'monthly_statements']);
    return [
      const AbsSectionTitle('Monthly statements', eyebrow: 'Reconciled reporting', subtitle: 'Completed due months can automatically create or reconcile statements in V15.7.4.'),
      const SizedBox(height: 10),
      if (statements.isEmpty)
        const EmptyState(title: 'No statements yet', message: 'Your monthly statements will appear here after they are published or automatically generated by ABS.', icon: Icons.receipt_long_outlined)
      else
        ...statements.map((s) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: InkWell(
                borderRadius: BorderRadius.circular(18),
                onTap: () {
                  final id = JsonTools.integer(s['id']);
                  if (id <= 0) return;
                  Navigator.of(context).push(MaterialPageRoute(builder: (_) => PrivateInvestorStatementScreen(statementId: id, initial: s)));
                },
                child: AbsCard(
                  child: Row(
                    children: [
                      Container(width: 42, height: 42, alignment: Alignment.center, decoration: BoxDecoration(color: AbsColors.panel2, borderRadius: BorderRadius.circular(12)), child: const Icon(Icons.receipt_long_outlined, color: AbsColors.cyan)),
                      const SizedBox(width: 11),
                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(JsonTools.text(s['statement_month'] ?? s['month'], 'Monthly statement'), style: const TextStyle(fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text('Profit paid ${number(s['profit_paid'] ?? s['profit_loss'] ?? s['monthly_profit'])} · Principal ${number(s['closing_principal'] ?? s['closing_balance'])}', style: const TextStyle(color: AbsColors.muted, fontSize: 10.5))])),
                      StatusChip(JsonTools.text(s['status'], 'READY').toUpperCase(), good: true),
                      const SizedBox(width: 4),
                      const Icon(Icons.chevron_right_rounded, color: AbsColors.muted2),
                    ],
                  ),
                ),
              ),
            )),
    ];
  }

  List<Widget> _requests() {
    final requests = _list(['requests', 'account_requests']);
    return [
      const AbsSectionTitle('Investment requests', eyebrow: 'Member actions', subtitle: 'Request additional investment or principal withdrawal without mixing it with monthly profit payouts.'),
      const SizedBox(height: 10),
      Row(
        children: [
          Expanded(child: ElevatedButton.icon(onPressed: () => _requestDialog('investment'), icon: const Icon(Icons.add_card_rounded), label: const Text('Add investment'))),
          const SizedBox(width: 8),
          Expanded(child: OutlinedButton.icon(onPressed: () => _requestDialog('withdrawal'), icon: const Icon(Icons.outbox_outlined), label: const Text('Withdraw principal'))),
        ],
      ),
      const SizedBox(height: 12),
      const AbsCard(
        accent: AbsColors.gold,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.info_outline_rounded, color: AbsColors.gold),
            SizedBox(width: 9),
            Expanded(child: Text('Monthly profit payout is not a capital withdrawal. Use the withdrawal request only when you want part of your investment principal returned.', style: TextStyle(color: AbsColors.muted, fontSize: 11.5, height: 1.4))),
          ],
        ),
      ),
      const SizedBox(height: 16),
      if (requests.isEmpty)
        const EmptyState(title: 'No requests returned', message: 'Submitted investor requests will appear here when the backend includes them in your account payload.', icon: Icons.swap_horiz_rounded)
      else
        ...requests.map((r) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: AbsCard(
                padding: const EdgeInsets.all(13),
                child: Row(
                  children: [
                    Icon(JsonTools.text(r['type']).toLowerCase().contains('withdraw') ? Icons.outbox_outlined : Icons.add_card_rounded, color: AbsColors.cyan),
                    const SizedBox(width: 10),
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(JsonTools.text(r['type'], 'Investment request').replaceAll('_', ' '), style: const TextStyle(fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(compactDate(r['requested_at'] ?? r['created_at']), style: const TextStyle(color: AbsColors.muted, fontSize: 10))])),
                    StatusChip(JsonTools.text(r['status'], 'PENDING').toUpperCase(), good: JsonTools.text(r['status']).toLowerCase() == 'approved', warning: JsonTools.text(r['status']).toLowerCase() == 'pending'),
                  ],
                ),
              ),
            )),
      const SizedBox(height: 12),
      TextButton.icon(onPressed: _openWebPortal, icon: const Icon(Icons.open_in_new_rounded), label: const Text('Open secure web investor portal')),
    ];
  }

  Future<void> _requestDialog(String type) async {
    final amount = TextEditingController();
    final notes = TextEditingController();
    final currency = JsonTools.text(_first(['principal.currency', 'investment.currency', 'original_currency', 'currency'], 'USD'));
    final submitted = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AbsColors.panel,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(26))),
      builder: (sheetContext) => Padding(
        padding: EdgeInsets.fromLTRB(18, 18, 18, MediaQuery.of(sheetContext).viewInsets.bottom + 22),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(type == 'investment' ? 'Request additional investment' : 'Request principal withdrawal', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900)),
            const SizedBox(height: 6),
            Text(type == 'investment' ? 'Send the amount you want to add for Admin review.' : 'This request is for return of principal only — not monthly profit payout.', style: const TextStyle(color: AbsColors.muted, fontSize: 11.5)),
            const SizedBox(height: 14),
            TextField(controller: amount, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: 'Amount ($currency)')),
            const SizedBox(height: 10),
            TextField(controller: notes, minLines: 2, maxLines: 4, decoration: const InputDecoration(labelText: 'Notes (optional)')),
            const SizedBox(height: 14),
            SizedBox(width: double.infinity, child: ElevatedButton(onPressed: () => Navigator.of(sheetContext).pop(true), child: const Text('Submit request'))),
          ],
        ),
      ),
    );
    if (submitted != true || !mounted) return;
    final parsed = double.tryParse(amount.text.trim());
    if (parsed == null || parsed <= 0) {
      showSnack(context, 'Enter a valid amount greater than zero.', error: true);
      return;
    }

    final api = SessionScope.of(context).api;
    final body = <String, dynamic>{
      'type': type == 'investment' ? 'additional_investment' : 'capital_withdrawal',
      'amount': parsed,
      'currency': currency,
      'notes': notes.text.trim(),
    };
    try {
      await _postFirst(api, const ['/private/requests', '/private/account/requests'], body);
      if (!mounted) return;
      showSnack(context, 'Request submitted for Admin review.');
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      if (e.statusCode == 404 || e.statusCode == 405) {
        showSnack(context, 'This backend does not expose investor requests to mobile yet. Opening the secure web portal.', error: true);
        await _openWebPortal();
      } else {
        showSnack(context, e.message, error: true);
      }
    }
  }

  Future<dynamic> _postFirst(ApiClient api, List<String> paths, Map<String, dynamic> body) async {
    ApiException? last;
    for (final path in paths) {
      try {
        return await api.post(path, body: body);
      } on ApiException catch (e) {
        last = e;
        if (e.statusCode != 404 && e.statusCode != 405) rethrow;
      }
    }
    throw last ?? const ApiException('Investor request endpoint is unavailable.', statusCode: 404);
  }

  Future<void> _openWebPortal() async {
    final uri = Uri.parse('${AppConfig.website}/private');
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  dynamic _valueFrom(Map<String, dynamic> source, List<String> keys, [dynamic fallback]) {
    for (final key in keys) {
      final value = source[key];
      if (value != null && value.toString().trim().isNotEmpty) return value;
    }
    return fallback;
  }

  String _transactionTitle(Map<String, dynamic> row) {
    final raw = JsonTools.text(row['description'] ?? row['type'], 'Account activity');
    return raw.replaceAll('_', ' ');
  }

  Color _transactionColor(Map<String, dynamic> row) {
    final type = JsonTools.text(row['type']).toLowerCase();
    if (type.contains('profit')) return AbsColors.green;
    if (type.contains('withdraw')) return AbsColors.gold;
    if (type.contains('invest') || type.contains('deposit')) return AbsColors.cyan;
    return AbsColors.muted;
  }

  IconData _transactionIcon(Map<String, dynamic> row) {
    final type = JsonTools.text(row['type']).toLowerCase();
    if (type.contains('profit')) return Icons.payments_outlined;
    if (type.contains('withdraw')) return Icons.outbox_outlined;
    if (type.contains('invest') || type.contains('deposit')) return Icons.add_card_rounded;
    return Icons.swap_horiz_rounded;
  }
}

class PrivateInvestorStatementScreen extends StatefulWidget {
  const PrivateInvestorStatementScreen({super.key, required this.statementId, this.initial});

  final int statementId;
  final Map<String, dynamic>? initial;

  @override
  State<PrivateInvestorStatementScreen> createState() => _PrivateInvestorStatementScreenState();
}

class _PrivateInvestorStatementScreenState extends State<PrivateInvestorStatementScreen> {
  bool loading = true;
  String? error;
  Map<String, dynamic> data = {};

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && data.isEmpty) _load();
  }

  Future<void> _load() async {
    try {
      final response = await SessionScope.of(context).api.get('/private/statements/${widget.statementId}');
      data = JsonTools.map(JsonTools.at(response, 'data', widget.initial ?? <String, dynamic>{}));
    } on ApiException catch (e) {
      error = e.message;
      data = widget.initial ?? <String, dynamic>{};
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  dynamic _first(List<String> keys, [dynamic fallback]) {
    for (final key in keys) {
      final value = JsonTools.at(data, key);
      if (value != null && value.toString().trim().isNotEmpty) return value;
    }
    return fallback;
  }

  @override
  Widget build(BuildContext context) {
    final currency = JsonTools.text(_first(['currency', 'principal_currency', 'investment_currency'], 'USD'));
    return AbsPage(
      title: 'Monthly Statement',
      subtitle: JsonTools.text(_first(['statement_month', 'month']), 'Investor statement'),
      actions: [IconButton(onPressed: _load, icon: const Icon(Icons.refresh_rounded))],
      child: loading
          ? const LoadingBlock()
          : error != null && data.isEmpty
              ? ErrorBlock(message: error!, onRetry: _load)
              : ListView(
                  children: [
                    PremiumHeroCard(
                      eyebrow: 'INVESTOR STATEMENT',
                      title: JsonTools.text(_first(['statement_month', 'month']), 'Monthly statement'),
                      message: 'Principal movement and profit distribution are shown separately for clear reconciliation.',
                      trailing: const Icon(Icons.receipt_long_rounded, color: AbsColors.cyan, size: 38),
                      footer: Wrap(spacing: 7, runSpacing: 7, children: [StatusChip(JsonTools.text(_first(['status'], 'READY')).toUpperCase(), good: true), if (JsonTools.boolean(_first(['auto_generated', 'generated_automatically'], false))) const StatusChip('AUTO-RECONCILED')]),
                    ),
                    const SizedBox(height: 16),
                    AbsCard(
                      child: Column(
                        children: [
                          KeyValueRow('Opening principal', '$currency ${number(_first(['opening_principal', 'opening_balance']))}'),
                          KeyValueRow('Additional investment', '$currency ${number(_first(['additional_investment', 'contributions']))}'),
                          KeyValueRow('Capital withdrawal', '$currency ${number(_first(['capital_withdrawal', 'withdrawals']))}', valueColor: AbsColors.gold),
                          KeyValueRow('Profit earned', '$currency ${number(_first(['profit_earned', 'monthly_profit', 'profit_loss']))}', valueColor: AbsColors.green),
                          KeyValueRow('Profit paid', '$currency ${number(_first(['profit_paid', 'paid_profit', 'distribution']))}', valueColor: AbsColors.green),
                          KeyValueRow('Closing principal', '$currency ${number(_first(['closing_principal', 'closing_balance']))}'),
                        ],
                      ),
                    ),
                    if (JsonTools.text(_first(['notes'], ''), '').isNotEmpty) ...[
                      const SizedBox(height: 12),
                      AbsCard(child: Text(JsonTools.text(_first(['notes'])), style: const TextStyle(color: AbsColors.muted, height: 1.45))),
                    ],
                    const SizedBox(height: 12),
                    const AbsCard(
                      accent: AbsColors.cyan,
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(Icons.verified_outlined, color: AbsColors.cyan),
                          SizedBox(width: 9),
                          Expanded(child: Text('In V15.7.4, Profit Paid is an external distribution with zero principal effect. Capital Withdrawal represents principal returned to the investor.', style: TextStyle(color: AbsColors.muted, fontSize: 11.5, height: 1.4))),
                        ],
                      ),
                    ),
                  ],
                ),
    );
  }
}

class _HeroMetric extends StatelessWidget {
  const _HeroMetric({required this.label, required this.value, this.valueColor});
  final String label;
  final String value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: AbsColors.bg.withOpacity(.42), borderRadius: BorderRadius.circular(14), border: Border.all(color: AbsColors.lineSoft)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(label.toUpperCase(), style: const TextStyle(color: AbsColors.muted, fontSize: 8.5, fontWeight: FontWeight.w900, letterSpacing: .7)), const SizedBox(height: 4), Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(fontSize: 15, fontWeight: FontWeight.w900, color: valueColor ?? AbsColors.text))]),
      );
}

class _RuleCard extends StatelessWidget {
  const _RuleCard({required this.icon, required this.title, required this.message});
  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) => AbsCard(
        padding: const EdgeInsets.all(13),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Container(width: 38, height: 38, alignment: Alignment.center, decoration: BoxDecoration(color: AbsColors.panel2, borderRadius: BorderRadius.circular(11)), child: Icon(icon, color: AbsColors.cyan, size: 19)), const SizedBox(width: 10), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(message, style: const TextStyle(color: AbsColors.muted, fontSize: 11.2, height: 1.4))]))]),
      );
}

class _ProgressBar extends StatelessWidget {
  const _ProgressBar({required this.value});
  final double value;

  @override
  Widget build(BuildContext context) {
    final normalized = value.isNaN ? 0.0 : value.clamp(0.0, 1.0).toDouble();
    return ClipRRect(
      borderRadius: BorderRadius.circular(20),
      child: LinearProgressIndicator(value: normalized, minHeight: 8, backgroundColor: AbsColors.panel3, color: AbsColors.cyan),
    );
  }
}

class _AccessError extends StatelessWidget {
  const _AccessError({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => ListView(
        children: [
          PremiumHeroCard(
            eyebrow: 'PRIVATE INVESTOR',
            title: 'Investor portal unavailable',
            message: message,
            trailing: const Icon(Icons.lock_outline_rounded, color: AbsColors.gold, size: 36),
            footer: Row(children: [Expanded(child: ElevatedButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh_rounded), label: const Text('Try again'))), const SizedBox(width: 8), Expanded(child: OutlinedButton.icon(onPressed: () => launchUrl(Uri.parse('${AppConfig.website}/private'), mode: LaunchMode.externalApplication), icon: const Icon(Icons.open_in_new_rounded), label: const Text('Web portal')))]),
          ),
        ],
      );
}
