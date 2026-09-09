import 'package:client_app/app/providers.dart';
import 'package:client_app/features/booking/domain/appointment.dart';
import 'package:client_app/features/home/presentation/home_screen.dart';
import 'package:client_app/features/white_label/domain/app_theme_builder.dart';
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

Appointment _appointment({String status = 'confirmed'}) => Appointment(
      uuid: 'a-1',
      status: status,
      startsAtUtc: DateTime.now().add(const Duration(days: 2)).toUtc(),
      endsAtUtc:
          DateTime.now().add(const Duration(days: 2, minutes: 30)).toUtc(),
      totalPriceCents: 1800,
      currency: 'EUR',
      locationName: 'Sede centrale',
      items: const [
        AppointmentItem(
          serviceName: 'Taglio capelli',
          variantName: 'Standard',
          durationMinutes: 30,
          priceCents: 1800,
          staffName: 'Marco',
          staffUuid: 's-1',
        ),
      ],
    );

Future<void> _pump(
  WidgetTester tester,
  String layout, {
  List<Appointment> upcoming = const [],
}) async {
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        whiteLabelConfigProvider
            .overrideWith(() => _FakeConfigNotifier(_config(layout))),
        appointmentsProvider
            .overrideWith((ref, isUpcoming) async => isUpcoming ? upcoming : const []),
      ],
      child: MaterialApp(
        theme: AppThemeBuilder.build(BrandTheme.fallback()),
        home: const HomeScreen(),
      ),
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

  testWidgets('standard layout keeps the shop identity with its tagline',
      (tester) async {
    await _pump(tester, 'standard');

    expect(find.byKey(const Key('home_hero_header')), findsNothing);
    expect(find.text('Giuffrida Barber'), findsWidgets);
    expect(find.text('Stile dal 1990'), findsOneWidget);
  });

  testWidgets('next appointment shows the service and clean metadata',
      (tester) async {
    await _pump(tester, 'standard', upcoming: [_appointment()]);

    expect(find.text('Taglio capelli'), findsOneWidget);
    // Metadata joins only the present segments (no orphan separator).
    expect(find.text('Marco · Sede centrale'), findsOneWidget);
    // Confirmed → no pending chip.
    expect(find.text('In attesa'), findsNothing);
    // The card is the single path to the list: no redundant link competes.
    expect(find.text('Le mie prenotazioni'), findsNothing);
    expect(find.text('Informazioni e contatti'), findsOneWidget);
  });

  testWidgets('pending appointment surfaces the awaiting chip', (tester) async {
    await _pump(tester, 'standard', upcoming: [_appointment(status: 'requested')]);

    expect(find.text('In attesa'), findsOneWidget);
  });

  testWidgets('empty state invites toward the booking and keeps list access',
      (tester) async {
    await _pump(tester, 'standard');

    expect(find.textContaining('Nessun appuntamento'), findsOneWidget);
    expect(
      find.text('Prenota dal pulsante qui sopra, quando vuoi.'),
      findsOneWidget,
    );
    // With no card to tap, a single quiet link preserves access to the list.
    expect(find.text('Le mie prenotazioni'), findsOneWidget);
    expect(find.text('Informazioni e contatti'), findsOneWidget);
  });
}
