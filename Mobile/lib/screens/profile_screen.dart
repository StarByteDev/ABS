import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';
import '../core/theme.dart';
import '../widgets/abs_ui.dart';
import 'account_extra_screens.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});
  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final form = GlobalKey<FormState>();
  final name = TextEditingController();
  final email = TextEditingController();
  final country = TextEditingController();
  final code = TextEditingController();
  final phone = TextEditingController();
  bool initialized = false;
  bool saving = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!initialized) {
      final user = SessionScope.of(context).user ?? {};
      name.text = JsonTools.text(user['name'], '');
      email.text = JsonTools.text(user['email'], '');
      country.text = JsonTools.text(user['country'], '');
      code.text = JsonTools.text(user['country_code'], '');
      phone.text = JsonTools.text(user['phone'], '');
      initialized = true;
    }
  }

  @override
  void dispose() {
    for (final c in [name, email, country, code, phone]) { c.dispose(); }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final session = SessionScope.of(context);
    final user = session.user ?? {};
    final verified = session.emailVerified;
    return AbsPage(
      title: 'Profile & Security',
      subtitle: 'ABS account information',
      child: Form(
        key: form,
        child: ListView(
          children: [
            if (!verified) ...[
              AbsCard(
                accent: AbsColors.gold,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 42,
                          height: 42,
                          decoration: BoxDecoration(
                            color: AbsColors.gold.withOpacity(.10),
                            borderRadius: BorderRadius.circular(13),
                          ),
                          child: const Icon(
                            Icons.mark_email_unread_outlined,
                            color: AbsColors.gold,
                            size: 21,
                          ),
                        ),
                        const SizedBox(width: 10),
                        const Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Account not activated', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 15)),
                              SizedBox(height: 3),
                              Text('Basic access is available while you verify your email.', style: TextStyle(color: AbsColors.muted, fontSize: 10.5)),
                            ],
                          ),
                        ),
                        const StatusChip('ACTION NEEDED', warning: true),
                      ],
                    ),
                    const SizedBox(height: 11),
                    Text(
                      'Open the ABS activation link sent to ${JsonTools.text(user['email'], 'your email')}. Full membership, scanner, signals, positions and trading controls unlock after verification.',
                      style: const TextStyle(color: AbsColors.muted, fontSize: 11.5, height: 1.4),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: saving ? null : _resendActivation,
                            icon: const Icon(Icons.outgoing_mail, size: 17),
                            label: const Text('Resend link'),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: ElevatedButton.icon(
                            onPressed: saving ? null : _checkActivation,
                            icon: const Icon(Icons.refresh_rounded, size: 17),
                            label: const Text('Check status'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
            ],
            AbsCard(
              child: Row(
                children: [
                  CircleAvatar(radius: 28, backgroundColor: AbsColors.panel2, child: Text(JsonTools.text(user['name'], 'A').substring(0, 1).toUpperCase(), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 22))),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(JsonTools.text(user['name']), style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18)),
                        const SizedBox(height: 3),
                        Text(JsonTools.text(user['email']), style: const TextStyle(color: AbsColors.muted)),
                      ],
                    ),
                  ),
                  StatusChip(
                    verified ? 'ACTIVATED' : 'NOT ACTIVATED',
                    good: verified,
                    warning: !verified,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            TextFormField(controller: name, decoration: const InputDecoration(labelText: 'Full name'), validator: (v) => (v?.trim().isEmpty ?? true) ? 'Name is required.' : null),
            const SizedBox(height: 10),
            TextFormField(controller: email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email'), validator: (v) => (v == null || !v.contains('@')) ? 'Enter a valid email.' : null),
            const SizedBox(height: 10),
            TextFormField(controller: country, decoration: const InputDecoration(labelText: 'Country')),
            const SizedBox(height: 10),
            Row(children: [
              SizedBox(width: 105, child: TextFormField(controller: code, decoration: const InputDecoration(labelText: 'Code'))),
              const SizedBox(width: 10),
              Expanded(child: TextFormField(controller: phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Phone'))),
            ]),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: saving ? null : _save, child: Text(saving ? 'Saving...' : 'Save profile')),
            const SizedBox(height: 18),
            const AbsSectionTitle('Security'),
            const SizedBox(height: 10),
            if (verified) ...[
              OutlinedButton.icon(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ChangePasswordScreen())), icon: const Icon(Icons.password), label: const Text('Change password')),
              const SizedBox(height: 8),
              OutlinedButton.icon(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const SessionsScreen())), icon: const Icon(Icons.devices), label: const Text('Active sessions')),
              const SizedBox(height: 8),
              OutlinedButton.icon(onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const RegisteredDevicesScreen())), icon: const Icon(Icons.phone_android), label: const Text('Registered mobile devices')),
              const SizedBox(height: 8),
              OutlinedButton.icon(onPressed: _logoutAll, icon: const Icon(Icons.phonelink_erase), label: const Text('Sign out from all devices')),
            ] else
              const AbsCard(
                child: Text(
                  'Advanced security sessions and trading-account controls become available after email activation.',
                  style: TextStyle(color: AbsColors.muted, fontSize: 11.5, height: 1.4),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _save() async {
    if (!form.currentState!.validate()) return;
    setState(() => saving = true);
    try {
      final response = await SessionScope.of(context).api.patch('/profile', body: {
        'name': name.text.trim(),
        'email': email.text.trim(),
        'country': country.text.trim().isEmpty ? null : country.text.trim(),
        'country_code': code.text.trim().isEmpty ? null : code.text.trim(),
        'phone': phone.text.trim().isEmpty ? null : phone.text.trim(),
      });
      final root = JsonTools.map(response);
      final emailVerify = JsonTools.boolean(
        root['email_verification_required'] ??
            JsonTools.at(root, 'data.email_verification_required', false),
      );
      if (emailVerify) {
        final current = Map<String, dynamic>.from(SessionScope.of(context).user ?? {});
        current['name'] = name.text.trim();
        current['email'] = email.text.trim();
        current['email_verified_at'] = null;
        current['activation_status'] = 'pending';
        await SessionScope.of(context).applyUserSnapshot(current);
        if (!mounted) return;
        showSnack(context, 'Profile saved. Basic access stays available while the new email is activated.');
      } else {
        await SessionScope.of(context).refreshIdentity();
        if (!mounted) return;
        showSnack(context, 'Profile updated.');
      }
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Future<void> _resendActivation() async {
    setState(() => saving = true);
    try {
      await SessionScope.of(context).api.post(
        '/auth/activation/resend',
        body: {'email': JsonTools.text((SessionScope.of(context).user ?? <String, dynamic>{})['email'], email.text.trim())},
      );
      if (mounted) showSnack(context, 'Activation email sent.');
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Future<void> _checkActivation() async {
    setState(() => saving = true);
    try {
      await SessionScope.of(context).refreshIdentity();
      if (!mounted) return;
      showSnack(context, 'Account activated. Full ABS features are unlocked.');
      setState(() {});
    } on ApiException catch (e) {
      if (mounted) {
        showSnack(
          context,
          e.statusCode == 403 ? 'Activation is not confirmed yet. Open the email link, then check again.' : e.message,
          error: true,
        );
      }
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Future<void> _logoutAll() async {
    final yes = await showDialog<bool>(context: context, builder: (context) => AlertDialog(
      title: const Text('Sign out everywhere?'),
      content: const Text('All ABS mobile and API sessions for this account will be revoked.'),
      actions: [TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')), ElevatedButton(onPressed: () => Navigator.pop(context, true), child: const Text('Sign out all'))],
    ));
    if (yes == true && mounted) {
      await SessionScope.of(context).logoutAll();
      if (mounted) Navigator.of(context).popUntil((r) => r.isFirst);
    }
  }
}
