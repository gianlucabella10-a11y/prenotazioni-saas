import 'package:client_app/app/providers.dart';
import 'package:client_app/core/network/api_failure.dart';
import 'package:client_app/features/booking/application/booking_flow_controller.dart';
import 'package:client_app/features/booking/data/booking_repository.dart';
import 'package:client_app/features/booking/domain/appointment.dart';
import 'package:client_app/features/booking/domain/availability.dart';
import 'package:client_app/features/white_label/domain/white_label_config.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// Scripted fake: records calls, returns programmed outcomes.
class FakeBookingRepository implements BookingRepository {
  ApiFailure? failWith;
  int createCalls = 0;
  String? lastIdempotencyKey;

  static final _appointment = Appointment(
    uuid: 'appt-1',
    status: 'confirmed',
    startsAtUtc: DateTime.utc(2026, 6, 15, 8),
    endsAtUtc: DateTime.utc(2026, 6, 15, 8, 30),
    totalPriceCents: 1800,
    currency: 'EUR',
    locationName: 'Sede',
    items: const [],
  );

  @override
  Future<Appointment> create({
    required String locationUuid,
    required List<String> variantUuids,
    required String staffUuid,
    required DateTime startsAtUtc,
    required String idempotencyKey,
  }) async {
    createCalls++;
    lastIdempotencyKey = idempotencyKey;

    final failure = failWith;

    if (failure != null) {
      throw failure;
    }

    return _appointment;
  }

  @override
  Future<DayAvailability> availability({
    required String locationUuid,
    required List<String> variantUuids,
    required String fromDay,
    required String toDay,
    String? staffUuid,
  }) async =>
      const DayAvailability(slotsByDay: {}, durationMinutes: 0);

  @override
  Future<List<Appointment>> list({required bool upcoming}) async => const [];

  @override
  Future<Appointment> cancel(String uuid, {String? reason}) async =>
      _appointment;
}

const _location = TenantLocation(
  uuid: 'loc-1',
  name: 'Sede',
  timezone: 'Europe/Rome',
  bookingWindowDays: 60,
  cancellationCutoffMinutes: 1440,
);

final _slot = AvailabilitySlot(
  startsAtUtc: DateTime.utc(2026, 6, 15, 8),
  staffUuid: 'staff-1',
);

void main() {
  late FakeBookingRepository fakeRepo;
  late ProviderContainer container;

  setUp(() {
    fakeRepo = FakeBookingRepository();
    container = ProviderContainer(overrides: [
      bookingRepositoryProvider.overrideWithValue(fakeRepo),
    ]);
    addTearDown(container.dispose);
  });

  BookingFlowController controller() =>
      container.read(bookingFlowProvider.notifier);

  BookingFlowState state() => container.read(bookingFlowProvider);

  void selectEverything() {
    controller()
      ..toggleVariant(variantUuid: 'v-1', serviceUuid: 's-1')
      ..setLocation(_location)
      ..selectStaff('staff-1')
      ..selectDay('2026-06-15')
      ..selectSlot(_slot);
  }

  group('selection invariants', () {
    test('changing the service chain clears staff and slot', () {
      selectEverything();
      expect(state().readyToSubmit, isTrue);

      controller().toggleVariant(variantUuid: 'v-2', serviceUuid: 's-2');

      expect(state().staffUuid, isNull);
      expect(state().selectedSlot, isNull);
      expect(state().idempotencyKey, isNull);
      expect(state().readyToSubmit, isFalse);
    });

    test('changing staff or day clears the slot, not the chain', () {
      selectEverything();

      controller().selectStaff('staff-2');

      expect(state().selectedSlot, isNull);
      expect(state().selectedVariantUuids, ['v-1']);
    });

    test('picking a slot mints a fresh idempotency key per attempt', () {
      selectEverything();
      final first = state().idempotencyKey;

      controller().selectSlot(_slot);
      final second = state().idempotencyKey;

      expect(first, isNotNull);
      expect(second, isNotNull);
      expect(first, isNot(second));
    });
  });

  group('submit', () {
    test('success stores the persisted appointment and sends the key', () async {
      selectEverything();
      final key = state().idempotencyKey;

      final appointment = await controller().submit();

      expect(appointment.uuid, 'appt-1');
      expect(state().confirmed?.uuid, 'appt-1');
      expect(state().submitting, isFalse);
      expect(fakeRepo.lastIdempotencyKey, key);
    });

    test('slot_unavailable clears the slot so the UI refetches (docs/30 §4)',
        () async {
      selectEverything();
      fakeRepo.failWith = const ApiFailure(
        code: 'slot_unavailable',
        message: 'taken',
        statusCode: 409,
      );

      await expectLater(controller().submit, throwsA(isA<ApiFailure>()));

      expect(state().selectedSlot, isNull);
      expect(state().idempotencyKey, isNull);
      expect(state().submitting, isFalse);
      expect(state().selectedVariantUuids, ['v-1'], reason: 'chain survives');
    });

    test('other failures keep the slot for a retry with the SAME key',
        () async {
      selectEverything();
      final key = state().idempotencyKey;
      fakeRepo.failWith = const ApiFailure(
        code: ApiFailure.networkCode,
        message: 'offline',
      );

      await expectLater(controller().submit, throwsA(isA<ApiFailure>()));

      expect(state().selectedSlot, isNotNull);
      expect(state().idempotencyKey, key,
          reason: 'retry of the same attempt must reuse the key (docs/25 §6)');

      // Retry succeeds and reuses the very same idempotency key.
      fakeRepo.failWith = null;
      await controller().submit();

      expect(fakeRepo.createCalls, 2);
      expect(fakeRepo.lastIdempotencyKey, key);
    });

    test('refuses to submit an incomplete flow', () {
      controller().toggleVariant(variantUuid: 'v-1', serviceUuid: 's-1');

      expect(() => controller().submit(), throwsStateError);
    });
  });
}
