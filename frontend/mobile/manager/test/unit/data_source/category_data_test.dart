import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';
import 'package:mocktail/mocktail.dart';
import 'package:permedjat_central/core/class/crud.dart';
import 'package:permedjat_central/core/class/status_request.dart';
import 'package:permedjat_central/core/constant/id/app_links.dart';
import 'package:permedjat_central/data/data_source/remote/category_data/category_data.dart';
import '../../helpers/test_helpers.dart';

class MockCRUD extends Mock implements CRUD {}

void main() {
  late MockCRUD mockCrud;
  late CategoryData categoryData;

  setUp(() {
    setupTestBinding();
    setupGetX();
    mockCrud = MockCRUD();
    Get.put<CRUD>(mockCrud);
    categoryData = CategoryData();
  });

  tearDown(() => teardownGetX());

  group('CategoryData', () {
    test('getCategories ينادي getData', () async {
      when(() => mockCrud.getData(any()))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await categoryData.getCategories();

      verify(() => mockCrud.getData(AppLinks.categories)).called(1);
    });

    test('createCategory ينادي postData', () async {
      when(() => mockCrud.postData(any(), any()))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await categoryData.createCategory({'name': 'تقنية'});

      verify(() => mockCrud.postData(AppLinks.categoryCreate, {'name': 'تقنية'}))
          .called(1);
    });

    test('updateCategory ينادي patchData والـ id في المسار لا في الجسم', () async {
      when(() => mockCrud.patchData(any(), any()))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await categoryData.updateCategory({'id': 7, 'name': 'محدث'});

      verify(() => mockCrud.patchData(AppLinks.categoryUpdate(7), {'name': 'محدث'}))
          .called(1);
    });

    test('updateCategory لا يعدّل الخريطة الممرّرة إليه', () async {
      when(() => mockCrud.patchData(any(), any()))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      final payload = {'id': 7, 'name': 'محدث'};
      await categoryData.updateCategory(payload);

      expect(payload, {'id': 7, 'name': 'محدث'});
    });

    test('deleteCategory ينادي deleteData مع id في المسار', () async {
      when(() => mockCrud.deleteData(any()))
          .thenAnswer((_) async => {'status': StatusRequest.success, 'data': null});

      await categoryData.deleteCategory(5);

      verify(() => mockCrud.deleteData(AppLinks.categoryDelete(5))).called(1);
    });
  });
}
