@Tags(['e2e'])
library;

/// End-to-end proof against the LIVE local backend: the same repositories
/// the app uses execute the full customer journey with real HTTP, a real
/// database and the real white label config.
///
/// Run (backend serving on :8000 with a provisioned tenant):
///   flutter test test/e2e --tags e2e \
///     --dart-define=TENANT_KEY=`tenant api key` \
///     --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1
import 'package:client_app/core/network/api_client.dart';
import 'package:client_app/core/network/api_failure.dart';
import 'package:client_app/core/storage/token_storage.dart';
import 'package:client_app/core/utils/formats.dart';
import 'package:client_app/features/auth/data/auth_repository.dart';
import 'package:client_app/features/booking/data/booking_repository.dart';
import 'package:client_app/features/catalog/data/catalog_repository.dart';
import 'package:client_app/features/white_label/domain/white_label_config.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

/// Reads the 6-digit verification code for [email] from the local Mailpit
/// API (the dev backend sends real SMTP to Mailpit on :1025).
Future<String> _fetchVerificationCodeFromMailpit(String email) async {
  const mailpitBase = String.fromEnvironment(
    'MAILPIT_URL',
    defaultValue: 'http://127.0.0.1:8025',
  );

  final dio = Dio(BaseOptions(baseUrl: mailpitBase));

  // The email is sent synchronously at registration; one short retry covers
  // SMTP delivery latency.
  for (var attempt = 0; attempt < 10; attempt++) {
    final search = await dio.get<Map<String, dynamic>>(
      '/api/v1/search',
      queryParameters: {'query': 'to:"$email"'},
    );

    final messages = (search.data?['messages'] as List?) ?? const [];

    if (messages.isNotEmpty) {
      final id = (messages.first as Map<String, dynamic>)['ID'] as String;
      final message =
          await dio.get<Map<String, dynamic>>('/api/v1/message/$id');
      final body = message.data?['Text'] as String? ?? '';
      final match = RegExp(r'\b(\d{6})\b').firstMatch(body);

      if (match != null) {
        return match.group(1)!;
      }
    }

    await Future<void>.delayed(const Duration(milliseconds: 300));
  }

  fail('Verification email for $email not found in Mailpit at $mailpitBase');
}

