import 'package:flutter/material.dart';

/// Theme for a device nobody holds.
///
/// A kiosk is read from one to three metres away, often by someone who is
/// tired, in a queue, and not wearing their glasses. Every size here is larger
/// than a phone app would use and every target is bigger than a thumb needs —
/// that is the whole design brief, and it is why this is not simply a copy of
/// permedjat_app's theme.
class KioskTheme {
  KioskTheme._();

  /// The Permedjat brand colour, shared across every product — the same values
  /// as `brand` in the other apps' app_colors.dart. Never substitute a blue here.
  ///
  /// The schemes below are written out instead of using ColorScheme.fromSeed:
  /// fromSeed re-derives primary from a tonal palette, so the buttons rendered a
  /// different shade from the icons that use [brand] directly.
  static const Color brand = Color(0xFFB8860B);
  static const Color brandDark = Color(0xFFE0B93C);

  /// Text and icons on a [brand] or [brandDark] fill.
  static const Color onBrand = Color(0xFF1A1A1A);

  /// Pale brand tint, and the text colour that reads on it.
  static const Color brandSubtle = Color(0xFFF4E7CF);
  static const Color onBrandSubtle = Color(0xFF715215);

  /// Brand-coloured text on the light canvas. The gold itself is ~3.1:1 there,
  /// which is fine for the big hero icons but not for button labels.
  static const Color brandText = Color(0xFF715215);

  static const Color success = Color(0xFF15803D);
  static const Color warning = Color(0xFFB45309);
  static const Color danger = Color(0xFFB91C1C);

  /// Warm neutrals, at the luminance of the slate set they replaced.
  static const Color canvas = Color(0xFFFCFAF5);
  static const Color canvasDark = Color(0xFF12110E);

  /// Minimum touch target. Well above the 48dp guideline: a worker with wet or
  /// gloved hands is the normal case at a factory door, not an edge case.
  static const double touchTarget = 72;

  static ThemeData light() {
    const scheme = ColorScheme(
      brightness: Brightness.light,
      primary: brand,
      onPrimary: onBrand,
      primaryContainer: brandSubtle,
      onPrimaryContainer: onBrandSubtle,
      secondary: Color(0xFF0E7C86),
      onSecondary: Color(0xFFFFFFFF),
      error: danger,
      onError: Color(0xFFFFFFFF),
      surface: canvas,
      onSurface: Color(0xFF191713),
      onSurfaceVariant: Color(0xFF494640),
      surfaceContainerHighest: Color(0xFFE6E3DC),
      outline: Color(0xFF7A776F),
      outlineVariant: Color(0xFFC9C6BE),
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: canvas,
      fontFamily: 'IBMPlexSansArabic',
      textTheme: _textTheme(scheme.onSurface),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size.fromHeight(touchTarget),
          textStyle: const TextStyle(fontSize: 24, fontWeight: FontWeight.w600),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: brandText,
          minimumSize: const Size.fromHeight(touchTarget),
          textStyle: const TextStyle(fontSize: 22, fontWeight: FontWeight.w600),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(foregroundColor: brandText),
      ),
    );
  }

  /// Dark is not a user preference here — a kiosk has no settings an employee
  /// can reach. It exists because some branches mount tablets in dim corridors
  /// where a white screen is the brightest object in the room.
  static ThemeData dark() {
    const scheme = ColorScheme(
      brightness: Brightness.dark,
      primary: brandDark,
      onPrimary: onBrand,
      primaryContainer: Color(0xFF3F3622),
      onPrimaryContainer: Color(0xFFF2D27A),
      secondary: Color(0xFF4FC6CC),
      onSecondary: onBrand,
      error: Color(0xFFE06050),
      onError: onBrand,
      surface: canvasDark,
      onSurface: Color(0xFFE9E7E3),
      onSurfaceVariant: Color(0xFFC9C6BE),
      surfaceContainerHighest: Color(0xFF37352F),
      outline: Color(0xFF949189),
      outlineVariant: Color(0xFF4A4741),
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: canvasDark,
      fontFamily: 'IBMPlexSansArabic',
      textTheme: _textTheme(scheme.onSurface),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size.fromHeight(touchTarget),
          textStyle: const TextStyle(fontSize: 24, fontWeight: FontWeight.w600),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
        ),
      ),
    );
  }

  static TextTheme _textTheme(Color onSurface) => TextTheme(
        // Used for the resolved employee's name — the single most important
        // string on the device, and the one a person confirms or rejects.
        displayMedium: TextStyle(
          fontSize: 48,
          fontWeight: FontWeight.w700,
          color: onSurface,
        ),
        headlineMedium: TextStyle(
          fontSize: 32,
          fontWeight: FontWeight.w600,
          color: onSurface,
        ),
        titleLarge: TextStyle(
          fontSize: 26,
          fontWeight: FontWeight.w600,
          color: onSurface,
        ),
        bodyLarge: TextStyle(fontSize: 22, color: onSurface),
        bodyMedium: TextStyle(fontSize: 20, color: onSurface),
      );
}
