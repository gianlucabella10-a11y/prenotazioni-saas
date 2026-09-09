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
    this.logoUrl,
    this.contacts = const BusinessContacts(),
    this.social = const BusinessSocial(),
    this.content = const BrandContent(),
    this.vatNumber,
    this.template = 'default',
    this.layout = 'standard',
    this.fontStyle,
    this.unavailableMessageKey,
  });

  final String tenantStatus;
  final int configVersion;
  final String appName;
  final String? tagline;
  final String? logoUrl;
  final BrandTheme theme;
  final String localeDefault;
  final Map<String, bool> features;
  final String confirmationMode; // auto_confirm | request_approve
  final BusinessContacts contacts;
  final BusinessSocial social;

  /// Editorial copy/images shown across the app (Fase 5 — Customer Experience).
  final BrandContent content;

  /// VAT / P.IVA shown in the legal footer of the business card (Fase 3).
  final String? vatNumber;

  /// App Factory skin: template code, layout variant e font (consumati per
  /// scegliere la variante di presentazione; il motore resta identico).
  final String template;
  final String layout;
  final String? fontStyle;

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
      logoUrl: json['logo_url'] as String?,
      template: json['template'] as String? ?? 'default',
      layout: json['layout'] as String? ?? 'standard',
      fontStyle: json['font_style'] as String?,
      theme: BrandTheme.fromJson(
        json['theme'] as Map<String, dynamic>? ?? const {},
      ),
      localeDefault: json['locale_default'] as String? ?? 'it',
      features: Map<String, dynamic>.from(
        json['features'] as Map? ?? const {},
      ).map((key, value) => MapEntry(key, value == true)),
      confirmationMode:
          booking['confirmation_mode'] as String? ?? 'auto_confirm',
      contacts: BusinessContacts.fromJson(
        json['contacts'] as Map<String, dynamic>? ?? const {},
      ),
      social: BusinessSocial.fromJson(
        json['social'] as Map<String, dynamic>? ?? const {},
      ),
      content: BrandContent.fromJson(
        json['content'] as Map<String, dynamic>? ?? const {},
      ),
      vatNumber:
          (json['business'] as Map<String, dynamic>?)?['vat_number'] as String?,
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
///
/// Fase 1 (Brand Identity — tema premium) adds: [mode] (light/dark/system),
/// an [accent] color, a component [elevation] level, and an optional [dark]
/// palette variant — the app now ships a real dark theme, not just light.
class BrandTheme {
  const BrandTheme({
    required this.colors,
    required this.radiusSmall,
    required this.radiusMedium,
    required this.radiusLarge,
    required this.typographyScale,
    this.mode = 'light',
    this.density = 'standard',
    this.elevation = 1,
    this.dark,
  });

  final Map<String, String> colors;
  final double radiusSmall;
  final double radiusMedium;
  final double radiusLarge;
  final double typographyScale;

  /// Requested theme mode: 'light' | 'dark' | 'system'. Drives `themeMode`.
  final String mode;

  /// App-wide visual density (Fase 2 — App Identity): 'comfortable' |
  /// 'standard' | 'compact'. Maps to [ThemeData.visualDensity].
  final String density;

  /// Component shadow level (0 flat … 4 pronounced) for cards/app bar.
  final double elevation;

  /// Optional dark palette variant. Shares geometry (radius/typography/
  /// elevation) with the light theme; only [colors] differ. Null = light-only.
  final BrandTheme? dark;

