import 'package:flutter_test/flutter_test.dart';
import 'package:abs_pulse/core/notifications/notification_router.dart';
import 'package:abs_pulse/core/notifications/notification_topics.dart';

void main() {
  NotificationDestination go(String type, {bool authenticated = true}) =>
      NotificationRouter.resolve({'type': type}, authenticated: authenticated);

  test('every historical type maps to its destination', () {
    expect(go('market_move').tab, PulseTab.home);
    expect(go('daily_brief').tab, PulseTab.home);
    expect(go('qualified_signal').tab, PulseTab.pulse);
    expect(go('entry_watch').tab, PulseTab.pulse);
    expect(go('macro_event').tab, PulseTab.news);
    expect(go('breaking_news').tab, PulseTab.news);
    expect(go('package_expiry'),
        const NotificationDestination(PulseTab.account, openPlans: true));
    expect(go('security').tab, PulseTab.account);
  });

  test('private alert mirrors route to Account; market notices to Home', () {
    for (final type in [
      'trade',
      'risk',
      'system',
      'support',
      'private_investor'
    ]) {
      expect(go(type).tab, PulseTab.account, reason: type);
    }
    expect(go('market_notice').tab, PulseTab.home);
  });

  test('private types never open account data for signed-out users', () {
    expect(go('package_expiry', authenticated: false),
        const NotificationDestination(PulseTab.account));
    expect(go('security', authenticated: false),
        const NotificationDestination(PulseTab.account));
  });

  test('unknown or incomplete payloads fall back to Home', () {
    expect(NotificationRouter.resolve({}, authenticated: false).tab,
        PulseTab.home);
    expect(go('something_new').tab, PulseTab.home);
    expect(
        NotificationRouter.resolve({'event': ' Breaking_News '},
                authenticated: false)
            .tab,
        PulseTab.news);
  });

  test('pending destination is consumed once', () {
    final router = NotificationRouter.instance;
    router.open({'type': 'breaking_news'}, authenticated: false);
    expect(router.take()?.tab, PulseTab.news);
    expect(router.take(), isNull);
  });

  test('guests only get public topics', () {
    expect(NotificationTopics.desiredFor(authenticated: false), {
      'abs_market_alerts',
      'abs_breaking_news',
      'abs_macro_alerts',
      'abs_daily_brief',
    });
  });

  test('members add signal topics, filtered by saved preferences', () {
    expect(NotificationTopics.desiredFor(authenticated: true),
        NotificationTopics.all);
    expect(
      NotificationTopics.desiredFor(
        authenticated: true,
        preferences: {'signals': false, 'daily_brief': false, 'market': true},
      ),
      {'abs_market_alerts', 'abs_breaking_news', 'abs_macro_alerts'},
    );
  });

  test('no topic is used for private account notifications', () {
    for (final topic in NotificationTopics.all) {
      expect(topic, isNot(contains('security')));
      expect(topic, isNot(contains('expiry')));
    }
    expect(
        NotificationTypes.private,
        containsAll(<String>[
          'package_expiry',
          'security',
          'qualified_signal',
          'trade',
          'risk'
        ]));
    expect(NotificationTypes.private, isNot(contains('market_move')));
    expect(NotificationTypes.private, isNot(contains('breaking_news')));
  });
}
