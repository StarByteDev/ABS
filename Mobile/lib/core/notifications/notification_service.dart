import 'dart:async';
import 'dart:convert';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../json_tools.dart';
import '../session.dart';
import 'notification_router.dart';
import 'notification_topics.dart';

/// Background/terminated FCM entry point. Notification messages are shown by
/// the OS while the app is in the background; this only needs Firebase ready.
@pragma('vm:entry-point')
Future<void> pulseFirebaseBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
}

/// Central Pulse push runtime: Firebase start-up, permission, FCM token and
/// refresh, foreground display, tap routing and guest/member topic sync.
/// Never blocks app start-up and never throws.
class NotificationService {
  NotificationService._();

  static final NotificationService instance = NotificationService._();

  static const String _topicsKey = 'abs_push_topics';
  static const AndroidNotificationChannel _channel = AndroidNotificationChannel(
    'pulse_alerts',
    'Pulse alerts',
    description: 'Market moves, Pulse signals, news and account alerts.',
    importance: Importance.high,
  );

  final FlutterLocalNotificationsPlugin _local =
      FlutterLocalNotificationsPlugin();

  AppSession? _session;
  bool _firebaseReady = false;
  bool _started = false;
  bool _ready = false;
  String? _lastAccount;
  Future<void> _sync = Future<void>.value();

  bool get _supported =>
      !kIsWeb &&
      (defaultTargetPlatform == TargetPlatform.android ||
          defaultTargetPlatform == TargetPlatform.iOS);

  bool get _isAndroid => defaultTargetPlatform == TargetPlatform.android;

  /// Called from main() before runApp: starts Firebase and registers the
  /// background handler. Returns false when this platform has no Firebase
  /// config (e.g. iOS without GoogleService-Info.plist); push then stays off.
  Future<bool> initializeFirebase() async {
    if (_firebaseReady || !_supported) return _firebaseReady;
    try {
      await Firebase.initializeApp();
      FirebaseMessaging.onBackgroundMessage(pulseFirebaseBackgroundHandler);
      _firebaseReady = true;
    } catch (_) {
      _debugLog('Firebase unavailable on this platform; push disabled.');
    }
    return _firebaseReady;
  }

  /// Starts permission, token, message and topic handling. Call after the
  /// session is restored so the first sync matches the signed-in account.
  Future<void> initialize(AppSession session) async {
    if (_started || !_firebaseReady) return;
    _started = true;
    _session = session;
    try {
      await _initLocalNotifications();

      final messaging = FirebaseMessaging.instance;
      await messaging.setForegroundNotificationPresentationOptions(
        alert: true,
        badge: true,
        sound: true,
      );
      FirebaseMessaging.onMessage.listen(_onForegroundMessage);
      FirebaseMessaging.onMessageOpenedApp.listen(_onOpened);
      messaging.onTokenRefresh.listen((token) => _onToken(token, fresh: true));

      final initial = await messaging.getInitialMessage();
      if (initial != null) _onOpened(initial);
      final launch = await _local.getNotificationAppLaunchDetails();
      if (launch?.didNotificationLaunchApp ?? false) {
        _openPayload(launch?.notificationResponse?.payload);
      }

      // Android 13+ POST_NOTIFICATIONS and iOS alert permission.
      await messaging.requestPermission(alert: true, badge: true, sound: true);

      _ready = true;
      _lastAccount = _accountKey(session);
      session.addListener(_onSessionChanged);
      final token = await messaging.getToken();
      _debugLog(token == null || token.isEmpty
          ? 'FCM token NOT obtained.'
          : 'FCM token obtained (${token.length} chars).');
      await _onToken(token);
    } catch (error) {
      // Push is supplementary; Pulse must keep working without it.
      _debugLog('Notification start-up failed: ${error.runtimeType}');
    }
  }

  /// Debug-only diagnostics. Never logs the token itself, and compiles to
  /// nothing useful in release/profile builds.
  void _debugLog(String message) {
    if (kDebugMode) debugPrint('[Pulse push] $message');
  }

  /// Identifies the signed-in account (null for guests) so an account switch
  /// is detected even without an intermediate sign-out.
  static String? _accountKey(AppSession session) {
    if (!session.authenticated) return null;
    final user = session.user ?? const <String, dynamic>{};
    return JsonTools.text(user['id'] ?? user['email'], 'signed-in');
  }

