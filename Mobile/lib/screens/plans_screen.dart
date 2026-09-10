import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';

class PlansScreen extends StatefulWidget {
  const PlansScreen({super.key});
  @override
  State<PlansScreen> createState() => _PlansScreenState();
}

class _PlansScreenState extends State<PlansScreen> {
  bool loading = true;
  String? error;
  Map<String, dynamic> membership = {};

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (loading && membership.isEmpty) _load();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      membership = JsonTools.map(
        JsonTools.at(
          await SessionScope.of(context).api.get('/pulse/membership'),
          'data',
          <String, dynamic>{},
        ),
      );
    } on ApiException catch (e) {
      error = e.message;
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => AbsPage(
    title: 'Pulse Membership',
    subtitle: 'Current plan, upgrade path and request status',
    actions: [IconButton(onPressed: _load, icon: const Icon(Icons.refresh))],
    child: loading
        ? const LoadingBlock()
        : error != null
        ? ErrorBlock(message: error!, onRetry: _load)
        : ListView(children: _content()),
  );

  List<Widget> _content() {
    final access = JsonTools.map(membership['access']);
    final currentPlan = JsonTools.map(access['plan']);
    final plans = JsonTools.mapList(membership['plans']);
    final requests = JsonTools.mapList(membership['requests']);
    final commerce = <String, dynamic>{
      'requests_enabled': membership['payment_requests_enabled'],
      'wallet_address': membership['wallet_address'],
      'network': membership['network'],
      'payment_instructions': membership['payment_instructions'],
      'proof_required': membership['proof_required'],
    };
    return [
      if (currentPlan.isNotEmpty) ...[
        AbsCard(
          child: Row(
            children: [
              const Icon(
                Icons.workspace_premium,
                color: AbsColors.gold,
                size: 36,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'YOUR CURRENT PLAN',
                      style: TextStyle(
                        color: AbsColors.gold,
                        fontSize: 10,
                        fontWeight: FontWeight.w900,
                        letterSpacing: .8,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      JsonTools.text(currentPlan['name']),
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      'Access until ${compactDate(access['ends_at'])}',
                      style: const TextStyle(
                        color: AbsColors.muted,
                        fontSize: 11,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
      ],
      const AbsSectionTitle(
        'Available upgrade path',
        subtitle: 'ABS shows the current tier clearly and promotes only relevant upgrades.',
      ),
      const SizedBox(height: 10),
      if (plans.isEmpty)
        const EmptyState(
          title: 'No upgrade required',
          message: 'There are no higher public Pulse plans available for your account.',
          icon: Icons.workspace_premium_outlined,
        )
      else
        ...plans.map(
          (plan) =>
              _PlanCard(plan: plan, commerce: commerce, onSubmitted: _load),
        ),
      if (requests.isNotEmpty) ...[
        const SizedBox(height: 18),
        const AbsSectionTitle('Membership requests'),
        const SizedBox(height: 10),
        ...requests.map(
          (r) => Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: AbsCard(
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          JsonTools.text(r['plan_name'], 'Pulse plan'),
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          'Submitted ${compactDate(r['submitted_at'])} · ${JsonTools.text(r['currency'], 'USDT')} ${number(r['amount'])}',
                          style: const TextStyle(
                            color: AbsColors.muted,
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      StatusChip(
                        JsonTools.text(r['status']).toUpperCase(),
                        good: JsonTools.text(r['status']) == 'approved',
                        warning: [
                          'submitted',
                          'under_review',
                        ].contains(JsonTools.text(r['status'])),
                      ),
                      if ([
                        'submitted',
                        'under_review',
                      ].contains(JsonTools.text(r['status'])))
                        TextButton(
                          onPressed: () => _cancelRequest(r),
                          child: const Text('Cancel request'),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
      const SizedBox(height: 24),
    ];
  }

  Future<void> _cancelRequest(Map<String, dynamic> request) async {
    final id = JsonTools.integer(request['id']);
    if (id <= 0) return;
    final yes = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cancel membership request?'),
        content: const Text(
          'This only cancels a request that has not yet been approved. It does not remove account history.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Keep request'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Cancel request'),
          ),
        ],
      ),
    );
    if (yes != true) return;
    try {
      final response = await SessionScope.of(context).api
          .patch('/pulse/membership/requests/$id');
      if (mounted)
        showSnack(
          context,
          JsonTools.text(
            JsonTools.map(response)['message'],
            'Membership request cancelled.',
          ),
        );
      await _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    }
  }
}

class _PlanCard extends StatelessWidget {
  const _PlanCard({
    required this.plan,
    required this.commerce,
    required this.onSubmitted,
  });
  final Map<String, dynamic> plan;
  final Map<String, dynamic> commerce;
  final VoidCallback onSubmitted;

  @override
  Widget build(BuildContext context) {
    final isCurrent = JsonTools.boolean(plan['is_current_plan']);
    final isNext = JsonTools.boolean(plan['is_next_upgrade']);
    final amount =
        plan['price'] ??
        plan['monthly_price'] ??
        plan['amount'] ??
        JsonTools.at(plan, 'pricing.amount');
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: AbsCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    JsonTools.text(plan['name']),
                    style: const TextStyle(
                      fontWeight: FontWeight.w900,
                      fontSize: 18,
                    ),
                  ),
                ),
                if (isCurrent)
                  const StatusChip('CURRENT', good: true)
                else if (isNext)
                  const StatusChip('RECOMMENDED', warning: true),
              ],
            ),
            const SizedBox(height: 7),
            Text(
              JsonTools.text(
                plan['description'],
                'Pulse Trading Intelligence membership',
              ),
              style: const TextStyle(color: AbsColors.muted),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 7,
              runSpacing: 7,
              children: [
                if (plan['max_selected_pairs'] != null)
                  StatusChip(
                    '${JsonTools.integer(plan['max_selected_pairs'])} PAIRS',
                  ),
                if (plan['manual_trades_per_day'] != null)
                  StatusChip(
                    '${JsonTools.integer(plan['manual_trades_per_day'])} MANUAL/DAY',
                  ),
                if (JsonTools.boolean(
                  plan['allow_live_trading'] ??
                      JsonTools.at(plan, 'capabilities.live_trading'),
                ))
                  const StatusChip('LIVE ELIGIBLE', warning: true),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: Text(
                    amount == null
                        ? 'Rate shown in quote'
                        : '${JsonTools.text(plan['currency'], 'USDT')} ${number(amount)}',
                    style: const TextStyle(
                      fontWeight: FontWeight.w900,
                      fontSize: 17,
                    ),
                  ),
                ),
                if (!isCurrent)
                  ElevatedButton(
                    onPressed:
                        JsonTools.boolean(plan['request_enabled'], true) &&
                            JsonTools.boolean(
                              commerce['requests_enabled'],
                              true,
                            )
                        ? () => _openRequest(context)
                        : null,
                    child: const Text('Request Plan'),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openRequest(BuildContext context) async {
    final result = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AbsColors.panel,
      builder: (_) => _MembershipRequestSheet(plan: plan, commerce: commerce),
    );
    if (result == true) onSubmitted();
  }
}

class _MembershipRequestSheet extends StatefulWidget {
  const _MembershipRequestSheet({required this.plan, required this.commerce});
  final Map<String, dynamic> plan;
  final Map<String, dynamic> commerce;

  @override
  State<_MembershipRequestSheet> createState() =>
      _MembershipRequestSheetState();
}

class _MembershipRequestSheetState extends State<_MembershipRequestSheet> {
  final reference = TextEditingController();
  final notes = TextEditingController();
  bool loadingQuote = false;
  bool submitting = false;
  Map<String, dynamic> quote = {};
  File? proof;

  @override
  void dispose() {
    reference.dispose();
    notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.of(context).viewInsets.bottom;
    final payment = quote;
    final q = JsonTools.map(quote['quote']);
    final proofRequired = JsonTools.boolean(
      payment['proof_required'],
      JsonTools.boolean(widget.commerce['proof_required']),
    );
    return Padding(
      padding: EdgeInsets.fromLTRB(18, 18, 18, 18 + bottom),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Request ${JsonTools.text(widget.plan['name'])}',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 6),
            const Text(
              'Get the current server quote before submitting payment details.',
              style: TextStyle(color: AbsColors.muted),
            ),
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: loadingQuote ? null : _getQuote,
                child: Text(loadingQuote ? 'Checking...' : 'Get current quote'),
              ),
            ),
            if (quote.isNotEmpty) ...[
              const SizedBox(height: 14),
              AbsCard(
                child: Column(
                  children: [
                    KeyValueRow(
                      'Final amount',
                      '${JsonTools.text(q['currency'], 'USDT')} ${number(q['final_amount'])}',
                    ),
                    KeyValueRow('Network', JsonTools.text(payment['network'])),
                    KeyValueRow(
                      'Wallet',
                      JsonTools.text(payment['wallet_address']),
                    ),
                    if (JsonTools.text(
                      payment['payment_instructions'],
                      '',
                    ).isNotEmpty)
                      KeyValueRow(
                        'Instructions',
                        JsonTools.text(payment['payment_instructions']),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: reference,
                decoration: const InputDecoration(
                  labelText: 'USDT transaction reference / hash',
                ),
              ),
              const SizedBox(height: 10),
              OutlinedButton.icon(
                onPressed: _pickProof,
                icon: const Icon(Icons.attach_file),
                label: Text(
                  proof == null
                      ? (proofRequired
                            ? 'Attach payment proof (required)'
                            : 'Attach payment proof')
                      : proof!.path.split(Platform.pathSeparator).last,
                ),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: notes,
                maxLines: 3,
                decoration: const InputDecoration(
                  labelText: 'Notes (optional)',
                ),
              ),
              const SizedBox(height: 14),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: submitting ? null : _submit,
                  child: Text(
                    submitting ? 'Submitting...' : 'Submit membership request',
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Future<void> _getQuote() async {
    setState(() => loadingQuote = true);
    try {
      final response = await SessionScope.of(context).api.post(
        '/pulse/membership/quote',
        body: {'pulse_plan_id': JsonTools.integer(widget.plan['id'])},
      );
      quote = JsonTools.map(
        JsonTools.at(response, 'data', <String, dynamic>{}),
      );
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => loadingQuote = false);
    }
  }

  Future<void> _pickProof() async {
    final picked = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'pdf'],
    );
    final path = picked?.files.single.path;
    if (path != null) setState(() => proof = File(path));
  }

  Future<void> _submit() async {
    if (quote.isEmpty) return;
    final proofRequired = JsonTools.boolean(
      quote['proof_required'],
      JsonTools.boolean(widget.commerce['proof_required']),
    );
    if (reference.text.trim().length < 6) {
      showSnack(
        context,
        'Enter the USDT transaction reference or hash.',
        error: true,
      );
      return;
    }
    if (proofRequired && proof == null) {
      showSnack(
        context,
        'Payment proof is required for this request.',
        error: true,
      );
      return;
    }
    setState(() => submitting = true);
    try {
      final fields = <String, String>{
        'pulse_plan_id': '${JsonTools.integer(widget.plan['id'])}',
        'payment_reference': reference.text.trim(),
        if (notes.text.trim().isNotEmpty) 'user_notes': notes.text.trim(),
      };
      final response = await SessionScope.of(context).api.multipartPost(
        '/pulse/membership/requests',
        fields: fields,
        fileField: proof == null ? null : 'payment_proof',
        file: proof,
      );
      if (!mounted) return;
      showSnack(
        context,
        JsonTools.text(
          JsonTools.map(response)['message'],
          'Membership request submitted.',
        ),
      );
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => submitting = false);
    }
  }
}
