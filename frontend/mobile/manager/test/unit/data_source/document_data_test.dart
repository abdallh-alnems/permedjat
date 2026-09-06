import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:mocktail/mocktail.dart';
import 'package:permedjat_central/core/class/crud.dart';
import 'package:permedjat_central/core/class/status_request.dart';
import 'package:permedjat_central/core/constant/id/app_links.dart';
import 'package:permedjat_central/data/data_source/remote/document_data/document_data.dart';
import '../../helpers/test_helpers.dart';

class MockCRUD extends Mock implements CRUD {}

void main() {
  late MockCRUD mockCrud;
  late DocumentData documentData;

  setUp(() {
    setupTestBinding();
    setupGetX();
    mockCrud = MockCRUD();
    Get.put<CRUD>(mockCrud);
    documentData = DocumentData();
  });

  tearDown(() => teardownGetX());

  group('DocumentData', () {
    test('getDocuments ينادي getData مع employeeId', () async {
      when(() => mockCrud.getData(any()))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await documentData.getDocuments(5);

      verify(() => mockCrud.getData(AppLinks.employeeDocuments(5))).called(1);
    });

    test('uploadFile ينادي postFile ويضيف employee_id إلى الحقول', () async {
      final file = File('test.pdf');
      when(() => mockCrud.postFile(any(), file, fields: any(named: 'fields')))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await documentData.uploadFile(5, file, {'document_type': 'national_id'});

      verify(() => mockCrud.postFile(
            AppLinks.employeeDocumentUpload,
            file,
            fields: {'employee_id': '5', 'document_type': 'national_id'},
          )).called(1);
    });

    test('deleteDocument ينادي deleteData مع docId في المسار', () async {
      when(() => mockCrud.deleteData(any()))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await documentData.deleteDocument(5, 10);

      verify(() => mockCrud.deleteData(AppLinks.employeeDeleteDocument(10)))
          .called(1);
    });

    test('updateDocument ينادي patchData مع docId في المسار', () async {
      when(() => mockCrud.patchData(any(), any()))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await documentData.updateDocument(10, {'expiry_date': '2027-01-01'});

      verify(() => mockCrud.patchData(
            AppLinks.employeeUpdateDocument(10),
            {'expiry_date': '2027-01-01'},
          )).called(1);
    });

    test('verifyDocument ينادي postData مع document_id', () async {
      when(() => mockCrud.postData(any(), any()))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await documentData.verifyDocument(10);

      verify(() => mockCrud.postData(
            AppLinks.employeeVerifyDocument,
            {'document_id': 10},
          )).called(1);
    });

    test('rejectDocument ينادي postData مع reason', () async {
      when(() => mockCrud.postData(any(), any()))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await documentData.rejectDocument(10, 'غير واضح');

      verify(() => mockCrud.postData(
            AppLinks.employeeRejectDocument,
            {'document_id': 10, 'reason': 'غير واضح'},
          )).called(1);
    });

    test('getMissingDocuments ينادي getData', () async {
      when(() => mockCrud.getData(any()))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await documentData.getMissingDocuments(5);

      verify(() => mockCrud.getData(AppLinks.employeeMissingDocuments(5)))
          .called(1);
    });
  });
}
