import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:hector_mobile/src/data/api_errors.dart';
import 'package:hector_mobile/src/data/auth/token_storage.dart';
import 'package:hector_mobile/src/state/auth_controller.dart';

import 'support/fakes.dart';

void main() {
  const tokens = AuthTokens(accessToken: 'access-1', refreshToken: 'refresh-1');
  final cachedProfile = jsonEncode(profileJson);

  group('restauration de session', () {
    test('sans token : déconnecté, aucun appel réseau', () async {
      final storage = InMemoryTokenStorage();
      final adapter = ScriptedAdapter((options) async => emptyResponse(500));
      final container = buildContainer(storage, adapter);
      addTearDown(container.dispose);

      await container.read(authControllerProvider.notifier).restoreSession();

      expect(container.read(authControllerProvider), isA<AuthSignedOut>());
      expect(adapter.requests, isEmpty);
    });

    test('en ligne : profil chargé et mis en cache', () async {
      final storage = InMemoryTokenStorage()..tokens = tokens;
      final adapter = ScriptedAdapter(
        (options) async => jsonResponse(200, profileJson),
      );
      final container = buildContainer(storage, adapter);
      addTearDown(container.dispose);

      await container.read(authControllerProvider.notifier).restoreSession();

      final state = container.read(authControllerProvider);
      expect(state, isA<AuthSignedIn>());
      expect((state as AuthSignedIn).user.companyName, 'Élec Chantier');
      expect(storage.userJson, contains('jean@chantier.fr'));
      expect(
        adapter.requests.single.headers['Authorization'],
        'Bearer access-1',
      );
    });

    test('hors ligne : session restaurée du cache, tokens conservés', () async {
      final storage = InMemoryTokenStorage()
        ..tokens = tokens
        ..userJson = cachedProfile;
      final adapter = ScriptedAdapter(
        (options) async => throw offline(options),
      );
      final container = buildContainer(storage, adapter);
      addTearDown(container.dispose);

      await container.read(authControllerProvider.notifier).restoreSession();

      final state = container.read(authControllerProvider);
      expect(state, isA<AuthSignedIn>());
      expect((state as AuthSignedIn).user.email, 'jean@chantier.fr');
      // Le refresh token (usage unique) n'a pas été consommé
      expect(adapter.calls, ['GET /api/v1/auth/me']);
      expect(storage.tokens?.refreshToken, 'refresh-1');
    });

    test('access token expiré : refresh puis profil', () async {
      final storage = InMemoryTokenStorage()..tokens = tokens;
      final adapter = ScriptedAdapter((options) async {
        if (options.path.contains('/token/refresh')) {
          return jsonResponse(200, {
            'token': 'access-2',
            'refresh_token': 'refresh-2',
          });
        }

        return options.headers['Authorization'] == 'Bearer access-2'
            ? jsonResponse(200, profileJson)
            : jsonResponse(401, {'code': 401, 'message': 'Expired JWT Token'});
      });
      final container = buildContainer(storage, adapter);
      addTearDown(container.dispose);

      await container.read(authControllerProvider.notifier).restoreSession();

      expect(container.read(authControllerProvider), isA<AuthSignedIn>());
      expect(storage.tokens?.accessToken, 'access-2');
      expect(storage.tokens?.refreshToken, 'refresh-2');
    });

    test(
      'access expiré + refresh en panne réseau : session conservée',
      () async {
        final storage = InMemoryTokenStorage()
          ..tokens = tokens
          ..userJson = cachedProfile;
        final adapter = ScriptedAdapter((options) async {
          if (options.path.contains('/token/refresh')) {
            throw offline(options);
          }

          return jsonResponse(401, {
            'code': 401,
            'message': 'Expired JWT Token',
          });
        });
        final container = buildContainer(storage, adapter);
        addTearDown(container.dispose);

        await container.read(authControllerProvider.notifier).restoreSession();

        expect(container.read(authControllerProvider), isA<AuthSignedIn>());
        expect(storage.tokens, isNotNull);
      },
    );

    test('refresh rejeté (401) : tokens purgés, déconnecté', () async {
      final storage = InMemoryTokenStorage()
        ..tokens = tokens
        ..userJson = cachedProfile;
      final adapter = ScriptedAdapter(
        (options) async =>
            jsonResponse(401, {'code': 401, 'message': 'Invalid'}),
      );
      final container = buildContainer(storage, adapter);
      addTearDown(container.dispose);

      await container.read(authControllerProvider.notifier).restoreSession();

      expect(container.read(authControllerProvider), isA<AuthSignedOut>());
      expect(storage.tokens, isNull);
      expect(storage.userJson, isNull);
    });
  });

  group('connexion et inscription', () {
    test('signIn : tokens stockés, profil chargé', () async {
      final storage = InMemoryTokenStorage();
      final adapter = ScriptedAdapter((options) async {
        return options.path.endsWith('/login')
            ? jsonResponse(200, {
                'token': 'access-1',
                'refresh_token': 'refresh-1',
              })
            : jsonResponse(200, profileJson);
      });
      final container = buildContainer(storage, adapter);
      addTearDown(container.dispose);

      await container
          .read(authControllerProvider.notifier)
          .signIn(email: 'jean@chantier.fr', password: 'motdepasse123');

      expect(container.read(authControllerProvider), isA<AuthSignedIn>());
      expect(storage.tokens?.refreshToken, 'refresh-1');
      expect(adapter.requests.first.data, {
        'email': 'jean@chantier.fr',
        'password': 'motdepasse123',
      });
    });

    test('signIn refusé : reste déconnecté, rien de stocké', () async {
      final storage = InMemoryTokenStorage();
      final adapter = ScriptedAdapter(
        (options) async =>
            jsonResponse(401, {'code': 401, 'message': 'Invalid credentials.'}),
      );
      final container = buildContainer(storage, adapter);
      addTearDown(container.dispose);

      await expectLater(
        container
            .read(authControllerProvider.notifier)
            .signIn(email: 'jean@chantier.fr', password: 'faux'),
        throwsA(isA<InvalidCredentialsException>()),
      );

      expect(container.read(authControllerProvider), isA<AuthUnknown>());
      expect(storage.tokens, isNull);
    });

    test('register : crée le compte puis ouvre la session', () async {
      final storage = InMemoryTokenStorage();
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
      final container = buildContainer(storage, adapter);
      addTearDown(container.dispose);

      await container
          .read(authControllerProvider.notifier)
          .register(
            fullName: 'Jean Dupont',
            email: 'jean@chantier.fr',
            password: 'motdepasse123',
            professional: true,
            companyName: 'Élec Chantier',
            siret: '12345678901234',
            vatNumber: 'FR12345678901',
          );

      expect(adapter.calls, [
        'POST /api/v1/auth/register',
        'POST /api/v1/auth/login',
        'GET /api/v1/auth/me',
      ]);
      expect(container.read(authControllerProvider), isA<AuthSignedIn>());
    });
  });

  group('profil', () {
    test('changer de mot de passe rouvre une session', () async {
      final storage = InMemoryTokenStorage()..tokens = tokens;
      final adapter = ScriptedAdapter((options) async {
        return options.path.endsWith('/login')
            ? jsonResponse(200, {
                'token': 'access-2',
                'refresh_token': 'refresh-2',
              })
            : jsonResponse(200, {...profileJson, 'fullName': 'Jean Volta'});
      });
      final container = buildContainer(storage, adapter);
      addTearDown(container.dispose);

      await container
          .read(authControllerProvider.notifier)
          .updateProfile(
            fullName: 'Jean Volta',
            email: 'jean@chantier.fr',
            professional: true,
            companyName: 'Élec Chantier',
            siret: '12345678901234',
            vatNumber: 'FR12345678901',
            currentPassword: 'ancien-mot-de-passe',
            newPassword: 'nouveau-mot-de-passe',
          );

      final state = container.read(authControllerProvider) as AuthSignedIn;
      expect(state.user.fullName, 'Jean Volta');
      expect(storage.userJson, contains('Jean Volta'));
      // Le serveur a révoqué les anciens refresh tokens : nouveaux tokens stockés
      expect(adapter.calls, ['PUT /api/v1/auth/me', 'POST /api/v1/auth/login']);
      expect(adapter.requests.last.data, {
        'email': 'jean@chantier.fr',
        'password': 'nouveau-mot-de-passe',
      });
      expect(storage.tokens?.refreshToken, 'refresh-2');
    });

    test('sans changement de mot de passe : tokens inchangés', () async {
      final storage = InMemoryTokenStorage()..tokens = tokens;
      final adapter = ScriptedAdapter(
        (options) async => jsonResponse(200, profileJson),
      );
      final container = buildContainer(storage, adapter);
      addTearDown(container.dispose);

      await container
          .read(authControllerProvider.notifier)
          .updateProfile(
            fullName: 'Jean Dupont',
            email: 'jean@chantier.fr',
            professional: false,
          );

      expect(adapter.calls, ['PUT /api/v1/auth/me']);
      // Les champs entreprise absents ne sont pas envoyés
      expect(adapter.requests.single.data, {
        'fullName': 'Jean Dupont',
        'email': 'jean@chantier.fr',
        'professional': false,
      });
      expect(storage.tokens?.refreshToken, 'refresh-1');
    });

    test('deleteAccount : compte supprimé, session locale effacée', () async {
      final storage = InMemoryTokenStorage()
        ..tokens = tokens
        ..userJson = cachedProfile;
      final adapter = ScriptedAdapter((options) async => emptyResponse(204));
      final container = buildContainer(storage, adapter);
      addTearDown(container.dispose);

      await container.read(authControllerProvider.notifier).deleteAccount();

      expect(adapter.calls, ['DELETE /api/v1/auth/me']);
      expect(container.read(authControllerProvider), isA<AuthSignedOut>());
      expect(storage.tokens, isNull);
    });
  });

  group('déconnexion', () {
    test(
      'révoque le refresh token côté serveur et efface la session',
      () async {
        final storage = InMemoryTokenStorage()
          ..tokens = tokens
          ..userJson = cachedProfile;
        final adapter = ScriptedAdapter(
          (options) async => jsonResponse(200, {'code': 200, 'message': 'ok'}),
        );
        final container = buildContainer(storage, adapter);
        addTearDown(container.dispose);

        await container.read(authControllerProvider.notifier).signOut();

        expect(adapter.calls, ['POST /api/v1/auth/token/invalidate']);
        expect(adapter.requests.single.data, {'refresh_token': 'refresh-1'});
        expect(container.read(authControllerProvider), isA<AuthSignedOut>());
        expect(storage.tokens, isNull);
        expect(storage.userJson, isNull);
      },
    );

    test('hors ligne : la session locale est quand même effacée', () async {
      final storage = InMemoryTokenStorage()..tokens = tokens;
      final adapter = ScriptedAdapter(
        (options) async => throw offline(options),
      );
      final container = buildContainer(storage, adapter);
      addTearDown(container.dispose);

      await container.read(authControllerProvider.notifier).signOut();

      expect(container.read(authControllerProvider), isA<AuthSignedOut>());
      expect(storage.tokens, isNull);
    });
  });
}
