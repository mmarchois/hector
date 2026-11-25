import 'package:dio/dio.dart';

import '../../domain/calculation.dart';
import '../api_errors.dart';

/// Calculs : calculateurs proposés, calcul, relecture. Dio authentifié.
class CalculationRepository {
  CalculationRepository(this._dio);

  final Dio _dio;

  /// Calculs proposés, dans l'ordre de présentation
  /// (`GET /api/v1/calculators`).
  Future<List<Calculator>> calculators() async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/api/v1/calculators',
    );

    return (response.data!['items'] as List<dynamic>)
        .map((c) => Calculator.fromJson(c as Map<String, dynamic>))
        .toList();
  }

  /// Fait un calcul (`POST /api/v1/calculations`) : une valeur et son unité
  /// par entrée de [calculator].
  /// Lève [CalculationRefusedException] (422, avec le motif par entrée),
  /// [TooManyCalculationsException] (429) ou DioException (réseau).
  Future<Calculation> run(
    String calculator,
    Map<String, QuantityInput> inputs,
  ) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        '/api/v1/calculations',
        data: {
          'calculator': calculator,
          'inputs': {
            for (final entry in inputs.entries) entry.key: entry.value.toJson(),
          },
        },
      );

      return Calculation.fromJson(response.data!);
    } on DioException catch (e) {
      if (e.response?.statusCode == 429) {
        final data = e.response?.data;
        throw TooManyCalculationsException(
          data is Map<String, dynamic> ? data['message'] as String? ?? '' : '',
        );
      }
      final refusal = ApiValidationException.fromDio(e);
      if (refusal != null) {
        throw CalculationRefusedException.fromFieldErrors(refusal.fieldErrors);
      }
      rethrow;
    }
  }

  /// Relit un calcul, tel qu'il a été fait
  /// (`GET /api/v1/calculations/{uuid}`).
  /// Lève [CalculationNotFoundException] (404).
  Future<Calculation> calculation(String uuid) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(
        '/api/v1/calculations/$uuid',
      );

      return Calculation.fromJson(response.data!);
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) {
        throw CalculationNotFoundException();
      }
      rethrow;
    }
  }
}
