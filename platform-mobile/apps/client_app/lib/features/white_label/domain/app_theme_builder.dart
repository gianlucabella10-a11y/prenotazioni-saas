import 'package:flutter/material.dart';

import 'white_label_config.dart';

/// Builds the Material [ThemeData] from the tenant's brand tokens.
///
/// This is the rendering half of the white label engine: every widget in
/// the app consumes ThemeData/ColorScheme — never raw colors — so swapping
/// the tenant config restyles the whole product without code changes.
///
/// Fase 1 (tema premium): builds both a [build] (light) and a [buildDark]
/// theme, wires the [themeMode] the tenant chose, maps the brand accent to
/// `ColorScheme.tertiary`, applies the elevation token, and publishes the
/// semantic colors (success/warning/accent) as a [BrandColors] extension so
/// widgets can read them from the theme instead of hard-coding hexes.
abstract final class AppThemeBuilder {
  /// Light theme from the tenant tokens.
  static ThemeData build(BrandTheme tokens, {String? fontStyle}) =>
      _buildScheme(tokens, Brightness.light, fontStyle: fontStyle);

  /// Dark theme from the tenant's dark palette (or a curated dark fallback when
  /// the tenant ships light-only, so `themeMode: system` is always safe).
  static ThemeData buildDark(BrandTheme tokens, {String? fontStyle}) =>
      _buildScheme(
        tokens.dark ?? BrandTheme.darkFallback(),
        Brightness.dark,
        fontStyle: fontStyle,
      );

  /// Maps the tenant's `mode` token to a Flutter [ThemeMode].
  static ThemeMode themeMode(BrandTheme tokens) => switch (tokens.mode) {
        'dark' => ThemeMode.dark,
        'system' => ThemeMode.system,
        _ => ThemeMode.light,
      };

  static ThemeData _buildScheme(
    BrandTheme tokens,
    Brightness brightness, {
    String? fontStyle,
  }) {
    final primary = _color(tokens.colors['primary']);
    final onPrimary = _color(tokens.colors['on_primary']);
    final secondary = _color(tokens.colors['secondary']);
    final onSecondary = _color(tokens.colors['on_secondary']);
    final accent =
        _color(tokens.colors['accent'] ?? tokens.colors['secondary']);
    final onAccent =
        _color(tokens.colors['on_accent'] ?? tokens.colors['on_secondary']);
    final surface = _color(tokens.colors['surface']);
    final onSurface = _color(tokens.colors['on_surface']);
    final success = _color(tokens.colors['success']);
    final warning = _color(tokens.colors['warning']);
    final error = _color(tokens.colors['error']);

    final scheme = ColorScheme(
      brightness: brightness,
      primary: primary,
      onPrimary: onPrimary,
      secondary: secondary,
      onSecondary: onSecondary,
      // Accent brand → tertiary slot (Material lo usa per elementi di richiamo).
      tertiary: accent,
      onTertiary: onAccent,
      surface: surface,
      onSurface: onSurface,
      error: error,
      onError: _readableOn(error),
    );

    final mediumRadius = BorderRadius.circular(tokens.radiusMedium);
    final elevation = tokens.elevation;

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      visualDensity: _density(tokens.density),
      scaffoldBackgroundColor: _color(tokens.colors['background']),
      appBarTheme: AppBarTheme(
        backgroundColor: primary,
        foregroundColor: onPrimary,
        centerTitle: true,
        elevation: elevation,
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
        elevation: elevation,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(tokens.radiusLarge),
        ),
      ),
      chipTheme: ChipThemeData(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(tokens.radiusSmall),
        ),
      ),
      // Semantic brand colors, previously in the payload but unused: now
      // reachable via `Theme.of(context).extension<BrandColors>()`.
      extensions: [
        BrandColors(
          success: success,
          onSuccess: _readableOn(success),
          warning: warning,
          onWarning: _readableOn(warning),
          accent: accent,
          onAccent: onAccent,
        ),
      ],
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

  /// Maps the `density` token (Fase 2 — App Identity) to a [VisualDensity]
  /// applied across the whole app (list, button, tile spacing).
  static VisualDensity _density(String density) => switch (density) {
        'comfortable' => VisualDensity.comfortable,
        'compact' => VisualDensity.compact,
        _ => VisualDensity.standard,
      };

  /// Black or white, whichever reads better on [background] (WCAG luminance).
  static Color _readableOn(Color background) =>
      background.computeLuminance() > 0.5 ? Colors.black : Colors.white;

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

/// Semantic brand colors that have no native slot in [ColorScheme]
/// (success/warning) plus the brand accent, exposed as a theme extension so
/// widgets read them from the theme — light and dark resolve automatically.
@immutable
class BrandColors extends ThemeExtension<BrandColors> {
  const BrandColors({
    required this.success,
    required this.onSuccess,
    required this.warning,
    required this.onWarning,
    required this.accent,
    required this.onAccent,
  });

  final Color success;
  final Color onSuccess;
  final Color warning;
  final Color onWarning;
  final Color accent;
  final Color onAccent;

  @override
  BrandColors copyWith({
    Color? success,
    Color? onSuccess,
    Color? warning,
    Color? onWarning,
    Color? accent,
    Color? onAccent,
  }) =>
      BrandColors(
        success: success ?? this.success,
        onSuccess: onSuccess ?? this.onSuccess,
        warning: warning ?? this.warning,
        onWarning: onWarning ?? this.onWarning,
        accent: accent ?? this.accent,
        onAccent: onAccent ?? this.onAccent,
      );

  @override
  BrandColors lerp(ThemeExtension<BrandColors>? other, double t) {
    if (other is! BrandColors) {
      return this;
    }

    return BrandColors(
      success: Color.lerp(success, other.success, t)!,
      onSuccess: Color.lerp(onSuccess, other.onSuccess, t)!,
      warning: Color.lerp(warning, other.warning, t)!,
      onWarning: Color.lerp(onWarning, other.onWarning, t)!,
      accent: Color.lerp(accent, other.accent, t)!,
      onAccent: Color.lerp(onAccent, other.onAccent, t)!,
    );
  }
}
