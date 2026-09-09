import 'package:client_app/app/providers.dart';
import 'package:client_app/features/booking/application/booking_flow_controller.dart';
import 'package:client_app/features/catalog/domain/service.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

ServiceVariant _variant(String uuid, {required int price, required int minutes}) =>
    ServiceVariant(
      uuid: uuid,
      name: uuid,
      durationMinutes: minutes,
      priceCents: price,
      currency: 'EUR',
      isDefault: true,
    );

CatalogService _service(String uuid, List<ServiceVariant> variants) =>
    CatalogService(uuid: uuid, name: uuid, variants: variants);

/// Presets the flow with a chosen set of variant uuids.
class _StubFlow extends BookingFlowController {
  _StubFlow(this._selected);

  final List<String> _selected;

  @override
  BookingFlowState build() =>
      BookingFlowState.initial().copyWith(selectedVariantUuids: _selected);
}

void main() {
  test('sums price and duration of exactly the selected variants', () async {
    final container = ProviderContainer(
      overrides: [
        servicesProvider.overrideWith((ref) async => [
              _service('s1', [
                _variant('v1', price: 1500, minutes: 30),
                _variant('v2', price: 2500, minutes: 45),
              ]),
              _service('s2', [
                _variant('v3', price: 1000, minutes: 20),
              ]),
            ]),
        bookingFlowProvider.overrideWith(() => _StubFlow(['v1', 'v3'])),
      ],
    );
    addTearDown(container.dispose);

    // Let the catalog resolve before reading the derived summary.
    await container.read(servicesProvider.future);

    final summary = container.read(bookingSelectionSummaryProvider);

    expect(summary.count, 2);
    expect(summary.totalPriceCents, 2500); // 1500 + 1000, v2 excluded
    expect(summary.totalDurationMinutes, 50); // 30 + 20
    expect(summary.currency, 'EUR');
    expect(summary.isEmpty, isFalse);
  });

  test('is empty when nothing is selected', () async {
    final container = ProviderContainer(
      overrides: [
        servicesProvider.overrideWith((ref) async => const <CatalogService>[]),
      ],
    );
    addTearDown(container.dispose);

    await container.read(servicesProvider.future);

    expect(container.read(bookingSelectionSummaryProvider).isEmpty, isTrue);
  });
}
