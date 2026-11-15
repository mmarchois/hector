import 'package:dio/dio.dart';

import '../../domain/user_profile.dart';
import '../api_errors.dart';

/// Mise à jour et suppression du compte (PUT/DELETE /api/v1/auth/me).
/// Utilise le Dio authentifié (Bearer + refresh automatique).
class ProfileRepository {
  ProfileRepository(this._dio);

  final Dio _dio;

  /// Lève [EmailAlreadyExistsException] (409), [ApiValidationException] (422,
  /// y compris « mot de passe actuel incorrect ») ou DioException.
  Future<UserProfile> update({
    required String fullName,
    required String email,
    required bool professional,
    String? companyName,
    String? siret,
    String? vatNumber,
    String? currentPassword,
    String? newPassword,
  }) async {
    try {
      final response = await _dio.put<Map<String, dynamic>>(
        '/api/v1/auth/me',
        data: {
          'fullName': fullName,
          'email': email,
          'professional': professional,
          'companyName': ?companyName,
          'siret': ?siret,
          'vatNumber': ?vatNumber,
          'currentPassword': ?currentPassword,
          'newPassword': ?newPassword,
        },
      );

      return UserProfile.fromJson(response.data!);
    } on DioException catch (e) {
      if (e.response?.statusCode == 409) {
        throw EmailAlreadyExistsException();
      }
      throw ApiValidationException.fromDio(e) ?? e;
    }
  }

  /// Suppression RGPD : le backend supprime le compte et révoque les tokens.
  Future<void> delete() async {
    await _dio.delete<void>('/api/v1/auth/me');
  }
}
