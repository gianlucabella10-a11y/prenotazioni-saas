import 'package:client_app/app/providers.dart';
import 'package:client_app/features/home/presentation/home_screen.dart';
import 'package:client_app/features/white_label/domain/white_label_config.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

class _FakeConfigNotifier extends WhiteLabelConfigNotifier {
  _FakeConfigNotifier(this._config);

  final WhiteLabelConfig _config;

  @override
  Future<WhiteLabelConfig> build() async => _config;
}

WhiteLabelConfig _config(String layout) => WhiteLabelConfig(
      tenantStatus: 'active',
      configVersion: 1,
      appName: 'Giuffrida Barber',
      tagline: 'Stile dal 1990',
      theme: BrandTheme.fallback(),
      localeDefault: 'it',
      features: const {},
      confirmationMode: 'auto_confirm',
      legal: const LegalLinks(),
      locations: const [],
      layout: layout,
    );

Future<void> _pump(WidgetTester tester, String layout) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        whiteLabelConfigProvider.overrideWith(() => _FakeConfigNotifier(_config(layout))),
        appointmentsProvider.overrideWith((ref, upcoming) async => const []),
      ],
      child: const MaterialApp(home: HomeScreen()),
    ),
  );

  await tester.pumpAndSettle();
}

void main() {
  setUpAll(() => initializeDateFormatting('it_IT'));

  testWidgets('hero layout renders the branded header (template rendering)',
      (tester) async {
    await _pump(tester, 'hero_dark');

    expect(find.byKey(const Key('home_hero_header')), findsOneWidget);
  });

  testWidgets('standard layout keeps the plain tagline header', (tester) async {
    await _pump(tester, 'standard');

    expect(find.byKey(const Key('home_hero_header')), findsNothing);
    expect(find.text('Stile dal 1990'), findsOneWidget);
  });
}
