import 'package:client_app/core/analytics/analytics_service.dart';
import 'package:flutter_test/flutter_test.dart';

class _CapturingSink implements AnalyticsSink {
  final List<({String event, Map<String, Object?> params})> events = [];

  @override
  void log(String event, Map<String, Object?> params) =>
      events.add((event: event, params: params));
}

void main() {
  test('eventi prodotto usano chiavi snake_case stabili', () {
    final sink = _CapturingSink();
    AnalyticsService(sink).appOpen();
    AnalyticsService(sink).bookingCreated('abc');

    expect(sink.events[0].event, 'app_open');
    expect(sink.events[1].event, 'booking_created');
    expect(sink.events[1].params['appointment_id'], 'abc');
  });

  test('il tenant è allegato a ogni evento quando presente', () {
    final sink = _CapturingSink();
    AnalyticsService(sink, tenantKey: 'tnt_123').login();

    expect(sink.events.single.params['tenant'], 'tnt_123');
  });

  test('senza tenant non aggiunge la chiave tenant', () {
    final sink = _CapturingSink();
    AnalyticsService(sink).error('boom');

    expect(sink.events.single.params.containsKey('tenant'), isFalse);
    expect(sink.events.single.params['message'], 'boom');
  });

  test('il sink no-op non lancia', () {
    expect(() => AnalyticsService(const NoopAnalyticsSink()).appOpen(), returnsNormally);
  });
}
