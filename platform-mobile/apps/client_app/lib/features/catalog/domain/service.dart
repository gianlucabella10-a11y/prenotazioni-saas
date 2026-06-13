/// Catalog models (contract: GET /catalog/services, docs/25 §3).
library;

class CatalogService {
  const CatalogService({
    required this.uuid,
    required this.name,
    required this.variants,
    this.description,
    this.category,
  });

  final String uuid;
  final String name;
  final String? description;
  final String? category;
  final List<ServiceVariant> variants;

  /// The variant preselected when the customer taps the service card.
  ServiceVariant get defaultVariant => variants.firstWhere(
        (v) => v.isDefault,
        orElse: () => variants.first,
      );

  factory CatalogService.fromJson(Map<String, dynamic> json) => CatalogService(
        uuid: json['uuid'] as String,
        name: json['name'] as String? ?? '',
        description: json['description'] as String?,
        category: json['category'] as String?,
        variants: ((json['variants'] as List?) ?? const [])
            .whereType<Map<String, dynamic>>()
            .map(ServiceVariant.fromJson)
            .toList(),
      );
}

class ServiceVariant {
  const ServiceVariant({
    required this.uuid,
    required this.name,
    required this.durationMinutes,
    required this.priceCents,
    required this.currency,
    required this.isDefault,
  });

  final String uuid;
  final String name;
  final int durationMinutes;
  final int priceCents;
  final String currency;
  final bool isDefault;

  factory ServiceVariant.fromJson(Map<String, dynamic> json) => ServiceVariant(
        uuid: json['uuid'] as String,
        name: json['name'] as String? ?? '',
        durationMinutes: (json['duration_minutes'] as num?)?.toInt() ?? 0,
        priceCents: (json['price_cents'] as num?)?.toInt() ?? 0,
        currency: json['currency'] as String? ?? 'EUR',
        isDefault: json['is_default'] == true,
      );
}

class StaffMember {
  const StaffMember({
    required this.uuid,
    required this.displayName,
    required this.serviceUuids,
    this.roleLabel,
  });

  final String uuid;
  final String displayName;
  final String? roleLabel;
  final Set<String> serviceUuids;

  /// Whether this staff member performs EVERY selected service (chained
  /// visits need one operator across the whole chain — docs/30 §6).
  bool performsAll(Iterable<String> selectedServiceUuids) =>
      selectedServiceUuids.every(serviceUuids.contains);

  factory StaffMember.fromJson(Map<String, dynamic> json) => StaffMember(
        uuid: json['uuid'] as String,
        displayName: json['display_name'] as String? ?? '',
        roleLabel: json['role_label'] as String?,
        serviceUuids: ((json['service_uuids'] as List?) ?? const [])
            .whereType<String>()
            .toSet(),
      );
}
