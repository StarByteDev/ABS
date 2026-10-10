import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';

import '../core/api_client.dart';
import '../core/json_tools.dart';
import '../core/session.dart';

/// Mobile V1.7.0: server-authoritative investor eligibility and portfolio.
/// This is a display preference, NOT authorization. Every privileged action
/// remains authenticated and checked by the ABS V15.9.1 server.
class InvestorExperience extends ChangeNotifier {
  InvestorExperience(this.session) {
    session.addListener(_onSessionChanged);
  }

  final AppSession session;
  bool loading = false;
  bool eligible = false;
  bool needsChoice = false;
  bool showBoth = false;
  bool investorMode = false;
  String? error;
  Map<String, dynamic> account = {};
  List<Map<String, dynamic>> statements = [];
  List<Map<String, dynamic>> requests = [];
  List<Map<String, dynamic>> transactions = [];
  bool hasMoreTransactions = false;
  bool transactionsLoading = false;
  String? transactionsError;
  int _transactionPage = 0;
  String? _userKey;
  bool _inFlight = false;

  static String _keyFor(Map<String, dynamic>? user) =>
      JsonTools.text(user?['id'] ?? user?['email'], '');

  void _onSessionChanged() {
    final key = session.authenticated ? _keyFor(session.user) : '';
    if (key.isEmpty) {
      if (_userKey != null) {
        _userKey = null;
        eligible = false;
        investorMode = false;
        needsChoice = false;
        showBoth = false;
        account = {};
        statements = [];
        requests = [];
        transactions = [];
        hasMoreTransactions = false;
        _transactionPage = 0;
        notifyListeners();
      }
      return;
    }
    if (key != _userKey) {
      _userKey = key;
      unawaited(refresh());
    }
  }

  Future<void> refresh() async {
    if (_inFlight || !session.authenticated || !session.emailVerified) return;
    _inFlight = true;
    loading = true;
    error = null;
    notifyListeners();
    final identity = _keyFor(session.user);
    try {
      final raw = await session.api.get('/private/account');
      if (!session.authenticated || _keyFor(session.user) != identity) return;
      final payload = JsonTools.map(JsonTools.at(raw, 'data'));
      final role = JsonTools.text(session.user?['role'], '').toLowerCase();
      final isInvestor = payload.isNotEmpty || role.contains('investor');
      eligible = isInvestor;
      account = payload;
      if (isInvestor) {
        final mode = await session.storage.read(key: 'pulse_mode_$identity');
        final seen = await session.storage.read(key: 'pulse_intro_$identity');
        investorMode = mode == 'trader' ? false : true;
        showBoth = mode == 'both';
        needsChoice = seen != 'yes';
        // Each investor feed is independent: a statement error must never
        // conceal transactions or requests. The account itself remains
        // authoritative for aggregate accounting values.
        statements = JsonTools.mapList(payload['recent_statements']);
        requests = [];
        transactions = JsonTools.mapList(payload['recent_transactions']);
        hasMoreTransactions = false;
        _transactionPage = 0;
        transactionsError = null;
        final feeds = await Future.wait<dynamic>([
          session.api.get('/private/statements', query: {'per_page': 50})
              .catchError((Object _) => null),
          session.api.get('/private/requests', query: {'per_page': 50})
              .catchError((Object _) => null),
          session.api.get('/private/transactions', query: {'per_page': 100, 'page': 1})
              .catchError((Object e) { transactionsError = e is ApiException ? e.message : 'Ledger unavailable'; return null; }),
        ]);
        if (!session.authenticated || _keyFor(session.user) != identity) return;
        if (feeds[0] != null) statements = JsonTools.pageItems(feeds[0]);
        if (feeds[1] != null) requests = JsonTools.pageItems(feeds[1]);
        if (feeds[2] != null) {
          transactions = JsonTools.pageItems(feeds[2]);
          final meta = JsonTools.map(JsonTools.at(feeds[2], 'data'));
          _transactionPage = JsonTools.integer(meta['current_page'], 1);
          hasMoreTransactions = _transactionPage < JsonTools.integer(meta['last_page'], 1);
        }
      } else {
        investorMode = false;
        needsChoice = false;
        showBoth = false;
        statements = [];
        requests = [];
        transactions = [];
        hasMoreTransactions = false;
      }
    } on ApiException catch (e) {
      // Treat transport errors as unverified, not a reason to expose private data.
      error = e.message;
      if (e.statusCode == 401 || e.statusCode == 403) {
        eligible = false;
        investorMode = false;
        account = {};
        statements = [];
        requests = [];
        transactions = [];
        hasMoreTransactions = false;
      }
    } catch (_) {
      error = 'Unable to refresh investor account. Try again.';
    } finally {
      _inFlight = false;
      loading = false;
      notifyListeners();
    }
  }

