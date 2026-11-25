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

/// Les conditions d'utilisation ont changé depuis leur lecture
/// (409, reason `terms_of_use_outdated`) : il faut accepter la nouvelle version.
class TermsOfUseOutdatedException implements Exception {}

/// Trop de comptes invités ouverts depuis cette connexion (429 sur /auth/guest).
class TooManyGuestAccountsException implements Exception {}

/// Motif lisible par l'application dans une erreur de l'API : {code, reason, message}.
String? apiErrorReason(DioException e) {
  final data = e.response?.data;

  return data is Map<String, dynamic> ? data['reason'] as String? : null;
}

/// Conversation inconnue, ou d'un autre utilisateur (404).
class ConversationNotFoundException implements Exception {}

/// Erreur 429 accompagnée d'un message de l'API, à afficher tel quel.
sealed class MessageLimitException implements Exception {
  MessageLimitException(this.message);

  final String message;
}

/// Allocation de l'essai sans compte épuisée : créer un compte débloque la suite.
class SignupRequiredException extends MessageLimitException {
  SignupRequiredException(super.message);
}

/// Allocation gratuite d'un compte épuisée pour les dernières 24 heures.
class FreeAllowanceExhaustedException extends MessageLimitException {
  FreeAllowanceExhaustedException(super.message);
}

/// Trop de messages envoyés à la suite.
class TooManyMessagesException extends MessageLimitException {
  TooManyMessagesException(super.message);
}

/// Budget global de l'API atteint (503 `service_saturated`) : réessayer plus tard.
class ServiceSaturatedException extends MessageLimitException {
  ServiceSaturatedException(super.message);
}
