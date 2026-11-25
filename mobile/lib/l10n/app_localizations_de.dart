// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for German (`de`).
class AppLocalizationsDe extends AppLocalizations {
  AppLocalizationsDe([String locale = 'de']) : super(locale);

  @override
  String get appTitle => 'Hector AI';

  @override
  String get appTagline => 'Der Assistent für Elektriker auf der Baustelle';

  @override
  String get networkError =>
      'Keine Verbindung zum Server. Prüfen Sie Ihr Netz und versuchen Sie es erneut.';

  @override
  String get unexpectedError =>
      'Es ist ein Fehler aufgetreten. Versuchen Sie es gleich noch einmal.';

  @override
  String get cancel => 'Abbrechen';

  @override
  String get retry => 'Erneut versuchen';

  @override
  String get welcomeIntro =>
      'Stellen Sie Hector Ihre Fragen, ohne ein Konto zu erstellen. Sie können später eines anlegen, um Ihre Unterhaltungen zu behalten.';

  @override
  String get welcomeAcceptTerms =>
      'Ich habe die Nutzungsbedingungen gelesen und akzeptiere sie';

  @override
  String get welcomeReadTerms => 'Nutzungsbedingungen lesen';

  @override
  String get welcomeStart => 'Ohne Konto starten';

  @override
  String get welcomeHaveAccount => 'Ich habe bereits ein Konto';

  @override
  String get welcomeTermsUnavailable =>
      'Die Nutzungsbedingungen können nicht geladen werden. Prüfen Sie Ihr Netz: Ein neuer Versuch startet automatisch.';

  @override
  String get termsOfUseTitle => 'Nutzungsbedingungen';

  @override
  String get termsOfUseOutdated =>
      'Die Nutzungsbedingungen haben sich geändert: Lesen Sie sie erneut und akzeptieren Sie die neue Fassung.';

  @override
  String get tooManyGuestAccounts =>
      'Von dieser Verbindung wurden zu viele Konten eröffnet. Versuchen Sie es später erneut oder melden Sie sich an.';

  @override
  String get loginTitle => 'Anmeldung';

  @override
  String get loginEmailLabel => 'E-Mail';

  @override
  String get loginPasswordLabel => 'Passwort';

  @override
  String get loginSubmit => 'Anmelden';

  @override
  String get loginNoAccount => 'Noch kein Konto?';

  @override
  String get loginRegisterLink => 'Konto erstellen';

  @override
  String get loginEmailRequired => 'Geben Sie Ihre E-Mail-Adresse ein.';

  @override
  String get loginEmailInvalid => 'Diese E-Mail-Adresse ist ungültig.';

  @override
  String get loginPasswordRequired => 'Geben Sie Ihr Passwort ein.';

  @override
  String get loginInvalidCredentials => 'E-Mail oder Passwort ist falsch.';

  @override
  String get registerTitle => 'Konto erstellen';

  @override
  String get registerSubtitle =>
      'Ein paar Angaben, und Hector begleitet Sie auf Ihren Baustellen.';

  @override
  String get registerPasswordLabel => 'Passwort (mindestens 8 Zeichen)';

  @override
  String get registerPasswordTooShort =>
      'Das Passwort muss mindestens 8 Zeichen lang sein.';

  @override
  String get registerSubmit => 'Mein Konto erstellen';

  @override
  String get registerEmailTaken =>
      'Mit dieser E-Mail-Adresse besteht bereits ein Konto.';

  @override
  String get registerGuestSubtitle =>
      'Sie behalten alles, was Sie ohne Konto begonnen haben.';

  @override
  String get registerHaveAccount => 'Bereits ein Konto?';

  @override
  String get registerLoginLink => 'Anmelden';

  @override
  String get accountTypePro => 'Gewerblich';

  @override
  String get accountTypeIndividual => 'Privat';

  @override
  String get accountCompanyNameLabel => 'Name des Unternehmens';

  @override
  String get accountCompanyNameRequired =>
      'Geben Sie den Namen Ihres Unternehmens ein.';

  @override
  String get accountSiretLabel => 'SIRET (14 Ziffern)';

  @override
  String get accountSiretRequired => 'Geben Sie Ihre SIRET-Nummer ein.';

  @override
  String get accountSiretInvalid => 'Die SIRET-Nummer muss 14 Ziffern haben.';

  @override
  String get accountVatLabel => 'USt-IdNr. (FR + 11 Ziffern)';

  @override
  String get accountVatRequired =>
      'Geben Sie Ihre Umsatzsteuer-Identifikationsnummer ein.';

  @override
  String get accountVatInvalid =>
      'Erwartetes Format: FR gefolgt von 11 Ziffern.';

  @override
  String get accountFullNameLabel => 'Vollständiger Name';

  @override
  String get accountFullNameRequired => 'Geben Sie Ihren Namen ein.';

  @override
  String homeGreeting(String name) {
    return 'Hallo $name';
  }

  @override
  String get homeGreetingAnonymous => 'Hallo';

  @override
  String get homeAccountReady => 'Ihr Konto ist bereit.';

  @override
  String get homeAccountIndividual => 'Privatkonto';

  @override
  String get homeProfileTooltip => 'Mein Konto';

  @override
  String get homeGuestIntro => 'Sie nutzen Hector ohne Konto.';

  @override
  String get homeCreateAccount => 'Konto erstellen';

