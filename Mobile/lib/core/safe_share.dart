import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:share_plus/share_plus.dart';

import '../ads/ad_service.dart';

/// One share entry point for the whole app.
///
/// Apple's share sheet is a popover on iPad and must be anchored: share_plus
/// rejects a zero rect with
/// `sharePositionOrigin: argument must be set ... must be non-zero`, which is
/// what made every ABS share button fail on iOS. The origin here is derived
/// from the real widget the user tapped.
///
/// It also suppresses App Open ads for the duration, because returning from the
/// system share sheet is an external handoff, not a genuine app open.
class SafeShare {
  const SafeShare._();

  /// Shares [text] anchored to the widget behind [context].
  ///
  /// Returns true when the sheet was presented. Pass the `BuildContext` of the
  /// button itself (a `Builder` inside the button, or the widget's own context)
  /// so the popover points at the control the user actually pressed.
  static Future<bool> shareText(
    BuildContext context, {
    required String text,
    String? subject,
    String failureMessage = 'Sharing is unavailable right now.',
  }) async {
    final origin = originFor(context);
    final messenger = ScaffoldMessenger.maybeOf(context);

    // A share sheet hands the user to the system UI; coming back is not a
    // meaningful app open.
    AdService.instance.suppressAppOpenForExternalFlow();
    try {
      await Share.share(text, subject: subject, sharePositionOrigin: origin);
      return true;
    } on PlatformException catch (error) {
      _note(error);
      _warn(messenger, failureMessage);
      return false;
    } catch (error) {
      _note(error);
      _warn(messenger, failureMessage);
      return false;
    } finally {
      // Re-arm the suppression so a slow dismissal still cannot trigger an ad.
      AdService.instance.suppressAppOpenForExternalFlow();
    }
  }

  /// The global rect of the widget behind [context], for the iPad popover.
  ///
  /// Falls back to a small non-zero rect at the centre of the screen when the
  /// render object is gone (the button was disposed mid-gesture). Never returns
  /// [Rect.zero], which the platform rejects outright.
  static Rect originFor(BuildContext context) {
    final box = context.findRenderObject() as RenderBox?;
    if (box != null && box.hasSize && box.size.width > 0) {
      return box.localToGlobal(Offset.zero) & box.size;
    }
    final size = MediaQuery.maybeSizeOf(context) ?? const Size(390, 844);
    return Rect.fromCenter(
      center: Offset(size.width / 2, size.height / 2),
      width: 1,
      height: 1,
    );
  }

  static void _warn(ScaffoldMessengerState? messenger, String message) {
    messenger?.showSnackBar(
      SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
    );
  }

  static void _note(Object error) {
    if (kDebugMode) {
      final code = error is PlatformException ? error.code : error.runtimeType;
      debugPrint('[SHARE] failed ($code)');
    }
  }
}
