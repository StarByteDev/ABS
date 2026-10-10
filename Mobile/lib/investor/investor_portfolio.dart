import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import 'investor_home.dart' show showInvestorRequestSheet;
import 'investor_ui.dart';
import 'premium_ui.dart';
import 'pulse_experience.dart';

/// The mobile investor ledger uses ABS V15.9.1 accounting without inventing
/// gains or aggregating partial transaction pages into lifetime totals.
class InvestorPortfolioScreen extends StatefulWidget {
  const InvestorPortfolioScreen({super.key, this.initialIndex = 0});
  final int initialIndex;
  @override
  State<InvestorPortfolioScreen> createState() => _InvestorPortfolioScreenState();
}

class _InvestorPortfolioScreenState extends State<InvestorPortfolioScreen> {
  late int section;
  String transactionFilter = 'all';
  static const _tabs = ['Overview', 'Performance', 'Cash flow', 'Statements', 'Requests'];
  static const _filters = <String, String>{
    'all': 'All', 'deposit': 'Top-ups', 'profit': 'Profit',
    'withdrawal': 'Cash-outs', 'fee': 'Fees', 'loss': 'Losses',
  };

  @override
  void initState() { super.initState(); section = widget.initialIndex.clamp(0, 4); }
  @override
  void didUpdateWidget(covariant InvestorPortfolioScreen old) {
    super.didUpdateWidget(old);
    if (old.initialIndex != widget.initialIndex) {
      setState(() => section = widget.initialIndex.clamp(0, 4));
    }
  }