  @override
  String get homeCreateAccountHint =>
      'Behalten Sie Ihre Unterhaltungen und finden Sie sie auf einem anderen Gerät wieder.';

  @override
  String get profileTitle => 'Mein Konto';

  @override
  String get profileSave => 'Speichern';

  @override
  String get profileSaved => 'Profil aktualisiert.';

  @override
  String get profilePasswordSection => 'Passwort ändern';

  @override
  String get profileCurrentPasswordLabel => 'Aktuelles Passwort';

  @override
  String get profileCurrentPasswordRequired =>
      'Geben Sie Ihr aktuelles Passwort ein.';

  @override
  String get profileNewPasswordLabel => 'Neues Passwort';

  @override
  String get profileSignOut => 'Abmelden';

  @override
  String get profileDeleteAccount => 'Mein Konto löschen';

  @override
  String get profileDeleteConfirmTitle => 'Ihr Konto löschen?';

  @override
  String get profileDeleteConfirmBody =>
      'Ihr Konto und Ihre Daten werden endgültig gelöscht.';

  @override
  String get profileDeleteConfirmAction => 'Löschen';

  @override
  String get profileGuestIntro =>
      'Sie nutzen Hector ohne Konto. Erstellen Sie eines, um Ihre Unterhaltungen zu behalten und sie auf einem anderen Gerät wiederzufinden.';

  @override
  String get profileGuestDelete => 'Meine Daten löschen';

  @override
  String get profileGuestDeleteConfirmBody =>
      'Die Daten Ihrer Testnutzung ohne Konto werden endgültig gelöscht.';

  @override
  String get homeNewConversation => 'Neue Unterhaltung';

  @override
  String get homeConversationsTitle => 'Ihre Unterhaltungen';

  @override
  String get homeNoConversation =>
      'Noch keine Unterhaltung. Stellen Sie Hector Ihre erste Frage.';

  @override
  String get homeConversationsError =>
      'Ihre Unterhaltungen können nicht geladen werden.';

  @override
  String get conversationUntitled => 'Neue Unterhaltung';

  @override
  String get chatAiNotice =>
      'Hector ist ein KI-Assistent: Seine Antworten können falsch sein. Prüfen Sie wichtige Angaben.';

  @override
  String get chatComposerHint => 'Ihre Frage';

  @override
  String get chatSendTooltip => 'Senden';

  @override
  String get chatThinking => 'Hector denkt nach…';

  @override
  String get chatLoadOlder => 'Frühere Nachrichten anzeigen';

  @override
  String get chatLoadError => 'Die Unterhaltung kann nicht geladen werden.';

  @override
  String get chatEmpty => 'Beschreiben Sie Ihre Frage oder Ihre Störung.';

  @override
  String get chatEmptyHint =>
      'Hector antwortet und stellt Ihnen bei Bedarf Fragen.';

  @override
  String get chatConnectionLost =>
      'Verbindung während des Wartens verloren. Hector hat vielleicht schon geantwortet: Laden Sie die Unterhaltung neu.';

  @override
  String get chatReload => 'Neu laden';

  @override
  String get chatRetry => 'Erneut versuchen';

  @override
  String get chatDismiss => 'Verwerfen';

  @override
  String get chatNotSent => 'Nachricht nicht gesendet.';

  @override
  String get chatSignupTitle => 'Limit der Testnutzung erreicht';

  @override
  String get homeAccountTitle => 'Ihr Konto';

  @override
  String get statementSourcedFact => 'Belegte Tatsache';

  @override
  String get statementDeclaredFact => 'Ihre Angabe';

  @override
  String get statementCalculationResult => 'Berechnungsergebnis';

  @override
  String get statementGeneralExplanation => 'Erklärung · ohne Quelle';

  @override
  String get statementHypothesis => 'Vermutung';

  @override
  String get statementRecommendation => 'Empfehlung';

  @override
  String get statementMissingInformation => 'Fehlende Angabe';

  @override
  String get statementReportedOpinion => 'Wiedergegebene Meinung';

  @override
  String get blockQuestion => 'Frage';

  @override
  String get blockWarning => 'Achtung';

  @override
  String get chatAssistantSubtitle => 'KI-Assistent';

  @override
  String get homeAskHint => 'Stellen Sie Hector Ihre Frage';

  @override
  String get homeSuggestion1 => 'Mein FI-Schalter löst aus';

  @override
  String get homeSuggestion2 => 'Eine Steckdose funktioniert nicht mehr';

  @override
  String get homeSuggestion3 => 'Welchen Leitungsquerschnitt soll ich wählen?';

  @override
  String get homeSuggestion4 => 'Meine Sicherung löst aus';

  @override
  String get welcomeFeature1Title => 'Geführte Fehlersuche';

  @override
  String get welcomeFeature1Body =>
      'Beschreiben Sie die Störung: Hector stellt Ihnen die richtigen Fragen.';

  @override
  String get welcomeFeature2Title => 'Tatsachen, Vermutungen, Empfehlungen';

  @override
  String get welcomeFeature2Body =>
      'Jede Antwort sagt, was feststeht und was vermutet wird.';

  @override
  String get welcomeFeature3Title => 'Sicherheit zuerst';

  @override
  String get welcomeFeature3Body =>
      'Hector ersetzt keine Elektrofachkraft und sagt Ihnen, wann Sie eine brauchen.';
}
