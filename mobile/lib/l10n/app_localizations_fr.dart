// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for French (`fr`).
class AppLocalizationsFr extends AppLocalizations {
  AppLocalizationsFr([String locale = 'fr']) : super(locale);

  @override
  String get appTitle => 'Hector AI';

  @override
  String get appTagline => 'L\'assistant des électriciens sur les chantiers';

  @override
  String get networkError =>
      'Connexion au serveur impossible. Vérifiez votre réseau et réessayez.';

  @override
  String get unexpectedError =>
      'Une erreur est survenue. Réessayez dans un instant.';

  @override
  String get cancel => 'Annuler';

  @override
  String get retry => 'Réessayer';

  @override
  String get welcomeIntro =>
      'Posez vos questions à Hector sans créer de compte. Vous pourrez en créer un ensuite pour garder vos conversations.';

  @override
  String get welcomeAcceptTerms =>
      'J\'ai lu et j\'accepte les conditions d\'utilisation';

  @override
  String get welcomeReadTerms => 'Lire les conditions d\'utilisation';

  @override
  String get welcomeStart => 'Commencer sans compte';

  @override
  String get welcomeHaveAccount => 'J\'ai déjà un compte';

  @override
  String get welcomeTermsUnavailable =>
      'Impossible de charger les conditions d\'utilisation. Vérifiez votre réseau : un nouvel essai est lancé automatiquement.';

  @override
  String get termsOfUseTitle => 'Conditions d\'utilisation';

  @override
  String get termsOfUseOutdated =>
      'Les conditions d\'utilisation ont changé : relisez-les et acceptez la nouvelle version.';

  @override
  String get tooManyGuestAccounts =>
      'Trop de comptes ouverts depuis cette connexion. Réessayez plus tard ou connectez-vous.';

  @override
  String get loginTitle => 'Connexion';

  @override
  String get loginEmailLabel => 'Email';

  @override
  String get loginPasswordLabel => 'Mot de passe';

  @override
  String get loginSubmit => 'Se connecter';

  @override
  String get loginNoAccount => 'Pas encore de compte ?';

  @override
  String get loginRegisterLink => 'Créer un compte';

  @override
  String get loginEmailRequired => 'Saisissez votre email.';

  @override
  String get loginEmailInvalid => 'Cet email n\'est pas valide.';

  @override
  String get loginPasswordRequired => 'Saisissez votre mot de passe.';

  @override
  String get loginInvalidCredentials => 'Email ou mot de passe incorrect.';

  @override
  String get registerTitle => 'Créer un compte';

  @override
  String get registerSubtitle =>
      'Quelques informations et Hector vous accompagne sur vos chantiers.';

  @override
  String get registerPasswordLabel => 'Mot de passe (8 caractères minimum)';

  @override
  String get registerPasswordTooShort =>
      'Le mot de passe doit contenir au moins 8 caractères.';

  @override
  String get registerSubmit => 'Créer mon compte';

  @override
  String get registerEmailTaken => 'Un compte existe déjà avec cet email.';

  @override
  String get registerGuestSubtitle =>
      'Vous gardez tout ce que vous avez commencé sans compte.';

  @override
  String get registerHaveAccount => 'Déjà un compte ?';

  @override
  String get registerLoginLink => 'Se connecter';

  @override
  String get accountTypePro => 'Professionnel';

  @override
  String get accountTypeIndividual => 'Particulier';

  @override
  String get accountCompanyNameLabel => 'Nom de l\'entreprise';

  @override
  String get accountCompanyNameRequired =>
      'Saisissez le nom de votre entreprise.';

  @override
  String get accountSiretLabel => 'SIRET (14 chiffres)';

  @override
  String get accountSiretRequired => 'Saisissez votre SIRET.';

  @override
  String get accountSiretInvalid => 'Le SIRET doit contenir 14 chiffres.';

  @override
  String get accountVatLabel => 'N° de TVA (FR + 11 chiffres)';

  @override
  String get accountVatRequired => 'Saisissez votre numéro de TVA.';

  @override
  String get accountVatInvalid => 'Format attendu : FR suivi de 11 chiffres.';

  @override
  String get accountFullNameLabel => 'Nom complet';

  @override
  String get accountFullNameRequired => 'Saisissez votre nom.';

  @override
  String homeGreeting(String name) {
    return 'Bonjour $name';
  }

  @override
  String get homeGreetingAnonymous => 'Bonjour';

  @override
  String get homeAccountReady => 'Votre compte est prêt.';

  @override
  String get homeAccountIndividual => 'Compte particulier';

  @override
  String get homeProfileTooltip => 'Mon compte';

  @override
  String get homeGuestIntro => 'Vous utilisez Hector sans compte.';

  @override
  String get homeCreateAccount => 'Créer un compte';

  @override
  String get homeCreateAccountHint =>
      'Gardez vos conversations et retrouvez-les sur un autre appareil.';

  @override
  String get profileTitle => 'Mon compte';

  @override
  String get profileSave => 'Enregistrer';

  @override
  String get profileSaved => 'Profil mis à jour.';

  @override
  String get profilePasswordSection => 'Changer de mot de passe';

  @override
  String get profileCurrentPasswordLabel => 'Mot de passe actuel';

  @override
  String get profileCurrentPasswordRequired =>
      'Saisissez votre mot de passe actuel.';

  @override
  String get profileNewPasswordLabel => 'Nouveau mot de passe';

  @override
  String get profileSignOut => 'Se déconnecter';

  @override
  String get profileDeleteAccount => 'Supprimer mon compte';

  @override
  String get profileDeleteConfirmTitle => 'Supprimer votre compte ?';

  @override
  String get profileDeleteConfirmBody =>
      'Votre compte et vos données seront supprimés définitivement.';

  @override
  String get profileDeleteConfirmAction => 'Supprimer';

  @override
  String get profileGuestIntro =>
      'Vous utilisez Hector sans compte. Créez-en un pour garder vos conversations et les retrouver sur un autre appareil.';

  @override
  String get profileGuestDelete => 'Supprimer mes données';

  @override
  String get profileGuestDeleteConfirmBody =>
      'Les données de votre essai sans compte seront supprimées définitivement.';
}