  @override
  Widget build(BuildContext context) {
    final exp = InvestorScope.of(context);
    final account = exp.account;
    final c = JsonTools.text(account['currency'], 'USD');
    return PremiumBackdrop(child: SafeArea(bottom: false, child: Column(children: [
      Padding(padding: const EdgeInsets.fromLTRB(16, 17, 16, 10), child: Row(children: [
        const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Investor Portfolio', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 24)),
          SizedBox(height: 3),
          Text('Capital · performance · payouts', style: TextStyle(color: invMuted, fontSize: 12)),
        ])),
        IconButton(tooltip: 'Refresh your investor records', onPressed: exp.refresh,
          icon: const Icon(Icons.refresh_rounded, color: invGold)),
      ])),
      SizedBox(height: 45, child: ListView(scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 13), children: [
          for (var i = 0; i < _tabs.length; i++) Padding(padding: const EdgeInsets.only(right: 6),
            child: TextButton(
              onPressed: () => setState(() => section = i),
              style: TextButton.styleFrom(backgroundColor: section == i ? invGold.withAlpha(30) : invCard,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(11),
                  side: BorderSide(color: section == i ? invGold : invBorder))),
              child: Text(_tabs[i], style: TextStyle(color: section == i ? invGold : invMuted,
                fontWeight: section == i ? FontWeight.w800 : FontWeight.w600, fontSize: 12)),
            )),
        ])),
      Expanded(child: RefreshIndicator(color: invGold, onRefresh: exp.refresh,
        child: ListView(key: ValueKey(section), physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(15, 17, 15, 35), children: [
          if (exp.loading && account.isEmpty)
            const Padding(padding: EdgeInsets.all(25), child: Center(child: CircularProgressIndicator(color: invGold)))
          else if (account.isEmpty)
            PremiumCard(child: Column(children: [
              const Text('Your investor account is not available.', style: TextStyle(color: Colors.white)),
              if (exp.error != null) Padding(padding: const EdgeInsets.only(top: 6),
                child: Text(exp.error!, style: const TextStyle(color: invMuted))),
            ]))
          else ...switch (section) {
            0 => _overview(account, c),
            1 => _performance(account, c),
            2 => _cashFlow(exp, account, c),
            3 => _statements(exp, account, c),
            _ => _requests(exp, account, c),
          },
        ]))),
    ])));
  }

  List<Widget> _overview(Map<String, dynamic> a, String c) {
    final plan = JsonTools.map(a['performance_plan']);
    final term = JsonTools.map(a['investment_agreement']);
    final activity = JsonTools.map(a['activity_summary']);
    final principal = JsonTools.number(a['net_contributions']);
    final paid = JsonTools.number(a['profit_paid']);
    final total = JsonTools.number(a['total_profit']);
    final reinvested = JsonTools.number(a['profit_reinvested']);
    final withdraw = JsonTools.number(activity['capital_withdrawal']);
    final indicative = term['indicative_value'] ?? a['current_value'];
    return [
      PremiumCard(highlight: true, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          const Expanded(child: Text('Reported portfolio value', style: TextStyle(color: invMuted, fontSize: 12.5))),
          PremiumChip(JsonTools.boolean(a['is_active']) ? 'ACTIVE' : 'INACTIVE',
            color: JsonTools.boolean(a['is_active']) ? invGreen : invMuted),
        ]),
        const SizedBox(height: 8),
        Text(money(indicative, c), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 27)),
        const SizedBox(height: 5),
        Text('Valuation: ${JsonTools.text(a['valuation_date'], 'Not published')}',
          style: const TextStyle(color: invMuted, fontSize: 11)),
        const SizedBox(height: 15),
        _metricTiles([
          _RecordMetric('Remaining principal', money(principal, c), Icons.account_balance_wallet_outlined),
          _RecordMetric('Lifetime profit', money(total, c), Icons.trending_up_rounded,
            color: total >= 0 ? invGreen : const Color(0xFFFF6479)),
          _RecordMetric('Profit paid out', money(paid, c), Icons.payments_outlined, color: invGold),
          _RecordMetric('Profit reinvested', money(reinvested, c), Icons.savings_outlined, color: invGreen),
        ]),
      ])),
      const PremiumSection('This month · accrued performance'),
      PremiumCard(child: Column(children: [
        _line('Month', JsonTools.text(plan['month'], 'Not scheduled')),
        _line('Agreed target rate', _optionalPercent(plan['target_rate'] ?? term['monthly_target_rate'])),
        _line('Monthly target', _optionalMoney(plan['target_amount'], c)),
        _line('Provisional accrued', _optionalMoney(plan['mtd_provisional'], c), color: invGreen),
        _line('Progress', plan['progress_percent'] == null ? '—' : pct(plan['progress_percent'])),
        _line('Days posted', plan['days_posted'] == null ? '—' :
          '${JsonTools.integer(plan['days_posted'])} / ${JsonTools.integer(plan['days_total'])}'),
        if (plan['progress_percent'] != null) Padding(
          padding: const EdgeInsets.symmetric(vertical: 11), child: ClipRRect(
            borderRadius: BorderRadius.circular(6), child: LinearProgressIndicator(
              value: (JsonTools.number(plan['progress_percent']) / 100).clamp(0, 1).toDouble(),
              minHeight: 8, color: invGold, backgroundColor: invBorder))),
      ])),
      const PremiumSection('Money paid and withdrawn'),
      PremiumCard(child: Column(children: [
        _line('Profit cash-outs · distributed', money(paid, c), color: invGreen),
        _line('Capital cash-outs · principal', money(withdraw, c), color: invGold),
        _line('Reinvested profit', money(reinvested, c)),
        _line('Additional deposits · ledger', money(activity['investment'], c)),
        _line('Open investor requests', JsonTools.integer(a['open_requests']).toString()),
        const SizedBox(height: 6),
        TextButton.icon(onPressed: () => setState(() => section = 2),
          icon: const Icon(Icons.receipt_long_outlined), label: const Text('View all cash movements')),
      ])),
      const PremiumSection('Recent investor activity'),
      if (JsonTools.mapList(a['recent_transactions']).isEmpty)
        const PremiumCard(child: Text('No posted activity is available.',
          style: TextStyle(color: invMuted)))
      else for (final tx in JsonTools.mapList(a['recent_transactions']).take(3))
        Padding(padding: const EdgeInsets.only(bottom: 7), child: _transactionTile(tx, c)),
      const PremiumSection('Investment agreement'),
      PremiumCard(child: Column(children: [
        _line('Agreement status', JsonTools.text(term['status'], 'Not configured')),
        _line('Performance start', JsonTools.text(term['effective_from'], 'Not set')),
        _line('Monthly rate', _optionalPercent(term['monthly_target_rate'])),
        _line('Payout schedule', _payoutText(term)),
        _line('Account currency', c),
      ])),
      const SizedBox(height: 15),
      _quickActions(c),
      const SizedBox(height: 13),
      const InvestorNotice('Profit paid out is separate from capital withdrawals. '
        'Provisional accrued profit is not confirmed cash, and a reported portfolio value is not a withdrawable balance.'),
    ];
  }

  Widget _quickActions(String c) => Row(children: [
    PremiumAction(icon: Icons.add_circle_outline_rounded, label: 'Top up', gold: true,
      onTap: () => showInvestorRequestSheet(context, 'add_investment')),
    PremiumAction(icon: Icons.north_east_rounded, label: 'Withdraw',
      onTap: () => showInvestorRequestSheet(context, 'withdrawal')),
    PremiumAction(icon: Icons.description_outlined, label: 'Statements',
      onTap: () => setState(() => section = 3)),
    PremiumAction(icon: Icons.support_agent_rounded, label: 'Review',
      onTap: () => showInvestorRequestSheet(context, 'portfolio_review')),
  ]);

  List<Widget> _performance(Map<String, dynamic> a, String c) {
    final history = JsonTools.mapList(a['performance_history']);
    final total = JsonTools.number(a['total_profit']);
    final term = JsonTools.map(a['investment_agreement']);
    final chartValues = <double>[];
    var cumulative = 0.0;
    for (final row in history) {
      cumulative += JsonTools.number(row['posted']);
      chartValues.add(cumulative);
    }
    return [
      PremiumCard(highlight: true, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('Lifetime reported investment profit', style: TextStyle(color: invMuted, fontSize: 12)),
        const SizedBox(height: 9),
        Text(money(total, c), style: TextStyle(color: total >= 0 ? invGreen : const Color(0xFFFF6479),
          fontSize: 31, fontWeight: FontWeight.w900)),
        const SizedBox(height: 4),
        Text('Reported return ${pct(a['overall_return_percent'], signed: true)}',
          style: const TextStyle(color: invMuted, fontSize: 13)),
        const SizedBox(height: 16),
        const Text('Cumulative posted profit · available monthly history',
          style: TextStyle(color: invMuted, fontSize: 11.5)),
        const SizedBox(height: 10),
        PremiumPerformanceChart(values: chartValues, height: 175),
        const SizedBox(height: 8),
        const Text('This chart sums posted monthly profit. It is not an account-value or market-price chart.',
          style: TextStyle(color: invMuted, fontSize: 10.5)),
      ])),
      const PremiumSection('Performance breakdown'),
      PremiumCard(child: Column(children: [
        _line('Lifetime profit', money(a['total_profit'], c)),
        _line('Distributed to investor', money(a['profit_paid'], c), color: invGreen),
        _line('Retained / reinvested', money(a['profit_reinvested'], c)),
        _line('Latest monthly return', _optionalPercent(a['latest_monthly_return_percent'])),
        _line('Current agreed monthly rate', _optionalPercent(term['monthly_target_rate'])),
      ])),
      const PremiumSection('Monthly performance reports'),
      if (history.isEmpty)
        const PremiumCard(child: Text('No monthly performance history has been published for this account.',
          style: TextStyle(color: invMuted)))
      else for (final row in history.reversed) Padding(padding: const EdgeInsets.only(bottom: 9),
        child: PremiumCard(padding: const EdgeInsets.all(13), child: Column(children: [
          Row(children: [
            const Icon(Icons.calendar_month_outlined, color: invGold, size: 20),
            const SizedBox(width: 9),
            Expanded(child: Text(_monthLabel(row['month']), style: const TextStyle(color: Colors.white,
              fontSize: 14, fontWeight: FontWeight.w800))),
            Text(_optionalMoney(row['posted'], c), style: TextStyle(
              color: JsonTools.number(row['posted']) >= 0 ? invGreen : const Color(0xFFFF6479),
              fontSize: 14, fontWeight: FontWeight.w900)),
          ]),
          const Divider(height: 22, color: invBorder),
          _line('Agreed rate', _optionalPercent(row['rate'])),
          _line('Monthly target', _optionalMoney(row['target'], c)),
          _line('Posted profit', _optionalMoney(row['posted'], c)),
          _line('Active from', JsonTools.text(row['active_from'])),
        ]))),
      const InvestorNotice('Monthly targets are based on agreed performance terms, not market trading returns. '
        'Only posted results and actual distributions are reported as completed.'),
    ];
  }

  List<Widget> _cashFlow(InvestorExperience exp, Map<String, dynamic> a, String c) {
    final summary = JsonTools.map(a['activity_summary']);
    final ledger = exp.transactions.isNotEmpty ? exp.transactions : JsonTools.mapList(a['recent_transactions']);
    final filtered = ledger.where((tx) => transactionFilter == 'all' ||
      JsonTools.text(tx['type'], '').toLowerCase() == transactionFilter).toList();
    return [
      const PremiumSection('Cash movement summary'),
      PremiumCard(highlight: true, child: _metricTiles([
        _RecordMetric('Additional deposits', money(summary['investment'], c), Icons.add_card_rounded),
        _RecordMetric('Profit paid · cash out', money(a['profit_paid'], c), Icons.payments_rounded, color: invGreen),
        _RecordMetric('Capital withdrawn', money(summary['capital_withdrawal'], c), Icons.account_balance_outlined, color: invGold),
        _RecordMetric('Profit reinvested', money(a['profit_reinvested'], c), Icons.autorenew_rounded, color: invBlue),
        _RecordMetric('Losses / fees (ledger)', money(summary['losses_and_fees'], c), Icons.receipt_long_outlined),
        _RecordMetric('Principal remaining', money(a['net_contributions'], c), Icons.savings_outlined),
      ])),
      const SizedBox(height: 12),
      ..._monthlyCashFlow(a, c),
      const SizedBox(height: 12),
      const InvestorNotice('Totals above are computed by ABS from posted account records. '
        'Opening principal is not included in the additional-deposits figure. '
        'Profit distributions do not reduce principal. A voided transaction is not a completed cash movement.'),
      const PremiumSection('Transaction ledger'),
      SingleChildScrollView(scrollDirection: Axis.horizontal, child: Row(children: [
        for (final f in _filters.entries) Padding(padding: const EdgeInsets.only(right: 6),
          child: ChoiceChip(label: Text(f.value), selected: transactionFilter == f.key,
            onSelected: (_) => setState(() => transactionFilter = f.key),
            selectedColor: invGold, backgroundColor: invCard,
            labelStyle: TextStyle(fontSize: 11.5, color: transactionFilter == f.key ? invBg : invMuted),
            side: BorderSide(color: transactionFilter == f.key ? invGold : invBorder))),
      ])),
      const SizedBox(height: 11),
      if (exp.transactionsError != null) Padding(padding: const EdgeInsets.only(bottom: 10),
        child: InvestorNotice('Ledger sync: ${exp.transactionsError}')),
      if (filtered.isEmpty) PremiumCard(child: Text(
        ledger.isEmpty ? 'ABS has no transaction records for this investor.' : 'No records match this transaction type.',
        style: const TextStyle(color: invMuted)))
      else for (final tx in filtered) Padding(padding: const EdgeInsets.only(bottom: 9),
        child: _transactionTile(tx, c)),
      if (exp.hasMoreTransactions) Padding(padding: const EdgeInsets.only(top: 8),
        child: PremiumButton(label: exp.transactionsLoading ? 'Loading ledger…' : 'Load older transactions',
          icon: Icons.more_horiz_rounded, gold: false,
          onTap: exp.transactionsLoading ? () {} : exp.loadMoreTransactions)),
      const SizedBox(height: 13),
      const InvestorNotice('This screen shows the available ledger pages. It does not sum incomplete pages '
        'to calculate lifetime account totals; the figures above come from ABS account reporting.'),
    ];
  }

  /// Monthly ledger buckets returned by ABS. "Performance" includes posted
  /// profits less losses and fees, and must not be presented as cash paid out.
  List<Widget> _monthlyCashFlow(Map<String, dynamic> a, String c) {
    final chart = JsonTools.map(a['activity_chart']);
    final labels = JsonTools.list(chart['labels']);
    final capitalIn = JsonTools.list(chart['investment']);
    final capitalOut = JsonTools.list(chart['withdrawals']);
    final performance = JsonTools.list(chart['performance']);
    if (labels.isEmpty) return const <Widget>[];
    final rows = <Widget>[];
    for (var i = labels.length - 1; i >= 0; i--) {
      double at(List<dynamic> source) => i < source.length ? JsonTools.number(source[i]) : 0;
      final incoming = at(capitalIn), outgoing = at(capitalOut), posted = at(performance);
      if (incoming == 0 && outgoing == 0 && posted == 0) continue;
      rows.add(Padding(padding: const EdgeInsets.only(bottom: 10),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(JsonTools.text(labels[i]), style: const TextStyle(color: Colors.white,
            fontSize: 12, fontWeight: FontWeight.w800)),
          const SizedBox(height: 5),
          _line('Added capital', money(incoming, c), color: invGreen),
          _line('Principal out', money(outgoing, c), color: invGold),
          _line('Net posted performance*', money(posted, c)),
          const Divider(height: 9, color: invBorder),
        ])));
    }
    return [
      const PremiumSection('Monthly activity report'),
      PremiumCard(child: rows.isEmpty
        ? const Text('No monthly ledger movements reported in the past 12 months.',
            style: TextStyle(color: invMuted))
        : Column(children: rows)),
      const SizedBox(height: 7),
      const Text('* Net posted performance includes reinvestment and loss/fee effects; '
        'it is not a cash payout figure. For actual cash received, use Profit paid and transaction details.',
        style: TextStyle(color: invMuted, fontSize: 11, height: 1.4)),
    ];
  }

  Widget _transactionTile(Map<String, dynamic> tx, String c) {
    final type = JsonTools.text(tx['type'], 'movement').toLowerCase();
    final status = JsonTools.text(tx['status'], 'unknown').toUpperCase();
    final posted = status == 'POSTED';
    final amount = JsonTools.number(tx['amount']);
    final isProfit = type == 'profit';
    final reinvested = isProfit && <String>{'automatic_profit_reinvestment', 'admin_profit_reinvestment'}
      .contains(JsonTools.text(tx['entry_source'], ''));
    final label = switch (type) {
      'deposit' => 'Investment added',
      'withdrawal' => 'Capital withdrawn',
      'profit' => reinvested ? 'Profit reinvested' : 'Profit distributed',
      'loss' => 'Portfolio loss',
      'fee' => 'Account fee',
      _ => type.replaceAll('_', ' '),
    };
    final positive = type == 'deposit' || isProfit;
    final tint = !posted ? invMuted : positive ? invGreen : invGold;
    return PremiumCard(padding: const EdgeInsets.all(13), child: Column(children: [
      Row(children: [
        Icon(type == 'profit' ? Icons.payments_outlined : type == 'deposit' ? Icons.add_card_rounded :
          Icons.receipt_long_outlined, color: tint, size: 22),
        const SizedBox(width: 9),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w800)),
          const SizedBox(height: 3),
          Text(JsonTools.text(tx['transaction_date'], JsonTools.text(tx['created_at'], 'No date')),
            style: const TextStyle(color: invMuted, fontSize: 11)),
        ])),
        Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
          Text(money(amount, JsonTools.text(tx['currency'], c)),
            style: TextStyle(color: tint, fontSize: 13, fontWeight: FontWeight.w800)),
          const SizedBox(height: 3),
          Text(status, style: const TextStyle(color: invMuted, fontSize: 10)),
        ]),
      ]),
      if (JsonTools.text(tx['reference'], '').isNotEmpty || JsonTools.text(tx['description'], '').isNotEmpty)
        Padding(padding: const EdgeInsets.only(top: 8),
          child: Align(alignment: Alignment.centerLeft,
            child: Text(JsonTools.text(tx['reference'], JsonTools.text(tx['description'], '')),
              maxLines: 2, overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: invMuted, fontSize: 10.5)))),
    ]));
  }

  List<Widget> _statements(InvestorExperience exp, Map<String, dynamic> a, String c) {
    final rows = exp.statements.isNotEmpty ? exp.statements : JsonTools.mapList(a['recent_statements']);
    return [
      const PremiumSection('Monthly investor statements'),
      const InvestorNotice('Published ABS statements are separate from provisional current-month accrual. '
        'Open a statement to check its closing principal, profit and paid distributions.'),
      const SizedBox(height: 12),
      if (rows.isEmpty) const PremiumCard(child: Text('No published statements are available for this account.',
        style: TextStyle(color: invMuted)))
      else for (final row in rows) Padding(padding: const EdgeInsets.only(bottom: 9),
        child: PremiumCard(padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
          child: PremiumTinyRow(icon: Icons.description_outlined, iconColor: invBlue,
            title: _monthLabel(row['statement_month'] ?? row['month']),
            subtitle: 'Profit paid: ${_optionalMoney(row['profit_paid'], c)} · ${JsonTools.text(row['status'], 'Published')}',
            onTap: () {
              final id = JsonTools.integer(row['id']);
              if (id > 0) Navigator.of(context).push(MaterialPageRoute<void>(
                builder: (_) => InvestorStatementDetail(id: id)));
            }))),
      const SizedBox(height: 10),
      const InvestorNotice('Statement downloads depend on the backend document endpoint. '
        'The secure in-app statement detail is available for published records.'),
    ];
  }

  List<Widget> _requests(InvestorExperience exp, Map<String, dynamic> a, String c) => [
    const PremiumSection('Investor services'),
    PremiumButton(label: 'Request investment top-up', icon: Icons.add_circle_outline_rounded,
      onTap: () => showInvestorRequestSheet(context, 'add_investment')),
    const SizedBox(height: 9),
    PremiumButton(label: 'Request capital withdrawal', icon: Icons.north_east_rounded, gold: false,
      onTap: () => showInvestorRequestSheet(context, 'withdrawal')),
    const SizedBox(height: 9),
    PremiumButton(label: 'Ask for portfolio review', icon: Icons.support_agent_rounded, gold: false,
      onTap: () => showInvestorRequestSheet(context, 'portfolio_review')),
    const SizedBox(height: 13),
    const InvestorNotice('A withdrawal request refers to investment principal—not monthly profit. '
      'Profit is paid on the ABS settlement schedule; these requests do not transfer money automatically.'),
    const PremiumSection('Request tracking'),
    if (exp.requests.isEmpty) const PremiumCard(child: Text('No investor requests have been submitted.',
      style: TextStyle(color: invMuted))),
    for (final req in exp.requests) Padding(padding: const EdgeInsets.only(bottom: 9),
      child: PremiumCard(child: Column(children: [
        _line('Request', JsonTools.text(req['type'], 'Request').replaceAll('_', ' ')),
        _line('Status', JsonTools.text(req['status'], 'unknown').toUpperCase()),
        _line('Amount', req['amount'] == null ? 'Not applicable' :
          money(req['amount'], JsonTools.text(req['currency'], c))),
        _line('Submitted', JsonTools.text(req['created_at'], 'Not reported')),
        if (JsonTools.text(req['status'], '') == 'submitted')
          Align(alignment: Alignment.centerRight,
            child: TextButton.icon(icon: const Icon(Icons.close_rounded, size: 17),
              label: const Text('Cancel pending request'), onPressed: () async {
                try {
                  await exp.session.api.patch('/private/requests/${req['id']}/cancel');
                  await exp.refresh();
                } catch (e) {
                  if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text(e is ApiException ? e.message : 'Could not cancel request')));
                }
              })),
      ]))),
  ];

  Widget _metricTiles(List<_RecordMetric> metrics) => LayoutBuilder(builder: (context, bounds) {
    final cols = bounds.maxWidth < 320 ? 1 : 2;
    final width = (bounds.maxWidth - (cols - 1) * 9) / cols;
    return Wrap(spacing: 9, runSpacing: 9, children: [
      for (final m in metrics) SizedBox(width: width, child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: invRaised, border: Border.all(color: invBorder),
          borderRadius: BorderRadius.circular(13)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(m.icon, size: 19, color: m.color ?? invGold),
          const SizedBox(height: 8),
          Text(m.title, maxLines: 2, style: const TextStyle(color: invMuted, fontSize: 11.5)),
          const SizedBox(height: 5),
          FittedBox(fit: BoxFit.scaleDown, alignment: Alignment.centerLeft,
            child: Text(m.value, style: TextStyle(color: m.color ?? Colors.white,
              fontSize: 16, fontWeight: FontWeight.w900))),
        ]))),
    ]);
  });

  static Widget _line(String label, String value, {Color color = Colors.white}) =>
    Padding(padding: const EdgeInsets.symmetric(vertical: 8), child: Row(children: [
      Expanded(child: Text(label, style: const TextStyle(color: invMuted, fontSize: 11.5))),
      const SizedBox(width: 9),
      Flexible(child: Text(value, textAlign: TextAlign.right, style: TextStyle(
        color: color, fontSize: 12, fontWeight: FontWeight.w800))),
    ]));
  static String _optionalMoney(dynamic value, String c) => value == null ? '—' : money(value, c);
  static String _optionalPercent(dynamic value) => value == null ? '—' : pct(value);
  static String _monthLabel(dynamic value) {
    final raw = JsonTools.text(value, 'Monthly statement');
    final date = DateTime.tryParse(raw.length == 7 ? '$raw-01' : raw);
    return date == null ? raw : DateFormat('MMMM yyyy').format(date);
  }
  static String _payoutText(Map<String, dynamic> term) {
    if (term.isEmpty) return 'Not configured';
    final day = JsonTools.integer(term['payout_day']);
    return day <= 0 ? 'Month-end settlement' : 'Following month · day $day';
  }
}

