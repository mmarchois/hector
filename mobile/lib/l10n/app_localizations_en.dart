// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appTitle => 'Hector AI';

  @override
  String get appTagline => 'The electrician\'s assistant on the job site';

  @override
  String get networkError =>
      'Can\'t reach the server. Check your network and try again.';

  @override
  String get unexpectedError => 'Something went wrong. Try again in a moment.';

  @override
  String get cancel => 'Cancel';

  @override
  String get retry => 'Try again';

  @override
  String get welcomeIntro =>
      'Ask Hector your questions without creating an account. You can create one later to keep your conversations.';

  @override
  String get welcomeAcceptTerms => 'I have read and accept the terms of use';

  @override
  String get welcomeReadTerms => 'Read the terms of use';

  @override
  String get welcomeStart => 'Start without an account';

  @override
  String get welcomeHaveAccount => 'I already have an account';

  @override
  String get welcomeTermsUnavailable =>
      'Can\'t load the terms of use. Check your network: a new attempt starts automatically.';

  @override
  String get termsOfUseTitle => 'Terms of use';

  @override
  String get termsOfUseOutdated =>
      'The terms of use have changed: read them again and accept the new version.';

  @override
  String get tooManyGuestAccounts =>
      'Too many accounts opened from this connection. Try again later or log in.';

  @override
  String get loginTitle => 'Log in';

  @override
  String get loginEmailLabel => 'Email';

  @override
  String get loginPasswordLabel => 'Password';

  @override
  String get loginSubmit => 'Log in';

  @override
  String get loginNoAccount => 'No account yet?';

  @override
  String get loginRegisterLink => 'Create an account';

  @override
  String get loginEmailRequired => 'Enter your email.';

  @override
  String get loginEmailInvalid => 'This email is not valid.';

  @override
  String get loginPasswordRequired => 'Enter your password.';

  @override
  String get loginInvalidCredentials => 'Incorrect email or password.';

  @override
  String get registerTitle => 'Create an account';

  @override
  String get registerSubtitle =>
      'A few details and Hector is with you on your job sites.';

  @override
  String get registerPasswordLabel => 'Password (8 characters minimum)';

  @override
  String get registerPasswordTooShort =>
      'The password must be at least 8 characters long.';

  @override
  String get registerSubmit => 'Create my account';

  @override
  String get registerEmailTaken => 'An account already exists with this email.';

  @override
  String get registerGuestSubtitle =>
      'You keep everything you started without an account.';

  @override
  String get registerHaveAccount => 'Already have an account?';

  @override
  String get registerLoginLink => 'Log in';

  @override
  String get accountTypePro => 'Professional';

  @override
  String get accountTypeIndividual => 'Individual';

  @override
  String get accountCompanyNameLabel => 'Company name';

  @override
  String get accountCompanyNameRequired => 'Enter your company name.';

  @override
  String get accountSiretLabel => 'SIRET (14 digits)';

  @override
  String get accountSiretRequired => 'Enter your SIRET number.';

  @override
  String get accountSiretInvalid => 'The SIRET number must have 14 digits.';

  @override
  String get accountVatLabel => 'VAT number (FR + 11 digits)';

  @override
  String get accountVatRequired => 'Enter your VAT number.';

  @override
  String get accountVatInvalid => 'Expected format: FR followed by 11 digits.';

  @override
  String get accountFullNameLabel => 'Full name';

  @override
  String get accountFullNameRequired => 'Enter your name.';

  @override
  String homeGreeting(String name) {
    return 'Hello $name';
  }

  @override
  String get homeGreetingAnonymous => 'Hello';

  @override
  String get homeAccountReady => 'Your account is ready.';

  @override
  String get homeAccountIndividual => 'Individual account';

  @override
  String get homeProfileTooltip => 'My account';

  @override
  String get homeGuestIntro => 'You are using Hector without an account.';

  @override
  String get homeCreateAccount => 'Create an account';

  @override
  String get homeCreateAccountHint =>
      'Keep your conversations and find them on another device.';

  @override
  String get profileTitle => 'My account';

  @override
  String get profileSave => 'Save';

  @override
  String get profileSaved => 'Profile updated.';

  @override
  String get profilePasswordSection => 'Change password';

  @override
  String get profileCurrentPasswordLabel => 'Current password';

  @override
  String get profileCurrentPasswordRequired => 'Enter your current password.';

  @override
  String get profileNewPasswordLabel => 'New password';

  @override
  String get profileSignOut => 'Log out';

  @override
  String get profileDeleteAccount => 'Delete my account';

  @override
  String get profileDeleteConfirmTitle => 'Delete your account?';

  @override
  String get profileDeleteConfirmBody =>
      'Your account and your data will be permanently deleted.';

  @override
  String get profileDeleteConfirmAction => 'Delete';

  @override
  String get profileGuestIntro =>
      'You are using Hector without an account. Create one to keep your conversations and find them on another device.';

  @override
  String get profileGuestDelete => 'Delete my data';

  @override
  String get profileGuestDeleteConfirmBody =>
      'The data from your trial without an account will be permanently deleted.';

  @override
  String get homeNewConversation => 'New conversation';

  @override
  String get homeConversationsTitle => 'Your conversations';

  @override
  String get homeNoConversation =>
      'No conversation yet. Ask Hector your first question.';

  @override
  String get homeConversationsError => 'Can\'t load your conversations.';

  @override
  String get conversationUntitled => 'New conversation';

  @override
  String get chatAiNotice =>
      'Hector is an AI assistant: its answers may be wrong. Check important information.';

  @override
  String get chatComposerHint => 'Your question';

  @override
  String get chatSendTooltip => 'Send';

  @override
  String get chatThinking => 'Hector is thinking…';

  @override
  String get chatLoadOlder => 'Show earlier messages';

  @override
  String get chatLoadError => 'Can\'t load the conversation.';

  @override
  String get chatEmpty => 'Describe your question or your fault.';

  @override
  String get chatEmptyHint =>
      'Hector answers, and asks you questions if needed.';

  @override
  String get chatConnectionLost =>
      'Connection lost while waiting. Hector may already have answered: reload the conversation.';

  @override
  String get chatReload => 'Reload';

  @override
  String get chatRetry => 'Try again';

  @override
  String get chatDismiss => 'Discard';

  @override
  String get chatNotSent => 'Message not sent.';

  @override
  String get chatSignupTitle => 'Trial limit reached';

  @override
  String get homeAccountTitle => 'Your account';

  @override
  String get statementSourcedFact => 'Sourced fact';

  @override
  String get statementDeclaredFact => 'You told me';

  @override
  String get statementCalculationResult => 'Calculation result';

  @override
  String get statementGeneralExplanation => 'Explanation · unsourced';

  @override
  String get statementHypothesis => 'Hypothesis';

  @override
  String get statementRecommendation => 'Recommendation';

  @override
  String get statementMissingInformation => 'Missing information';

  @override
  String get statementReportedOpinion => 'Reported opinion';

  @override
  String get blockQuestion => 'Question';

  @override
  String get blockWarning => 'Warning';

  @override
  String get chatAssistantSubtitle => 'AI assistant';

  @override
  String get homeAskHint => 'Ask Hector your question';

  @override
  String get homeSuggestion1 => 'My RCD keeps tripping';

  @override
  String get homeSuggestion2 => 'A socket has stopped working';

  @override
  String get homeSuggestion3 => 'Which cable size should I choose?';

  @override
  String get homeSuggestion4 => 'My circuit breaker trips';

  @override
  String get welcomeFeature1Title => 'Guided troubleshooting';

  @override
  String get welcomeFeature1Body =>
      'Describe the fault: Hector asks you the right questions.';

  @override
  String get welcomeFeature2Title => 'Facts, hypotheses, advice';

  @override
  String get welcomeFeature2Body =>
      'Every answer says what is established and what is assumed.';

  @override
  String get welcomeFeature3Title => 'Safety first';

  @override
  String get welcomeFeature3Body =>
      'Hector does not replace an electrician, and tells you when you need one.';

  @override
  String get chatSourcesTitle => 'Sources';

  @override
  String sourceVersion(String publisher, String version) {
    return '$publisher · version $version';
  }

  @override
  String sourcePublishedOn(String date) {
    return 'Published on $date';
  }

  @override
  String sourceEffectiveFrom(String date) {
    return 'In force from $date';
  }

  @override
  String sourceEffectiveUntil(String date) {
    return 'In force until $date';
  }

  @override
  String sourcePage(int page) {
    return 'Page $page';
  }

  @override
  String sourceArticle(String article) {
    return 'Article $article';
  }

  @override
  String get sourceNoExcerpt =>
      'The excerpt from this source cannot be displayed. Refer to the document.';

  @override
  String get sourceWithdrawn => 'Version withdrawn since';

  @override
  String get calculationInputs => 'Given values';

  @override
  String get calculationSteps => 'Steps';

  @override
  String get calculationAssumptions => 'Assumptions';

  @override
  String calculationExact(String value) {
    return 'Exact value: $value';
  }

  @override
  String calculationSource(String source) {
    return 'Source: $source';
  }

  @override
  String calculationVersion(String title, String version) {
    return '$title · version $version';
  }

  @override
  String get calculationRefusedTitle => 'Calculation not carried out';
}
