import 'package:client_app/features/white_label/domain/white_label_config.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('WhiteLabelConfig.fromJson', () {
    test('parses a full active payload', () {
      final config = WhiteLabelConfig.fromJson({
        'tenant_status': 'active',
        'config_version': 3,
        'app_name': 'Salone Verdi',
        'tagline': 'Dal 1980',
        'theme': {
          'colors': {'primary': '#112233'},
          'radius': {'medium': 16},
          'typography': {'scale': 1.1},
        },
        'locale_default': 'it',
        'features': {'waitlist': true, 'campaigns': false},
        'booking': {'confirmation_mode': 'request_approve'},
        'locations': [
          {
            'uuid': 'loc-1',
            'name': 'Sede centrale',
            'address': 'Via Roma 1',
            'phone': '+39 000 000',
            'timezone': 'Europe/Rome',
            'booking_window_days': 45,
            'cancellation_cutoff_minutes': 720,
          }
        ],
      });

      expect(config.isOperating, isTrue);
      expect(config.configVersion, 3);
      expect(config.appName, 'Salone Verdi');
      expect(config.requiresApproval, isTrue);
      expect(config.hasFeature('waitlist'), isTrue);
      expect(config.hasFeature('campaigns'), isFalse);
      expect(config.hasFeature('unknown'), isFalse);
      expect(config.locations, hasLength(1));
      expect(config.locations.first.bookingWindowDays, 45);

      // Custom token wins, missing tokens fall back to the curated set.
      expect(config.theme.colors['primary'], '#112233');
      expect(config.theme.colors['on_primary'], '#FFFFFF');
      expect(config.theme.radiusMedium, 16);
      expect(config.theme.radiusSmall, 8);
      expect(config.theme.typographyScale, 1.1);
    });

    test('parses the App Factory skin (template/layout/font) with defaults', () {
      final withSkin = WhiteLabelConfig.fromJson({
        'tenant_status': 'active',
        'app_name': 'Giuffrida Barber',
        'template': 'barber_dark',
        'layout': 'hero_dark',
        'font_style': 'oswald',
      });

      expect(withSkin.template, 'barber_dark');
      expect(withSkin.layout, 'hero_dark');
      expect(withSkin.fontStyle, 'oswald');

      // Defaults when the skin is absent.
      final bare = WhiteLabelConfig.fromJson({
        'tenant_status': 'active',
        'app_name': 'X',
      });

      expect(bare.template, 'default');
      expect(bare.layout, 'standard');
      expect(bare.fontStyle, isNull);
    });

    test('suspended tenant yields the courtesy model (docs/27 §7)', () {
      final config = WhiteLabelConfig.fromJson({
        'tenant_status': 'suspended',
        'app_name': 'Salone Verdi',
        'message_key': 'service_suspended',
      });

      expect(config.isOperating, isFalse);
      expect(config.unavailableMessageKey, 'service_suspended');
      expect(config.locations, isEmpty);
    });

    test('white label proof: two tenants, same code, different identity', () {
      WhiteLabelConfig configFor(String name, String primary) =>
          WhiteLabelConfig.fromJson({
            'tenant_status': 'active',
            'app_name': name,
            'theme': {
              'colors': {'primary': primary},
            },
          });

      final barber = configFor('Giuffrida Barber', '#1F2937');
      final doctor = configFor('Dott. Verdi', '#0B4F8A');

      expect(barber.appName, isNot(doctor.appName));
      expect(
        barber.theme.colors['primary'],
        isNot(doctor.theme.colors['primary']),
      );
    });
  });
}
