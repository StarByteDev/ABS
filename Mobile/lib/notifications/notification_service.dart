import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:firebase_remote_config/firebase_remote_config.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../ads/ad_service.dart';
import '../core/safe_storage.dart';
import '../core/session.dart';
import '../firebase_options.dart';
import 'notification_config.dart';
import 'notification_payload.dart';
import 'notification_preferences.dart';

/// Background/terminated FCM handler.
///
/// Must be a top-level function. The system draws the notification itself in
/// this state, so nothing is displayed here — Firebase only needs to be
/// initialised so the message is delivered and the tap is routed on launch.
@pragma('vm:entry-point')
Future<void> absFirebaseBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp(
      options: DefaultFirebaseOptions.currentPlatform,
    );
  } catch (_) {
    // A background isolate that cannot init Firebase simply drops the message.
  }
}

/// Android notification channels. Only genuinely time-sensitive alerts get
/// high importance; everything else stays quiet by default.
class AbsChannels {
  const AbsChannels._();

  static const market = AndroidNotificationChannel(
    'abs_market_alerts',
    'Pulse Market Alerts',
    description: 'Rapid market moves on BTC, ETH and tracked markets.',
    importance: Importance.high,
  );
  static const signals = AndroidNotificationChannel(
    'abs_signals',
    'Pulse Signals',
    description: 'Qualified Pulse signals and Entry Watch alerts.',
    importance: Importance.high,
  );
  static const macroNews = AndroidNotificationChannel(
    'abs_macro_news',
    'Pulse Macro & News',
    description: 'Economic events, breaking news and the daily brief.',
    importance: Importance.defaultImportance,
  );
  static const account = AndroidNotificationChannel(
    'abs_account',
    'Pulse Account',
    description: 'Package expiry, security and login alerts.',
    importance: Importance.high,
  );

  static const all = [market, signals, macroNews, account];

  static AndroidNotificationChannel forType(AbsNotificationType type) =>
      switch (type) {
        AbsNotificationType.marketMove => market,
        AbsNotificationType.qualifiedSignal ||
        AbsNotificationType.entryWatch => signals,
        AbsNotificationType.macroEvent ||
        AbsNotificationType.breakingNews ||
        AbsNotificationType.dailyBrief => macroNews,
        AbsNotificationType.packageExpiry ||
        AbsNotificationType.security => account,
        AbsNotificationType.unknown => macroNews,
      };
}

/// Owns Firebase Messaging, Remote Config, permission state and topic sync.
///
/// Every Firebase call is guarded: if Firebase is unavailable the service
/// reports itself unavailable, ABS keeps working on in-app defaults, and no
/// exception escapes into the app.
class NotificationService extends ChangeNotifier {
  NotificationService._();

  static final NotificationService instance = NotificationService._();

  static const _permissionPromptKey = 'abs_notify_prompt_seen';

  final FlutterLocalNotificationsPlugin _local =
      FlutterLocalNotificationsPlugin();

  AppSession? _session;
  NotificationPreferences? _preferences;
  NotificationConfig _config = NotificationConfig.defaults;
  bool _available = false;
  bool _initialized = false;
  bool _permissionGranted = false;
  bool _promptSeen = false;
  String? _token;
  DateTime? _lastRemoteFetch;
  StreamSubscription<RemoteMessage>? _foregroundSub;
  StreamSubscription<String>? _tokenSub;
  StreamSubscription<RemoteMessage>? _openedSub;

  /// Fires when a notification should navigate. The app listens once.
  final ValueNotifier<AbsNotificationPayload?> pendingRoute = ValueNotifier(
    null,
  );

  NotificationConfig get config => _config;
  NotificationPreferences? get preferences => _preferences;
  bool get available => _available;
  bool get permissionGranted => _permissionGranted;
  bool get promptSeen => _promptSeen;

  /// Debug-only. Never surfaced in release UI.
  String? get debugToken => kDebugMode ? _token : null;

  /// Remote Config is refreshed on init and on a meaningful foreground return,
  /// never per screen or per rebuild.
  static const _minimumRefreshGap = Duration(minutes: 30);

  Future<void> initialize(AppSession session) async {
    _session = session;
    _preferences = NotificationPreferences(session.storage);
    await _preferences!.load();
    _promptSeen = (await _readFlag(_permissionPromptKey)) ?? false;
    notifyListeners();

    if (_initialized) return;
    _initialized = true;

    try {
      await Firebase.initializeApp(
        options: DefaultFirebaseOptions.currentPlatform,
      );
      _available = true;
    } catch (error) {
      _log('firebase unavailable (${error.runtimeType}) — using defaults');
      notifyListeners();
      return;
    }

    await _initializeLocalNotifications();
    await _refreshRemoteConfig(force: true);
    await _wireMessaging();
    notifyListeners();
  }

