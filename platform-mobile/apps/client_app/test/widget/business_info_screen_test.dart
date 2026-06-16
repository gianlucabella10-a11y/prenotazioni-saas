import 'package:client_app/app/providers.dart';
import 'package:client_app/features/business/presentation/business_info_screen.dart';
import 'package:client_app/features/catalog/domain/service.dart';
import 'package:client_app/features/white_label/domain/white_label_config.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

class _FakeConfigNotifier extends WhiteLabelConfigNotifier {
  _FakeConfigNotifier(this._config);

  final WhiteLabelConfig _config;

  @override
  Future<WhiteLabelConfig> build() async => _config;
}

WhiteLabelConfig _config({
  BusinessContacts contacts = const BusinessContacts(),
  BusinessSocial social = const BusinessSocial(),
  Map<int, List<OpeningInterval>> hours = const {},
}) =>
    WhiteLabelConfig(
      tenantStatus: 'active',
      configVersion: 1,
      appName: 'Salone Demo',
      tagline: 'Il tuo stile',
      theme: BrandTheme.fallback(),
      localeDefault: 'it',
      features: const {},
      confirmationMode: 'auto_confirm',
      contacts: contacts,
      social: social,
      legal: const LegalLinks(),
      locations: [
        TenantLocation(
          uuid: 'loc-1',
          name: 'Sede centrale',
          address: 'Via Roma 1',
          timezone: 'Europe/Rome',
          bookingWindowDays: 60,
          cancellationCutoffMinutes: 1440,
          openingHours: hours,
        ),
      ],
    );

Future<void> _pump(
  WidgetTester tester, {
  required WhiteLabelConfig config,
  List<StaffMember> staff = const [],
}) async {
  // Tall viewport so the lazy ListView builds every section (the staff card
  // is the last child and would otherwise be off-screen in tests).
  tester.view.physicalSize = const Size(1200, 3000);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        whiteLabelConfigProvider.overrideWith(() => _FakeConfigNotifier(config)),
        staffProvider.overrideWith((ref) async => staff),
      ],
      child: const MaterialApp(home: BusinessInfoScreen()),
    ),
  );

  await tester.pumpAndSettle();
}

void main() {
  testWidgets('renders header, contacts, social, hours and staff', (tester) async {
    await _pump(
      tester,
      config: _config(
        contacts: const BusinessContacts(phone: '0951234567', email: 'info@demo.it'),
        social: const BusinessSocial(
          instagramUrl: 'https://instagram.com/demo',
          whatsapp: WhatsAppContact(number: '+393331234567', message: 'Ciao'),
        ),
        hours: const {
          0: [OpeningInterval(start: '09:00', end: '13:00')],
        },
      ),
      staff: const [
        StaffMember(
          uuid: 's-1',
          displayName: 'Marco Rossi',
          roleLabel: 'Senior Barber',
          serviceUuids: {},
        ),
      ],
    );

    // Header + contacts
    expect(find.text('Salone Demo'), findsOneWidget);
    expect(find.text('0951234567'), findsOneWidget);
    expect(find.text('info@demo.it'), findsOneWidget);

    // Social present vs hidden
    expect(find.text('WhatsApp'), findsOneWidget);
    expect(find.text('Instagram'), findsOneWidget);
    expect(find.text('Facebook'), findsNothing); // not configured → hidden
    expect(find.text('Maps'), findsNothing); // no maps_url → hidden

    // Hours
    expect(find.text('Orari di apertura'), findsOneWidget);
    expect(find.text('Lunedì'), findsOneWidget);
    expect(find.text('09:00–13:00'), findsOneWidget);

    // Staff
    expect(find.text('Il nostro team'), findsOneWidget);
    expect(find.text('Marco Rossi'), findsOneWidget);
    expect(find.text('Senior Barber'), findsOneWidget);
  });

  testWidgets('elegant fallback: empty sections are hidden', (tester) async {
    await _pump(tester, config: _config());

    // The name always renders…
    expect(find.text('Salone Demo'), findsOneWidget);

    // …but optional sections disappear instead of showing empty blocks.
    expect(find.text('Orari di apertura'), findsNothing);
    expect(find.text('Il nostro team'), findsNothing);
    expect(find.text('WhatsApp'), findsNothing);
  });
}
