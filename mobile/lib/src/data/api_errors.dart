import 'package:dio/dio.dart';

/// Erreur 422 de l'API : violations de validation par champ.
/// Format backend : {code, message, violations: [{property, message}]}.
class ApiValidationException implements Exception {
  ApiValidationException(this.fieldErrors);

  /// property → premier message de violation.
  final Map<String, String> fieldErrors;

  static ApiValidationException? fromDio(DioException e) {
    if (e.response?.statusCode != 422) {
      return null;
    }
    final data = e.response?.data;
    if (data is! Map<String, dynamic>) {
      return null;
    }
    final violations = data['violations'];
    if (violations is! List) {
      return null;
    }

    final errors = <String, String>{};
    for (final violation in violations) {
      if (violation is Map<String, dynamic>) {
        final property = violation['property'] as String?;
        final message = violation['message'] as String?;
        if (property != null && message != null) {
          errors.putIfAbsent(property, () => message);
        }
      }
    }

    return ApiValidationException(errors);
  }
}

/// Un compte existe déjà avec cet email (409 sur /auth/register ou PUT /auth/me).
class EmailAlreadyExistsException implements Exception {}

/// Identifiants refusés (401 sur /auth/login).
class InvalidCredentialsException implements Exception {}
