import 'package:client_app/app/providers.dart';
import 'package:client_app/features/booking/application/booking_flow_controller.dart';
import 'package:client_app/features/booking/domain/appointment.dart';
import 'package:client_app/features/booking/presentation/booking_success_screen.dart';
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

/// Presets the flow with a confirmed appointment (no network).
class _StubFlow extends BookingFlowController {
  _StubFlow(this._appointment);

  final Appointment _appointment;

  @override
  BookingFlowState build() =>
      BookingFlowState.initial().copyWith(confirmed: _appointment);
}

/// Serves a ready config so the screen can read the tenant name offline.
class _StubConfig extends WhiteLabelConfigNotifier {
  @override
  Future<WhiteLabelConfig> build() async =>
      WhiteLabelConfig.fromJson(<String, dynamic>{'app_name': 'Salone Demo'});
}

void main() {
  setUpAll(() => initializeDateFormatting('it_IT'));

  Future<void> pump(WidgetTester tester, {String status = 'confirmed'}) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          bookingFlowProvider
              .overrideWith(() => _StubFlow(_appointment(status: status))),
          whiteLabelConfigProvider.overrideWith(_StubConfig.new),
        ],
        child: MaterialApp(
          theme: AppThemeBuilder.build(BrandTheme.fallback()),
          home: const BookingSuccessScreen(),
        ),
      ),
    );

    await tester.pumpAndSettle();
  }

  testWidgets('confirmed booking shows recap, reminder and calendar CTA',
      (tester) async {
    await pump(tester);

    expect(find.text('Prenotazione confermata'), findsOneWidget);
    expect(find.text('Taglio capelli'), findsOneWidget);
    expect(find.text('Marco'), findsOneWidget);
    expect(find.text('Sede centrale'), findsOneWidget);
    // Trust: the confirmed state now promises a reminder too.
    expect(find.textContaining('promemoria'), findsOneWidget);
    // Primary next step: own the appointment in your calendar.
    expect(find.text('Aggiungi al calendario'), findsOneWidget);
    expect(find.text('Le mie prenotazioni'), findsOneWidget);
    expect(find.text('Torna alla home'), findsOneWidget);
  });

  testWidgets('pending request has no calendar CTA and its own reassurance',
      (tester) async {
    await pump(tester, status: 'requested');

    expect(find.text('Richiesta inviata'), findsOneWidget);
    // An unconfirmed slot must not be added to a calendar as if it were final.
    expect(find.text('Aggiungi al calendario'), findsNothing);
    expect(find.textContaining('conferma la richiesta'), findsOneWidget);
    expect(find.text('Le mie prenotazioni'), findsOneWidget);
  });
}
