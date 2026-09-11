import 'package:country_picker/country_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import '../../constant/theme/theme.dart';

/// Huawei AppGallery rule 4.8 (territorial integrity): these regions must not
/// be presented as standalone countries — not in the picker, and not as the
/// flag an existing number is shown under.
const _excludedRegions = {'TW', 'HK', 'MO'};

/// Dial codes shared by several countries, and the one an existing number is
/// shown under. Only the flag depends on this: the number sent back is the
/// same whichever of them is selected.
const _primaryForSharedCode = {
  '1': 'US',
  '7': 'RU',
  '44': 'GB',
  '47': 'NO',
  '61': 'AU',
  '212': 'MA',
  '262': 'RE',
  '358': 'FI',
  '500': 'FK',
  '590': 'GP',
  '599': 'CW',
  '672': 'NF',
};

/// Strips non-digits and the national trunk prefix (leading zeros) from a
/// locally-typed number so it can be joined to a country code as E.164.
/// E.g. Egypt "01023809407" + code "20" → "+201023809407" (not "+2001023809407").
String nationalDigits(String raw) =>
    raw.replaceAll(RegExp(r'\D'), '').replaceFirst(RegExp(r'^0+'), '');

/// Builds an E.164 number from a country dial code and a locally-typed number.
String toE164(String phoneCode, String raw) =>
    '+$phoneCode${nationalDigits(raw)}';

/// A phone number as [PhoneField] edits it: a country, and the digits after
/// its dial code.
class PhoneParts {
  final Country? country;
  final String national;

  const PhoneParts(this.country, this.national);

  /// Splits a stored number back into a country and the rest.
  ///
  /// `employees.phone` holds one string, mostly E.164. Dial codes are
  /// prefix-free, so at most one of them can lead an international number and
  /// the split is never a guess. Whatever follows the code is kept as stored,
  /// so an old "+2001023809407" shows as +20 / 01023809407 rather than being
  /// silently rewritten. A number with no "+" (stored before a country code
  /// was required) or with an unknown code gets no country: the digits are
  /// shown as they are and the admin picks the country if they edit it.
  factory PhoneParts.parse(String? stored) {
    var value = (stored ?? '').trim().replaceAllMapped(
      RegExp('[٠-٩]'),
      (m) => String.fromCharCode(m[0]!.codeUnitAt(0) - 0x0660 + 0x30),
    );
    if (value.startsWith('00')) value = '+${value.substring(2)}';
    final digits = value.replaceAll(RegExp(r'\D'), '');
    if (!value.startsWith('+')) return PhoneParts(null, digits);

    final service = CountryService();
    for (final c in service.getAll()) {
      if (c.phoneCode.isEmpty || !digits.startsWith(c.phoneCode)) continue;
      if (_excludedRegions.contains(c.countryCode)) break;
      final primary = _primaryForSharedCode[c.phoneCode];
      final country =
          primary == null ? c : (service.findByCode(primary) ?? c);
      return PhoneParts(country, digits.substring(c.phoneCode.length));
    }
    return PhoneParts(null, digits);
  }

  /// True while [country] and [national] still hold this exact number — i.e.
  /// the admin has not touched the field.
  bool isSameAs(Country? country, String national) =>
      country?.phoneCode == this.country?.phoneCode &&
      national.trim() == this.national;
}

/// Country selector + phone number, joined as E.164 by the caller via
/// [toE164]. The picker lists every country (bar [_excludedRegions]).
class PhoneField extends StatelessWidget {
  final TextEditingController controller;
  final Country? country;
  final ValueChanged<Country> onCountryChanged;

  /// The number the form opened with. Left as it is, it is accepted without
  /// validation, so a number stored before the country code was required
  /// does not stop the admin from saving the rest of the form.
  final PhoneParts? original;

  const PhoneField({
    super.key,
    required this.controller,
    required this.country,
    required this.onCountryChanged,
    this.original,
  });

  void _pickCountry(BuildContext context) {
    showCountryPicker(
      context: context,
      showPhoneCode: true,
      exclude: _excludedRegions.toList(),
      onSelect: onCountryChanged,
    );
  }

  @override
  Widget build(BuildContext context) {
    final colors = AppColors.of(context);
    final hasCountry = country != null;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(bottom: AppSpacing.s2),
          child: Row(
            children: [
              Text(
                'phone_number'.tr,
                style: TextStyle(
                  fontFamily: 'IBM Plex Sans Arabic',
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                  color: colors.textSecondary,
                ),
              ),
              const SizedBox(width: AppSpacing.s1),
              Text(
                '(${'optional'.tr})',
                style: TextStyle(
                  fontFamily: 'IBM Plex Sans Arabic',
                  fontSize: 11,
                  fontWeight: FontWeight.w400,
                  color: colors.textTertiary,
                ),
              ),
            ],
          ),
        ),
        // Two separate cards: the country selector on the left and the phone
        // field on the right. Top-aligned so a validation error growing under
        // the phone field never stretches the country card. The country card's
        // height comes from its padding (matching the field) — not a fixed
        // height — so the two boxes line up at rest.
        Row(
          textDirection: TextDirection.ltr,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            InkWell(
              onTap: () => _pickCountry(context),
              borderRadius: BorderRadius.circular(AppRadius.md),
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: AppSpacing.s3,
                  vertical: 13,
                ),
                decoration: BoxDecoration(
                  color: colors.surface,
                  borderRadius: BorderRadius.circular(AppRadius.md),
                  border: Border.all(color: colors.borderHairline),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      hasCountry
                          ? '${country!.flagEmoji}  +${country!.phoneCode}'
                          : 'select_country'.tr,
                      style: TextStyle(
                        fontFamily: 'Geist',
                        fontSize: 15,
                        fontWeight: FontWeight.w600,
                        color: hasCountry
                            ? colors.textPrimary
                            : colors.textTertiary,
                      ),
                    ),
                    const SizedBox(width: AppSpacing.s1),
                    Icon(
                      Icons.expand_more,
                      size: 18,
                      color: colors.textTertiary,
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(width: AppSpacing.s2),
            Expanded(
              child: TextFormField(
                controller: controller,
                keyboardType: TextInputType.phone,
                textDirection: TextDirection.ltr,
                inputFormatters: [
                  FilteringTextInputFormatter.digitsOnly,
                  LengthLimitingTextInputFormatter(14),
                ],
                style: TextStyle(
                  fontFamily: 'Geist',
                  fontSize: 16,
                  color: colors.textPrimary,
                ),
                decoration: InputDecoration(hintText: 'phone_number_hint'.tr),
                validator: (v) {
                  final n = (v ?? '').trim();
                  // Phone is optional — blank is valid (sign-in via link/QR).
                  if (n.isEmpty) return null;
                  if (original?.isSameAs(country, n) ?? false) return null;
                  // Once a number is typed, a country code is required to build
                  // a valid E.164 number (8–15 digits total).
                  if (country == null) return 'country_required'.tr;
                  final full = '${country!.phoneCode}${nationalDigits(n)}';
                  if (!RegExp(r'^[1-9]\d{7,14}$').hasMatch(full)) {
                    return 'phone_invalid'.tr;
                  }
                  return null;
                },
              ),
            ),
          ],
        ),
      ],
    );
  }
}
