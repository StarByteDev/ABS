// Generated from the ABS Firebase project configuration files already present
// in this repository (android/app/google-services.json and
// ios/Runner/GoogleService-Info.plist). These are public client identifiers,
// not secrets; access is enforced by Firebase security rules and APNs keys.
//
// Regenerate with `flutterfire configure` if the Firebase apps change.
import 'package:firebase_core/firebase_core.dart' show FirebaseOptions;
import 'package:flutter/foundation.dart'
    show TargetPlatform, defaultTargetPlatform, kIsWeb;

class DefaultFirebaseOptions {
  const DefaultFirebaseOptions._();

  static FirebaseOptions get currentPlatform {
    if (kIsWeb) {
      throw UnsupportedError('Pulse does not ship a Firebase web app.');
    }
    return switch (defaultTargetPlatform) {
      TargetPlatform.android => android,
      TargetPlatform.iOS => ios,
      _ => throw UnsupportedError(
        'No Firebase app is registered for $defaultTargetPlatform.',
      ),
    };
  }

  static const FirebaseOptions android = FirebaseOptions(
    apiKey: 'AIzaSyCG9knjBrLJHEntFYyHVgOJD0X65l_ug3E',
    appId: '1:718896233501:android:f1a4bde7bd6c94e0ffdaf4',
    messagingSenderId: '718896233501',
    projectId: 'alphablocksolutions-a54df',
    storageBucket: 'alphablocksolutions-a54df.firebasestorage.app',
  );
  static const FirebaseOptions ios = FirebaseOptions(
    apiKey: 'AIzaSyA2jVMUxr97-jjS3bWMWER0zhSTHOtot3g',
    appId: '1:718896233501:ios:613335c21f25b67effdaf4',
    messagingSenderId: '718896233501',
    projectId: 'alphablocksolutions-a54df',
    storageBucket: 'alphablocksolutions-a54df.firebasestorage.app',
    iosBundleId: 'com.alphablocksolutions.absMobile',
  );
}
