import 'package:dio/dio.dart';

import '../domain/terms_of_use.dart';

/// Lecture des conditions d'utilisation : route publique, Dio « nu ».
class TermsOfUseRepository {
  TermsOfUseRepository(this._dio);

  final Dio _dio;

  /// Lève DioException (réseau, serveur).
  Future<TermsOfUse> fetch() async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/api/v1/terms-of-use',
    );

    return TermsOfUse.fromJson(response.data!);
  }
}
