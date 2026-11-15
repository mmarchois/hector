import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hector_mobile/src/data/api_errors.dart';
import 'package:hector_mobile/src/data/auth/auth_repository.dart';

import 'support/fakes.dart';

void main() {
  AuthRepository repositoryWith(ScriptedAdapter adapter) {
    return AuthRepository(
      Dio(BaseOptions(baseUrl: 'https://example.test'))
        ..httpClientAdapter = adapter,
    );
  }

  test('register : un particulier n\'envoie aucun champ entreprise', () async {
    final adapter = ScriptedAdapter(
      (options) async => jsonResponse(201, profileJson),
    );

    await repositoryWith(adapter).register(
      fullName: 'Paul Volt',
      email: 'paul@exemple.fr',
      password: 'motdepasse123',
      professional: false,
    );

    expect(adapter.requests.single.data, {
      'fullName': 'Paul Volt',
      'email': 'paul@exemple.fr',
      'password': 'motdepasse123',
      'professional': false,
    });
  });

  test('register : 409 → EmailAlreadyExistsException', () async {
    final adapter = ScriptedAdapter(
      (options) async => jsonResponse(409, {'code': 409, 'message': 'Conflit'}),
    );

    await expectLater(
      repositoryWith(adapter).register(
        fullName: 'Paul Volt',
        email: 'paul@exemple.fr',
        password: 'motdepasse123',
        professional: false,
      ),
      throwsA(isA<EmailAlreadyExistsException>()),
    );
  });

  test('register : 422 → erreurs par champ', () async {
    final adapter = ScriptedAdapter(
      (options) async => jsonResponse(422, {
        'code': 422,
        'message': 'Validation failed.',
        'violations': [
          {
            'property': 'siret',
            'message': 'Le SIRET doit contenir 14 chiffres.',
          },
          {'property': 'siret', 'message': 'Second message ignoré'},
          {'property': 'email', 'message': 'Cet email n\'est pas valide.'},
        ],
      }),
    );

    await expectLater(
      repositoryWith(adapter).register(
        fullName: 'Paul Volt',
        email: 'paul',
        password: 'motdepasse123',
        professional: true,
        companyName: 'Volt',
        siret: '123',
        vatNumber: 'FR12345678901',
      ),
      throwsA(
        isA<ApiValidationException>()
            .having((e) => e.fieldErrors, 'fieldErrors', {
              'siret': 'Le SIRET doit contenir 14 chiffres.',
              'email': 'Cet email n\'est pas valide.',
            }),
      ),
    );
  });

  test('register : erreur serveur → DioException', () async {
    final adapter = ScriptedAdapter(
      (options) async => jsonResponse(500, {'code': 500, 'message': 'Erreur'}),
    );

    await expectLater(
      repositoryWith(adapter).register(
        fullName: 'Paul Volt',
        email: 'paul@exemple.fr',
        password: 'motdepasse123',
        professional: false,
      ),
      throwsA(isA<DioException>()),
    );
  });

  test('login : 401 → InvalidCredentialsException', () async {
    final adapter = ScriptedAdapter(
      (options) async =>
          jsonResponse(401, {'code': 401, 'message': 'Invalid credentials.'}),
    );

    await expectLater(
      repositoryWith(adapter).login(email: 'a@b.fr', password: 'faux'),
      throwsA(isA<InvalidCredentialsException>()),
    );
  });

  test(
    'login : panne réseau → DioException (pas « identifiants faux »)',
    () async {
      final adapter = ScriptedAdapter(
        (options) async => throw offline(options),
      );

      await expectLater(
        repositoryWith(adapter).login(email: 'a@b.fr', password: 'x'),
        throwsA(isA<DioException>()),
      );
    },
  );
}
