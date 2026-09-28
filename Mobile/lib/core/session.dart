import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:flutter/widgets.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'api_client.dart';
import 'app_config.dart';
import 'json_tools.dart';

class AppSession extends ChangeNotifier {
  AppSession({ApiClient? api, FlutterSecureStorage? storage})
      : api = api ?? ApiClient(),
        storage = storage ?? const FlutterSecureStorage(
          aOptions: AndroidOptions(encryptedSharedPreferences: true),
        );

  static const _tokenKey = 'abs_sanctum_token';
  static const _deviceUuidKey = 'abs_device_uuid';
  static const _traderExperienceKey = 'abs_trader_experience';
  static const _userCacheKey = 'abs_user_snapshot';

  final ApiClient api;
  final FlutterSecureStorage storage;

  bool initializing = true;
  bool busy = false;
  String? error;
  Map<String, dynamic>? user;
  Map<String, dynamic>? bootstrap;
  Map<String, dynamic>? access;
  String traderExperience = 'simple';

  bool get proMode => traderExperience == 'pro';

  bool get authenticated => user != null && api.token != null && api.token!.isNotEmpty;

  bool get emailVerified {
    final u = user;
    if (u == null || u.isEmpty) return false;

    // Explicit verification fields always win. In Laravel-style payloads an
    // `email_verified_at: null` value means the account is still unverified,
    // even when the general account status itself is active.
    for (final key in ['email_verified_at', 'verified_at', 'activated_at']) {
      if (u.containsKey(key)) {
        return JsonTools.text(u[key], '').isNotEmpty;
      }
    }
    for (final key in ['email_verified', 'is_verified', 'verified', 'activated']) {
      if (u.containsKey(key)) return JsonTools.boolean(u[key]);
    }
    for (final key in ['activation_status', 'verification_status']) {
      if (u.containsKey(key)) {
        final state = JsonTools.text(u[key], '').toLowerCase();
        return ['verified', 'activated', 'active'].contains(state);
      }
    }

    // Compatibility fallback for older ABS user payloads that expose only a
    // general account status and no verification-specific field.
    final state = JsonTools.text(u['status'], '').toLowerCase();
    return ['verified', 'activated', 'active'].contains(state);
  }

  bool get limitedAccount => authenticated && !emailVerified;
  bool get hasPulseAccess => JsonTools.boolean(access?['has_access']);
  Map<String, dynamic> get capabilities => JsonTools.map(access?['effective_capabilities']);
  Map<String, dynamic> get appBootstrap => JsonTools.map(bootstrap?['app']);
  bool get maintenanceMode => JsonTools.boolean(appBootstrap['maintenance_mode']);
  String get maintenanceMessage => JsonTools.text(
        appBootstrap['maintenance_message'],
        'ABS mobile access is temporarily under maintenance. Please try again shortly.',
      );
  String get backendBuild => JsonTools.text(appBootstrap['build'], 'unknown');
  int get marketRefreshSeconds => JsonTools.integer(appBootstrap['market_refresh_seconds'], 60);
  String get minimumMobileVersion => JsonTools.text(appBootstrap['minimum_mobile_version'], '');
  String get recommendedMobileVersion => JsonTools.text(appBootstrap['recommended_mobile_version'], '');
  bool get updateRequired => minimumMobileVersion.isNotEmpty && _compareVersions(AppConfig.mobileVersion, minimumMobileVersion) < 0;
  bool get updateRecommended => recommendedMobileVersion.isNotEmpty && _compareVersions(AppConfig.mobileVersion, recommendedMobileVersion) < 0;
  bool get backendTooOld => backendBuild != 'unknown' && _compareVersions(backendBuild, AppConfig.minimumBackendBuild) < 0;

  int _compareVersions(String current, String target) {
    List<int> parse(String value) => value.split(RegExp(r'[.+-]')).take(3).map((part) => int.tryParse(part.replaceAll(RegExp(r'[^0-9]'), '')) ?? 0).toList();
    final a = parse(current);
    final b = parse(target);
    for (var i = 0; i < 3; i++) {
      final av = i < a.length ? a[i] : 0;
      final bv = i < b.length ? b[i] : 0;
      if (av != bv) return av.compareTo(bv);
    }
    return 0;
  }

