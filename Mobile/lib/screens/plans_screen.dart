import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';
import '../template_rebase/screens/auth_screen.dart';

class PlansScreen extends StatefulWidget {
  const PlansScreen({super.key});
  @override
  State<PlansScreen> createState() => _PlansScreenState();
}

class _PlansScreenState extends State<PlansScreen> {
  bool loading = true;
  bool sessionExpired = false;
  String? error;
  Map<String, dynamic> membership = {};

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final session = SessionScope.of(context);
    if (!session.authenticated || !session.emailVerified) {
      loading = false;
      return;
    }
    if (loading && membership.isEmpty) _load();
  }

  Future<void> _load() async {
    final session = SessionScope.of(context);
    if (!session.authenticated || !session.emailVerified) {
      if (mounted) setState(() => loading = false);
      return;
    }
    setState(() {
      loading = true;
      sessionExpired = false;
      error = null;
    });
    try {
      membership = JsonTools.map(
        JsonTools.at(
          await session.api.get('/pulse/membership'),
          'data',
          <String, dynamic>{},
        ),
      );
    } on ApiException catch (e) {
      if (e.statusCode == 401) {
        sessionExpired = true;
      } else {
        error = e.message;
      }
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    return AbsPage(
      title: 'Pulse Membership',
      subtitle: 'Plans, access and membership requests',
      actions: session.authenticated && session.emailVerified
          ? [IconButton(onPressed: loading ? null : _load, icon: const Icon(Icons.refresh))]
          : null,
      child: !session.authenticated
          ? _guestState()
          : !session.emailVerified
              ? _activationState(session)
              : sessionExpired
                  ? _expiredState()
                  : loading
                      ? const LoadingBlock(label: 'Loading Pulse membership...')
                      : error != null
                          ? ListView(
                              physics: const AlwaysScrollableScrollPhysics(),
                              children: [
                                SizedBox(width: double.infinity, child: ErrorBlock(message: error!, onRetry: _load)),
                              ],
                            )
                          : ListView(
                              physics: const AlwaysScrollableScrollPhysics(),
                              children: _content(),
                            ),
    );
  }

  Widget _guestState() => ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          PremiumHeroCard(
            eyebrow: 'PULSE MEMBERSHIP',
            title: 'Sign in to view your Pulse access',
            message: 'Your available plan, upgrade path and payment-request status are linked to your ABS account.',
            trailing: const Icon(Icons.workspace_premium_rounded, color: AbsColors.gold, size: 38),
            footer: SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const AuthScreen())),
                icon: const Icon(Icons.login_rounded),
                label: const Text('Sign in to Pulse'),
              ),
            ),
          ),
          const SizedBox(height: 12),
          const AbsCard(
            child: Text(
              'Public Markets, Free Signal and Pulse Intelligence remain available without membership.',
              style: TextStyle(color: AbsColors.muted, height: 1.45),
            ),
          ),
        ],
      );

  Widget _activationState(AppSession session) => ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          PremiumHeroCard(
            eyebrow: 'ACCOUNT ACTIVATION',
            title: 'Activate your email to unlock Pulse plans',
            message: 'Basic ABS access remains available now. Membership, scanner, signals and trading tools unlock after email activation.',
            trailing: const Icon(Icons.mark_email_unread_outlined, color: AbsColors.gold, size: 38),
            footer: Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                ElevatedButton.icon(
                  onPressed: () => _resendActivation(session),
                  icon: const Icon(Icons.forward_to_inbox_rounded),
                  label: const Text('Resend activation'),
                ),
                OutlinedButton.icon(
                  onPressed: () => _checkActivation(session),
                  icon: const Icon(Icons.refresh_rounded),
                  label: const Text('Check status'),
                ),
              ],
            ),
          ),
        ],
      );

  Widget _expiredState() => ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          PremiumHeroCard(
            eyebrow: 'SESSION',
            title: 'Sign in again to refresh membership',
            message: 'Your saved ABS session is no longer accepted by the server. Sign in again to securely load your current plan and requests.',
            trailing: const Icon(Icons.lock_clock_outlined, color: AbsColors.gold, size: 38),
            footer: SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const AuthScreen())),
                icon: const Icon(Icons.login_rounded),
                label: const Text('Sign in again'),
              ),
            ),
          ),
        ],
      );

  Future<void> _resendActivation(AppSession session) async {
    final email = JsonTools.text(session.user?['email'], '');
    if (email.isEmpty) return;
    try {
      await session.api.post('/auth/activation/resend', body: {'email': email});
      if (mounted) showSnack(context, 'Activation email sent. Check your inbox and spam folder.');
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    }
  }

  Future<void> _checkActivation(AppSession session) async {
    try {
      await session.refreshIdentity();
      if (!mounted) return;
      if (session.emailVerified) {
        showSnack(context, 'Account activated. Loading your Pulse membership.');
        await _load();
      } else {
        showSnack(context, 'Activation is still pending. Open the email link, then check again.');
      }
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    }
  }

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
    if (currentPlan.isEmpty && plans.isEmpty && requests.isEmpty) {
      final session = SessionScope.of(context);
      return [
        PremiumHeroCard(
          eyebrow: session.hasPulseAccess ? 'PULSE ACCESS ACTIVE' : 'PULSE MEMBERSHIP',
          title: session.hasPulseAccess ? 'Your Pulse access is active' : 'Membership details are syncing',
          message: session.hasPulseAccess
              ? 'ABS recognizes your Pulse access. Plan details are temporarily unavailable; refresh to load the current package and expiry.'
              : 'ABS did not return membership catalogue data yet. Pull to refresh or try again shortly.',
          trailing: Icon(
            session.hasPulseAccess ? Icons.verified_rounded : Icons.sync_rounded,
            color: AbsColors.gold,
            size: 38,
          ),
          footer: SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: _load,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Refresh membership'),
            ),
          ),
        ),
      ];
    }
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
                    child: const Text('Pay with USDT'),
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
  bool loadingPayment = true;
  bool submitting = false;
  bool acknowledged = false;
  String? paymentError;
  Map<String, dynamic> payment = <String, dynamic>{};
  File? proof;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadPaymentDetails());
  }

  @override
  void dispose() {
    reference.dispose();
    notes.dispose();
    super.dispose();
  }

  Map<String, dynamic> get _quote => JsonTools.map(payment['quote']);

  String get _currency => JsonTools.text(
        _quote['currency'] ?? widget.plan['currency'],
        'USDT',
      );

  dynamic get _amountRaw => _quote['final_amount'] ??
      _quote['amount'] ??
      widget.plan['price'] ??
      widget.plan['monthly_price'] ??
      widget.plan['amount'] ??
      JsonTools.at(widget.plan, 'pricing.amount');

  String get _network => JsonTools.text(
        payment['network'] ?? widget.commerce['network'],
        '',
      );

  String get _wallet => JsonTools.text(
        payment['wallet_address'] ?? widget.commerce['wallet_address'],
        '',
      );

  String get _instructions => JsonTools.text(
        payment['payment_instructions'] ?? widget.commerce['payment_instructions'],
        '',
      );

  bool get _proofRequired => JsonTools.boolean(
        payment['proof_required'],
        JsonTools.boolean(widget.commerce['proof_required']),
      );

  int get _durationDays {
    for (final value in <dynamic>[
      widget.plan['duration_days'],
      widget.plan['plan_days'],
      widget.plan['days'],
      widget.plan['access_days'],
      JsonTools.at(widget.plan, 'pricing.duration_days'),
    ]) {
      final days = JsonTools.integer(value);
      if (days > 0) return days;
    }
    return 30;
  }

  bool get _paymentReady =>
      !loadingPayment && paymentError == null && payment.isNotEmpty && _wallet.isNotEmpty && _network.isNotEmpty;

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.of(context).viewInsets.bottom;
    final planName = JsonTools.text(widget.plan['name'], 'Pulse plan');
    return SafeArea(
      top: false,
      child: Padding(
        padding: EdgeInsets.fromLTRB(18, 12, 18, 18 + bottom),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 46,
                  height: 4,
                  decoration: BoxDecoration(
                    color: AbsColors.muted.withOpacity(.35),
                    borderRadius: BorderRadius.circular(99),
                  ),
                ),
              ),
              const SizedBox(height: 18),
              const Text(
                'ABS PULSE · SECURE PACKAGE PAYMENT',
                style: TextStyle(
                  color: AbsColors.cyanSoft,
                  fontSize: 9.5,
                  fontWeight: FontWeight.w900,
                  letterSpacing: 1.35,
                ),
              ),
              const SizedBox(height: 7),
              Text(
                planName,
                style: const TextStyle(fontSize: 25, fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 7),
              const Text(
                'Transfer the exact package amount, keep the blockchain transaction ID, then submit it to ABS for manual verification.',
                style: TextStyle(color: AbsColors.muted, height: 1.45),
              ),
              const SizedBox(height: 16),
              if (loadingPayment)
                const AbsCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      LinearProgressIndicator(minHeight: 2),
                      SizedBox(height: 12),
                      Text('Preparing secure payment instructions...', style: TextStyle(fontWeight: FontWeight.w800)),
                      SizedBox(height: 4),
                      Text('ABS is loading the exact amount, network and destination wallet for this package.', style: TextStyle(color: AbsColors.muted, height: 1.4)),
                    ],
                  ),
                )
              else if (paymentError != null)
                ErrorBlock(message: paymentError!, onRetry: _loadPaymentDetails)
              else ...[
                AbsCard(
                  accent: AbsColors.gold,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('PAYMENT SUMMARY', style: TextStyle(color: AbsColors.cyanSoft, fontSize: 9.5, fontWeight: FontWeight.w900, letterSpacing: 1.1)),
                                SizedBox(height: 4),
                                Text('Direct USDT transfer', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900)),
                              ],
                            ),
                          ),
                          const StatusChip('SECURE VERIFICATION', good: true),
                        ],
                      ),
                      const SizedBox(height: 16),
                      KeyValueRow('Amount to transfer', '$_currency ${number(_amountRaw)}'),
                      KeyValueRow('Access after approval', '$_durationDays days'),
                      const Divider(height: 24),
                      const Text('TRANSFER DESTINATION', style: TextStyle(color: AbsColors.muted, fontSize: 9.5, fontWeight: FontWeight.w900, letterSpacing: .9)),
                      const SizedBox(height: 9),
                      KeyValueRow('Network', _network.isEmpty ? 'Not configured' : _network),
                      const SizedBox(height: 6),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AbsColors.bg.withOpacity(.45),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AbsColors.line),
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(
                              child: SelectableText(
                                _wallet.isEmpty ? 'Wallet not configured' : _wallet,
                                style: TextStyle(
                                  color: _wallet.isEmpty ? AbsColors.red : AbsColors.text,
                                  fontWeight: FontWeight.w700,
                                  height: 1.35,
                                ),
                              ),
                            ),
                            if (_wallet.isNotEmpty)
                              IconButton(
                                tooltip: 'Copy wallet address',
                                onPressed: () async {
                                  await Clipboard.setData(ClipboardData(text: _wallet));
                                  if (mounted) showSnack(context, 'Wallet address copied.');
                                },
                                icon: const Icon(Icons.copy_rounded, size: 19),
                              ),
                          ],
                        ),
                      ),
                      if (_instructions.isNotEmpty) ...[
                        const SizedBox(height: 10),
                        Text(_instructions, style: const TextStyle(color: AbsColors.muted, fontSize: 11.5, height: 1.45)),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                AbsCard(
                  accent: AbsColors.cyan,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: const [
                      Text('HOW TO ACTIVATE', style: TextStyle(color: AbsColors.cyanSoft, fontSize: 10, fontWeight: FontWeight.w900, letterSpacing: 1.0)),
                      SizedBox(height: 12),
                      _PaymentStep(number: '1', title: 'Transfer', detail: 'Send the exact USDT amount to the wallet shown above using the displayed network.'),
                      SizedBox(height: 10),
                      _PaymentStep(number: '2', title: 'Keep the TXID', detail: 'Copy the blockchain transaction ID after your transfer is submitted.'),
                      SizedBox(height: 10),
                      _PaymentStep(number: '3', title: 'Submit for verification', detail: 'ABS reviews the transaction and activates the package after confirmation.'),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                const Text('VERIFY YOUR PAYMENT', style: TextStyle(color: AbsColors.cyanSoft, fontSize: 10, fontWeight: FontWeight.w900, letterSpacing: 1.0)),
                const SizedBox(height: 10),
                TextField(
                  controller: reference,
                  decoration: const InputDecoration(
                    labelText: 'USDT transaction ID / hash',
                    hintText: 'Paste the blockchain TXID',
                  ),
                ),
                const SizedBox(height: 10),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    onPressed: _pickProof,
                    icon: const Icon(Icons.attach_file_rounded),
                    label: Text(
                      proof == null
                          ? (_proofRequired ? 'Attach payment proof (required)' : 'Attach payment proof (optional)')
                          : proof!.path.split(Platform.pathSeparator).last,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: notes,
                  maxLines: 3,
                  decoration: const InputDecoration(
                    labelText: 'Note (optional)',
                    hintText: 'Anything the verification team should know',
                  ),
                ),
                const SizedBox(height: 10),
                AbsCard(
                  child: CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    controlAffinity: ListTileControlAffinity.leading,
                    value: acknowledged,
                    onChanged: (v) => setState(() => acknowledged = v == true),
                    title: const Text(
                      'I confirm the wallet address and network before transfer and acknowledge the Terms, Risk Disclosure and Market Disclaimer.',
                      style: TextStyle(fontSize: 11.5, height: 1.4),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: submitting || !_paymentReady || !acknowledged ? null : _submit,
                    icon: submitting
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                        : const Icon(Icons.verified_user_outlined),
                    label: Text(submitting ? 'Submitting...' : 'Submit Payment for Verification'),
                  ),
                ),
                if (!_paymentReady && _wallet.isEmpty) ...[
                  const SizedBox(height: 8),
                  const Text(
                    'Payment submission is disabled until ABS returns a configured destination wallet and network.',
                    style: TextStyle(color: AbsColors.gold, fontSize: 10.5, height: 1.4),
                  ),
                ],
              ],
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _loadPaymentDetails() async {
    if (!mounted) return;
    setState(() {
      loadingPayment = true;
      paymentError = null;
    });
    try {
      final response = await SessionScope.of(context).api.post(
        '/pulse/membership/quote',
        body: {'pulse_plan_id': JsonTools.integer(widget.plan['id'])},
      );
      payment = JsonTools.map(JsonTools.at(response, 'data', <String, dynamic>{}));
      if (payment.isEmpty) {
        throw const FormatException('Empty payment response');
      }
    } on ApiException catch (e) {
      paymentError = e.message;
    } catch (_) {
      paymentError = 'ABS could not load the package payment instructions. Please retry.';
    } finally {
      if (mounted) setState(() => loadingPayment = false);
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
    if (!_paymentReady) {
      showSnack(context, 'Refresh the secure payment instructions before submitting.', error: true);
      return;
    }
    if (reference.text.trim().length < 6) {
      showSnack(context, 'Enter the USDT blockchain transaction ID / hash.', error: true);
      return;
    }
    if (_proofRequired && proof == null) {
      showSnack(context, 'Payment proof is required for this package request.', error: true);
      return;
    }
    if (!acknowledged) {
      showSnack(context, 'Confirm the payment acknowledgement before submitting.', error: true);
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
          'Payment submitted for ABS verification.',
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

class _PaymentStep extends StatelessWidget {
  const _PaymentStep({required this.number, required this.title, required this.detail});
  final String number;
  final String title;
  final String detail;

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 26,
            height: 26,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: AbsColors.cyan.withOpacity(.08),
              borderRadius: BorderRadius.circular(9),
              border: Border.all(color: AbsColors.cyan.withOpacity(.26)),
            ),
            child: Text(number, style: const TextStyle(color: AbsColors.cyanSoft, fontWeight: FontWeight.w900)),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 12.5)),
                const SizedBox(height: 2),
                Text(detail, style: const TextStyle(color: AbsColors.muted, fontSize: 10.5, height: 1.4)),
              ],
            ),
          ),
        ],
      );
}
