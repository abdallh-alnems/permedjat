import 'package:country_picker/country_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:permedjat_central/core/shared/input_fields/phone_input.dart';

/// Mounts a [PhoneField] in a [Form], holding the selected country the way
/// the edit sheet does. Returns the form key and a getter for the country.
Future<(GlobalKey<FormState>, Country? Function())> _pumpField(
  WidgetTester tester, {
  required String? stored,
}) async {
  final formKey = GlobalKey<FormState>();
  final original = PhoneParts.parse(stored);
  final ctrl = TextEditingController(text: original.national);
  Country? country = original.country;
  await tester.pumpWidget(
    GetMaterialApp(
      localizationsDelegates: const [CountryLocalizations.delegate],
      home: Scaffold(
        body: Form(
          key: formKey,
          child: StatefulBuilder(
            builder: (context, setState) => PhoneField(
              controller: ctrl,
              country: country,
              original: original,
              onCountryChanged: (c) => setState(() => country = c),
            ),
          ),
        ),
      ),
    ),
  );
  // GetMaterialApp builds `home` a frame after the first pump.
  await tester.pumpAndSettle();
  return (formKey, () => country);
}

void main() {
  group('PhoneParts.parse', () {
    test('E.164 سليم ⇒ الدولة + باقي الرقم', () {
      final p = PhoneParts.parse('+201023809407');
      expect(p.country?.countryCode, 'EG');
      expect(p.national, '1023809407');
    });

    test('أكواد الخليج بثلاثة أرقام', () {
      expect(PhoneParts.parse('+966501234567').country?.countryCode, 'SA');
      expect(PhoneParts.parse('+966501234567').national, '501234567');
      expect(PhoneParts.parse('+971501234567').country?.countryCode, 'AE');
    });

    test('صفر محلي بعد الكود يُعرض كما هو ولا يُصحَّح', () {
      final p = PhoneParts.parse('+2001023809407');
      expect(p.country?.countryCode, 'EG');
      expect(p.national, '01023809407');
    });

    test('رقم بلا كود دولة ⇒ بلا دولة، الأرقام كما هي', () {
      final p = PhoneParts.parse('01023809407');
      expect(p.country, isNull);
      expect(p.national, '01023809407');
    });

    test('فارغ أو null', () {
      expect(PhoneParts.parse(null).country, isNull);
      expect(PhoneParts.parse(null).national, '');
      expect(PhoneParts.parse('').national, '');
    });

    test('00 بدل + وأرقام عربية', () {
      final p = PhoneParts.parse('٠٠٢٠١٠٢٣٨٠٩٤٠٧');
      expect(p.country?.countryCode, 'EG');
      expect(p.national, '1023809407');
    });

    test('كود مشترك ⇒ الدولة الأساسية', () {
      expect(PhoneParts.parse('+12025550123').country?.countryCode, 'US');
      expect(PhoneParts.parse('+447911123456').country?.countryCode, 'GB');
      expect(PhoneParts.parse('+212612345678').country?.countryCode, 'MA');
    });

    test('المناطق المستبعدة من الـpicker لا تُعرض كدولة', () {
      final p = PhoneParts.parse('+85291234567');
      expect(p.country, isNull);
      expect(p.national, '85291234567');
    });

    test('التقسيم ثم التجميع يعيد نفس الرقم', () {
      for (final stored in ['+201023809407', '+966501234567', '+12025550123']) {
        final p = PhoneParts.parse(stored);
        expect(toE164(p.country!.phoneCode, p.national), stored);
      }
    });

    test('التجميع يصحح الصفر المحلي عند التعديل', () {
      final p = PhoneParts.parse('+2001023809407');
      expect(toE164(p.country!.phoneCode, p.national), '+201023809407');
    });
  });

  group('PhoneParts.isSameAs', () {
    test('بدون لمس ⇒ true', () {
      final p = PhoneParts.parse('+201023809407');
      expect(p.isSameAs(p.country, '1023809407'), isTrue);
    });

    test('تغيير الأرقام أو الدولة ⇒ false', () {
      final p = PhoneParts.parse('+201023809407');
      final sa = PhoneParts.parse('+966501234567').country;
      expect(p.isSameAs(p.country, '1023809408'), isFalse);
      expect(p.isSameAs(sa, '1023809407'), isFalse);
    });

    test('رقم قديم بلا كود: اختيار دولة يُعدّ تعديلًا', () {
      final p = PhoneParts.parse('01023809407');
      final eg = PhoneParts.parse('+201023809407').country;
      expect(p.isSameAs(null, '01023809407'), isTrue);
      expect(p.isSameAs(eg, '01023809407'), isFalse);
    });
  });

  group('PhoneField', () {
    testWidgets('رقم E.164 ⇒ الكود في الزر والباقي في الحقل', (tester) async {
      await _pumpField(tester, stored: '+201023809407');
      expect(find.textContaining('+20'), findsOneWidget);
      expect(find.text('1023809407'), findsOneWidget);
    });

    testWidgets('رقم قديم بلا كود لم يُلمس ⇒ لا يمنع حفظ باقي الحقول',
        (tester) async {
      final (formKey, _) = await _pumpField(tester, stored: '01023809407');
      expect(find.text('select_country'), findsOneWidget);
      expect(formKey.currentState!.validate(), isTrue);
    });

    testWidgets('تعديل رقم قديم بلا اختيار دولة ⇒ country_required',
        (tester) async {
      final (formKey, _) = await _pumpField(tester, stored: '01023809407');
      await tester.enterText(find.byType(TextFormField), '01023809408');
      expect(formKey.currentState!.validate(), isFalse);
      await tester.pump();
      expect(find.text('country_required'), findsOneWidget);
    });

    testWidgets('اختيار دولة من القائمة الكاملة', (tester) async {
      final (formKey, country) = await _pumpField(tester, stored: null);
      await tester.tap(find.text('select_country'));
      await tester.pumpAndSettle();
      // The picker's search box sits above our own phone field.
      await tester.enterText(find.byType(TextField).last, 'Saudi');
      await tester.pumpAndSettle();
      await tester.tap(find.textContaining('Saudi Arabia'));
      await tester.pumpAndSettle();
      expect(country()?.countryCode, 'SA');
      expect(find.textContaining('+966'), findsOneWidget);

      await tester.enterText(find.byType(TextFormField), '0501234567');
      expect(formKey.currentState!.validate(), isTrue);
      expect(toE164(country()!.phoneCode, '0501234567'), '+966501234567');
    });

    testWidgets('المناطق المستبعدة لا تظهر في القائمة', (tester) async {
      // Rows only — the search box itself also holds the typed text.
      Finder row(String name) => find.byWidgetPredicate(
            (w) => w is Text && (w.data ?? '').contains(name),
          );
      await _pumpField(tester, stored: null);
      await tester.tap(find.text('select_country'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField).last, 'Egypt');
      await tester.pumpAndSettle();
      expect(row('Egypt'), findsWidgets);
      await tester.enterText(find.byType(TextField).last, 'Taiwan');
      await tester.pumpAndSettle();
      expect(row('Taiwan'), findsNothing);
    });
  });
}