  Future<void> initialize() async {
    initializing = true;
    final savedExperience = await storage.read(key: _traderExperienceKey);
    traderExperience = savedExperience == 'pro' ? 'pro' : 'simple';
    error = null;
    notifyListeners();
    try {
      final boot = await api.get('/bootstrap');
      bootstrap = JsonTools.map(JsonTools.at(boot, 'data', <String, dynamic>{}));
    } on ApiException catch (e) {
      bootstrap = <String, dynamic>{};
      error = e.message;
    } catch (_) {
      bootstrap = <String, dynamic>{};
    }

    final saved = await storage.read(key: _tokenKey);
    if (saved != null && saved.isNotEmpty) {
      api.token = saved;
      await _restoreUserSnapshot();
      try {
        await refreshIdentity();
      } on ApiException catch (e) {
        final body = JsonTools.map(e.body);
        final activationRequired = e.statusCode == 403 &&
            (JsonTools.boolean(body['activation_required']) ||
                (user != null && !emailVerified));
        if (activationRequired && user != null) {
          // Keep the registration token and cached identity so an account that is
          // waiting for email activation can still use the public/basic mobile
          // experience. Pulse trading access remains locked until activation.
          access = <String, dynamic>{'has_access': false, 'activation_required': true};
        } else if (e.statusCode == 401 || e.statusCode == 403) {
          await _clearToken();
          user = null;
          access = null;
        } else {
          error = e.message;
        }
      }
    }
    initializing = false;
    notifyListeners();
  }


  Future<void> setTraderExperience(String value) async {
    final next = value == 'pro' ? 'pro' : 'simple';
    if (traderExperience == next) return;
    traderExperience = next;
    await storage.write(key: _traderExperienceKey, value: next);
    notifyListeners();
  }

  Future<void> refreshBootstrap() async {
    final boot = await api.get('/bootstrap');
    bootstrap = JsonTools.map(JsonTools.at(boot, 'data', <String, dynamic>{}));
    notifyListeners();
  }

  Future<void> refreshIdentity() async {
    final me = await api.get('/auth/me');
    user = JsonTools.map(JsonTools.at(me, 'data', <String, dynamic>{}));
    try {
      access = JsonTools.map(await api.get('/pulse/access'));
    } on ApiException catch (e) {
      if (e.statusCode == 403 || e.statusCode == 404) {
        access = <String, dynamic>{'has_access': false};
      } else {
        rethrow;
      }
    }
    await _cacheUserSnapshot();
    await _registerDeviceBestEffort();
    notifyListeners();
  }

  Future<void> login(String email, String password) async {
    await _busy(() async {
      dynamic response;
      try {
        response = await api.post('/auth/login', body: {
          'email': email.trim(),
          'password': password,
          'device_name': _deviceName,
          // Newer ABS backends may use this hint to return a limited-access
          // token before email activation. Older backends safely ignore it.
          'allow_unverified_basic_access': true,
        });
      } on ApiException catch (e) {
        final body = JsonTools.map(e.body);
        if (e.statusCode == 403 && JsonTools.boolean(body['activation_required'])) {
          final limited = JsonTools.map(
            JsonTools.at(body, 'data', <String, dynamic>{}),
          );
          final token = JsonTools.text(limited['token'] ?? body['token'], '');
          final limitedUser = JsonTools.map(limited['user'] ?? body['user']);
          if (token.isNotEmpty && limitedUser.isNotEmpty) {
            await _saveToken(token);
            user = limitedUser;
            access = <String, dynamic>{
              'has_access': false,
              'activation_required': true,
            };
            await _cacheUserSnapshot();
            notifyListeners();
            return;
          }
        }
        rethrow;
      }

      final data = JsonTools.map(JsonTools.at(response, 'data', <String, dynamic>{}));
      final token = JsonTools.text(data['token'], '');
      if (token.isEmpty) throw const ApiException('ABS did not return a login token.');
      await _saveToken(token);
      user = JsonTools.map(data['user']);
      await _cacheUserSnapshot();
      if (emailVerified) {
        await refreshIdentity();
      } else {
        access = <String, dynamic>{
          'has_access': false,
          'activation_required': true,
        };
        notifyListeners();
      }
    });
  }