void main() {
  // Self-skip without backend coordinates: plain `flutter test` stays green
  // offline; CI/dev run this explicitly with --dart-define.
  const tenantKey = String.fromEnvironment('TENANT_KEY');

  late ApiClient api;
  late AuthRepository auth;
  late CatalogRepository catalog;
  late BookingRepository booking;

  setUpAll(() async {
    await initializeDateFormatting('it_IT');

    // Auth and API client share the SAME storage for bearer injection.
    final shared = InMemoryTokenStorage();
    api = ApiClient(shared);
    auth = AuthRepository(api, shared);
    catalog = CatalogRepository(api);
    booking = BookingRepository(api);
  });

  test(
      'full customer journey: config → register → catalog → slots → book → history → cancel',
      skip: tenantKey.isEmpty
          ? 'E2E: provide TENANT_KEY/API_BASE_URL via --dart-define with the backend running'
          : false, () async {
    // 1. White label config from the live backend.
    final configResponse = await api.getRaw('/app/config');
    final config = WhiteLabelConfig.fromJson(configResponse.data!);

    expect(config.isOperating, isTrue);
    expect(config.appName, isNotEmpty);
    expect(config.locations, isNotEmpty);
    expect(config.theme.colors['primary'], isNotNull);

    final location = config.locations.first;

    // 2. Register a brand new customer: the account is born UNVERIFIED
    //    (Fase 1, S1 fix) and identity-bound endpoints reject it.
    final email =
        'e2e+${DateTime.now().millisecondsSinceEpoch}@example.com';

    final user = await auth.register(
      email: email,
      password: 'password-e2e-sicura',
      firstName: 'Cliente',
      lastName: 'E2E',
      privacyAccepted: true,
      privacyVersion: '2026-01',
    );

    expect(user.email, email);
    expect(user.emailVerified, isFalse);

    await expectLater(
      booking.list(upcoming: true),
      throwsA(isA<ApiFailure>()
          .having((f) => f.code, 'code', 'email_not_verified')),
    );

    // 2b. Verify with the REAL code delivered by email (read from the
    //     local Mailpit instance — production-shaped test infrastructure).
    final code = await _fetchVerificationCodeFromMailpit(email);

    await auth.verifyEmail(code);

    // 3. Catalog and staff are real tenant data.
    final services = await catalog.services();
    final staff = await catalog.staff();

    expect(services, isNotEmpty);
    expect(staff, isNotEmpty);

    final variant = services.first.defaultVariant;
    final capable = staff.firstWhere(
      (m) => m.serviceUuids.contains(services.first.uuid),
    );

    // 4. Availability for a day inside the booking window.
    final day = Formats.dayKey(DateTime.now().add(const Duration(days: 3)));

    final availability = await booking.availability(
      locationUuid: location.uuid,
      variantUuids: [variant.uuid],
      staffUuid: capable.uuid,
      fromDay: day,
      toDay: day,
    );

    final slots = availability.forDay(day);

    expect(slots, isNotEmpty,
        reason: 'the provisioned tenant must have open slots at +3 days');
    expect(availability.durationMinutes, greaterThan(0));

    // 5. Book the first slot — idempotent retry must return the SAME
    //    appointment, not a duplicate. Keys are unique PER RUN: idempotency
    //    keys persist server-side, so a fixed key would replay a stale
    //    appointment from a previous run against the same database.
    final slot = slots.first;
    final runId = DateTime.now().millisecondsSinceEpoch;
    final idempotencyKey = 'e2e-$runId-1';

    final created = await booking.create(
      locationUuid: location.uuid,
      variantUuids: [variant.uuid],
      staffUuid: capable.uuid,
      startsAtUtc: slot.startsAtUtc,
      idempotencyKey: idempotencyKey,
    );

    expect(created.status, 'confirmed');
    expect(created.totalPriceCents, variant.priceCents);

    final replayed = await booking.create(
      locationUuid: location.uuid,
      variantUuids: [variant.uuid],
      staffUuid: capable.uuid,
      startsAtUtc: slot.startsAtUtc,
      idempotencyKey: idempotencyKey,
    );

    expect(replayed.uuid, created.uuid, reason: 'idempotency (docs/25 §6)');

    // 6. The booked slot is gone from availability (cache invalidated).
    final after = await booking.availability(
      locationUuid: location.uuid,
      variantUuids: [variant.uuid],
      staffUuid: capable.uuid,
      fromDay: day,
      toDay: day,
    );

    expect(
      after.forDay(day).where((s) => s.startsAtUtc == slot.startsAtUtc),
      isEmpty,
      reason: 'a booked slot must disappear (docs/30 §3-4)',
    );

    // 7. Double booking the same slot fails with the stable 409 code.
    await expectLater(
      booking.create(
        locationUuid: location.uuid,
        variantUuids: [variant.uuid],
        staffUuid: capable.uuid,
        startsAtUtc: slot.startsAtUtc,
        idempotencyKey: 'e2e-$runId-2',
      ),
      throwsA(isA<ApiFailure>()
          .having((f) => f.code, 'code', 'slot_unavailable')),
    );

    // 8. History shows the appointment.
    final upcoming = await booking.list(upcoming: true);

    expect(upcoming.map((a) => a.uuid), contains(created.uuid));

    // 9. Cancel within the cutoff frees the slot again.
    final cancelled = await booking.cancel(created.uuid);

    expect(cancelled.status, 'cancelled_by_customer');

    final reopened = await booking.availability(
      locationUuid: location.uuid,
      variantUuids: [variant.uuid],
      staffUuid: capable.uuid,
      fromDay: day,
      toDay: day,
    );

    expect(
      reopened.forDay(day).where((s) => s.startsAtUtc == slot.startsAtUtc),
      isNotEmpty,
      reason: 'cancellation must reopen the slot',
    );
  });
}
