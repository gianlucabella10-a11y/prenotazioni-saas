import 'package:client_app/app/providers.dart';
import 'package:client_app/features/appointments/presentation/my_appointments_screen.dart';
import 'package:client_app/features/booking/domain/appointment.dart';
import 'package:client_app/features/white_label/domain/app_theme_builder.dart';
import 'package:client_app/features/white_label/domain/white_label_config.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

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

void main() {
  setUpAll(() => initializeDateFormatting('it_IT'));

  Future<void> pump(WidgetTester tester, List<Appointment> upcoming) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appointmentsProvider.overrideWith(
            (ref, isUpcoming) async => isUpcoming ? upcoming : const [],
          ),
        ],
        child: MaterialApp(
          theme: AppThemeBuilder.build(BrandTheme.fallback()),
          home: const MyAppointmentsScreen(),
        ),
      ),
    );

    await tester.pumpAndSettle();
  }

  testWidgets('empty upcoming state invites toward a booking', (tester) async {
    await pump(tester, const []);

    expect(find.text('Non hai appuntamenti in programma.'), findsOneWidget);
    expect(find.text('Prenota'), findsOneWidget);
  });

  testWidgets('confirmed appointment shows a status badge and opens its sheet',
      (tester) async {
    await pump(tester, [_appointment()]);

    expect(find.text('Taglio capelli'), findsOneWidget);
    expect(find.text('Confermato'), findsOneWidget);
    expect(find.text('Marco · Sede centrale'), findsOneWidget);

    // The whole row is a door to the appointment's sheet.
    await tester.tap(find.text('Taglio capelli'));
    await tester.pumpAndSettle();

    // In-domain actions live in the sheet, not scattered on the row.
    expect(find.text('Aggiungi al calendario'), findsOneWidget);
    expect(find.text('Annulla prenotazione'), findsOneWidget);
  });

  testWidgets('cancelling is a deliberate, protected action', (tester) async {
    await pump(tester, [_appointment()]);

    await tester.tap(find.text('Taglio capelli'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Annulla prenotazione'));
    await tester.pumpAndSettle();

    // The confirmation appears before anything destructive happens, and the
    // safe choice ("Mantieni") is the prominent one.
    expect(find.text('Annullare la prenotazione?'), findsOneWidget);
    expect(find.text('Mantieni'), findsOneWidget);
    expect(find.text('Sì, annulla'), findsOneWidget);
  });

  testWidgets('pending request surfaces the awaiting badge', (tester) async {
    await pump(tester, [_appointment(status: 'requested')]);

    expect(find.text('In attesa'), findsOneWidget);
  });
}