class _RecordMetric {
  const _RecordMetric(this.title, this.value, this.icon, {this.color});
  final String title;
  final String value;
  final IconData icon;
  final Color? color;
}

class InvestorStatementDetail extends StatefulWidget {
  const InvestorStatementDetail({super.key, required this.id});
  final int id;
  @override
  State<InvestorStatementDetail> createState() => _InvestorStatementDetailState();
}

class _InvestorStatementDetailState extends State<InvestorStatementDetail> {
  Map<String, dynamic>? statement;
  String? error;
  bool requested = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!requested) { requested = true; _load(); }
  }

  Future<void> _load() async {
    try {
      final raw = await SessionScope.of(context).api.get('/private/statements/${widget.id}');
      if (mounted) setState(() => statement = JsonTools.map(JsonTools.at(raw, 'data')));
    } on ApiException catch (e) {
      if (mounted) setState(() => error = e.message);
    } catch (_) {
      if (mounted) setState(() => error = 'Unable to load the investor statement.');
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: invBg,
    appBar: AppBar(title: const Text('Monthly statement'), backgroundColor: invBg),
    body: statement == null ? Center(child: Text(error ?? 'Loading statement…',
      style: const TextStyle(color: invMuted))) : ListView(
      padding: const EdgeInsets.all(17), children: [
        PremiumCard(highlight: true, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Statement ${JsonTools.text(statement?['statement_month'], '')}',
            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 20)),
          const SizedBox(height: 10),
          for (final key in ['opening_balance', 'closing_balance', 'profit_loss',
            'profit_paid', 'status', 'published_at'])
            Padding(padding: const EdgeInsets.symmetric(vertical: 9), child: Row(children: [
              Expanded(child: Text(key.replaceAll('_', ' '),
                style: const TextStyle(color: invMuted, fontSize: 12))),
              Flexible(child: Text(JsonTools.text(statement?[key], 'Not reported'),
                textAlign: TextAlign.right, style: const TextStyle(color: Colors.white,
                  fontSize: 12, fontWeight: FontWeight.w700))),
            ])),
        ])),
        const SizedBox(height: 15),
        const InvestorNotice('Read-only ABS statement. Contact the investor desk for reconciliation questions.'),
      ]),
  );
}
