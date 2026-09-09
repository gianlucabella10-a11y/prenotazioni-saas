/// Appointment as returned by the API (AppointmentResource, docs/25).
library;

class Appointment {
  const Appointment({
    required this.uuid,
    required this.status,
    required this.startsAtUtc,
    required this.endsAtUtc,
    required this.totalPriceCents,
    required this.currency,
    required this.locationName,
    required this.items,
    this.cancellationReason,
  });

  final String uuid;
  final String status;
  final DateTime startsAtUtc;
  final DateTime endsAtUtc;
  final int totalPriceCents;
  final String currency;
  final String locationName;
  final String? cancellationReason;
  final List<AppointmentItem> items;

  DateTime get startsAtLocal => startsAtUtc.toLocal();

  DateTime get endsAtLocal => endsAtUtc.toLocal();

  /// Planned span (end − start), used by the confirmation recap and the
  /// calendar link.
  Duration get plannedDuration => endsAtUtc.difference(startsAtUtc);

  bool get isCancellable => status == 'confirmed' || status == 'requested';

  bool get isPendingApproval => status == 'requested';

  String get servicesLabel => items.map((i) => i.serviceName).join(' + ');

  String? get staffName => items.isEmpty ? null : items.first.staffName;

  factory Appointment.fromJson(Map<String, dynamic> json) => Appointment(
        uuid: json['uuid'] as String,
        status: json['status'] as String? ?? '',
        startsAtUtc: DateTime.parse(json['starts_at'] as String).toUtc(),
        endsAtUtc: DateTime.parse(json['ends_at'] as String).toUtc(),
        totalPriceCents: (json['total_price_cents'] as num?)?.toInt() ?? 0,
        currency: json['currency'] as String? ?? 'EUR',
        locationName: json['location_name'] as String? ?? '',
        cancellationReason: json['cancellation_reason'] as String?,
        items: ((json['items'] as List?) ?? const [])
            .whereType<Map<String, dynamic>>()
            .map(AppointmentItem.fromJson)
            .toList(),
      );
}

class AppointmentItem {
  const AppointmentItem({
    required this.serviceName,
    required this.variantName,
    required this.durationMinutes,
    required this.priceCents,
    required this.staffName,
    required this.staffUuid,
  });

  final String serviceName;
  final String variantName;
  final int durationMinutes;
  final int priceCents;
  final String staffName;
  final String staffUuid;

  factory AppointmentItem.fromJson(Map<String, dynamic> json) =>
      AppointmentItem(
        serviceName: json['service_name'] as String? ?? '',
        variantName: json['variant_name'] as String? ?? '',
        durationMinutes: (json['duration_minutes'] as num?)?.toInt() ?? 0,
        priceCents: (json['price_cents'] as num?)?.toInt() ?? 0,
        staffName: json['staff_name'] as String? ?? '',
        staffUuid: json['staff_uuid'] as String? ?? '',
      );
}
