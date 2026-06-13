import 'package:client_app/app/providers.dart';
import 'package:client_app/features/appointments/presentation/my_appointments_screen.dart';
import 'package:client_app/features/booking/domain/appointment.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

Appointment _appointment({String status = 'confirmed'}) => Appointment(
      uuid: 'a-1',
      status: status,
      startsAtUtc: DateTime.now().add(const Duration(days: 2)).toUtc(),
      endsAtUtc: DateTime.now()
          .add(const Duration(days: 2, minutes: 30))
          .toUtc(),
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
        child: const MaterialApp(home: MyAppointmentsScreen()),
      ),
    );

    await tester.pumpAndSettle();
  }

  testWidgets('empty state mirrors the reference UX (SCREEN_ANALYSIS S10)',
      (tester) async {
    await pump(tester, const []);

    expect(
      find.text('Non ci sono prenotazioni da visualizzare.'),
      findsOneWidget,
    );
  });

  testWidgets('renders an upcoming appointment with cancel affordance',
      (tester) async {
    await pump(tester, [_appointment()]);

    expect(find.text('Taglio capelli'), findsOneWidget);
    expect(find.textContaining('Marco'), findsOneWidget);
    expect(find.byTooltip('Annulla prenotazione'), findsOneWidget);

    // The confirmation dialog appears before any destructive call.
    await tester.tap(find.byTooltip('Annulla prenotazione'));
    await tester.pumpAndSettle();

    expect(find.text('Annullare la prenotazione?'), findsOneWidget);
  });

  testWidgets('pending requests show the approval chip (request_approve mode)',
      (tester) async {
    await pump(tester, [_appointment(status: 'requested')]);

    expect(find.text('In attesa di conferma'), findsOneWidget);
  });
}
