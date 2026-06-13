import 'package:client_app/app/providers.dart';
import 'package:client_app/core/session/session_controller.dart';
import 'package:client_app/features/auth/presentation/register_screen.dart';
import 'package:client_app/features/auth/presentation/verify_email_screen.dart';
import 'package:client_app/features/white_label/domain/white_label_config.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

class _FixedConfig extends WhiteLabelConfigNotifier {
  @override
  Future<WhiteLabelConfig> build() async => WhiteLabelConfig.fromJson({
        'tenant_status': 'active',
        'app_name': 'Salone Verdi',
        'legal': {'privacy_policy_url': 'https://salone.example/privacy'},
      });
}

/// Session stub: unverified authenticated user; records verify calls.
class _UnverifiedSession extends SessionController {
  static final List<String> submittedCodes = [];
  static int resendCalls = 0;

  @override
  Future<SessionState> build() async =>
      const AuthenticatedSession(email: 'cliente@example.com', verified: false);

  @override
  Future<void> verifyEmail(String code) async {
    submittedCodes.add(code);
  }

  @override
  Future<void> resendVerificationCode() async {
    resendCalls++;
  }
}

void main() {
  Future<void> pump(WidgetTester tester, Widget screen) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          whiteLabelConfigProvider.overrideWith(_FixedConfig.new),
          sessionControllerProvider.overrideWith(_UnverifiedSession.new),
        ],
        child: MaterialApp(home: screen),
      ),
    );

    await tester.pumpAndSettle();
  }

  testWidgets(
      'registration is blocked without explicit privacy acceptance (GDPR)',
      (tester) async {
    await pump(tester, const RegisterScreen());

    // Fill every field correctly but leave the consent unchecked.
    final fields = find.byType(TextFormField);
    await tester.enterText(fields.at(0), 'Luca');
    await tester.enterText(fields.at(2), 'luca@example.com');
    await tester.enterText(fields.at(4), 'password-sicura-123');

    await tester.tap(find.text('Registrati'));
    await tester.pumpAndSettle();

    expect(
      find.text('Per registrarti devi accettare la privacy policy.'),
      findsOneWidget,
    );

    // The privacy policy link from the tenant config is rendered.
    expect(find.text('privacy policy'), findsOneWidget);
  });

  testWidgets('verify screen shows the email and submits the 6-digit code',
      (tester) async {
    _UnverifiedSession.submittedCodes.clear();

    await pump(tester, const VerifyEmailScreen());

    expect(find.textContaining('cliente@example.com'), findsOneWidget);

    // Short code is rejected client-side.
    await tester.enterText(find.byType(TextField), '123');
    await tester.tap(find.text('Verifica'));
    await tester.pumpAndSettle();

    expect(find.text('Inserisci il codice di 6 cifre.'), findsOneWidget);
    expect(_UnverifiedSession.submittedCodes, isEmpty);

    // Full code is submitted to the session controller.
    await tester.enterText(find.byType(TextField), '123456');
    await tester.tap(find.text('Verifica'));
    await tester.pumpAndSettle();

    expect(_UnverifiedSession.submittedCodes, ['123456']);
  });

  testWidgets('resend starts the cooldown', (tester) async {
    _UnverifiedSession.resendCalls = 0;

    await pump(tester, const VerifyEmailScreen());

    await tester.tap(find.textContaining('Invia di nuovo'));
    await tester.pump();

    expect(_UnverifiedSession.resendCalls, 1);
    expect(find.textContaining('Invia di nuovo tra'), findsOneWidget);

    // Let the 30s cooldown timer run out so no timers leak from the test.
    await tester.pump(const Duration(seconds: 31));
  });
}
