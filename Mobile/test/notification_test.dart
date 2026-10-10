import 'package:abs_pulse/notifications/notification_config.dart';
import 'package:abs_pulse/notifications/notification_payload.dart';
import 'package:abs_pulse/notifications/notification_preferences.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('notification config defaults', () {
    test('safe defaults match the documented backend contract', () {
      const c = NotificationConfig.defaults;
      expect(c.enabled, isTrue);
      expect(c.marketFastMovePercent, 3.0);
      expect(c.marketFastMoveWindowMinutes, 5);
      expect(c.marketFastMoveCooldownMinutes, 30);
      expect(c.btcFastMovePercent, 3.0);
      expect(c.ethFastMovePercent, 4.0);
      expect(c.macroAlertLeadMinutes, 30);
      expect(c.maxMarketAlertsPerHour, 3);
      // Noisier categories stay off until deliberately enabled.
      expect(c.entryWatchEnabled, isFalse);
      expect(c.freeSignalReadyEnabled, isFalse);
    });

    test('every key has a Remote Config default', () {
      final defaults = NotificationConfig.remoteDefaults;
      for (final key in NotificationConfigKeys.all) {
        expect(defaults.containsKey(key), isTrue, reason: 'missing $key');
      }
      expect(defaults.length, NotificationConfigKeys.all.length);
    });
  });

  group('Remote Config parsing', () {
    test('string-typed server values are coerced correctly', () {
      final c = NotificationConfig.fromRemote({
        NotificationConfigKeys.master: 'true',
        NotificationConfigKeys.btcFastMovePercent: '2.5',
        NotificationConfigKeys.macroAlertLeadMinutes: '45',
        NotificationConfigKeys.entryWatchEnabled: 'true',
        NotificationConfigKeys.maxMarketAlertsPerHour: '5',
      });
      expect(c.enabled, isTrue);
      expect(c.btcFastMovePercent, 2.5);
      expect(c.macroAlertLeadMinutes, 45);
      expect(c.entryWatchEnabled, isTrue);
      expect(c.maxMarketAlertsPerHour, 5);
    });

    test('nonsense values fall back instead of breaking alerting', () {
      final c = NotificationConfig.fromRemote({
        NotificationConfigKeys.btcFastMovePercent: '-4',
        NotificationConfigKeys.ethFastMovePercent: 'not-a-number',
        NotificationConfigKeys.marketFastMoveWindowMinutes: '0',
        NotificationConfigKeys.macroAlertLeadMinutes: '99999',
        NotificationConfigKeys.maxMarketAlertsPerHour: '-1',
        NotificationConfigKeys.master: 'maybe',
      });
      const d = NotificationConfig.defaults;
      expect(c.btcFastMovePercent, d.btcFastMovePercent);
      expect(c.ethFastMovePercent, d.ethFastMovePercent);
      expect(c.marketFastMoveWindowMinutes, d.marketFastMoveWindowMinutes);
      expect(c.macroAlertLeadMinutes, d.macroAlertLeadMinutes);
      expect(c.maxMarketAlertsPerHour, d.maxMarketAlertsPerHour);
      expect(c.enabled, d.enabled);
    });

    test('an empty Remote Config yields exactly the in-app defaults', () {
      final c = NotificationConfig.fromRemote(const {});
      expect(c.toPrettyJson(), NotificationConfig.defaults.toPrettyJson());
    });
  });

  group('payload routing', () {
    AbsNotificationPayload parse(Map<String, dynamic> data) =>
        AbsNotificationPayload.fromData(data);

    test('each documented type reaches its default route', () {
      expect(
        parse({'type': 'market_move', 'symbol': 'BTCUSDT'}).route,
        AbsNotificationRoute.marketDetail,
      );
      expect(
        parse({'type': 'qualified_signal', 'signal_id': '123'}).route,
        AbsNotificationRoute.signalDetail,
      );
      expect(
        parse({'type': 'macro_event'}).route,
        AbsNotificationRoute.calendar,
      );
      expect(parse({'type': 'breaking_news'}).route, AbsNotificationRoute.news);
      expect(parse({'type': 'daily_brief'}).route, AbsNotificationRoute.pulse);
      expect(
        parse({'type': 'package_expiry'}).route,
        AbsNotificationRoute.account,
      );
      expect(parse({'type': 'security'}).route, AbsNotificationRoute.account);
    });

    test('an unknown type navigates nowhere', () {
      final payload = parse({'type': 'totally_made_up'});
      expect(payload.type, AbsNotificationType.unknown);
      expect(payload.route, AbsNotificationRoute.none);
      expect(payload.isActionable, isFalse);
    });

    test('a console campaign with no data opens the app only', () {
      expect(parse(const {}).isActionable, isFalse);
    });

    test('a missing argument degrades to the safe parent screen', () {
      expect(
        parse({'type': 'qualified_signal'}).route,
        AbsNotificationRoute.signals,
      );
      expect(
        parse({'type': 'market_move'}).route,
        AbsNotificationRoute.markets,
      );
    });

    test('a crafted route cannot redirect a type somewhere unrelated', () {
      // A "breaking news" push must never land on signal detail.
      final payload = parse({
        'type': 'breaking_news',
        'route': 'signal_detail',
        'signal_id': '9',
      });
      expect(payload.route, AbsNotificationRoute.news);
    });

    test('symbols and urls are validated before use', () {
      expect(
        parse({'type': 'market_move', 'symbol': 'BTC/../..'}).symbol,
        isNull,
      );
      expect(
        parse({'type': 'market_move', 'symbol': 'ethusdt'}).symbol,
        'ETHUSDT',
      );
      expect(
        parse({'type': 'breaking_news', 'url': 'javascript:alert(1)'}).url,
        isNull,
      );
      expect(
        parse({'type': 'breaking_news', 'url': 'https://abs.example/a'}).url,
        'https://abs.example/a',
      );
    });

    test('a non-positive signal id is rejected', () {
      expect(
        parse({'type': 'qualified_signal', 'signal_id': '0'}).signalId,
        isNull,
      );
      expect(
        parse({'type': 'qualified_signal', 'signal_id': '-3'}).route,
        AbsNotificationRoute.signals,
      );
    });
  });

  group('topics and preference mapping', () {
    test('broadcast categories have topics, personal ones never do', () {
      for (final category in NotificationCategory.values) {
        final personal =
            category == NotificationCategory.security ||
            category == NotificationCategory.packageExpiry;
        expect(
          category.topic,
          personal ? isNull : isNotNull,
          reason: category.key,
        );
      }
    });

    test('no topic name can carry PII', () {
      for (final category in NotificationCategory.values) {
        final topic = category.topic;
        if (topic == null) continue;
        expect(topic, startsWith('abs_'));
        expect(RegExp(r'^[a-z0-9_]+$').hasMatch(topic), isTrue);
        expect(topic.contains('@'), isFalse);
      }
    });

    test('security alerts are mandatory and cannot be disabled', () {
      expect(NotificationCategory.security.mandatory, isTrue);
      final prefs = NotificationPreferences(null);
      expect(
        prefs.isEnabled(
          NotificationCategory.security,
          NotificationConfig.defaults,
        ),
        isTrue,
      );
    });

    test('the master switch silences every optional category', () async {
      final prefs = NotificationPreferences(null);
      await prefs.setMaster(false);
      const config = NotificationConfig.defaults;
      for (final category in NotificationCategory.values) {
        expect(
          prefs.isEnabled(category, config),
          category.mandatory,
          reason: category.key,
        );
      }
      expect(prefs.desiredTopics(config), isEmpty);
    });

    test(
      'Remote Config can gate a category the user has switched on',
      () async {
        final prefs = NotificationPreferences(null);
        await prefs.setCategory(NotificationCategory.breakingNews, true);
        final off = NotificationConfig.fromRemote({
          NotificationConfigKeys.breakingNewsEnabled: 'false',
        });
        expect(
          prefs.isEnabled(NotificationCategory.breakingNews, off),
          isFalse,
        );
        expect(prefs.desiredTopics(off), isNot(contains('abs_breaking_news')));
        expect(prefs.unwantedTopics(off), contains('abs_breaking_news'));
      },
    );

    test('wanted and unwanted topics never overlap', () {
      final prefs = NotificationPreferences(null);
      const config = NotificationConfig.defaults;
      expect(
        prefs.desiredTopics(config).intersection(prefs.unwantedTopics(config)),
        isEmpty,
      );
      // Entry Watch is default-off, so it starts in the unwanted set.
      expect(prefs.unwantedTopics(config), contains('abs_entry_watch'));
    });
  });
}
