import 'package:flutter/material.dart';

class AppColorScheme {
  final Color canvas;
  final Color surface;
  final Color sunken;
  final Color borderHairline;
  final Color borderStrong;
  final Color textPrimary;
  final Color textSecondary;
  final Color textTertiary;
  final Color brand;
  final Color brandHover;
  final Color brandSubtle;

  /// Text and icons on a [brand] fill.
  final Color onBrand;

  /// Text and icons on a [brandSubtle] fill.
  final Color onBrandSubtle;

  /// Brand-coloured text, links and icons on the canvas/surfaces. In light
  /// mode the gold itself is ~3.1:1 there, so this is a darker shade; fills,
  /// borders, indicators and switches keep [brand].
  final Color brandText;

  /// The second accent. It was gold while the brand was teal; the two swapped
  /// when gold became the brand, so the name no longer describes the hue.
  final Color accentWarm;

  /// Text and icons on an [accentWarm] fill.
  final Color onAccentWarm;
  final Color error;

  /// Burnt orange, kept clear of the brand gold.
  final Color warning;

  /// Text and icons on a [warning] fill.
  final Color onWarning;
  final Color success;

  const AppColorScheme({
    required this.canvas,
    required this.surface,
    required this.sunken,
    required this.borderHairline,
    required this.borderStrong,
    required this.textPrimary,
    required this.textSecondary,
    required this.textTertiary,
    required this.brand,
    required this.brandHover,
    required this.brandSubtle,
    required this.onBrand,
    required this.onBrandSubtle,
    required this.brandText,
    required this.accentWarm,
    required this.onAccentWarm,
    required this.error,
    required this.warning,
    required this.onWarning,
    required this.success,
  });
}

class AppColors {
  AppColors._();

  static AppColorScheme of(BuildContext context) {
    return Theme.of(context).brightness == Brightness.light ? light : dark;
  }

  static Color brand(BuildContext context) => of(context).brand;
  static Color brandText(BuildContext context) => of(context).brandText;
  static Color textPrimary(BuildContext context) => of(context).textPrimary;
  static Color textSecondary(BuildContext context) => of(context).textSecondary;
  static Color textTertiary(BuildContext context) => of(context).textTertiary;

  // Same values as frontend/web/manager/src/app/globals.css.
  static const light = AppColorScheme(
    canvas: Color(0xFFFDFBF6),
    surface: Color(0xFFF8F6F1),
    sunken: Color(0xFFEFECE6),
    borderHairline: Color(0xFFDDD9D0),
    borderStrong: Color(0xFFBFBBB1),
    textPrimary: Color(0xFF292723),
    textSecondary: Color(0xFF68655E),
    textTertiary: Color(0xFF98958D),
    brand: Color(0xFFB8860B),
    brandHover: Color(0xFFAD7E0E),
    brandSubtle: Color(0xFFF4E7CF),
    onBrand: Color(0xFF1A1A1A),
    onBrandSubtle: Color(0xFF715215),
    brandText: Color(0xFF715215),
    accentWarm: Color(0xFF0E7C86),
    onAccentWarm: Color(0xFFFFFFFF),
    error: Color(0xFFC0392B),
    warning: Color(0xFFA93A0A),
    onWarning: Color(0xFFFFFFFF),
    success: Color(0xFF27AE60),
  );

  static const dark = AppColorScheme(
    canvas: Color(0xFF1E1D1A),
    surface: Color(0xFF272521),
    sunken: Color(0xFF191815),
    borderHairline: Color(0xFF3C3A34),
    borderStrong: Color(0xFF545148),
    textPrimary: Color(0xFFF6F3EC),
    textSecondary: Color(0xFFBDBAB2),
    textTertiary: Color(0xFF8C8981),
    brand: Color(0xFFE0B93C),
    brandHover: Color(0xFFECC756),
    brandSubtle: Color(0xFF3F3622),
    onBrand: Color(0xFF1A1A1A),
    onBrandSubtle: Color(0xFFF2D27A),
    brandText: Color(0xFFE0B93C),
    accentWarm: Color(0xFF4FC6CC),
    onAccentWarm: Color(0xFF1A1A1A),
    error: Color(0xFFE06050),
    warning: Color(0xFFFB923C),
    onWarning: Color(0xFF1A1A1A),
    success: Color(0xFF50C890),
  );
}
