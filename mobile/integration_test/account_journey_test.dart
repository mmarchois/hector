// Parcours de bout en bout sur un vrai appareil, contre une vraie API :
//   make mobile_e2e   (API locale démarrée, simulateur ou émulateur lancé)
// Le compte créé est supprimé en fin de parcours.
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hector_mobile/main.dart';
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

  Future<void> tapVisible(WidgetTester tester, Finder finder) async {
    await tester.ensureVisible(finder);
    await tester.pumpAndSettle();
    await tester.tap(finder);
    await tester.pump();
  }

  testWidgets('inscription, profil, mot de passe, déconnexion, suppression', (
    tester,
  ) async {
    final stamp = DateTime.now().millisecondsSinceEpoch;
    final email = 'e2e-$stamp@hector.test';
    final siret = stamp.toString().padLeft(14, '0');

    // Aucune session héritée d'un lancement précédent
    await const FlutterSecureStorage().deleteAll();
    await tester.pumpWidget(const ProviderScope(child: HectorApp()));
    await waitFor(tester, find.text('Se connecter'));

    // Inscription d'un professionnel
    await tapVisible(tester, find.text('Créer un compte'));
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
    await waitFor(tester, find.text('Se connecter'));
    await tester.enterText(field('email'), email);
    await tester.enterText(field('password'), 'motdepasse123');
    await tapVisible(tester, find.text('Se connecter'));
    await waitFor(tester, find.text('Email ou mot de passe incorrect.'));
    await tester.enterText(field('password'), 'nouveau-mot-de-passe');
    await tapVisible(tester, find.text('Se connecter'));
    await waitFor(tester, find.text('Bonjour Camille'));

    // Suppression du compte : retour à la connexion, compte inutilisable
    await tapVisible(tester, find.byTooltip('Mon compte'));
    await waitFor(tester, find.text('Supprimer mon compte'));
    expect(find.widgetWithText(TextFormField, 'Camille Volta'), findsOneWidget);
    await tapVisible(tester, find.text('Supprimer mon compte'));
    await waitFor(tester, find.text('Supprimer'));
    await tester.tap(find.text('Supprimer'));
    await waitFor(tester, find.text('Se connecter'));
    await tester.enterText(field('email'), email);
    await tester.enterText(field('password'), 'nouveau-mot-de-passe');
    await tapVisible(tester, find.text('Se connecter'));
    await waitFor(tester, find.text('Email ou mot de passe incorrect.'));
  });
}