  /// Loads the authenticated ledger a page at a time. Amount totals in the
  /// UI come from /private/account, never from a potentially partial page.
  Future<void> loadMoreTransactions() async {
    if (transactionsLoading || !hasMoreTransactions || !eligible || !session.authenticated) return;
    final identity = _keyFor(session.user);
    transactionsLoading = true;
    transactionsError = null;
    notifyListeners();
    try {
      final raw = await session.api.get('/private/transactions',
        query: {'per_page': 100, 'page': _transactionPage + 1});
      if (!session.authenticated || identity != _keyFor(session.user)) return;
      final next = JsonTools.pageItems(raw);
      final known = transactions.map((e) => JsonTools.text(e['id'], '')).toSet();
      transactions.addAll(next.where((r) => !known.contains(JsonTools.text(r['id'], ''))));
      final meta = JsonTools.map(JsonTools.at(raw, 'data'));
      _transactionPage = JsonTools.integer(meta['current_page'], _transactionPage + 1);
      hasMoreTransactions = _transactionPage < JsonTools.integer(meta['last_page'], _transactionPage);
    } on ApiException catch (e) {
      transactionsError = e.message;
    } catch (_) {
      transactionsError = 'Unable to load more investor transactions.';
    } finally {
      transactionsLoading = false;
      notifyListeners();
    }
  }

  Future<void> selectMode(String value) async {
    if (!eligible) return;
    final next = (value == 'investor' || value == 'trader' || value == 'both')
        ? value : 'investor';
    investorMode = next != 'trader';
    showBoth = next == 'both';
    needsChoice = false;
    final identity = _keyFor(session.user);
    if (identity.isNotEmpty) {
      await session.storage.write(key: 'pulse_mode_$identity', value: next);
      await session.storage.write(key: 'pulse_intro_$identity', value: 'yes');
    }
    notifyListeners();
  }

  Future<void> switchMode(bool isInvestor) async {
    if (!eligible) return;
    investorMode = isInvestor;
    final identity = _keyFor(session.user);
    if (identity.isNotEmpty) {
      await session.storage.write(
        key: 'pulse_mode_$identity',
        value: showBoth ? 'both' : (isInvestor ? 'investor' : 'trader'),
      );
    }
    notifyListeners();
  }

  Future<void> submitRequest({
    required String type,
    double? amount,
    String message = '',
  }) async {
    if (!eligible || !session.authenticated) {
      throw const ApiException('Private investor access is not available.');
    }
    if (!{'add_investment', 'withdrawal', 'portfolio_review'}.contains(type)) {
      throw const ApiException('Unsupported request type.');
    }
    if (type != 'portfolio_review' && (amount == null || amount <= 0)) {
      throw const ApiException('Enter an amount greater than zero.');
    }
    final currency = JsonTools.text(account['currency'], 'USD');
    await session.api.post('/private/requests', body: {
      'type': type,
      if (amount != null && type != 'portfolio_review') 'amount': amount,
      'currency': currency,
      'message': message.trim(),
    });
    await refresh();
  }

  @override
  void dispose() {
    session.removeListener(_onSessionChanged);
    super.dispose();
  }
}

class InvestorScope extends InheritedNotifier<InvestorExperience> {
  const InvestorScope({super.key, required InvestorExperience experience, required super.child})
      : super(notifier: experience);

  static InvestorExperience of(BuildContext context) {
    final scope = context.dependOnInheritedWidgetOfExactType<InvestorScope>();
    assert(scope != null, 'InvestorScope missing');
    return scope!.notifier!;
  }

  static InvestorExperience read(BuildContext context) {
    final element = context.getElementForInheritedWidgetOfExactType<InvestorScope>();
    final scope = element?.widget as InvestorScope?;
    assert(scope != null, 'InvestorScope missing');
    return scope!.notifier!;
  }
}
