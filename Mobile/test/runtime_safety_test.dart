import 'package:abs_pulse/core/safe_share.dart';
import 'package:abs_pulse/core/safe_storage.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('share origin is always a valid popover anchor', () {
    testWidgets('resolves the real rect of the tapped button', (tester) async {
      late Rect origin;
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Center(
              child: Builder(
                builder: (buttonContext) => ElevatedButton(
                  onPressed: () => origin = SafeShare.originFor(buttonContext),
                  child: const Text('Share'),
                ),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Share'));
      await tester.pump();

      // share_plus rejects a zero rect on Apple platforms with
      // "sharePositionOrigin: argument must be set ... must be non-zero".
      expect(origin, isNot(Rect.zero));
      expect(origin.width, greaterThan(0));
      expect(origin.height, greaterThan(0));
      expect(origin.left, greaterThanOrEqualTo(0));
      expect(origin.top, greaterThanOrEqualTo(0));
    });

    testWidgets('falls back to a non-zero rect without a render box', (
      tester,
    ) async {
      late BuildContext captured;
      await tester.pumpWidget(
        MaterialApp(
          home: Builder(
            builder: (context) {
              captured = context;
              return const SizedBox.shrink();
            },
          ),
        ),
      );
      final origin = SafeShare.originFor(captured);
      expect(origin, isNot(Rect.zero));
      expect(origin.width, greaterThan(0));
      expect(origin.height, greaterThan(0));
    });
  });

  group('secure storage degrades instead of crashing', () {
    late List<MethodCall> calls;

    setUp(() {
      calls = [];
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(
            const MethodChannel('plugins.it_nomads.com/flutter_secure_storage'),
            (call) async {
              calls.add(call);
              // Exactly what iOS raises when no keychain is available.
              throw PlatformException(
                code: 'Unexpected security result code',
                message: 'Code: -25291, Message: No keychain is available.',
              );
            },
          );
    });

    tearDown(() {
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(
            const MethodChannel('plugins.it_nomads.com/flutter_secure_storage'),
            null,
          );
    });

    test('a keychain failure never escapes read/write/delete', () async {
      const storage = FlutterSecureStorage();

      await expectLater(
        SafeStorage.read(storage, key: 'abs_free_signal_pending_claim'),
        completion(isNull),
      );
      await expectLater(
        SafeStorage.write(storage, key: 'k', value: 'v'),
        completion(isFalse),
      );
      await expectLater(
        SafeStorage.delete(storage, key: 'k'),
        completion(isFalse),
      );
      await expectLater(
        SafeStorage.deleteAll(storage, keys: const ['a', 'b']),
        completion(isFalse),
      );
      expect(calls, isNotEmpty);
    });
  });
}
