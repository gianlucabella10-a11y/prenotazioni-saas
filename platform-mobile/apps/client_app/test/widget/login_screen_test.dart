import 'package:client_app/app/providers.dart';
import 'package:client_app/features/auth/presentation/login_screen.dart';
import 'package:client_app/features/white_label/domain/white_label_config.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// Config notifier with a fixed tenant — no network in widget tests.
class _FixedConfig extends WhiteLabelConfigNotifier {
  @override
  Future<WhiteLabelConfig> build() async => WhiteLabelConfig.fromJson({
        'tenant_status': 'active',
        'app_name': 'Salone Verdi',
        'tagline': 'Dal 1980',
      });
}

void main() {
  Future<void> pumpLogin(WidgetTester tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [whiteLabelConfigProvider.overrideWith(_FixedConfig.new)],
        child: const MaterialApp(home: LoginScreen()),
      ),
    );

    await tester.pumpAndSettle();
  }

  testWidgets('renders the tenant brand from the runtime config',
      (tester) async {
    await pumpLogin(tester);

    expect(find.text('Salone Verdi'), findsOneWidget);
    expect(find.text('Dal 1980'), findsOneWidget);
  });

  testWidgets('validates email and password before any network call',
      (tester) async {
    await pumpLogin(tester);

    await tester.enterText(find.byType(TextFormField).first, 'non-una-email');
    await tester.tap(find.text('Accedi'));
    await tester.pumpAndSettle();

    expect(find.text('Inserisci un indirizzo email valido'), findsOneWidget);
    expect(find.text('Inserisci la password'), findsOneWidget);
  });

  testWidgets('password field can toggle its visibility', (tester) async {
    await pumpLogin(tester);

    // Hidden by default: the "show" affordance is present.
    expect(find.byIcon(Icons.visibility_outlined), findsOneWidget);

    await tester.tap(find.byIcon(Icons.visibility_outlined));
    await tester.pumpAndSettle();

    // After toggling, it offers to hide again.
    expect(find.byIcon(Icons.visibility_off_outlined), findsOneWidget);
  });
}
