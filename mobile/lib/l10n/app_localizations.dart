import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_fr.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[Locale('fr')];

  /// No description provided for @appTitle.
  ///
  /// In fr, this message translates to:
  /// **'Hector AI'**
  String get appTitle;

  /// No description provided for @appTagline.
  ///
  /// In fr, this message translates to:
  /// **'L\'assistant des électriciens sur les chantiers'**
  String get appTagline;

  /// No description provided for @networkError.
  ///
  /// In fr, this message translates to:
  /// **'Connexion au serveur impossible. Vérifiez votre réseau et réessayez.'**
  String get networkError;

  /// No description provided for @unexpectedError.
  ///
  /// In fr, this message translates to:
  /// **'Une erreur est survenue. Réessayez dans un instant.'**
  String get unexpectedError;

  /// No description provided for @cancel.
  ///
  /// In fr, this message translates to:
  /// **'Annuler'**
  String get cancel;

  /// No description provided for @retry.
  ///
  /// In fr, this message translates to:
  /// **'Réessayer'**
  String get retry;

  /// No description provided for @welcomeIntro.
  ///
  /// In fr, this message translates to:
  /// **'Posez vos questions à Hector sans créer de compte. Vous pourrez en créer un ensuite pour garder vos conversations.'**
  String get welcomeIntro;

  /// No description provided for @welcomeAcceptTerms.
  ///
  /// In fr, this message translates to:
  /// **'J\'ai lu et j\'accepte les conditions d\'utilisation'**
  String get welcomeAcceptTerms;

  /// No description provided for @welcomeReadTerms.
  ///
  /// In fr, this message translates to:
  /// **'Lire les conditions d\'utilisation'**
  String get welcomeReadTerms;

  /// No description provided for @welcomeStart.
  ///
  /// In fr, this message translates to:
  /// **'Commencer sans compte'**
  String get welcomeStart;

  /// No description provided for @welcomeHaveAccount.
  ///
  /// In fr, this message translates to:
  /// **'J\'ai déjà un compte'**
  String get welcomeHaveAccount;

  /// No description provided for @welcomeTermsUnavailable.
  ///
  /// In fr, this message translates to:
  /// **'Impossible de charger les conditions d\'utilisation. Vérifiez votre réseau : un nouvel essai est lancé automatiquement.'**
  String get welcomeTermsUnavailable;

  /// No description provided for @termsOfUseTitle.
  ///
  /// In fr, this message translates to:
  /// **'Conditions d\'utilisation'**
  String get termsOfUseTitle;

  /// No description provided for @termsOfUseOutdated.
  ///
  /// In fr, this message translates to:
  /// **'Les conditions d\'utilisation ont changé : relisez-les et acceptez la nouvelle version.'**
  String get termsOfUseOutdated;

  /// No description provided for @tooManyGuestAccounts.
  ///
  /// In fr, this message translates to:
  /// **'Trop de comptes ouverts depuis cette connexion. Réessayez plus tard ou connectez-vous.'**
  String get tooManyGuestAccounts;

  /// No description provided for @loginTitle.
  ///
  /// In fr, this message translates to:
  /// **'Connexion'**
  String get loginTitle;

  /// No description provided for @loginEmailLabel.
  ///
  /// In fr, this message translates to:
  /// **'Email'**
  String get loginEmailLabel;

  /// No description provided for @loginPasswordLabel.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe'**
  String get loginPasswordLabel;

  /// No description provided for @loginSubmit.
  ///
  /// In fr, this message translates to:
  /// **'Se connecter'**
  String get loginSubmit;

  /// No description provided for @loginNoAccount.
  ///
  /// In fr, this message translates to:
  /// **'Pas encore de compte ?'**
  String get loginNoAccount;

  /// No description provided for @loginRegisterLink.
  ///
  /// In fr, this message translates to:
  /// **'Créer un compte'**
  String get loginRegisterLink;

  /// No description provided for @loginEmailRequired.
  ///
  /// In fr, this message translates to:
  /// **'Saisissez votre email.'**
  String get loginEmailRequired;

  /// No description provided for @loginEmailInvalid.
  ///
  /// In fr, this message translates to:
  /// **'Cet email n\'est pas valide.'**
  String get loginEmailInvalid;

  /// No description provided for @loginPasswordRequired.
  ///
  /// In fr, this message translates to:
  /// **'Saisissez votre mot de passe.'**
  String get loginPasswordRequired;

  /// No description provided for @loginInvalidCredentials.
  ///
  /// In fr, this message translates to:
  /// **'Email ou mot de passe incorrect.'**
  String get loginInvalidCredentials;

  /// No description provided for @registerTitle.
  ///
  /// In fr, this message translates to:
  /// **'Créer un compte'**
  String get registerTitle;

  /// No description provided for @registerSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Quelques informations et Hector vous accompagne sur vos chantiers.'**
  String get registerSubtitle;

  /// No description provided for @registerPasswordLabel.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe (8 caractères minimum)'**
  String get registerPasswordLabel;

  /// No description provided for @registerPasswordTooShort.
  ///
  /// In fr, this message translates to:
  /// **'Le mot de passe doit contenir au moins 8 caractères.'**
  String get registerPasswordTooShort;

  /// No description provided for @registerSubmit.
  ///
  /// In fr, this message translates to:
  /// **'Créer mon compte'**
  String get registerSubmit;

  /// No description provided for @registerEmailTaken.
  ///
  /// In fr, this message translates to:
  /// **'Un compte existe déjà avec cet email.'**
  String get registerEmailTaken;

  /// No description provided for @registerGuestSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Vous gardez tout ce que vous avez commencé sans compte.'**
  String get registerGuestSubtitle;

  /// No description provided for @registerHaveAccount.
  ///
  /// In fr, this message translates to:
  /// **'Déjà un compte ?'**
  String get registerHaveAccount;

  /// No description provided for @registerLoginLink.
  ///
  /// In fr, this message translates to:
  /// **'Se connecter'**
  String get registerLoginLink;

  /// No description provided for @accountTypePro.
  ///
  /// In fr, this message translates to:
  /// **'Professionnel'**
  String get accountTypePro;

  /// No description provided for @accountTypeIndividual.
  ///
  /// In fr, this message translates to:
  /// **'Particulier'**
  String get accountTypeIndividual;

  /// No description provided for @accountCompanyNameLabel.
  ///
  /// In fr, this message translates to:
  /// **'Nom de l\'entreprise'**
  String get accountCompanyNameLabel;

  /// No description provided for @accountCompanyNameRequired.
  ///
  /// In fr, this message translates to:
  /// **'Saisissez le nom de votre entreprise.'**
  String get accountCompanyNameRequired;

  /// No description provided for @accountSiretLabel.
  ///
  /// In fr, this message translates to:
  /// **'SIRET (14 chiffres)'**
  String get accountSiretLabel;

  /// No description provided for @accountSiretRequired.
  ///
  /// In fr, this message translates to:
  /// **'Saisissez votre SIRET.'**
  String get accountSiretRequired;

  /// No description provided for @accountSiretInvalid.
  ///
  /// In fr, this message translates to:
  /// **'Le SIRET doit contenir 14 chiffres.'**
  String get accountSiretInvalid;

  /// No description provided for @accountVatLabel.
  ///
  /// In fr, this message translates to:
  /// **'N° de TVA (FR + 11 chiffres)'**
  String get accountVatLabel;

  /// No description provided for @accountVatRequired.
  ///
  /// In fr, this message translates to:
  /// **'Saisissez votre numéro de TVA.'**
  String get accountVatRequired;

  /// No description provided for @accountVatInvalid.
  ///
  /// In fr, this message translates to:
  /// **'Format attendu : FR suivi de 11 chiffres.'**
  String get accountVatInvalid;

  /// No description provided for @accountFullNameLabel.
  ///
  /// In fr, this message translates to:
  /// **'Nom complet'**
  String get accountFullNameLabel;

  /// No description provided for @accountFullNameRequired.
  ///
  /// In fr, this message translates to:
  /// **'Saisissez votre nom.'**
  String get accountFullNameRequired;

  /// No description provided for @homeGreeting.
  ///
  /// In fr, this message translates to:
  /// **'Bonjour {name}'**
  String homeGreeting(String name);

  /// No description provided for @homeGreetingAnonymous.
  ///
  /// In fr, this message translates to:
  /// **'Bonjour'**
  String get homeGreetingAnonymous;

  /// No description provided for @homeAccountReady.
  ///
  /// In fr, this message translates to:
  /// **'Votre compte est prêt.'**
  String get homeAccountReady;

  /// No description provided for @homeAccountIndividual.
  ///
  /// In fr, this message translates to:
  /// **'Compte particulier'**
  String get homeAccountIndividual;

  /// No description provided for @homeProfileTooltip.
  ///
  /// In fr, this message translates to:
  /// **'Mon compte'**
  String get homeProfileTooltip;

  /// No description provided for @homeGuestIntro.
  ///
  /// In fr, this message translates to:
  /// **'Vous utilisez Hector sans compte.'**
  String get homeGuestIntro;

  /// No description provided for @homeCreateAccount.
  ///
  /// In fr, this message translates to:
  /// **'Créer un compte'**
  String get homeCreateAccount;

  /// No description provided for @homeCreateAccountHint.
  ///
  /// In fr, this message translates to:
  /// **'Gardez vos conversations et retrouvez-les sur un autre appareil.'**
  String get homeCreateAccountHint;

  /// No description provided for @profileTitle.
  ///
  /// In fr, this message translates to:
  /// **'Mon compte'**
  String get profileTitle;

  /// No description provided for @profileSave.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrer'**
  String get profileSave;

  /// No description provided for @profileSaved.
  ///
  /// In fr, this message translates to:
  /// **'Profil mis à jour.'**
  String get profileSaved;

  /// No description provided for @profilePasswordSection.
  ///
  /// In fr, this message translates to:
  /// **'Changer de mot de passe'**
  String get profilePasswordSection;

  /// No description provided for @profileCurrentPasswordLabel.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe actuel'**
  String get profileCurrentPasswordLabel;

  /// No description provided for @profileCurrentPasswordRequired.
  ///
  /// In fr, this message translates to:
  /// **'Saisissez votre mot de passe actuel.'**
  String get profileCurrentPasswordRequired;

  /// No description provided for @profileNewPasswordLabel.
  ///
  /// In fr, this message translates to:
  /// **'Nouveau mot de passe'**
  String get profileNewPasswordLabel;

  /// No description provided for @profileSignOut.
  ///
  /// In fr, this message translates to:
  /// **'Se déconnecter'**
  String get profileSignOut;

  /// No description provided for @profileDeleteAccount.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer mon compte'**
  String get profileDeleteAccount;

  /// No description provided for @profileDeleteConfirmTitle.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer votre compte ?'**
  String get profileDeleteConfirmTitle;

  /// No description provided for @profileDeleteConfirmBody.
  ///
  /// In fr, this message translates to:
  /// **'Votre compte et vos données seront supprimés définitivement.'**
  String get profileDeleteConfirmBody;

  /// No description provided for @profileDeleteConfirmAction.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer'**
  String get profileDeleteConfirmAction;

  /// No description provided for @profileGuestIntro.
  ///
  /// In fr, this message translates to:
  /// **'Vous utilisez Hector sans compte. Créez-en un pour garder vos conversations et les retrouver sur un autre appareil.'**
  String get profileGuestIntro;

  /// No description provided for @profileGuestDelete.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer mes données'**
  String get profileGuestDelete;

  /// No description provided for @profileGuestDeleteConfirmBody.
  ///
  /// In fr, this message translates to:
  /// **'Les données de votre essai sans compte seront supprimées définitivement.'**
  String get profileGuestDeleteConfirmBody;
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['fr'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'fr':
      return AppLocalizationsFr();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