  Future<void> _initLocalNotifications() async {
    await _local.initialize(
      settings: const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
        iOS: DarwinInitializationSettings(
          requestAlertPermission: false,
          requestBadgePermission: false,
          requestSoundPermission: false,
        ),
      ),
      onDidReceiveNotificationResponse: (response) =>
          _openPayload(response.payload),
    );
    await _local
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(_channel);
  }

  /// Private account types must only arrive device-targeted. Anything private
  /// that comes through a public topic is dropped.
  bool _allowed(RemoteMessage message) {
    final fromTopic = (message.from ?? '').startsWith('/topics/');
    return !(fromTopic &&
        NotificationTypes.private
            .contains(NotificationRouter.typeOf(message.data)));
  }

  void _onForegroundMessage(RemoteMessage message) {
    // iOS presents foreground notifications natively (options above); on
    // Android FCM does not, so Pulse shows them through the local channel.
    if (!_isAndroid || !_allowed(message)) return;
    final title = message.notification?.title ??
        JsonTools.text(message.data['title'], '');
    final body =
        message.notification?.body ?? JsonTools.text(message.data['body'], '');
    if (title.isEmpty && body.isEmpty) return;
    _local.show(
      id: message.messageId?.hashCode ?? DateTime.now().millisecondsSinceEpoch,
      title: title,
      body: body,
      notificationDetails: NotificationDetails(
        android: AndroidNotificationDetails(
          _channel.id,
          _channel.name,
          channelDescription: _channel.description,
          importance: Importance.high,
          priority: Priority.high,
        ),
      ),
      payload: jsonEncode(message.data),
    );
  }

  void _onOpened(RemoteMessage message) {
    if (!_allowed(message)) return;
    _route(message.data);
  }

  void _openPayload(String? payload) {
    if (payload == null || payload.isEmpty) return _route(const {});
    try {
      _route(JsonTools.map(jsonDecode(payload)));
    } catch (_) {
      _route(const {});
    }
  }

  void _route(Map<String, dynamic> data) {
    NotificationRouter.instance.open(
      data,
      authenticated: _session?.authenticated ?? false,
    );
  }

  Future<void> _onToken(String? token, {bool fresh = false}) async {
    final session = _session;
    if (session == null || token == null || token.isEmpty) return;
    final changed = session.pushToken != token;
    session.pushToken = token;
    if (fresh) _debugLog('FCM token refreshed; re-registering device.');
    // A new token carries no topic subscriptions; resubscribe from scratch.
    if (fresh || changed) await _writeTopics(const <String>{});
    if (changed && session.authenticated) await session.syncDevice();
    _queueTopicSync();
  }

  void _onSessionChanged() {
    final session = _session;
    if (session == null || !_ready) return;
    final account = _accountKey(session);
    if (account == _lastAccount) return;
    final previous = _lastAccount;
    _lastAccount = account;
    if (previous != null) {
      // Sign-out or account switch: rotate the token so nothing addressed to
      // the previous account can reach this device (even if the detach call
      // failed). The new token is registered for the new account, if any.
      _sync = _sync.then((_) => _rotateToken()).catchError((_) {});
    } else {
      // Guest -> signed in: attach the current token to the account and
      // add member topics.
      _sync = _sync.then((_) => session.syncDevice()).catchError((_) {});
      _queueTopicSync();
    }
  }

  Future<void> _rotateToken() async {
    final messaging = FirebaseMessaging.instance;
    await messaging.deleteToken();
    _session?.pushToken = null;
    await _onToken(await messaging.getToken(), fresh: true);
  }

  void _queueTopicSync() {
    _sync = _sync.then((_) => _syncTopics()).catchError((_) {});
  }

  Future<void> _syncTopics() async {
    final session = _session;
    if (session == null || session.pushToken == null) return;
    var preferences = const <String, dynamic>{};
    if (session.authenticated) {
      try {
        final response = await session.api.get('/notification-preferences');
        preferences = JsonTools.map(JsonTools.at(response, 'data'));
      } catch (_) {}
    }
    final desired = NotificationTopics.desiredFor(
      authenticated: session.authenticated,
      preferences: preferences,
    );
    final current = await _readTopics();
    final messaging = FirebaseMessaging.instance;
    final removed = current.difference(desired);
    final added = desired.difference(current);
    for (final topic in removed) {
      await messaging.unsubscribeFromTopic(topic);
    }
    for (final topic in added) {
      await messaging.subscribeToTopic(topic);
    }
    await _writeTopics(desired);
    _debugLog('Topics (${session.authenticated ? 'signed-in' : 'guest'}): '
        '${(desired.toList()..sort()).join(', ')} '
        '[+${added.length} -${removed.length}]');
  }

  Future<Set<String>> _readTopics() async {
    final raw = await _session?.storage.read(key: _topicsKey) ?? '';
    return raw.split(',').where(NotificationTopics.all.contains).toSet();
  }

  Future<void> _writeTopics(Set<String> topics) async {
    await _session?.storage.write(key: _topicsKey, value: topics.join(','));
  }
}
