import 'package:flutter/material.dart';

import 'white_label_config.dart';

/// Builds the Material [ThemeData] from the tenant's brand tokens.
///
/// This is the rendering half of the white label engine: every widget in
/// the app consumes ThemeData/ColorScheme — never raw colors — so swapping
/// the tenant config restyles the whole product without code changes.
abstract final class AppThemeBuilder {
  static ThemeData build(BrandTheme tokens, {String? fontStyle}) {
    final primary = _color(tokens.colors['primary']);
    final onPrimary = _color(tokens.colors['on_primary']);
    final secondary = _color(tokens.colors['secondary']);
    final onSecondary = _color(tokens.colors['on_secondary']);
    final surface = _color(tokens.colors['surface']);
    final onSurface = _color(tokens.colors['on_surface']);
    final error = _color(tokens.colors['error']);

    final scheme = ColorScheme(
      brightness: Brightness.light,
      primary: primary,
      onPrimary: onPrimary,
      secondary: secondary,
      onSecondary: onSecondary,
      surface: surface,
      onSurface: onSurface,
      error: error,
      onError: Colors.white,
    );

    final mediumRadius = BorderRadius.circular(tokens.radiusMedium);

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: _color(tokens.colors['background']),
      appBarTheme: AppBarTheme(
        backgroundColor: primary,
        foregroundColor: onPrimary,
        centerTitle: true,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: onPrimary,
          minimumSize: const Size.fromHeight(48),
          shape: RoundedRectangleBorder(borderRadius: mediumRadius),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: primary,
          minimumSize: const Size.fromHeight(48),
          shape: RoundedRectangleBorder(borderRadius: mediumRadius),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        border: OutlineInputBorder(borderRadius: mediumRadius),
        filled: true,
        fillColor: surface,
      ),
      cardTheme: CardThemeData(
        color: surface,
        elevation: 1,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(tokens.radiusLarge),
        ),
      ),
      chipTheme: ChipThemeData(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(tokens.radiusSmall),
        ),
      ),
      // Color-only typography merged with the 2021 geometry: apply() needs
      // concrete font sizes to scale.
      textTheme: Typography.blackMountainView
          .merge(Typography.englishLike2021)
          .apply(
            fontSizeFactor: tokens.typographyScale,
            fontFamily: _fontFamily(fontStyle),
            bodyColor: onSurface,
            displayColor: onSurface,
          ),
    );
  }

  /// Maps a curated `font_style` (App Factory template) to a bundled family.
  /// Returns null (platform default) when unknown or not yet bundled — the
  /// mechanism is wired; the actual .ttf files are added with the templates.
  static String? _fontFamily(String? fontStyle) => switch (fontStyle) {
        'oswald' => 'Oswald',
        'poppins' => 'Poppins',
        'inter' => 'Inter',
        _ => null,
      };

  /// Parses `#RRGGBB`; an invalid token falls back to a readable neutral
  /// instead of crashing the UI (the backend already validates contrast,
  /// this is the last line of defence).
  static Color _color(String? hex) {
    if (hex == null) {
      return const Color(0xFF1F2937);
    }

    final cleaned = hex.replaceFirst('#', '');

    if (cleaned.length != 6) {
      return const Color(0xFF1F2937);
    }

    final value = int.tryParse(cleaned, radix: 16);

    return value == null
        ? const Color(0xFF1F2937)
        : Color(0xFF000000 | value);
  }
}
