// Parcours de bout en bout sur un vrai appareil, contre une vraie API :
//   make mobile_e2e   (API locale démarrée, simulateur ou émulateur lancé)
// Le compte créé est supprimé en fin de parcours.
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hector_mobile/main.dart';
import 'package:hector_mobile/src/locale.dart';
import 'package:integration_test/integration_test.dart';

void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  Finder field(String key) => find.byKey(Key(key));

  /// Attend qu'un élément apparaisse (les réponses de l'API arrivent en temps réel).
  Future<void> waitFor(WidgetTester tester, Finder finder) async {
    for (var i = 0; i < 150; i++) {
      await tester.pump(const Duration(milliseconds: 100));
      if (finder.evaluate().isNotEmpty) {
        return;
      }
    }
    fail('Élément attendu introuvable après 15 s : $finder');
  }

  /// Attend qu'un élément disparaisse (fin d'un tour : jusqu'à 4 minutes).
  Future<void> waitForGone(WidgetTester tester, Finder finder) async {
    for (var i = 0; i < 2400; i++) {
      await tester.pump(const Duration(milliseconds: 100));
      if (finder.evaluate().isEmpty) {
        return;
      }
    }
    fail('Élément toujours présent après 4 min : $finder');
  }

  Future<void> tapVisible(WidgetTester tester, Finder finder) async {
    await tester.ensureVisible(finder);
    await tester.pumpAndSettle();
    await tester.tap(finder);
    await tester.pump();
  }

  /// Écran de bienvenue : conditions acceptées, puis connexion à un compte.
  Future<void> acceptTermsAndGoToLogin(WidgetTester tester) async {
    await waitFor(tester, field('acceptTermsOfUse'));
    await tapVisible(tester, field('acceptTermsOfUse'));
    await tapVisible(tester, field('haveAccount'));
    await waitFor(tester, find.text('Se connecter'));
  }

  testWidgets(
    'essai sans compte, inscription, profil, mot de passe, déconnexion, suppression',
    (tester) async {
      final stamp = DateTime.now().millisecondsSinceEpoch;
      final email = 'e2e-$stamp@hector.test';
      final siret = stamp.toString().padLeft(14, '0');

      // Aucune session héritée d'un lancement précédent
      await const FlutterSecureStorage().deleteAll();
      // Le parcours est écrit en français, quelle que soit la langue du simulateur
      await tester.pumpWidget(
        ProviderScope(
          overrides: [appLocaleProvider.overrideWithValue(const Locale('fr'))],
          child: const HectorApp(),
        ),
      );

      // Premier lancement : conditions d'utilisation, puis essai sans compte
      await waitFor(tester, field('acceptTermsOfUse'));
      expect(
        tester.widget<FilledButton>(field('startAsGuest')).onPressed,
        isNull,
      );
      await tapVisible(tester, field('acceptTermsOfUse'));
      await tapVisible(tester, field('startAsGuest'));
      await waitFor(tester, find.text('Vous utilisez Hector sans compte.'));

      // Première conversation, sans compte : la mention IA est affichée, et
      // l'envoi aboutit à une réponse (ou, sans clé d'API, au message d'échec)
      await tapVisible(tester, field('newConversation'));
      await waitFor(tester, field('aiNotice'));
      await tester.enterText(field('composer'), 'Mon différentiel saute');
      await tester.pump();
      await tapVisible(tester, field('send'));
      await waitFor(tester, field('thinking'));
      await waitForGone(tester, field('thinking'));
      expect(find.text('Mon différentiel saute'), findsOneWidget);
      await tapVisible(tester, find.byType(BackButton));
      await waitFor(tester, find.text('Mon différentiel saute'));

      // L'invité crée son compte de professionnel : même compte, même session
      await tapVisible(tester, field('createAccount'));
      await waitFor(tester, find.text('Créer mon compte'));
      await tester.enterText(field('companyName'), 'E2E Élec');
      await tester.enterText(field('siret'), siret);
      await tester.enterText(field('vatNumber'), 'FR12345678901');
      await tester.enterText(field('fullName'), 'Camille Essai');
      await tester.enterText(field('email'), email);
      await tester.enterText(field('password'), 'motdepasse123');
      await tapVisible(tester, find.text('Créer mon compte'));

      // Session ouverte : accueil personnalisé
      await waitFor(tester, find.text('Bonjour Camille'));
      expect(find.text('E2E Élec'), findsOneWidget);
      expect(find.text(email), findsOneWidget);

      // Profil prérempli, puis changement de nom et de mot de passe
      await tapVisible(tester, find.byTooltip('Mon compte'));
      await waitFor(tester, find.text('Enregistrer'));
      expect(find.widgetWithText(TextFormField, email), findsOneWidget);
      await tester.enterText(field('fullName'), 'Camille Volta');
      await tester.enterText(field('currentPassword'), 'motdepasse123');
      await tester.enterText(field('newPassword'), 'nouveau-mot-de-passe');
      await tapVisible(tester, find.text('Enregistrer'));
      await waitFor(tester, find.text('Profil mis à jour.'));

      // Déconnexion puis reconnexion avec le nouveau mot de passe
      await tapVisible(tester, find.text('Se déconnecter'));
      await acceptTermsAndGoToLogin(tester);
      await tester.enterText(field('email'), email);
      await tester.enterText(field('password'), 'motdepasse123');
      await tapVisible(tester, find.text('Se connecter'));
      await waitFor(tester, find.text('Email ou mot de passe incorrect.'));
      await tester.enterText(field('password'), 'nouveau-mot-de-passe');
      await tapVisible(tester, find.text('Se connecter'));
      await waitFor(tester, find.text('Bonjour Camille'));

      // Suppression du compte : retour à l'accueil sans session, compte inutilisable
      await tapVisible(tester, find.byTooltip('Mon compte'));
      await waitFor(tester, find.text('Supprimer mon compte'));
      expect(
        find.widgetWithText(TextFormField, 'Camille Volta'),
        findsOneWidget,
      );
      await tapVisible(tester, find.text('Supprimer mon compte'));
      await waitFor(tester, find.text('Supprimer'));
      await tester.tap(find.text('Supprimer'));
      await acceptTermsAndGoToLogin(tester);
      await tester.enterText(field('email'), email);
      await tester.enterText(field('password'), 'nouveau-mot-de-passe');
      await tapVisible(tester, find.text('Se connecter'));
      await waitFor(tester, find.text('Email ou mot de passe incorrect.'));
    },
  );
}
