// Integration reference for the Pulse Flutter mobile app.
// Requires firebase_core, firebase_messaging, flutter_local_notifications.
// Wire registerToken to authenticated POST /api/v1/devices and openEvent to
// the existing Economic Calendar navigation. This file is NOT yet integrated
// into the last available Pulse V1.6.4 source tree.

import 'dart:io';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

@pragma('vm:entry-point')
Future<void> pulseMacroBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  // Notification display is handled by FCM in the background/terminated state.
}

class PulseMacroPushBridge {
  PulseMacroPushBridge({
    required this.registerToken,
    required this.openEvent,
  });

  final Future<void> Function(String token) registerToken;
  final void Function(int eventId) openEvent;
  final _local = FlutterLocalNotificationsPlugin();

  static const channel = AndroidNotificationChannel(
    'pulse_macro', 'Pulse Economic Releases',
    description: 'Verified macro results with Pulse crypto market interpretation',
    importance: Importance.high,
  );

  Future<void> initialize() async {
    // Call only after Firebase.initializeApp() AND authenticated login.
    // In main() register FirebaseMessaging.onBackgroundMessage(...).
    await _local.initialize(
      const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
        iOS: DarwinInitializationSettings(),
      ),
      onDidReceiveNotificationResponse: (response) {
        final id = int.tryParse(response.payload ?? '');
        if (id != null) openEvent(id);
      },
    );
    await _local.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>()?.createNotificationChannel(channel);

    final messaging = FirebaseMessaging.instance;
    await messaging.requestPermission(alert: true, badge: true, sound: true);
    if (Platform.isIOS) {
      await messaging.setForegroundNotificationPresentationOptions(
        alert: true, badge: true, sound: true,
      );
    }
    final token = await messaging.getToken();
    if (token != null) await registerToken(token);
    messaging.onTokenRefresh.listen((token) async => registerToken(token));
    FirebaseMessaging.onMessage.listen((message) async {
      if (Platform.isAndroid && message.data['type'] == 'macro_release') {
        final note = message.notification;
        if (note != null) {
          await _local.show(
            message.messageId.hashCode, note.title, note.body,
            const NotificationDetails(
              android: AndroidNotificationDetails(
                'pulse_macro', 'Pulse Economic Releases',
                channelDescription: 'Released economic results and Pulse market analysis',
                importance: Importance.high, priority: Priority.high,
              ),
            ),
            payload: message.data['event_id']?.toString(),
          );
        }
      }
    });
    FirebaseMessaging.onMessageOpenedApp.listen(_openMessage);
    final initial = await messaging.getInitialMessage();
    if (initial != null) _openMessage(initial);
  }

  void _openMessage(RemoteMessage message) {
    if (message.data['type'] != 'macro_release') return;
    final id = int.tryParse(message.data['event_id']?.toString() ?? '');
    if (id != null) openEvent(id);
  }
}
