import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:mocktail/mocktail.dart';
import 'package:permedjat_central/core/class/crud.dart';
import 'package:permedjat_central/core/class/status_request.dart';
import 'package:permedjat_central/core/constant/id/app_links.dart';
import 'package:permedjat_central/data/data_source/remote/loan_data/loan_data.dart';
import '../../helpers/test_helpers.dart';

class MockCRUD extends Mock implements CRUD {}

void main() {
  late MockCRUD mockCrud;
  late LoanData loanData;

  setUp(() {
    setupTestBinding();
    setupGetX();
    mockCrud = MockCRUD();
    Get.put<CRUD>(mockCrud);
    loanData = LoanData();
  });

  tearDown(() => teardownGetX());

  group('LoanData', () {
    test('getLoans ينادي getData', () async {
      when(() => mockCrud.getData(any(), queryParameters: any(named: 'queryParameters')))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await loanData.getLoans();

      verify(() => mockCrud.getData(
            AppLinks.loans,
            queryParameters: <String, dynamic>{},
          )).called(1);
    });

    test('getLoans مع status filter', () async {
      when(() => mockCrud.getData(any(), queryParameters: any(named: 'queryParameters')))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await loanData.getLoans(status: 'active');

      verify(() => mockCrud.getData(
            AppLinks.loans,
            queryParameters: <String, dynamic>{'status': 'active'},
          )).called(1);
    });

    test('createLoan ينادي postData', () async {
      when(() => mockCrud.postData(any(), any()))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await loanData.createLoan({'employee_id': 5, 'total_amount': 5000});

      verify(() => mockCrud.postData(
            AppLinks.loanCreate,
            {'employee_id': 5, 'total_amount': 5000},
          )).called(1);
    });

    test('approveLoan ينادي postData', () async {
      when(() => mockCrud.postData(any(), any()))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await loanData.approveLoan(5);

      verify(() => mockCrud.postData(AppLinks.loanApprove, {'id': 5})).called(1);
    });

    test('cancelLoan ينادي postData', () async {
      when(() => mockCrud.postData(any(), any()))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await loanData.cancelLoan(5);

      verify(() => mockCrud.postData(AppLinks.loanCancel, {'id': 5})).called(1);
    });
  });
}
