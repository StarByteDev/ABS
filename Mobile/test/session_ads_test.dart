import 'package:abs_pulse/core/session.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('normal ad eligibility waits for session and package resolution', () {
    final session = AppSession();

    expect(session.canShowMonetizationAds, isFalse);

    session.initializing = false;
    session.accessResolved = true;
    expect(session.canShowMonetizationAds, isTrue);

    session.api.token = 'test-token';
    session.user = <String, dynamic>{'id': 1};
    session.accessResolved = false;
    expect(session.canShowMonetizationAds, isFalse);

    session.access = <String, dynamic>{'has_access': false};
    session.accessResolved = true;
    expect(session.canShowMonetizationAds, isTrue);

    session.access = <String, dynamic>{'has_access': true};
    expect(session.canShowMonetizationAds, isFalse);

    session.dispose();
  });
}
