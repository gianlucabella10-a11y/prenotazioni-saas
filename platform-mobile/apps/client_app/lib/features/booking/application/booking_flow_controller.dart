import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../../app/providers.dart';
import '../../../core/network/api_failure.dart';
import '../../catalog/domain/service.dart';
import '../../white_label/domain/white_label_config.dart';
import '../domain/appointment.dart';
import '../domain/availability.dart';

/// Selection state of the two-step booking flow (SCREEN_ANALYSIS S5-S6):
/// services (multi-select) → location/staff/day/slot → confirm.
class BookingFlowState {
  const BookingFlowState({
    required this.selectedVariantUuids,
    required this.serviceUuidByVariant,
    this.location,
    this.staffUuid,
    this.selectedDay,
    this.selectedSlot,
    this.idempotencyKey,
    this.submitting = false,
    this.confirmed,
  });

  factory BookingFlowState.initial() => const BookingFlowState(
        selectedVariantUuids: [],
        serviceUuidByVariant: {},
      );

  /// Ordered variant uuids: the chain booked in sequence (docs/30 §6).
  final List<String> selectedVariantUuids;

  /// variant uuid → parent service uuid (needed to filter capable staff).
  final Map<String, String> serviceUuidByVariant;

  final TenantLocation? location;

  /// Selected staff member; null = "no preference" is NOT supported by the
  /// current flow because booking requires an explicit staff (the UI picks
  /// the first capable one as default).
  final String? staffUuid;

  /// Local day `yyyy-MM-dd` shown in the calendar strip.
  final String? selectedDay;

  final AvailabilitySlot? selectedSlot;

  /// Generated when a slot is picked; stable across submit retries of the
  /// SAME slot, regenerated when the slot changes (docs/25 §6).
  final String? idempotencyKey;

  final bool submitting;

  /// Set after a successful booking: drives the confirmation screen.
  final Appointment? confirmed;

  bool get hasSelection => selectedVariantUuids.isNotEmpty;

  bool get readyToSubmit =>
      hasSelection &&
      location != null &&
      staffUuid != null &&
      selectedSlot != null &&
      !submitting;

  Set<String> get selectedServiceUuids =>
      selectedVariantUuids.map((v) => serviceUuidByVariant[v]!).toSet();

  BookingFlowState copyWith({
    List<String>? selectedVariantUuids,
    Map<String, String>? serviceUuidByVariant,
    TenantLocation? location,
    String? staffUuid,
    String? selectedDay,
    AvailabilitySlot? selectedSlot,
    String? idempotencyKey,
    bool? submitting,
    Appointment? confirmed,
    bool clearSlot = false,
    bool clearStaff = false,
  }) {
    return BookingFlowState(
      selectedVariantUuids: selectedVariantUuids ?? this.selectedVariantUuids,
      serviceUuidByVariant: serviceUuidByVariant ?? this.serviceUuidByVariant,
      location: location ?? this.location,
      staffUuid: clearStaff ? null : (staffUuid ?? this.staffUuid),
      selectedDay: selectedDay ?? this.selectedDay,
      selectedSlot: clearSlot ? null : (selectedSlot ?? this.selectedSlot),
      idempotencyKey: clearSlot ? null : (idempotencyKey ?? this.idempotencyKey),
      submitting: submitting ?? this.submitting,
      confirmed: confirmed ?? this.confirmed,
    );
  }
}

class BookingFlowController extends Notifier<BookingFlowState> {
  static const _uuid = Uuid();

  @override
  BookingFlowState build() => BookingFlowState.initial();

  void reset() => state = BookingFlowState.initial();

  void setLocation(TenantLocation location) {
    state = state.copyWith(location: location, clearSlot: true);
  }

  /// Toggles a service variant in the chain. Changing the chain invalidates
  /// staff/slot choices (durations and capable staff change).
  void toggleVariant({
    required String variantUuid,
    required String serviceUuid,
  }) {
    final selection = List<String>.from(state.selectedVariantUuids);
    final mapping = Map<String, String>.from(state.serviceUuidByVariant);

    if (selection.contains(variantUuid)) {
      selection.remove(variantUuid);
      mapping.remove(variantUuid);
    } else {
      selection.add(variantUuid);
      mapping[variantUuid] = serviceUuid;
    }

    state = state.copyWith(
      selectedVariantUuids: selection,
      serviceUuidByVariant: mapping,
      clearSlot: true,
      clearStaff: true,
    );
  }

  void selectStaff(String staffUuid) {
    if (state.staffUuid == staffUuid) {
      return;
    }

    state = state.copyWith(staffUuid: staffUuid, clearSlot: true);
  }

