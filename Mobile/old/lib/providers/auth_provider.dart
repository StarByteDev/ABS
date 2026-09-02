import 'package:flutter/foundation.dart';

import '../core/api_client.dart';
import '../models/user_model.dart';

class AuthProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient();

  UserModel? user;
  bool loading = false;
  bool booting = true;
  String? error;

  bool get isLoggedIn => user != null;

  Future<void> loadSession() async {
    booting = true;
    notifyListeners();

    final token = _api.token;
    if (token.isEmpty) {
      booting = false;
      notifyListeners();
      return;
    }

    try {
      final data = await _api.get('/me');
      user = UserModel.fromJson(data['user']);
    } catch (_) {
      await _api.clearToken();
      user = null;
    }

    booting = false;
    notifyListeners();
  }

  Future<bool> login(String email, String password) async {
    loading = true;
    error = null;
    notifyListeners();

    try {
      final data = await _api.post('/login', {
        'email': email,
        'password': password,
      });

      await _api.saveToken(data['token'].toString());
      user = UserModel.fromJson(data['user']);
      loading = false;
      notifyListeners();
      return true;
    } catch (e) {
      error = e.toString();
      loading = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? referralCode,
  }) async {
    loading = true;
    error = null;
    notifyListeners();

    try {
      final data = await _api.post('/register', {
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
        'referral_code': referralCode,
      });

      await _api.saveToken(data['token'].toString());
      user = UserModel.fromJson(data['user']);
      loading = false;
      notifyListeners();
      return true;
    } catch (e) {
      error = e.toString();
      loading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    try {
      await _api.post('/logout', {});
    } catch (_) {}
    await _api.clearToken();
    user = null;
    notifyListeners();
  }
}
