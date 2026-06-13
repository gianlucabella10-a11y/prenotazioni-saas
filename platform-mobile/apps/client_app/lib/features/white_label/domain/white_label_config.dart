/// Runtime white label configuration (contract: docs/27 §2, served by
/// `GET /app/config`). This model is the heart of the SaaS promise: the
/// same binary renders ANY tenant from this data — no tenant-specific code.
library;

class WhiteLabelConfig {
  const WhiteLabelConfig({
    required this.tenantStatus,
    required this.configVersion,
    required this.appName,
    required this.tagline,
    required this.theme,
    required this.localeDefault,
    required this.features,
    required this.confirmationMode,
    required this.legal,
    required this.locations,
    this.unavailableMessageKey,
  });

  final String tenantStatus;
  final int configVersion;
  final String appName;
  final String? tagline;
  final BrandTheme theme;
  final String localeDefault;
  final Map<String, bool> features;
  final String confirmationMode; // auto_confirm | request_approve
  final LegalLinks legal;
  final List<TenantLocation> locations;

  /// Set only for suspended/terminated tenants (courtesy screen).
  final String? unavailableMessageKey;

  bool get isOperating => tenantStatus == 'active' || tenantStatus == 'at_risk';

  bool get requiresApproval => confirmationMode == 'request_approve';

  bool hasFeature(String code) => features[code] ?? false;

  factory WhiteLabelConfig.fromJson(Map<String, dynamic> json) {
    final status = json['tenant_status'] as String? ?? 'active';

    // Suspended/terminated tenants receive the minimal courtesy payload
    // (docs/27 §7): app_name + message key only.
    if (status != 'active' && status != 'at_risk') {
      return WhiteLabelConfig(
        tenantStatus: status,
        configVersion: 0,
        appName: json['app_name'] as String? ?? '',
        tagline: null,
        theme: BrandTheme.fallback(),
        localeDefault: 'it',
        features: const {},
        confirmationMode: 'auto_confirm',
        legal: const LegalLinks(),
        locations: const [],
        unavailableMessageKey: json['message_key'] as String?,
      );
    }

    final booking = json['booking'] as Map<String, dynamic>? ?? const {};

    return WhiteLabelConfig(
      tenantStatus: status,
      configVersion: (json['config_version'] as num?)?.toInt() ?? 0,
      appName: json['app_name'] as String? ?? '',
      tagline: json['tagline'] as String?,
      theme: BrandTheme.fromJson(
        json['theme'] as Map<String, dynamic>? ?? const {},
      ),
      localeDefault: json['locale_default'] as String? ?? 'it',
      features: Map<String, dynamic>.from(
        json['features'] as Map? ?? const {},
      ).map((key, value) => MapEntry(key, value == true)),
      confirmationMode:
          booking['confirmation_mode'] as String? ?? 'auto_confirm',
      legal: LegalLinks.fromJson(
        json['legal'] as Map<String, dynamic>? ?? const {},
      ),
      locations: ((json['locations'] as List?) ?? const [])
          .whereType<Map<String, dynamic>>()
          .map(TenantLocation.fromJson)
          .toList(),
    );
  }
}

/// Design-system tokens of the tenant (docs/27 §4): the Flutter theme is
/// built from these, with curated fallbacks for missing keys so a partial
/// payload can never produce an unreadable UI.
class BrandTheme {
  const BrandTheme({
    required this.colors,
    required this.radiusSmall,
    required this.radiusMedium,
    required this.radiusLarge,
    required this.typographyScale,
  });

  final Map<String, String> colors;
  final double radiusSmall;
  final double radiusMedium;
  final double radiusLarge;
  final double typographyScale;

  /// Fallback palette: the platform's curated default (config/branding.php
  /// on the backend). Used before the first config fetch and for courtesy
  /// screens.
  factory BrandTheme.fallback() => const BrandTheme(
        colors: {
          'primary': '#1F2937',
          'on_primary': '#FFFFFF',
          'secondary': '#C8A24B',
          'on_secondary': '#1F2937',
          'surface': '#FFFFFF',
          'on_surface': '#111827',
          'background': '#F9FAFB',
          'success': '#15803D',
          'warning': '#B45309',
          'error': '#B91C1C',
        },
        radiusSmall: 8,
        radiusMedium: 12,
        radiusLarge: 24,
        typographyScale: 1,
      );

  factory BrandTheme.fromJson(Map<String, dynamic> json) {
    final fallback = BrandTheme.fallback();

    final rawColors = json['colors'] as Map<String, dynamic>? ?? const {};
    final radius = json['radius'] as Map<String, dynamic>? ?? const {};
    final typography = json['typography'] as Map<String, dynamic>? ?? const {};

    return BrandTheme(
      colors: {
        ...fallback.colors,
        ...rawColors.map((k, v) => MapEntry(k, v.toString())),
      },
      radiusSmall: (radius['small'] as num?)?.toDouble() ?? fallback.radiusSmall,
      radiusMedium:
          (radius['medium'] as num?)?.toDouble() ?? fallback.radiusMedium,
      radiusLarge: (radius['large'] as num?)?.toDouble() ?? fallback.radiusLarge,
      typographyScale: (typography['scale'] as num?)?.toDouble() ??
          fallback.typographyScale,
    );
  }
}

/// Tenant-configurable legal/support links (Fase 3+5: GDPR + store).
class LegalLinks {
  const LegalLinks({this.privacyPolicyUrl, this.termsUrl, this.supportUrl});

  final String? privacyPolicyUrl;
  final String? termsUrl;
  final String? supportUrl;

  factory LegalLinks.fromJson(Map<String, dynamic> json) => LegalLinks(
        privacyPolicyUrl: json['privacy_policy_url'] as String?,
        termsUrl: json['terms_url'] as String?,
        supportUrl: json['support_url'] as String?,
      );
}

class TenantLocation {
  const TenantLocation({
    required this.uuid,
    required this.name,
    required this.timezone,
    required this.bookingWindowDays,
    required this.cancellationCutoffMinutes,
    this.address,
    this.phone,
  });

  final String uuid;
  final String name;
  final String? address;
  final String? phone;
  final String timezone;
  final int bookingWindowDays;
  final int cancellationCutoffMinutes;

  factory TenantLocation.fromJson(Map<String, dynamic> json) => TenantLocation(
        uuid: json['uuid'] as String,
        name: json['name'] as String? ?? '',
        address: json['address'] as String?,
        phone: json['phone'] as String?,
        timezone: json['timezone'] as String? ?? 'Europe/Rome',
        bookingWindowDays: (json['booking_window_days'] as num?)?.toInt() ?? 60,
        cancellationCutoffMinutes:
            (json['cancellation_cutoff_minutes'] as num?)?.toInt() ?? 1440,
      );
}
