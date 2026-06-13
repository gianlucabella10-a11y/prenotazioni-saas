import '../../../core/network/api_client.dart';
import '../domain/appointment.dart';
import '../domain/availability.dart';

/// Booking operations against the platform API (docs/25 §3).
class BookingRepository {
  BookingRepository(this._api);

  final ApiClient _api;

  Future<DayAvailability> availability({
    required String locationUuid,
    required List<String> variantUuids,
    required String fromDay,
    required String toDay,
    String? staffUuid,
  }) async {
    final json = await _api.getJson('/availability', query: {
      'location_uuid': locationUuid,
      'variant_uuids[]': variantUuids,
      'from': fromDay,
      'to': toDay,
      'staff_uuid': ?staffUuid,
    });

    return DayAvailability.fromJson(json);
  }

  /// Creates the appointment. [idempotencyKey] makes network retries safe:
  /// the backend replays the original response instead of double-booking
  /// (docs/25 §6).
  Future<Appointment> create({
    required String locationUuid,
    required List<String> variantUuids,
    required String staffUuid,
    required DateTime startsAtUtc,
    required String idempotencyKey,
  }) async {
    final json = await _api.postJson(
      '/appointments',
      headers: {'Idempotency-Key': idempotencyKey},
      body: {
        'location_uuid': locationUuid,
        'variant_uuids': variantUuids,
        'staff_uuid': staffUuid,
        'starts_at': '${startsAtUtc.toIso8601String().split('.').first}Z',
      },
    );

    return Appointment.fromJson(json['data'] as Map<String, dynamic>);
  }

  Future<List<Appointment>> list({required bool upcoming}) async {
    final json = await _api.getJson('/appointments', query: {
      'scope': upcoming ? 'upcoming' : 'past',
    });

    return ((json['data'] as List?) ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(Appointment.fromJson)
        .toList();
  }

  Future<Appointment> cancel(String uuid, {String? reason}) async {
    final json = await _api.postJson(
      '/appointments/$uuid/cancel',
      body: {if (reason != null && reason.isNotEmpty) 'reason': reason},
    );

    return Appointment.fromJson(json['data'] as Map<String, dynamic>);
  }
}
