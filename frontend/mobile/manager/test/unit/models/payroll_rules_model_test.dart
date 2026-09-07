import 'package:flutter_test/flutter_test.dart';
import 'package:permedjat_central/data/model/financial_summary_model.dart';

void main() {
  group('PayrollRules.fromJson', () {
    test('بيانات كاملة', () {
      final rules = PayrollRules.fromJson({
        'late_type': 'proportional',
        'late_unit_minutes': 15,
        'late_deduction_per_unit': 10,
        'late_fixed_amount': 25,
        'absence_multiplier': 2,
        'overtime_multiplier': 1.5,
      });

      expect(rules.lateType, 'proportional');
      expect(rules.lateUnitMinutes, 15);
      expect(rules.lateDeductionPerUnit, 10);
      expect(rules.lateFixedAmount, 25);
      expect(rules.absenceMultiplier, 2);
      expect(rules.overtimeMultiplier, 1.5);
    });

    test('بيانات ناقصة/null', () {
      final rules = PayrollRules.fromJson({});

      expect(rules.lateType, isNull);
      expect(rules.absenceMultiplier, isNull);
      expect(rules.overtimeMultiplier, isNull);
    });
  });

  // The card is for settings a company chose. `overtime_multiplier` stopped
  // being one when `bonus_rules` was dropped — the backend now always sends the
  // calculator's constant — so it must not be what decides the card appears.
  group('PayrollRules.hasAny', () {
    test('مضاعف الإضافي وحده لا يفتح البطاقة', () {
      final rules = PayrollRules.fromJson({'overtime_multiplier': 1.5});

      expect(rules.overtimeMultiplier, 1.5);
      expect(rules.hasAny, isFalse);
    });

    test('قاعدة خصم حقيقية تفتح البطاقة، ومضاعف الإضافي يظهر معها', () {
      final rules = PayrollRules.fromJson({
        'absence_multiplier': 2,
        'overtime_multiplier': 1.5,
      });

      expect(rules.hasAny, isTrue);
      expect(rules.overtimeMultiplier, 1.5);
    });

    test('كل قاعدة خصم على حدة تكفي لفتح البطاقة', () {
      for (final key in const [
        'late_type',
        'absence_multiplier',
        'late_deduction_per_unit',
        'late_fixed_amount',
      ]) {
        final rules = PayrollRules.fromJson({
          key: key == 'late_type' ? 'fixed' : 1,
          'overtime_multiplier': 1.5,
        });

        expect(rules.hasAny, isTrue, reason: '$key يجب أن يفتح البطاقة');
      }
    });

    test('لا شيء مضبوط = البطاقة مخفية', () {
      expect(PayrollRules.fromJson({}).hasAny, isFalse);
    });
  });
}
