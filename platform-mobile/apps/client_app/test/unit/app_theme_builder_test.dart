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
  });
}