  Future<void> _initializeLocalNotifications() async {
    try {
      await _local.initialize(
        settings: const InitializationSettings(
          android: AndroidInitializationSettings('@mipmap/ic_launcher'),
          iOS: DarwinInitializationSettings(
            // The OS prompt is requested later, behind the ABS explainer.
            requestAlertPermission: false,
            requestBadgePermission: false,
            requestSoundPermission: false,
          ),
        ),
        onDidReceiveNotificationResponse: (response) {
          final raw = response.payload;
          if (raw == null || raw.isEmpty) return;
          _queue(AbsNotificationPayload.fromData(_decode(raw)));
        },
      );
      final android = _local
          .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin
          >();
      for (final channel in AbsChannels.all) {
        await android?.createNotificationChannel(channel);
      }
    } catch (error) {
      _log('local notifications unavailable (${error.runtimeType})');
    }
  }

  Future<void> _wireMessaging() async {
    try {
      final messaging = FirebaseMessaging.instance;
      final settings = await messaging.getNotificationSettings();
      _permissionGranted =
          settings.authorizationStatus == AuthorizationStatus.authorized ||
          settings.authorizationStatus == AuthorizationStatus.provisional;

      // iOS: let the system present foreground notifications itself so a
      // local copy is never drawn on top of it.
      await messaging.setForegroundNotificationPresentationOptions(
        alert: true,
        badge: true,
        sound: true,
      );

      _foregroundSub = FirebaseMessaging.onMessage.listen(_onForeground);
      _openedSub = FirebaseMessaging.onMessageOpenedApp.listen(_onOpened);
      _tokenSub = messaging.onTokenRefresh.listen((token) {
        _token = token;
        _log('token refreshed');
        notifyListeners();
      });

      final initial = await messaging.getInitialMessage();
      if (initial != null) _onOpened(initial);

      if (_permissionGranted) await _afterPermissionGranted();
    } catch (error) {
      _log('messaging wiring failed (${error.runtimeType})');
    }
  }

  /// Called only after the user taps "Enable Notifications" in the ABS
  /// explainer — never automatically on the first frame.
  Future<bool> requestPermission() async {
    _promptSeen = true;
    await _writeFlag(_permissionPromptKey, true);
    if (!_available) {
      notifyListeners();
      return false;
    }
    try {
      final settings = await FirebaseMessaging.instance.requestPermission(
        alert: true,
        badge: true,
        sound: true,
      );
      _permissionGranted =
          settings.authorizationStatus == AuthorizationStatus.authorized ||
          settings.authorizationStatus == AuthorizationStatus.provisional;
      _log(_permissionGranted ? 'permission granted' : 'permission denied');
      if (_permissionGranted) await _afterPermissionGranted();
    } catch (error) {
      _log('permission request failed (${error.runtimeType})');
      _permissionGranted = false;
    }
    notifyListeners();
    return _permissionGranted;
  }

  Future<void> _afterPermissionGranted() async {
    try {
      _token = await FirebaseMessaging.instance.getToken();
      if (_token != null) _log('token available');
    } catch (error) {
      _log('token unavailable (${error.runtimeType})');
    }
    await syncTopics();
  }

  /// Applies the current preferences to FCM topic subscriptions.
  Future<void> syncTopics() async {
    final prefs = _preferences;
    if (!_available || prefs == null || !_permissionGranted) return;
    final messaging = FirebaseMessaging.instance;
    try {
      for (final topic in prefs.desiredTopics(_config)) {
        await messaging.subscribeToTopic(topic);
      }
      for (final topic in prefs.unwantedTopics(_config)) {
        await messaging.unsubscribeFromTopic(topic);
      }
      _log('topics synced (${prefs.desiredTopics(_config).length} active)');
    } catch (error) {
      _log('topic sync failed (${error.runtimeType})');
    }
  }

  Future<void> setMaster(bool value) async {
    await _preferences?.setMaster(value);
    notifyListeners();
    await syncTopics();
  }

  Future<void> setCategory(NotificationCategory category, bool value) async {
    await _preferences?.setCategory(category, value);
    notifyListeners();
    await syncTopics();
  }

