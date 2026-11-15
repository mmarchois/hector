import 'package:flutter_test/flutter_test.dart';
import 'package:hector_mobile/src/domain/account_validators.dart';
import 'package:hector_mobile/src/domain/user_profile.dart';
import 'package:hector_mobile/src/router.dart';
import 'package:hector_mobile/src/state/auth_controller.dart';

void main() {
  group('redirection selon la session', () {
    const signedIn = AuthSignedIn(UserProfile.empty);

    test('session en cours de restauration : écran d\'attente', () {
      expect(redirectFor(const AuthUnknown(), '/'), '/demarrage');
      expect(redirectFor(const AuthUnknown(), '/profil'), '/demarrage');
      expect(redirectFor(const AuthUnknown(), '/demarrage'), isNull);
    });

    test('déconnecté : seules connexion et inscription sont accessibles', () {
      expect(redirectFor(const AuthSignedOut(), '/'), '/connexion');
      expect(redirectFor(const AuthSignedOut(), '/profil'), '/connexion');
      expect(redirectFor(const AuthSignedOut(), '/demarrage'), '/connexion');
      expect(redirectFor(const AuthSignedOut(), '/connexion'), isNull);
      expect(redirectFor(const AuthSignedOut(), '/inscription'), isNull);
    });

    test('connecté : les écrans publics ramènent à l\'accueil', () {
      expect(redirectFor(signedIn, '/connexion'), '/');
      expect(redirectFor(signedIn, '/inscription'), '/');
      expect(redirectFor(signedIn, '/demarrage'), '/');
      expect(redirectFor(signedIn, '/'), isNull);
      expect(redirectFor(signedIn, '/profil'), isNull);
    });
  });

  group('règles de saisie du compte', () {
    test('SIRET : exactement 14 chiffres', () {
      expect(AccountValidators.isSiret('12345678901234'), isTrue);
      expect(AccountValidators.isSiret('1234567890123'), isFalse);
      expect(AccountValidators.isSiret('123456789012345'), isFalse);
      expect(AccountValidators.isSiret('1234567890123A'), isFalse);
    });

    test('TVA : FR suivi de 11 chiffres', () {
      expect(AccountValidators.isVatNumber('FR12345678901'), isTrue);
      expect(AccountValidators.isVatNumber('fr12345678901'), isFalse);
      expect(AccountValidators.isVatNumber('DE12345678901'), isFalse);
      expect(AccountValidators.isVatNumber('FR1234567890'), isFalse);
    });

    test('email', () {
      expect(AccountValidators.isEmail('jean@chantier.fr'), isTrue);
      expect(AccountValidators.isEmail('jean@chantier'), isFalse);
      expect(AccountValidators.isEmail('jean chantier.fr'), isFalse);
    });

    test('mot de passe : 8 caractères minimum', () {
      expect(AccountValidators.isPasswordLongEnough('12345678'), isTrue);
      expect(AccountValidators.isPasswordLongEnough('1234567'), isFalse);
    });
  });
}
