import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../core/session.dart';
import '../../screens/auth_screens.dart' show ForgotPasswordScreen;
import '../state/app_state.dart';
import '../theme/app_theme.dart';
import '../widgets/common.dart';

class AuthScreen extends StatefulWidget {
  const AuthScreen({super.key, this.startRegister = false});
  final bool startRegister;

  @override
  State<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends State<AuthScreen> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _countryCode = TextEditingController(text: '+971');
  final _phone = TextEditingController();
  final _password = TextEditingController();
  bool _register = false;
  bool _obscure = true;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _register = widget.startRegister;
  }

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _countryCode.dispose();
    _phone.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate() || _busy) return;
    final session = SessionScope.of(context);
    final state = AppScope.read(context);
    setState(() => _busy = true);
    try {
      if (_register) {
        final countryCode = _countryCode.text.trim();
        final phone = _phone.text.trim();
        await session.register(<String, dynamic>{
          'name': _name.text.trim(),
          'email': _email.text.trim(),
          'country_code': countryCode,
          'phone': phone,
          'password': _password.text,
        });
        if (!mounted) return;
        if (session.authenticated) {
          // Older ABS register endpoints accepted only name/email/password,
          // while the account profile endpoint already supports phone fields.
          // Persist it immediately when possible, but never block account
          // creation if an unverified token cannot edit profile yet.
          try {
            await session.api.patch('/profile', body: <String, dynamic>{
              'country_code': countryCode,
              'phone': phone,
            });
            await session.refreshAccount();
          } on ApiException {
            // The same values were also included in /auth/register for newer
            // backends. If profile is gated until activation, the user can
            // still complete/confirm the number later from Profile.
          }
          await state.refreshHome();
          await state.refreshNews();
          await state.refreshAccount();
          if (!mounted) return;
          snack(
            context,
            session.emailVerified
                ? 'Account created. Welcome to Pulse.'
                : 'Account created. Basic access is ready now. Activate your email from Account to unlock all features.',
          );
          Navigator.of(context).pop();
          return;
        }
        snack(
          context,
          'Account created. Sign in to continue while you wait for the activation email.',
        );
        setState(() => _register = false);
      } else {
        await session.login(_email.text.trim(), _password.text);
        if (!mounted) return;
        await state.refreshHome();
        await state.refreshNews();
        await state.refreshAccount();
        if (session.emailVerified) await state.refreshPulse();
        if (!mounted) return;
        snack(
          context,
          session.emailVerified
              ? 'Signed in to Pulse.'
              : 'Signed in with basic access. Activate your email to unlock full Pulse features.',
        );
        Navigator.of(context).pop();
      }
    } on ApiException catch (e) {
      if (mounted) snack(context, e.message);
    } catch (_) {
      if (mounted) snack(context, 'ABS could not complete this request. Please try again.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(24, 8, 24, 28),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 440),
              child: Form(
                key: _form,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    const Center(child: AbsLogo(size: 58)),
                    const SizedBox(height: 20),
                    Text(
                      _register ? 'Create your ABS account' : 'Welcome back',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontSize: 26,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -.5,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      _register
                          ? 'Start with market intelligence and Free Signal. Email activation unlocks full member and trading tools.'
                          : 'Sign in to sync your Pulse access, signals, watchlist and account tools.',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: AppColors.muted, height: 1.45),
                    ),
                    const SizedBox(height: 26),
                    SegmentToggle(
                      options: const <String>['Sign in', 'Create account'],
                      index: _register ? 1 : 0,
                      onChanged: (i) => setState(() => _register = i == 1),
                    ),
                    const SizedBox(height: 20),
                    if (_register) ...<Widget>[
                      TextFormField(
                        controller: _name,
                        textCapitalization: TextCapitalization.words,
                        textInputAction: TextInputAction.next,
                        decoration: const InputDecoration(
                          labelText: 'Full name',
                          prefixIcon: Icon(Icons.person_outline_rounded),
                        ),
                        validator: (v) => (v == null || v.trim().isEmpty)
                            ? 'Enter your name'
                            : null,
                      ),
                      const SizedBox(height: 12),
                    ],
                    TextFormField(
                      controller: _email,
                      keyboardType: TextInputType.emailAddress,
                      textInputAction: TextInputAction.next,
                      decoration: const InputDecoration(
                        labelText: 'Email',
                        prefixIcon: Icon(Icons.mail_outline_rounded),
                      ),
                      validator: (v) => (v != null && v.contains('@'))
                          ? null
                          : 'Enter a valid email address',
                    ),
                    if (_register) ...<Widget>[
                      const SizedBox(height: 12),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          SizedBox(
                            width: 104,
                            child: TextFormField(
                              controller: _countryCode,
                              keyboardType: TextInputType.phone,
                              textInputAction: TextInputAction.next,
                              decoration: const InputDecoration(
                                labelText: 'Code',
                                hintText: '+971',
                              ),
                              validator: (v) => RegExp(r'^\+[0-9]{1,6}$').hasMatch((v ?? '').trim())
                                  ? null
                                  : 'Use +code',
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: TextFormField(
                              controller: _phone,
                              keyboardType: TextInputType.phone,
                              textInputAction: TextInputAction.next,
                              decoration: const InputDecoration(
                                labelText: 'Mobile number',
                                prefixIcon: Icon(Icons.phone_android_rounded),
                              ),
                              validator: (v) {
                                final value = (v ?? '').trim();
                                if (value.length < 5) return 'Enter mobile number';
                                return RegExp(r'^[0-9\s().-]+$').hasMatch(value)
                                    ? null
                                    : 'Use digits only';
                              },
                            ),
                          ),
                        ],
                      ),
                    ],
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _password,
                      obscureText: _obscure,
                      onFieldSubmitted: (_) => _submit(),
                      decoration: InputDecoration(
                        labelText: 'Password',
                        helperText: _register
                            ? 'Use 8+ characters with upper/lower case and a number.'
                            : null,
                        prefixIcon: const Icon(Icons.lock_outline_rounded),
                        suffixIcon: IconButton(
                          onPressed: () => setState(() => _obscure = !_obscure),
                          icon: Icon(
                            _obscure
                                ? Icons.visibility_outlined
                                : Icons.visibility_off_outlined,
                          ),
                        ),
                      ),
                      validator: (v) {
                        final value = v ?? '';
                        if (_register) {
                          if (value.length < 8) return 'Use at least 8 characters';
                          if (!RegExp(r'[A-Z]').hasMatch(value) ||
                              !RegExp(r'[a-z]').hasMatch(value) ||
                              !RegExp(r'[0-9]').hasMatch(value)) {
                            return 'Use upper/lower case letters and a number';
                          }
                          return null;
                        }
                        return value.length >= 6 ? null : 'Enter your password';
                      },
                    ),
                    if (!_register)
                      Align(
                        alignment: Alignment.centerRight,
                        child: TextButton(
                          onPressed: () => push(context, const ForgotPasswordScreen()),
                          child: const Text('Forgot password?'),
                        ),
                      ),
                    SizedBox(height: _register ? 22 : 8),
                    FilledButton(
                      onPressed: _busy ? null : _submit,
                      child: Text(
                        _busy
                            ? 'Please wait…'
                            : _register
                                ? 'Create account'
                                : 'Sign in',
                      ),
                    ),
                    const SizedBox(height: 10),
                    TextButton(
                      onPressed: _busy ? null : () => Navigator.of(context).pop(),
                      child: const Text('Continue with public access'),
                    ),
                    const SizedBox(height: 14),
                    const Text(
                      'Your exchange credentials, trading permissions and risk controls remain protected by the ABS server.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: AppColors.faint, fontSize: 12, height: 1.4),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