  Future<Map<String, dynamic>> register(Map<String, dynamic> payload) async {
    late Map<String, dynamic> result;
    await _busy(() async {
      final response = await api.post('/auth/register', body: {
        ...payload,
        'device_name': _deviceName,
      });
      result = JsonTools.map(JsonTools.at(response, 'data', <String, dynamic>{}));
      final token = JsonTools.text(result['token'], '');
      final registeredUser = JsonTools.map(result['user']);

      if (token.isNotEmpty && registeredUser.isNotEmpty) {
        await _saveToken(token);
        user = registeredUser;
        access = <String, dynamic>{
          'has_access': false,
          'activation_required': !emailVerified,
        };
        await _cacheUserSnapshot();
        if (emailVerified) {
          await refreshIdentity();
        } else {
          notifyListeners();
        }
      } else {
        user = null;
        access = null;
      }
    });
    return result;
  }

  Future<void> applyUserSnapshot(Map<String, dynamic> snapshot) async {
    if (snapshot.isEmpty) return;
    user = snapshot;
    await _cacheUserSnapshot();
    notifyListeners();
  }

  Future<void> refreshAccount() async {
    if (!authenticated) return;
    await refreshIdentity();
  }

  Future<void> logout() async {
    try {
      await api.post('/auth/logout');
    } catch (_) {}
    await _clearToken();
    user = null;
    access = null;
    notifyListeners();
  }

  Future<void> logoutAll() async {
    try {
      await api.post('/auth/logout-all');
    } catch (_) {}
    await _clearToken();
    user = null;
    access = null;
    notifyListeners();
  }

  Future<void> _busy(Future<void> Function() action) async {
    busy = true;
    error = null;
    notifyListeners();
    try {
      await action();
    } on ApiException catch (e) {
      error = e.message;
      rethrow;
    } finally {
      busy = false;
      notifyListeners();
    }
  }

  Future<void> _saveToken(String token) async {
    api.token = token;
    await storage.write(key: _tokenKey, value: token);
  }

  Future<void> _clearToken() async {
    api.token = null;
    await storage.delete(key: _tokenKey);
    await storage.delete(key: _userCacheKey);
  }

  Future<void> _cacheUserSnapshot() async {
    final snapshot = user;
    if (snapshot == null || snapshot.isEmpty) return;
    await storage.write(key: _userCacheKey, value: jsonEncode(snapshot));
  }

  Future<void> _restoreUserSnapshot() async {
    final raw = await storage.read(key: _userCacheKey);
    if (raw == null || raw.trim().isEmpty) return;
    try {
      final decoded = jsonDecode(raw);
      final snapshot = JsonTools.map(decoded);
      if (snapshot.isNotEmpty) user = snapshot;
    } catch (_) {
      await storage.delete(key: _userCacheKey);
    }
  }

  Future<String> _deviceUuid() async {
    final existing = await storage.read(key: _deviceUuidKey);
    if (existing != null && existing.isNotEmpty) return existing;
    final random = Random.secure();
    final parts = List<int>.generate(16, (_) => random.nextInt(256));
    parts[6] = (parts[6] & 0x0f) | 0x40;
    parts[8] = (parts[8] & 0x3f) | 0x80;
    String h(int v) => v.toRadixString(16).padLeft(2, '0');
    final hex = parts.map(h).join();
    final value = '${hex.substring(0, 8)}-${hex.substring(8, 12)}-${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
    await storage.write(key: _deviceUuidKey, value: value);
    return value;
  }

  String get _platform {
    if (Platform.isIOS) return 'ios';
    if (Platform.isAndroid) return 'android';
    return 'unknown';
  }

  String get _deviceName => Platform.isIOS ? 'ABS iPhone/iPad' : 'ABS Android';

  Future<void> _registerDeviceBestEffort() async {
    if (!authenticated) return;
    try {
      await api.post('/devices', body: {
        'device_uuid': await _deviceUuid(),
        'platform': _platform,
        'device_name': _deviceName,
        'app_version': '${AppConfig.mobileVersion}+${AppConfig.mobileBuild}',
        'os_version': Platform.operatingSystemVersion,
      });
    } catch (_) {
      // Device registration is supplementary and must never block account access.
    }
  }
}

class SessionScope extends InheritedNotifier<AppSession> {
  const SessionScope({
    super.key,
    required AppSession session,
    required super.child,
  }) : super(notifier: session);

  static AppSession of(BuildContext context) {
    final scope = context.dependOnInheritedWidgetOfExactType<SessionScope>();
    assert(scope != null, 'SessionScope not found');
    return scope!.notifier!;
  }
}
