import 'package:client_app/core/push/push_notification_service.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  // init() calls Firebase.initializeApp(): without native config it fails and
  // disables push (the behavior under test). The binding makes that
  // deterministic in the test environment.
  TestWidgetsFlutterBinding.ensureInitialized();

  test('does not register a device when push is unavailable', () async {
    var registerCalls = 0;

    final service = PushNotificationService(
      ({required platform, required fcmToken, locale}) async {
        registerCalls++;
      },
      (_) async {},
    );

    // Without init() Firebase is not available: registration must be a strict
    // no-op (the security guarantee — never touch the backend blindly).
    expect(service.isAvailable, isFalse);

    await service.registerForUser();

    expect(registerCalls, 0);
  });

  test('unregister is a no-op when push is unavailable or has no token',
      () async {
    var unregisterCalls = 0;

    final service = PushNotificationService(
      ({required platform, required fcmToken, locale}) async {},
      (_) async {
        unregisterCalls++;
      },
    );

    await service.unregister();

    expect(unregisterCalls, 0);
  });
}
