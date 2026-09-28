import 'package:flutter_test/flutter_test.dart';
import 'package:abs_pulse/core/session.dart';

void main() {
  test('null email_verified_at keeps account limited', () {
    final session = AppSession();
    session.api.token = 'test-token';
    session.user = <String, dynamic>{
      'id': 1,
      'name': 'ABS Member',
      'status': 'active',
      'email_verified_at': null,
    };
    expect(session.authenticated, isTrue);
    expect(session.emailVerified, isFalse);
    expect(session.limitedAccount, isTrue);
  });

  test('verified timestamp unlocks full account state', () {
    final session = AppSession();
    session.api.token = 'test-token';
    session.user = <String, dynamic>{
      'id': 1,
      'email_verified_at': '2026-09-28T10:00:00Z',
    };
    expect(session.emailVerified, isTrue);
    expect(session.limitedAccount, isFalse);
  });
}
