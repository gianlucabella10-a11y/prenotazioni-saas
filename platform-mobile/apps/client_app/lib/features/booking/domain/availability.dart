/// Availability read model (contract: GET /availability, docs/30 §3).
library;

class AvailabilitySlot {
  const AvailabilitySlot({required this.startsAtUtc, required this.staffUuid});

  /// Instant of the slot start, UTC (the backend's source of truth).
  final DateTime startsAtUtc;

  final String staffUuid;

  DateTime get startsAtLocal => startsAtUtc.toLocal();

  factory AvailabilitySlot.fromJson(Map<String, dynamic> json) =>
      AvailabilitySlot(
        startsAtUtc: DateTime.parse(json['starts_at'] as String).toUtc(),
        staffUuid: json['staff_uuid'] as String,
      );
}

class DayAvailability {
  const DayAvailability({required this.slotsByDay, required this.durationMinutes});

  /// Key: local date `yyyy-MM-dd` of the location; value: ordered slots.
  final Map<String, List<AvailabilitySlot>> slotsByDay;

  final int durationMinutes;

  List<AvailabilitySlot> forDay(String day) => slotsByDay[day] ?? const [];

  factory DayAvailability.fromJson(Map<String, dynamic> json) {
    final raw = json['slots'] as Map<String, dynamic>? ?? const {};

    return DayAvailability(
      durationMinutes: (json['duration_minutes'] as num?)?.toInt() ?? 0,
      slotsByDay: raw.map(
        (day, slots) => MapEntry(
          day,
          ((slots as List?) ?? const [])
              .whereType<Map<String, dynamic>>()
              .map(AvailabilitySlot.fromJson)
              .toList(),
        ),
      ),
    );
  }
}

/// Groups a day's slots into the Morning/Afternoon bands observed in the
/// reference UX (SCREEN_ANALYSIS S6): the cut is local noon-adjacent.
({List<AvailabilitySlot> morning, List<AvailabilitySlot> afternoon})
    groupSlotsByDayPart(List<AvailabilitySlot> slots, {int afternoonFromHour = 13}) {
  final morning = <AvailabilitySlot>[];
  final afternoon = <AvailabilitySlot>[];

  for (final slot in slots) {
    if (slot.startsAtLocal.hour < afternoonFromHour) {
      morning.add(slot);
    } else {
      afternoon.add(slot);
    }
  }

  return (morning: morning, afternoon: afternoon);
}