  /// Fetch + activate Remote Config, rate-limited so it never runs per screen.
  Future<void> _refreshRemoteConfig({bool force = false}) async {
    if (!_available) return;
    final last = _lastRemoteFetch;
    if (!force &&
        last != null &&
        DateTime.now().difference(last) < _minimumRefreshGap) {
      return;
    }
    try {
      final remote = FirebaseRemoteConfig.instance;
      await remote.setConfigSettings(
        RemoteConfigSettings(
          fetchTimeout: const Duration(seconds: 12),
          // Short in development so config changes are testable; production
          // uses a sensible interval so ABS is not chatty.
          minimumFetchInterval: kDebugMode
              ? const Duration(minutes: 1)
              : const Duration(hours: 6),
        ),
      );
      await remote.setDefaults(NotificationConfig.remoteDefaults);
      await remote.fetchAndActivate();
      _config = NotificationConfig.fromRemote(
        remote.getAll().map((key, value) => MapEntry(key, value.asString())),
      );
      _lastRemoteFetch = DateTime.now();
      _log('remote config active');
      notifyListeners();
      await syncTopics();
    } catch (error) {
      _log('remote config unavailable (${error.runtimeType}) — using defaults');
    }
  }

  /// Called on a meaningful foreground return.
  Future<void> refreshOnForeground() => _refreshRemoteConfig();

  void _onForeground(RemoteMessage message) {
    final payload = AbsNotificationPayload.fromData(message.data);
    if (!_isAllowed(payload.type)) {
      _log('foreground message suppressed by preferences');
      return;
    }
    // iOS presents foreground notifications itself (configured above), so a
    // local copy here would duplicate it. Android does not, so it draws one.
    if (defaultTargetPlatform == TargetPlatform.android) {
      _showLocal(message, payload);
    }
    _log('foreground message: ${payload.type.name}');
  }

  void _onOpened(RemoteMessage message) {
    final payload = AbsNotificationPayload.fromData(message.data);
    _queue(payload);
  }

  void _queue(AbsNotificationPayload payload) {
    if (!payload.isActionable) return;
    // The user tapped a notification to reach specific content. An App Open or
    // interstitial ad landing on top of that would be a bait-and-switch.
    AdService.instance.suppressAppOpenForExternalFlow();
    pendingRoute.value = payload;
    _log('opened: ${payload.type.name} -> ${payload.route.name}');
  }

  void consumeRoute() => pendingRoute.value = null;

  bool _isAllowed(AbsNotificationType type) {
    final prefs = _preferences;
    if (prefs == null) return true;
    final category = switch (type) {
      AbsNotificationType.marketMove => NotificationCategory.rapidMarketMoves,
      AbsNotificationType.breakingNews => NotificationCategory.breakingNews,
      AbsNotificationType.qualifiedSignal =>
        NotificationCategory.qualifiedSignals,
      AbsNotificationType.entryWatch => NotificationCategory.entryWatch,
      AbsNotificationType.macroEvent => NotificationCategory.macroEvents,
      AbsNotificationType.dailyBrief => NotificationCategory.dailyBrief,
      AbsNotificationType.packageExpiry => NotificationCategory.packageExpiry,
      AbsNotificationType.security => NotificationCategory.security,
      // A console campaign carries no ABS type; the master switch governs it.
      AbsNotificationType.unknown => null,
    };
    if (category == null) return prefs.master && _config.enabled;
    return prefs.isEnabled(category, _config);
  }

  Future<void> _showLocal(
    RemoteMessage message,
    AbsNotificationPayload payload,
  ) async {
    final notification = message.notification;
    final title = notification?.title ?? payload.title;
    final body = notification?.body ?? payload.body;
    if (title == null && body == null) return;
    final channel = AbsChannels.forType(payload.type);
    try {
      await _local.show(
        id: message.hashCode,
        title: title,
        body: body,
        notificationDetails: NotificationDetails(
          android: AndroidNotificationDetails(
            channel.id,
            channel.name,
            channelDescription: channel.description,
            importance: channel.importance,
            priority: channel.importance == Importance.high
                ? Priority.high
                : Priority.defaultPriority,
          ),
        ),
        payload: _encode(message.data),
      );
    } catch (error) {
      _log('local display failed (${error.runtimeType})');
    }
  }

  Future<bool?> _readFlag(String key) async {
    final storage = _session?.storage;
    if (storage == null) return null;
    final raw = await SafeStorage.read(storage, key: key);
    return raw == null ? null : raw == 'true';
  }

  Future<void> _writeFlag(String key, bool value) async {
    final storage = _session?.storage;
    if (storage == null) return;
    await SafeStorage.write(storage, key: key, value: value.toString());
  }

  static String _encode(Map<String, dynamic> data) =>
      data.entries.map((e) => '${e.key}=${e.value}').join('&');

  static Map<String, dynamic> _decode(String raw) => {
    for (final pair in raw.split('&'))
      if (pair.contains('=')) pair.split('=').first: pair.split('=').last,
  };

  void _log(String message) {
    if (kDebugMode) debugPrint('[FCM] $message');
  }

  @override
  void dispose() {
    _foregroundSub?.cancel();
    _openedSub?.cancel();
    _tokenSub?.cancel();
    pendingRoute.dispose();
    super.dispose();
  }
}
