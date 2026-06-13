import 'package:client_app/features/booking/domain/availability.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  AvailabilitySlot slotAtLocalHour(int hour, [int minute = 0]) {
    final local = DateTime(2026, 6, 15, hour, minute);

    return AvailabilitySlot(startsAtUtc: local.toUtc(), staffUuid: 's-1');
  }

  group('groupSlotsByDayPart', () {
    test('splits at the afternoon boundary (local time)', () {
      final grouped = groupSlotsByDayPart([
        slotAtLocalHour(9),
        slotAtLocalHour(12, 30),
        slotAtLocalHour(13),
        slotAtLocalHour(18, 45),
      ]);

      expect(grouped.morning, hasLength(2));
      expect(grouped.afternoon, hasLength(2));
      expect(grouped.morning.first.startsAtLocal.hour, 9);
      expect(grouped.afternoon.first.startsAtLocal.hour, 13);
    });

    test('handles empty and single-band days', () {
      expect(groupSlotsByDayPart(const []).morning, isEmpty);

      final onlyMorning = groupSlotsByDayPart([slotAtLocalHour(10)]);

      expect(onlyMorning.morning, hasLength(1));
      expect(onlyMorning.afternoon, isEmpty);
    });
  });

  group('DayAvailability.fromJson', () {
    test('parses slots keyed by local day', () {
      final result = DayAvailability.fromJson({
        'duration_minutes': 35,
        'slots': {
          '2026-06-15': [
            {'starts_at': '2026-06-15T07:00:00Z', 'staff_uuid': 's-1'},
            {'starts_at': '2026-06-15T07:15:00Z', 'staff_uuid': 's-1'},
          ],
        },
      });

      expect(result.durationMinutes, 35);
      expect(result.forDay('2026-06-15'), hasLength(2));
      expect(result.forDay('2026-06-16'), isEmpty);
      expect(result.forDay('2026-06-15').first.startsAtUtc.isUtc, isTrue);
    });
  });
}
