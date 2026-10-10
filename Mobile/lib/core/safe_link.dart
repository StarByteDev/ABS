import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../ads/ad_service.dart';

/// Opens external URLs without ever producing a dead button.
///
/// `launchUrl` throws when no handler exists and returns false when the
/// platform declines. Fire-and-forget calls therefore surfaced as unhandled
/// async exceptions while the button appeared to do nothing. Every failure
/// path now tells the user.
///
/// Leaving for a browser is an external handoff, so App Open is suppressed.
class SafeLink {
  const SafeLink._();

  static Future<bool> open(
    BuildContext context,
    String url, {
    String failureMessage = 'That link could not be opened.',
  }) async {
    final messenger = ScaffoldMessenger.maybeOf(context);
    final uri = Uri.tryParse(url.trim());
    if (uri == null || !uri.hasScheme || !uri.host.isNotEmpty) {
      _note('invalid url');
      _warn(messenger, failureMessage);
      return false;
    }

    AdService.instance.suppressAppOpenForExternalFlow();
    try {
      final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!opened) {
        _note('platform declined');
        _warn(messenger, failureMessage);
      }
      return opened;
    } catch (error) {
      _note('$error');
      _warn(messenger, failureMessage);
      return false;
    }
  }

  static void _warn(ScaffoldMessengerState? messenger, String message) {
    messenger?.showSnackBar(
      SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
    );
  }

  static void _note(String detail) {
    if (kDebugMode) debugPrint('[LINK] open failed ($detail)');
  }
}
