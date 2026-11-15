/// Règles de saisie du compte, alignées sur la validation de l'API
/// (config/validator/Application/User/*.xml côté backend).
abstract final class AccountValidators {
  static const minPasswordLength = 8;

  static final _siretPattern = RegExp(r'^\d{14}$');
  static final _vatPattern = RegExp(r'^FR\d{11}$');
  static final _emailPattern = RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$');

  static bool isSiret(String value) => _siretPattern.hasMatch(value);

  static bool isVatNumber(String value) => _vatPattern.hasMatch(value);

  static bool isEmail(String value) => _emailPattern.hasMatch(value);

  static bool isPasswordLongEnough(String value) {
    return value.length >= minPasswordLength;
  }
}