  /// Fallback palette: the platform's curated default (config/branding.php
  /// on the backend). Used before the first config fetch and for courtesy
  /// screens.
  factory BrandTheme.fallback() => const BrandTheme(
        colors: {
          'primary': '#1F2937',
          'on_primary': '#FFFFFF',
          'secondary': '#C8A24B',
          'on_secondary': '#1F2937',
          'accent': '#C8A24B',
          'on_accent': '#1F2937',
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
        mode: 'light',
        elevation: 1,
      );

  /// Curated dark neutrals used when the tenant enables dark mode but a key is
  /// missing — a dark palette merged over the LIGHT fallback would leak white
  /// surfaces, so dark keys degrade to dark defaults instead.
  factory BrandTheme.darkFallback() => const BrandTheme(
        colors: {
          'primary': '#E5B84B',
          'on_primary': '#1F2937',
          'secondary': '#C8A24B',
          'on_secondary': '#1F2937',
          'accent': '#E5B84B',
          'on_accent': '#1F2937',
          'surface': '#111827',
          'on_surface': '#E5E7EB',
          'background': '#0B1220',
          'success': '#4ADE80',
          'warning': '#FBBF24',
          'error': '#F87171',
        },
        radiusSmall: 8,
        radiusMedium: 12,
        radiusLarge: 24,
        typographyScale: 1,
        mode: 'dark',
        elevation: 1,
      );

  factory BrandTheme.fromJson(Map<String, dynamic> json) {
    final fallback = BrandTheme.fallback();

    final rawColors = json['colors'] as Map<String, dynamic>? ?? const {};
    final radius = json['radius'] as Map<String, dynamic>? ?? const {};
    final typography = json['typography'] as Map<String, dynamic>? ?? const {};
    final elevation = json['elevation'] as Map<String, dynamic>? ?? const {};

    final radiusSmall =
        (radius['small'] as num?)?.toDouble() ?? fallback.radiusSmall;
    final radiusMedium =
        (radius['medium'] as num?)?.toDouble() ?? fallback.radiusMedium;
    final radiusLarge =
        (radius['large'] as num?)?.toDouble() ?? fallback.radiusLarge;
    final scale =
        (typography['scale'] as num?)?.toDouble() ?? fallback.typographyScale;
    final elevationLevel =
        (elevation['level'] as num?)?.toDouble() ?? fallback.elevation;
    final density = json['density'] as String? ?? fallback.density;

    // Dark variant inherits geometry, overrides colors over dark neutrals.
    BrandTheme? dark;
    final rawDark = json['dark'];
    if (rawDark is Map<String, dynamic>) {
      final darkColors = rawDark['colors'] as Map<String, dynamic>? ?? const {};
      dark = BrandTheme(
        colors: {
          ...BrandTheme.darkFallback().colors,
          ...darkColors.map((k, v) => MapEntry(k, v.toString())),
        },
        radiusSmall: radiusSmall,
        radiusMedium: radiusMedium,
        radiusLarge: radiusLarge,
        typographyScale: scale,
        mode: 'dark',
        density: density,
        elevation: elevationLevel,
      );
    }

    return BrandTheme(
      colors: {
        ...fallback.colors,
        ...rawColors.map((k, v) => MapEntry(k, v.toString())),
      },
      radiusSmall: radiusSmall,
      radiusMedium: radiusMedium,
      radiusLarge: radiusLarge,
      typographyScale: scale,
      mode: json['mode'] as String? ?? fallback.mode,
      density: density,
      elevation: elevationLevel,
      dark: dark,
    );
  }
}

/// Direct business contacts shown in the premium "scheda attività".
/// Every field is optional: the UI hides what's absent (no blank rows).
class BusinessContacts {
  const BusinessContacts({this.phone, this.email, this.website});

  final String? phone;
  final String? email;
  final String? website;

  bool get hasAny => phone != null || email != null || website != null;

  factory BusinessContacts.fromJson(Map<String, dynamic> json) =>
      BusinessContacts(
        phone: json['phone'] as String?,
        email: json['email'] as String?,
        website: json['website'] as String?,
      );
}

/// Social + quick links (Instagram, Facebook, TikTok, Google Maps, WhatsApp).
/// External deep links only — no in-app chat/CRM (Fase commerciale).
class BusinessSocial {
  const BusinessSocial({
    this.instagramUrl,
    this.facebookUrl,
    this.tiktokUrl,
    this.mapsUrl,
    this.whatsapp,
  });

  final String? instagramUrl;
  final String? facebookUrl;
  final String? tiktokUrl;
  final String? mapsUrl;
  final WhatsAppContact? whatsapp;

  bool get hasAny =>
      instagramUrl != null ||
      facebookUrl != null ||
      tiktokUrl != null ||
      mapsUrl != null ||
      whatsapp != null;

  factory BusinessSocial.fromJson(Map<String, dynamic> json) => BusinessSocial(
        instagramUrl: json['instagram_url'] as String?,
        facebookUrl: json['facebook_url'] as String?,
        tiktokUrl: json['tiktok_url'] as String?,
        mapsUrl: json['maps_url'] as String?,
        whatsapp: json['whatsapp'] is Map<String, dynamic>
            ? WhatsAppContact.fromJson(json['whatsapp'] as Map<String, dynamic>)
            : null,
      );
}

class WhatsAppContact {
  const WhatsAppContact({required this.number, this.message});

  final String number;
  final String? message;

