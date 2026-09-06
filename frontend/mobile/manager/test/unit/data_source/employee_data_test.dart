import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:mocktail/mocktail.dart';
import 'package:permedjat_central/core/class/crud.dart';
import 'package:permedjat_central/core/class/status_request.dart';
import 'package:permedjat_central/core/constant/id/app_links.dart';
import 'package:permedjat_central/data/data_source/remote/employee_data/employee_data.dart';
import '../../helpers/test_helpers.dart';

class MockCRUD extends Mock implements CRUD {}

void main() {
  late MockCRUD mockCrud;
  late EmployeeData employeeData;

  setUp(() {
    setupTestBinding();
    setupGetX();
    mockCrud = MockCRUD();
    Get.put<CRUD>(mockCrud);
    employeeData = EmployeeData();
  });

  tearDown(() => teardownGetX());

  group('EmployeeData', () {
    test('getEmployees ينادي getData مع endpoint الصحيح', () async {
      when(() => mockCrud.getData(any(), queryParameters: any(named: 'queryParameters')))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await employeeData.getEmployees();

      verify(() => mockCrud.getData(
            AppLinks.employees,
            queryParameters: <String, dynamic>{},
          )).called(1);
    });

    test('getEmployees مع branchId و search', () async {
      when(() => mockCrud.getData(any(), queryParameters: any(named: 'queryParameters')))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await employeeData.getEmployees(branchId: 1, search: 'أحمد');

      verify(() => mockCrud.getData(
            AppLinks.employees,
            queryParameters: <String, dynamic>{'branch_id': 1, 'search': 'أحمد'},
          )).called(1);
    });

    test('getEmployee ينادي getData مع id', () async {
      when(() => mockCrud.getData(any()))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await employeeData.getEmployee(5);

      verify(() => mockCrud.getData(AppLinks.employeeDetail(5))).called(1);
    });

    test('createEmployee ينادي postData', () async {
      when(() => mockCrud.postData(any(), any()))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await employeeData.createEmployee({'name': 'أحمد'});

      verify(() => mockCrud.postData(AppLinks.employeeCreate, {'name': 'أحمد'}))
          .called(1);
    });

    test('updateEmployee ينادي patchData والـ id في المسار لا في الجسم', () async {
      when(() => mockCrud.patchData(any(), any()))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await employeeData.updateEmployee(5, {'name': 'أحمد'});

      verify(() => mockCrud.patchData(
            AppLinks.employeeUpdate(5),
            {'name': 'أحمد'},
          )).called(1);
    });

    test('suspendEmployee ينادي postData مع الحقول المطلوبة', () async {
      when(() => mockCrud.postData(any(), any()))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await employeeData.suspendEmployee(
        5,
        reason: 'تحقيق',
        payMode: 'unpaid',
        startDate: '2026-09-01',
      );

      verify(() => mockCrud.postData(AppLinks.employeeSuspend, {
            'employee_id': 5,
            'reason': 'تحقيق',
            'pay_mode': 'unpaid',
            'start_date': '2026-09-01',
          })).called(1);
    });

    test('endSuspension يرسل employee_id فقط بدون ملاحظة', () async {
      when(() => mockCrud.postData(any(), any()))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await employeeData.endSuspension(5);

      verify(() => mockCrud.postData(
            AppLinks.employeeEndSuspension,
            {'employee_id': 5},
          )).called(1);
    });
  });
}
