import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

/// Backend device registration seam (kept as functions so the service is
/// trivially testable without Firebase or a real repository).
typedef RegisterDevice = Future<void> Function({
  required String platform,
  required String fcmToken,
  String? locale,
});
typedef UnregisterDevice = Future<void> Function(String fcmToken);

/// Background isolate handler (must be top-level / vm:entry-point). The OS
/// renders the notification; data-only routing is added when payload deep
/// links are defined.
@pragma('vm:entry-point')
Future<void> pushBackgroundHandler(RemoteMessage message) async {}

/// Push foundation (Fase 4): Firebase init + permission + FCM token lifecycle
/// + backend registration. Fully GUARDED — web (no web push setup yet) and a
/// missing Firebase configuration disable push WITHOUT crashing the app; the
/// backend keeps delivering via the email channel fallback. White-label safe:
/// the token is bound server-side to the authenticated user/tenant.
class PushNotificationService {
  PushNotificationService(this._register, this._unregister);

  final RegisterDevice _register;
  final UnregisterDevice _unregister;

  bool _available = false;
  String? _token;
  Future<void>? _initFuture;

  bool get isAvailable => _available;

  /// Initialize Firebase once. Idempotent and race-safe (app-start init and
  /// session-driven registration may call it concurrently): the underlying
  /// work runs at most once. Any failure (web, missing native config)
  /// disables push silently.
  Future<void> init() => _initFuture ??= _doInit();

  Future<void> _doInit() async {
    if (kIsWeb) {
      return;
    }

    try {
      await Firebase.initializeApp();
      FirebaseMessaging.onBackgroundMessage(pushBackgroundHandler);
      _available = true;
    } catch (_) {
      _available = false;
    }
  }

  /// Called when a VERIFIED user is authenticated: request permission, get
  /// the FCM token and register it on the backend. No-op if push is
  /// unavailable or the user denied permission.
  Future<void> registerForUser({String? locale}) async {
    await init(); // ensure Firebase is ready (no-op after the first call)

    if (!_available) {
      return;
    }

    try {
      final messaging = FirebaseMessaging.instance;

      final settings = await messaging.requestPermission(
        alert: true,
        badge: true,
        sound: true,
      );

      if (settings.authorizationStatus == AuthorizationStatus.denied) {
        return;
      }

      final token = await messaging.getToken();

      if (token == null) {
        return;
      }

      _token = token;
      await _register(platform: _platform(), fcmToken: token, locale: locale);

      messaging.onTokenRefresh.listen((refreshed) {
        _token = refreshed;
        _register(platform: _platform(), fcmToken: refreshed, locale: locale);
      });
    } catch (_) {
      // Best-effort: push never blocks the app.
    }
  }

  /// Called on logout / account deletion: remove the token server-side and
  /// locally, so a different account on the same device never inherits the
  /// previous binding.
  Future<void> unregister() async {
    if (!_available || _token == null) {
      return;
    }

    final token = _token!;
    _token = null;

    try {
      await _unregister(token);
      await FirebaseMessaging.instance.deleteToken();
    } catch (_) {
      // Non-fatal.
    }
  }

  String _platform() =>
      defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android';
}