  void selectDay(String day) {
    if (state.selectedDay == day) {
      return;
    }

    state = state.copyWith(selectedDay: day, clearSlot: true);
  }

  /// Picking a slot mints the idempotency key for this exact attempt.
  void selectSlot(AvailabilitySlot slot) {
    state = state.copyWith(
      selectedSlot: slot,
      idempotencyKey: _uuid.v4(),
    );
  }

  /// Submits the booking. On `slot_unavailable` (someone else won the race,
  /// docs/30 §4) the slot selection is cleared so the UI re-fetches
  /// availability — the failure is rethrown for the screen to message.
  Future<Appointment> submit() async {
    final current = state;

    if (!current.readyToSubmit) {
      throw StateError('Booking flow is not complete.');
    }

    state = current.copyWith(submitting: true);

    try {
      final appointment = await ref.read(bookingRepositoryProvider).create(
            locationUuid: current.location!.uuid,
            variantUuids: current.selectedVariantUuids,
            staffUuid: current.staffUuid!,
            startsAtUtc: current.selectedSlot!.startsAtUtc,
            idempotencyKey: current.idempotencyKey!,
          );

      // The agenda changed: lists and any cached availability are stale.
      ref.invalidate(appointmentsProvider);

      state = state.copyWith(submitting: false, confirmed: appointment);

      return appointment;
    } on ApiFailure catch (failure) {
      state = state.copyWith(
        submitting: false,
        clearSlot: failure.code == 'slot_unavailable',
      );

      rethrow;
    } catch (_) {
      state = state.copyWith(submitting: false);

      rethrow;
    }
  }
}

final bookingFlowProvider =
    NotifierProvider<BookingFlowController, BookingFlowState>(
  BookingFlowController.new,
);

/// Aggregate of the current selection — count, total price and total duration
/// — derived once from the flow + the loaded catalog. Both the services
/// summary and the confirm button read it, so the total lives in exactly one
/// place (no duplicated price math across screens).
class BookingSelectionSummary {
  const BookingSelectionSummary({
    required this.count,
    required this.totalPriceCents,
    required this.totalDurationMinutes,
    required this.currency,
  });

  final int count;
  final int totalPriceCents;
  final int totalDurationMinutes;
  final String currency;

  bool get isEmpty => count == 0;

  static const empty = BookingSelectionSummary(
    count: 0,
    totalPriceCents: 0,
    totalDurationMinutes: 0,
    currency: 'EUR',
  );
}

final bookingSelectionSummaryProvider = Provider<BookingSelectionSummary>((ref) {
  final selected =
      ref.watch(bookingFlowProvider.select((s) => s.selectedVariantUuids));
  final services = ref.watch(servicesProvider).value;

  if (services == null || selected.isEmpty) {
    return BookingSelectionSummary.empty;
  }

  final wanted = selected.toSet();
  var priceCents = 0;
  var durationMinutes = 0;
  var currency = BookingSelectionSummary.empty.currency;

  for (final service in services) {
    for (final variant in service.variants) {
      if (wanted.contains(variant.uuid)) {
        priceCents += variant.priceCents;
        durationMinutes += variant.durationMinutes;
        currency = variant.currency;
      }
    }
  }

  return BookingSelectionSummary(
    count: selected.length,
    totalPriceCents: priceCents,
    totalDurationMinutes: durationMinutes,
    currency: currency,
  );
});

/// Availability for the current selection, keyed by the parameters so day
/// changes refetch naturally. Kept outside the flow state: it is server
/// data, not selection.
final availabilityProvider = FutureProvider.autoDispose
    .family<DayAvailability, ({String day, String? staffUuid})>(
        (ref, params) async {
  // Audit fix F1: watch ONLY the inputs of the fetch (location + service
  // chain), not the whole flow state — otherwise every slot tap (which
  // mutates the state) re-triggered a network refetch and made the slot
  // list flicker. The list instance is replaced only when the chain
  // changes, so identity-based equality is exactly right here.
  final inputs = ref.watch(bookingFlowProvider.select(
    (s) => (location: s.location, variantUuids: s.selectedVariantUuids),
  ));

  if (inputs.location == null || inputs.variantUuids.isEmpty) {
    return const DayAvailability(slotsByDay: {}, durationMinutes: 0);
  }

  return ref.watch(bookingRepositoryProvider).availability(
        locationUuid: inputs.location!.uuid,
        variantUuids: inputs.variantUuids,
        staffUuid: params.staffUuid,
        fromDay: params.day,
        toDay: params.day,
      );
});
