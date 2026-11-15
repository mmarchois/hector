import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hector_mobile/src/data/auth/token_storage.dart';
import 'package:hector_mobile/src/presentation/login_screen.dart';
import 'package:hector_mobile/src/presentation/profile_screen.dart';
import 'package:hector_mobile/src/presentation/register_screen.dart';
import 'package:hector_mobile/src/state/auth_controller.dart';

import 'support/fakes.dart';

void main() {
  Finder field(String key) => find.byKey(Key(key));

  /// Fait défiler jusqu'au bouton puis le touche (formulaires plus hauts que l'écran).
  Future<void> tapButton(WidgetTester tester, String label) async {
    final button = find.text(label);
    await tester.ensureVisible(button);
    await tester.pumpAndSettle();
    await tester.tap(button);
    await tester.pumpAndSettle();
  }

  group('connexion', () {
    testWidgets('champs vides : erreurs locales, aucun appel réseau', (
      tester,
    ) async {
      final adapter = ScriptedAdapter((options) async => emptyResponse(500));
      final container = buildContainer(InMemoryTokenStorage(), adapter);
      addTearDown(container.dispose);
      await tester.pumpWidget(testApp(container, const LoginScreen()));

      await tapButton(tester, 'Se connecter');

      expect(find.text('Saisissez votre email.'), findsOneWidget);
      expect(find.text('Saisissez votre mot de passe.'), findsOneWidget);
      expect(adapter.requests, isEmpty);
    });

    testWidgets('identifiants refusés : message explicite', (tester) async {
      final adapter = ScriptedAdapter(
        (options) async =>
            jsonResponse(401, {'code': 401, 'message': 'Invalid credentials.'}),
      );
      final container = buildContainer(InMemoryTokenStorage(), adapter);
      addTearDown(container.dispose);
      await tester.pumpWidget(testApp(container, const LoginScreen()));

      await tester.enterText(field('email'), 'jean@chantier.fr');
      await tester.enterText(field('password'), 'mauvais');
      await tapButton(tester, 'Se connecter');

      expect(find.text('Email ou mot de passe incorrect.'), findsOneWidget);
      expect(container.read(authControllerProvider), isA<AuthUnknown>());
    });

    testWidgets('serveur injoignable : message réseau', (tester) async {
      final adapter = ScriptedAdapter(
        (options) async => throw offline(options),
      );
      final container = buildContainer(InMemoryTokenStorage(), adapter);
      addTearDown(container.dispose);
      await tester.pumpWidget(testApp(container, const LoginScreen()));

      await tester.enterText(field('email'), 'jean@chantier.fr');
      await tester.enterText(field('password'), 'motdepasse123');
      await tapButton(tester, 'Se connecter');

      expect(
        find.textContaining('Connexion au serveur impossible'),
        findsOneWidget,
      );
    });

    testWidgets('identifiants valides : session ouverte', (tester) async {
      final adapter = ScriptedAdapter((options) async {
        return options.path.endsWith('/login')
            ? jsonResponse(200, {
                'token': 'access-1',
                'refresh_token': 'refresh-1',
              })
            : jsonResponse(200, profileJson);
      });
      final container = buildContainer(InMemoryTokenStorage(), adapter);
      addTearDown(container.dispose);
      await tester.pumpWidget(testApp(container, const LoginScreen()));

      await tester.enterText(field('email'), ' jean@chantier.fr ');
      await tester.enterText(field('password'), 'motdepasse123');
      await tapButton(tester, 'Se connecter');

      expect(container.read(authControllerProvider), isA<AuthSignedIn>());
      // L'email est envoyé sans les espaces saisis par erreur
      expect(adapter.requests.first.data['email'], 'jean@chantier.fr');
    });
  });

  group('inscription', () {
    testWidgets('un particulier ne voit pas les champs entreprise', (
      tester,
    ) async {
      final adapter = ScriptedAdapter((options) async => emptyResponse(500));
      final container = buildContainer(InMemoryTokenStorage(), adapter);
      addTearDown(container.dispose);
      await tester.pumpWidget(testApp(container, const RegisterScreen()));

      // Professionnel par défaut
      expect(field('companyName'), findsOneWidget);
      expect(field('siret'), findsOneWidget);
      expect(field('vatNumber'), findsOneWidget);

      await tester.tap(find.text('Particulier'));
      await tester.pumpAndSettle();

      expect(field('companyName'), findsNothing);
      expect(field('siret'), findsNothing);
      expect(field('vatNumber'), findsNothing);
    });

    testWidgets('saisie invalide : erreurs par champ, aucun appel réseau', (
      tester,
    ) async {
      final adapter = ScriptedAdapter((options) async => emptyResponse(500));
      final container = buildContainer(InMemoryTokenStorage(), adapter);
      addTearDown(container.dispose);
      await tester.pumpWidget(testApp(container, const RegisterScreen()));

      await tester.enterText(field('siret'), '123');
      await tester.enterText(field('vatNumber'), 'DE123');
      await tester.enterText(field('email'), 'pas-un-email');
      await tester.enterText(field('password'), 'court');
      await tapButton(tester, 'Créer mon compte');

      expect(
        find.text('Saisissez le nom de votre entreprise.'),
        findsOneWidget,
      );
      expect(find.text('Le SIRET doit contenir 14 chiffres.'), findsOneWidget);
      expect(
        find.text('Format attendu : FR suivi de 11 chiffres.'),
        findsOneWidget,
      );
      expect(find.text('Saisissez votre nom.'), findsOneWidget);
      expect(find.text('Cet email n\'est pas valide.'), findsOneWidget);
      expect(
        find.text('Le mot de passe doit contenir au moins 8 caractères.'),
        findsOneWidget,
      );
      expect(adapter.requests, isEmpty);
    });

    testWidgets('un pro crée son compte : données envoyées, session ouverte', (
      tester,
    ) async {
      final adapter = ScriptedAdapter((options) async {
        if (options.path.endsWith('/register')) {
          return jsonResponse(201, profileJson);
        }

        return options.path.endsWith('/login')
            ? jsonResponse(200, {
                'token': 'access-1',
                'refresh_token': 'refresh-1',
              })
            : jsonResponse(200, profileJson);
      });
      final container = buildContainer(InMemoryTokenStorage(), adapter);
      addTearDown(container.dispose);
      await tester.pumpWidget(testApp(container, const RegisterScreen()));

      await tester.enterText(field('companyName'), 'Élec Chantier');
      await tester.enterText(field('siret'), '12345678901234');
      await tester.enterText(field('vatNumber'), 'fr12345678901');
      await tester.enterText(field('fullName'), 'Jean Dupont');
      await tester.enterText(field('email'), 'jean@chantier.fr');
      await tester.enterText(field('password'), 'motdepasse123');
      await tapButton(tester, 'Créer mon compte');

      expect(adapter.requests.first.data, {
        'fullName': 'Jean Dupont',
        'email': 'jean@chantier.fr',
        'password': 'motdepasse123',
        'professional': true,
        'companyName': 'Élec Chantier',
        'siret': '12345678901234',
        // Saisie en minuscules normalisée
        'vatNumber': 'FR12345678901',
      });
      expect(container.read(authControllerProvider), isA<AuthSignedIn>());
    });

    testWidgets('email déjà utilisé : message explicite', (tester) async {
      final adapter = ScriptedAdapter(
        (options) async =>
            jsonResponse(409, {'code': 409, 'message': 'Conflit'}),
      );
      final container = buildContainer(InMemoryTokenStorage(), adapter);
      addTearDown(container.dispose);
      await tester.pumpWidget(testApp(container, const RegisterScreen()));

      await tester.tap(find.text('Particulier'));
      await tester.pumpAndSettle();
      await tester.enterText(field('fullName'), 'Jean Dupont');
      await tester.enterText(field('email'), 'jean@chantier.fr');
      await tester.enterText(field('password'), 'motdepasse123');
      await tapButton(tester, 'Créer mon compte');

      expect(
        find.text('Un compte existe déjà avec cet email.'),
        findsOneWidget,
      );
    });

    testWidgets('erreurs de l\'API (422) affichées sous les champs', (
      tester,
    ) async {
      final adapter = ScriptedAdapter(
        (options) async => jsonResponse(422, {
          'code': 422,
          'message': 'Validation failed.',
          'violations': [
            {'property': 'email', 'message': 'Refusé par le serveur.'},
          ],
        }),
      );
      final container = buildContainer(InMemoryTokenStorage(), adapter);
      addTearDown(container.dispose);
      await tester.pumpWidget(testApp(container, const RegisterScreen()));

      await tester.tap(find.text('Particulier'));
      await tester.pumpAndSettle();
      await tester.enterText(field('fullName'), 'Jean Dupont');
      await tester.enterText(field('email'), 'jean@chantier.fr');
      await tester.enterText(field('password'), 'motdepasse123');
      await tapButton(tester, 'Créer mon compte');

      expect(find.text('Refusé par le serveur.'), findsOneWidget);
    });
  });

  group('compte', () {
    Future<ProviderContainer> signedInContainer(ScriptedAdapter adapter) async {
      final storage = InMemoryTokenStorage()
        ..tokens = const AuthTokens(accessToken: 'a', refreshToken: 'r')
        ..userJson = jsonEncode(profileJson);
      final container = buildContainer(storage, adapter);
      await container.read(authControllerProvider.notifier).restoreSession();

      return container;
    }

    testWidgets('le formulaire est prérempli avec le profil', (tester) async {
      final adapter = ScriptedAdapter(
        (options) async => jsonResponse(200, profileJson),
      );
      final container = await tester.runAsync(() => signedInContainer(adapter));
      addTearDown(container!.dispose);
      await tester.pumpWidget(testApp(container, const ProfileScreen()));

      expect(
        find.widgetWithText(TextFormField, 'Élec Chantier'),
        findsOneWidget,
      );
      expect(
        find.widgetWithText(TextFormField, '12345678901234'),
        findsOneWidget,
      );
      expect(find.widgetWithText(TextFormField, 'Jean Dupont'), findsOneWidget);
      expect(
        find.widgetWithText(TextFormField, 'jean@chantier.fr'),
        findsOneWidget,
      );
    });

    testWidgets('nouveau mot de passe sans l\'actuel : refusé localement', (
      tester,
    ) async {
      final adapter = ScriptedAdapter(
        (options) async => jsonResponse(200, profileJson),
      );
      final container = await tester.runAsync(() => signedInContainer(adapter));
      addTearDown(container!.dispose);
      await tester.pumpWidget(testApp(container, const ProfileScreen()));
      final requestsBefore = adapter.requests.length;

      await tester.enterText(field('newPassword'), 'nouveau-mot-de-passe');
      await tapButton(tester, 'Enregistrer');

      expect(find.text('Saisissez votre mot de passe actuel.'), findsOneWidget);
      expect(adapter.requests.length, requestsBefore);
    });

    testWidgets('mot de passe actuel faux : erreur de l\'API sous le champ', (
      tester,
    ) async {
      final adapter = ScriptedAdapter((options) async {
        return options.method == 'PUT'
            ? jsonResponse(422, {
                'code': 422,
                'message': 'Validation failed.',
                'violations': [
                  {
                    'property': 'currentPassword',
                    'message': 'Mot de passe actuel incorrect.',
                  },
                ],
              })
            : jsonResponse(200, profileJson);
      });
      final container = await tester.runAsync(() => signedInContainer(adapter));
      addTearDown(container!.dispose);
      await tester.pumpWidget(testApp(container, const ProfileScreen()));

      await tester.enterText(field('currentPassword'), 'mauvais');
      await tester.enterText(field('newPassword'), 'nouveau-mot-de-passe');
      await tapButton(tester, 'Enregistrer');

      expect(find.text('Mot de passe actuel incorrect.'), findsOneWidget);
    });
  });
}
