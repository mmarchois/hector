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

/// Calcul refusé par l'API (422). Le calculateur dit pourquoi : il ne suppose
/// jamais une valeur absente et n'extrapole pas hors de son domaine.
class CalculationRefusedException implements Exception {
  CalculationRefusedException({
    this.inputErrors = const {},
    this.calculatorError,
    this.resultError,
  });

  /// Range les violations de l'API : `inputs.<clé>`, `calculator` ou `inputs`.
  factory CalculationRefusedException.fromFieldErrors(
    Map<String, String> fieldErrors,
  ) {
    const prefix = 'inputs.';

    return CalculationRefusedException(
      inputErrors: {
        for (final error in fieldErrors.entries)
          if (error.key.startsWith(prefix))
            error.key.substring(prefix.length): error.value,
      },
      calculatorError: fieldErrors['calculator'],
      resultError: fieldErrors['inputs'],
    );
  }

  /// Clé d'une entrée → motif (valeur manquante, sans unité, hors bornes…).
  final Map<String, String> inputErrors;

  /// Calculateur inconnu ou absent.
  final String? calculatorError;

  /// Résultat hors du domaine couvert, ou entrées absentes de la requête.
  final String? resultError;

  /// Tous les motifs, à afficher tels quels.
  List<String> get reasons => [
    ?calculatorError,
    ...inputErrors.values,
    ?resultError,
  ];
}

/// Calcul inconnu, ou d'un autre utilisateur (404).
class CalculationNotFoundException implements Exception {}

/// Trop de calculs lancés à la suite (429), avec le message de l'API.
class TooManyCalculationsException implements Exception {
  TooManyCalculationsException(this.message);

  final String message;
}