  /// `https://wa.me/<digits>?text=<message>` — opens WhatsApp chat.
  Uri get uri {
    final digits = number.replaceAll(RegExp(r'[^0-9]'), '');
    final query = (message == null || message!.isEmpty)
        ? ''
        : '?text=${Uri.encodeComponent(message!)}';

    return Uri.parse('https://wa.me/$digits$query');
  }

  factory WhatsAppContact.fromJson(Map<String, dynamic> json) => WhatsAppContact(
        number: json['number'] as String? ?? '',
        message: json['message'] as String?,
      );
}

/// Editorial copy/images the tenant can override (Fase 5 — Customer
/// Experience). Defaults reproduce the platform's original strings, so an
/// unconfigured tenant looks exactly as before; overrides make the app "speak"
/// in the tenant's voice. Null-able entries are simply hidden.
class BrandContent {
  const BrandContent({
    this.welcomeMessage,
    this.homeTitle = 'Il tuo prossimo appuntamento',
    this.homeSubtitle,
    this.primaryCtaLabel = 'Prenota ora',
    this.emptyAppointments = 'Nessun appuntamento in programma.',
    this.heroImageUrl,
  });

  final String? welcomeMessage;
  final String homeTitle;
  final String? homeSubtitle;
  final String primaryCtaLabel;
  final String emptyAppointments;
  final String? heroImageUrl;

  factory BrandContent.fromJson(Map<String, dynamic> json) => BrandContent(
        welcomeMessage: json['welcome_message'] as String?,
        homeTitle: json['home_title'] as String? ?? 'Il tuo prossimo appuntamento',
        homeSubtitle: json['home_subtitle'] as String?,
        primaryCtaLabel: json['primary_cta_label'] as String? ?? 'Prenota ora',
        emptyAppointments: json['empty_appointments'] as String? ??
            'Nessun appuntamento in programma.',
        heroImageUrl: json['hero_image_url'] as String?,
      );
}

/// A single open interval (local wall-clock "HH:MM") within a weekday.
class OpeningInterval {
  const OpeningInterval({required this.start, required this.end});

  final String start;
  final String end;

  factory OpeningInterval.fromJson(Map<String, dynamic> json) => OpeningInterval(
        start: json['start'] as String? ?? '',
        end: json['end'] as String? ?? '',
      );
}

/// Tenant-configurable legal/support links (Fase 3+5: GDPR + store).
class LegalLinks {
  const LegalLinks({
    this.privacyPolicyUrl,
    this.termsUrl,
    this.cookieUrl,
    this.supportUrl,
  });

  final String? privacyPolicyUrl;
  final String? termsUrl;
  final String? cookieUrl;
  final String? supportUrl;

  factory LegalLinks.fromJson(Map<String, dynamic> json) => LegalLinks(
        privacyPolicyUrl: json['privacy_policy_url'] as String?,
        termsUrl: json['terms_url'] as String?,
        cookieUrl: json['cookie_url'] as String?,
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
    this.openingHours = const {},
  });

  final String uuid;
  final String name;
  final String? address;
  final String? phone;
  final String timezone;
  final int bookingWindowDays;
  final int cancellationCutoffMinutes;

  /// Weekday (0 = Monday) → open intervals, in the location's local time.
  /// Empty map = no published hours.
  final Map<int, List<OpeningInterval>> openingHours;

  bool get hasOpeningHours => openingHours.isNotEmpty;

  factory TenantLocation.fromJson(Map<String, dynamic> json) {
    final rawHours = json['opening_hours'];
    final hours = <int, List<OpeningInterval>>{};

    if (rawHours is Map) {
      rawHours.forEach((key, value) {
        final weekday = int.tryParse(key.toString());

        if (weekday == null) {
          return;
        }

        hours[weekday] = ((value as List?) ?? const [])
            .whereType<Map<String, dynamic>>()
            .map(OpeningInterval.fromJson)
            .toList();
      });
    }

    return TenantLocation(
      uuid: json['uuid'] as String,
      name: json['name'] as String? ?? '',
      address: json['address'] as String?,
      phone: json['phone'] as String?,
      timezone: json['timezone'] as String? ?? 'Europe/Rome',
      bookingWindowDays: (json['booking_window_days'] as num?)?.toInt() ?? 60,
      cancellationCutoffMinutes:
          (json['cancellation_cutoff_minutes'] as num?)?.toInt() ?? 1440,
      openingHours: hours,
    );
  }
}
