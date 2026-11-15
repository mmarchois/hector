import 'package:dio/dio.dart';

import '../../domain/user_profile.dart';
import '../api_errors.dart';
import 'token_storage.dart';

/// Appels d'authentification. Utilise un Dio « nu » (sans AuthInterceptor) :
/// ces routes sont publiques et ne doivent jamais déclencher de refresh.
class AuthRepository {
  AuthRepository(this._dio);

  final Dio _dio;

  /// Lève [InvalidCredentialsException] (401) ou DioException (réseau).
  Future<AuthTokens> login({
    required String email,
    required String password,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(
        '/api/v1/auth/login',
        data: {'email': email, 'password': password},
      );

      return _tokensFrom(response.data!);
    } on DioException catch (e) {
      if (e.response?.statusCode == 401) {
        throw InvalidCredentialsException();
      }
      rethrow;
    }
  }

  /// Rotation : le refresh token est invalidé et remplacé à chaque appel.
  Future<AuthTokens> refresh(String refreshToken) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/api/v1/auth/token/refresh',
      data: {'refresh_token': refreshToken},
    );

    return _tokensFrom(response.data!);
  }

  /// Déconnexion côté serveur : révoque le refresh token.
  Future<void> invalidate(String refreshToken) async {
    await _dio.post<void>(
      '/api/v1/auth/token/invalidate',
      data: {'refresh_token': refreshToken},
    );
  }

  /// Création de compte. Lève [EmailAlreadyExistsException] (409) ou
  /// [ApiValidationException] (422, erreurs par champ).
  Future<void> register({
    required String fullName,
    required String email,
    required String password,
    required bool professional,
    String? companyName,
    String? siret,
    String? vatNumber,
  }) async {
    try {
      await _dio.post<Map<String, dynamic>>(
        '/api/v1/auth/register',
        data: {
          'fullName': fullName,
          'email': email,
          'password': password,
          'professional': professional,
          'companyName': ?companyName,
          'siret': ?siret,
          'vatNumber': ?vatNumber,
        },
      );
    } on DioException catch (e) {
      if (e.response?.statusCode == 409) {
        throw EmailAlreadyExistsException();
      }
      throw ApiValidationException.fromDio(e) ?? e;
    }
  }

  Future<UserProfile> me(String accessToken) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/api/v1/auth/me',
      options: Options(headers: {'Authorization': 'Bearer $accessToken'}),
    );

    return UserProfile.fromJson(response.data!);
  }

  AuthTokens _tokensFrom(Map<String, dynamic> json) {
    return AuthTokens(
      accessToken: json['token'] as String,
      refreshToken: json['refresh_token'] as String,
    );
  }
}
