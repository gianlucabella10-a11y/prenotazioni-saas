import 'package:client_app/features/white_label/domain/app_theme_builder.dart';
import 'package:client_app/features/white_label/domain/white_label_config.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('AppThemeBuilder', () {
    test('builds the color scheme from tenant tokens', () {
      final theme = AppThemeBuilder.build(BrandTheme.fromJson({
        'colors': {
          'primary': '#0B4F8A',
          'on_primary': '#FFFFFF',
          'secondary': '#C8A24B',
        },
      }));

      expect(theme.colorScheme.primary, const Color(0xFF0B4F8A));
      expect(theme.colorScheme.onPrimary, const Color(0xFFFFFFFF));
      expect(theme.colorScheme.secondary, const Color(0xFFC8A24B));
      expect(theme.useMaterial3, isTrue);
    });

    test('invalid hex tokens degrade to a readable neutral, never crash', () {
      final theme = AppThemeBuilder.build(const BrandTheme(
        colors: {'primary': 'not-a-color', 'on_primary': '#GGGGGG'},
        radiusSmall: 8,
        radiusMedium: 12,
        radiusLarge: 24,
        typographyScale: 1,
      ));

      expect(theme.colorScheme.primary, const Color(0xFF1F2937));
      expect(theme.colorScheme.onPrimary, const Color(0xFF1F2937));
    });

    test('typography scale is applied', () {
      final base = AppThemeBuilder.build(BrandTheme.fallback());
      final scaled = AppThemeBuilder.build(BrandTheme.fromJson({
        'typography': {'scale': 1.5},
      }));

      expect(
        scaled.textTheme.bodyMedium!.fontSize,
        base.textTheme.bodyMedium!.fontSize! * 1.5,
      );
    });

    test('font_style (App Factory template) maps to a curated family', () {
      final oswald =
          AppThemeBuilder.build(BrandTheme.fallback(), fontStyle: 'oswald');
      final poppins =
          AppThemeBuilder.build(BrandTheme.fallback(), fontStyle: 'poppins');

      expect(oswald.textTheme.bodyMedium!.fontFamily, 'Oswald');
      expect(poppins.textTheme.titleLarge!.fontFamily, 'Poppins');
    });

    test('accent maps to ColorScheme.tertiary', () {
      final theme = AppThemeBuilder.build(BrandTheme.fromJson({
        'colors': {'accent': '#7C3AED', 'on_accent': '#FFFFFF'},
      }));

      expect(theme.colorScheme.tertiary, const Color(0xFF7C3AED));
      expect(theme.colorScheme.onTertiary, const Color(0xFFFFFFFF));
    });

    test('elevation token drives card/app bar elevation', () {
      final flat = AppThemeBuilder.build(BrandTheme.fromJson({
        'elevation': {'level': 0},
      }));
      final raised = AppThemeBuilder.build(BrandTheme.fromJson({
        'elevation': {'level': 3},
      }));

      expect(flat.cardTheme.elevation, 0);
      expect(raised.cardTheme.elevation, 3);
      expect(raised.appBarTheme.elevation, 3);
    });

    test('density token maps to visual density (App Identity)', () {
      final compact = AppThemeBuilder.build(BrandTheme.fromJson({
        'density': 'compact',
      }));
      final comfortable = AppThemeBuilder.build(BrandTheme.fromJson({
        'density': 'comfortable',
      }));
      final standard = AppThemeBuilder.build(BrandTheme.fallback());

      expect(compact.visualDensity, VisualDensity.compact);
      expect(comfortable.visualDensity, VisualDensity.comfortable);
      expect(standard.visualDensity, VisualDensity.standard);
    });

    test('semantic colors are published as a BrandColors extension', () {
      final theme = AppThemeBuilder.build(BrandTheme.fromJson({
        'colors': {'success': '#15803D', 'warning': '#B45309'},
      }));

      final brand = theme.extension<BrandColors>();

      expect(brand, isNotNull);
      expect(brand!.success, const Color(0xFF15803D));
      expect(brand.warning, const Color(0xFFB45309));
    });
  });

  group('AppThemeBuilder dark theme', () {
    test('themeMode maps the mode token', () {
      expect(
        AppThemeBuilder.themeMode(BrandTheme.fromJson({'mode': 'dark'})),
        ThemeMode.dark,
      );
      expect(
        AppThemeBuilder.themeMode(BrandTheme.fromJson({'mode': 'system'})),
        ThemeMode.system,
      );
      expect(
        AppThemeBuilder.themeMode(BrandTheme.fromJson({'mode': 'light'})),
        ThemeMode.light,
      );
    });

    test('buildDark uses the tenant dark palette', () {
      final tokens = BrandTheme.fromJson({
        'mode': 'dark',
        'colors': {'primary': '#1F2937'},
        'dark': {
          'colors': {
            'primary': '#E5B84B',
            'surface': '#111827',
            'background': '#0B1220',
          },
        },
      });

      final dark = AppThemeBuilder.buildDark(tokens);

      expect(dark.colorScheme.brightness, Brightness.dark);
      expect(dark.colorScheme.primary, const Color(0xFFE5B84B));
      expect(dark.colorScheme.surface, const Color(0xFF111827));
      expect(dark.scaffoldBackgroundColor, const Color(0xFF0B1220));
    });

    test('buildDark falls back to curated dark neutrals when light-only', () {
      // Nessuna palette dark fornita → neutri dark curati, mai superfici bianche.
      final dark = AppThemeBuilder.buildDark(BrandTheme.fallback());

      expect(dark.colorScheme.brightness, Brightness.dark);
      expect(dark.colorScheme.surface, const Color(0xFF111827));
      expect(dark.scaffoldBackgroundColor, const Color(0xFF0B1220));
    });
  });
}
