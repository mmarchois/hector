import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hector_mobile/src/data/api_errors.dart';
import 'package:hector_mobile/src/data/auth/token_storage.dart';
import 'package:hector_mobile/src/presentation/profile_screen.dart';
import 'package:hector_mobile/src/presentation/register_screen.dart';
import 'package:hector_mobile/src/presentation/welcome_screen.dart';
import 'package:hector_mobile/src/state/auth_controller.dart';

import 'support/fakes.dart';

/// Essai sans compte (lot L3) : conditions d'utilisation, compte invité,
/// conversion en compte complet.
void main() {
  const guestTokens = AuthTokens(
    accessToken: 'guest-access',
    refreshToken: 'guest-refresh',
  );

  ScriptedAdapter guestApi() {
    return ScriptedAdapter((options) async {
      if (options.path.endsWith('/terms-of-use')) {
        return jsonResponse(200, termsOfUseJson);
      }
      if (options.path.endsWith('/auth/guest')) {
        return jsonResponse(201, {
          'token': 'guest-access',
          'refresh_token': 'guest-refresh',
        });
      }

      return jsonResponse(200, guestProfileJson);
    });
  }

  group('compte invité', () {
    test('conditions acceptées : compte ouvert et session invitée', () async {
      final storage = InMemoryTokenStorage();
      final adapter = guestApi();
      final container = buildContainer(storage, adapter);
      addTearDown(container.dispose);
      final auth = container.read(authControllerProvider.notifier);

      auth.acceptTermsOfUse('2026-11-24');
      await auth.startAsGuest();

      expect(adapter.calls, ['POST /api/v1/auth/guest', 'GET /api/v1/auth/me']);
      expect(adapter.requests.first.data, {
        'acceptedTermsOfUseVersion': '2026-11-24',
      });
      expect(storage.tokens?.refreshToken, 'guest-refresh');
      final state = container.read(authControllerProvider);
      expect(state, isA<AuthSignedIn>());
      expect((state as AuthSignedIn).user.guest, isTrue);
      expect(state.user.email, '');
      // Session invitée restaurable hors ligne, comme une autre
      expect(jsonDecode(storage.userJson!)['guest'], isTrue);
    });

    test('sans conditions acceptées : aucun appel', () async {
      final adapter = guestApi();
      final container = buildContainer(InMemoryTokenStorage(), adapter);
      addTearDown(container.dispose);

      await expectLater(
        container.read(authControllerProvider.notifier).startAsGuest(),
        throwsStateError,
      );
      expect(adapter.requests, isEmpty);
    });

    test('conditions périmées (409) et trop de comptes (429)', () async {
      for (final (status, matcher) in [
        (409, isA<TermsOfUseOutdatedException>()),
        (429, isA<TooManyGuestAccountsException>()),
      ]) {
        final adapter = ScriptedAdapter(
          (options) async => jsonResponse(status, {'code': status}),
        );
        final container = buildContainer(InMemoryTokenStorage(), adapter);
        addTearDown(container.dispose);
        final auth = container.read(authControllerProvider.notifier)
          ..acceptTermsOfUse('2026-11-24');

        await expectLater(auth.startAsGuest(), throwsA(matcher));
      }
    });

    test(
      's\'inscrire depuis un compte invité convertit ce compte, sans reconnexion',
      () async {
        final storage = InMemoryTokenStorage()
          ..tokens = guestTokens
          ..userJson = jsonEncode(guestProfileJson);
        final adapter = ScriptedAdapter((options) async {
          if (options.path.endsWith('/register')) {
            return jsonResponse(201, {...profileJson, 'uuid': 'g1'});
          }

          return jsonResponse(200, guestProfileJson);
        });
        final container = buildContainer(storage, adapter);
        addTearDown(container.dispose);
        final auth = container.read(authControllerProvider.notifier);
        await auth.restoreSession();

        await auth.register(
          fullName: 'Jean Dupont',
          email: 'jean@chantier.fr',
          password: 'motdepasse123',
          professional: false,
        );

        // Même compte, même session : pas de connexion par identifiants
        expect(adapter.calls, [
          'GET /api/v1/auth/me',
          'POST /api/v1/auth/register',
        ]);
        expect(
          adapter.requests.last.headers['Authorization'],
          'Bearer guest-access',
        );
        expect(storage.tokens?.accessToken, 'guest-access');
        final state = container.read(authControllerProvider) as AuthSignedIn;
        expect(state.user.guest, isFalse);
        expect(state.user.uuid, 'g1');
      },
    );

    test(
      'sans compte invité, l\'inscription envoie la version acceptée',
      () async {
        final adapter = ScriptedAdapter((options) async {
          if (options.path.endsWith('/register')) {
            return jsonResponse(201, profileJson);
          }

          return options.path.endsWith('/login')
              ? jsonResponse(200, {'token': 'a', 'refresh_token': 'r'})
              : jsonResponse(200, profileJson);
        });
        final container = buildContainer(InMemoryTokenStorage(), adapter);
        addTearDown(container.dispose);
        final auth = container.read(authControllerProvider.notifier)
          ..acceptTermsOfUse('2026-11-24');

        await auth.register(
          fullName: 'Jean Dupont',
          email: 'jean@chantier.fr',
          password: 'motdepasse123',
          professional: false,
        );

        expect(
          adapter.requests.first.data['acceptedTermsOfUseVersion'],
          '2026-11-24',
        );
      },
    );
  });

  group('écran de bienvenue', () {
    testWidgets('commencer exige d\'accepter les conditions', (tester) async {
      final adapter = guestApi();
      final container = buildContainer(InMemoryTokenStorage(), adapter);
      addTearDown(container.dispose);
      await tester.pumpWidget(testApp(container, const WelcomeScreen()));
      await tester.pumpAndSettle();

      final start = find.byKey(const Key('startAsGuest'));
      expect(tester.widget<FilledButton>(start).onPressed, isNull);

      await tester.tap(find.byKey(const Key('acceptTermsOfUse')));
      await tester.pumpAndSettle();
      await tester.tap(start);
      await tester.pumpAndSettle();

      expect(adapter.calls, [
        'GET /api/v1/terms-of-use',
        'POST /api/v1/auth/guest',
        'GET /api/v1/auth/me',
      ]);
      final state = container.read(authControllerProvider);
      expect((state as AuthSignedIn).user.guest, isTrue);
    });

    testWidgets('les conditions se lisent en entier', (tester) async {
      final container = buildContainer(InMemoryTokenStorage(), guestApi());
      addTearDown(container.dispose);
      await tester.pumpWidget(testApp(container, const WelcomeScreen()));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Lire les conditions d\'utilisation'));
      await tester.pumpAndSettle();

      expect(find.text('Conditions d\'utilisation d\'Hector'), findsOneWidget);
      expect(
        find.text('Hector ne remplace pas un électricien.'),
        findsOneWidget,
      );
    });

    testWidgets('sans réseau : message clair, puis nouvel essai automatique', (
      tester,
    ) async {
      var online = false;
      final adapter = ScriptedAdapter((options) async {
        if (!online) {
          throw offline(options);
        }

        return jsonResponse(200, termsOfUseJson);
      });
      final container = buildContainer(InMemoryTokenStorage(), adapter);
      addTearDown(container.dispose);
      await tester.pumpWidget(testApp(container, const WelcomeScreen()));
      await tester.pumpAndSettle();

      expect(
        find.textContaining('Impossible de charger les conditions'),
        findsOneWidget,
      );
      expect(find.byKey(const Key('startAsGuest')), findsNothing);

      // Le réseau revient : l'écran recharge seul, sans action de l'utilisateur
      online = true;
      await tester.pump(WelcomeScreen.retryDelay);
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('startAsGuest')), findsOneWidget);
      expect(adapter.calls, [
        'GET /api/v1/terms-of-use',
        'GET /api/v1/terms-of-use',
      ]);
    });

    testWidgets('conditions modifiées entre-temps : à accepter de nouveau', (
      tester,
    ) async {
      final adapter = ScriptedAdapter((options) async {
        return options.path.endsWith('/terms-of-use')
            ? jsonResponse(200, termsOfUseJson)
            : jsonResponse(409, {
                'code': 409,
                'reason': 'terms_of_use_outdated',
              });
      });
      final container = buildContainer(InMemoryTokenStorage(), adapter);
      addTearDown(container.dispose);
      await tester.pumpWidget(testApp(container, const WelcomeScreen()));
      await tester.pumpAndSettle();

      await tester.tap(find.byKey(const Key('acceptTermsOfUse')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('startAsGuest')));
      await tester.pumpAndSettle();

      expect(find.textContaining('ont changé'), findsOneWidget);
      final checkbox = tester.widget<CheckboxListTile>(
        find.byKey(const Key('acceptTermsOfUse')),
      );
      expect(checkbox.value, isFalse);
      expect(container.read(authControllerProvider), isA<AuthSignedOut>());
    });
  });

  group('écrans d\'un invité', () {
    /// Session invitée restaurée en temps réel : dans la zone de temps simulé
    /// des tests de widgets, les minuteries de Dio n'avanceraient pas.
    Future<void> signInAsGuest(
      WidgetTester tester,
      ProviderContainer container,
    ) async {
      await tester.runAsync(
        () => container.read(authControllerProvider.notifier).restoreSession(),
      );
    }

    testWidgets('profil : créer un compte au lieu d\'un formulaire', (
      tester,
    ) async {
      final storage = InMemoryTokenStorage()..tokens = guestTokens;
      final container = buildContainer(storage, guestApi());
      addTearDown(container.dispose);
      await signInAsGuest(tester, container);
      await tester.pumpWidget(testApp(container, const ProfileScreen()));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('guestCreateAccount')), findsOneWidget);
      expect(find.byKey(const Key('guestLogIn')), findsOneWidget);
      expect(find.text('Supprimer mes données'), findsOneWidget);
      expect(find.byKey(const Key('email')), findsNothing);
      expect(find.text('Se déconnecter'), findsNothing);
    });

    testWidgets('inscription : l\'invité garde ce qu\'il a commencé', (
      tester,
    ) async {
      final storage = InMemoryTokenStorage()..tokens = guestTokens;
      final container = buildContainer(storage, guestApi());
      addTearDown(container.dispose);
      await signInAsGuest(tester, container);
      await tester.pumpWidget(testApp(container, const RegisterScreen()));
      await tester.pumpAndSettle();

      expect(
        find.text('Vous gardez tout ce que vous avez commencé sans compte.'),
        findsOneWidget,
      );
    });
  });
}
