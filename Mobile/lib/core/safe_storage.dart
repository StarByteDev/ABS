import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Keychain/Keystore access that can fail without taking a screen down with it.
///
/// `flutter_secure_storage` throws a raw [PlatformException] when the platform
/// keychain is unavailable — on iOS that surfaces as OSStatus `-25291`
/// ("No keychain is available"), which is exactly what crashed the Free Signal
/// flow *after* a reward had already been earned.
///
/// Secure storage here is a convenience cache for resumable local state
/// (visitor token, pending-claim marker, launch counters). The server stays
/// authoritative for the reward, the claim and the cooldown, so a failed read,
/// write or delete must degrade quietly rather than throw.
class SafeStorage {
  const SafeStorage._();

  /// Set when the platform keychain has refused at least once this session, so
  /// the UI can explain a degraded state instead of pretending nothing is wrong.
  static bool get unavailable => _unavailable;
  static bool _unavailable = false;

  /// Reads a key, returning null when secure storage is unavailable.
  static Future<String?> read(
    FlutterSecureStorage storage, {
    required String key,
  }) async {
    try {
      return await storage.read(key: key);
    } on PlatformException catch (error) {
      _note('read', key, error);
      return null;
    } catch (error) {
      _note('read', key, error);
      return null;
    }
  }

  /// Writes a key. Returns false when the value could not be persisted.
  static Future<bool> write(
    FlutterSecureStorage storage, {
    required String key,
    required String value,
  }) async {
    try {
      await storage.write(key: key, value: value);
      return true;
    } on PlatformException catch (error) {
      _note('write', key, error);
      return false;
    } catch (error) {
      _note('write', key, error);
      return false;
    }
  }

  /// Deletes a key. Returns false when the entry could not be removed.
  ///
  /// A failed delete is never fatal: callers clear their in-memory copy so a
  /// stale marker cannot be replayed as a duplicate claim.
  static Future<bool> delete(
    FlutterSecureStorage storage, {
    required String key,
  }) async {
    try {
      await storage.delete(key: key);
      return true;
    } on PlatformException catch (error) {
      _note('delete', key, error);
      return false;
    } catch (error) {
      _note('delete', key, error);
      return false;
    }
  }

  /// Deletes several keys, reporting whether every one of them succeeded.
  static Future<bool> deleteAll(
    FlutterSecureStorage storage, {
    required List<String> keys,
  }) async {
    var ok = true;
    for (final key in keys) {
      if (!await delete(storage, key: key)) ok = false;
    }
    return ok;
  }

  static void _note(String operation, String key, Object error) {
    final code = error is PlatformException ? error.code : error.runtimeType;
    // -25291 is errSecNotAvailable: the keychain itself is not reachable.
    if (error is PlatformException &&
            (error.message?.contains('-25291') ?? false) ||
        '$error'.contains('-25291')) {
      _unavailable = true;
    }
    if (kDebugMode) {
      // Key names only — never the stored value.
      debugPrint('[STORAGE] $operation failed for "$key" ($code)');
    }
  }
}
