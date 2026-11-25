import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hector_mobile/src/data/api.dart';
import 'package:hector_mobile/src/data/auth/token_storage.dart';
import 'package:hector_mobile/src/locale.dart';
import 'package:hector_mobile/src/presentation/welcome_screen.dart';

import 'support/fakes.dart';

/// Décision D19 : l'application parle la langue du téléphone si elle est
/// ouverte, sinon le français, et l'annonce à l'API.
void main() {
  group('langue de l\'application', () {
    test('la première langue ouverte du téléphone, sinon le français', () {
      expect(
        AppLocales.resolve(const [Locale('en', 'US')]),
        const Locale('en'),
      );
      expect(
        AppLocales.resolve(const [Locale('fr', 'CA')]),
        const Locale('fr'),
      );
      expect(
        AppLocales.resolve(const [Locale('de'), Locale('en', 'GB')]),
        const Locale('en'),
      );
      expect(AppLocales.resolve(const [Locale('de')]), const Locale('fr'));
      expect(AppLocales.resolve(const []), const Locale('fr'));
    });

    test('chaque langue ouverte traduit tous les textes', () {
      Set<String> keys(String code) =>
          (jsonDecode(File('lib/l10n/app_$code.arb').readAsStringSync())
                  as Map<String, dynamic>)
              .keys
              .where((key) => !key.startsWith('@'))
              .toSet();

      for (final locale in AppLocales.supported) {
        expect(
          keys(locale.languageCode),
          keys('fr'),
          reason: 'app_${locale.languageCode}.arb',
        );
      }
    });
  });

  group('langue annoncée à l\'API', () {
    for (final (locale, expected) in [
      (const Locale('fr'), 'fr'),
      (const Locale('en'), 'en'),
    ]) {
      test('Accept-Language : $expected, avec ou sans session', () async {
        final adapter = ScriptedAdapter(
          (options) async => jsonResponse(200, termsOfUseJson),
        );
        final c = buildContainer(
          InMemoryTokenStorage()
            ..tokens = const AuthTokens(accessToken: 'a', refreshToken: 'r'),
          adapter,
          locale: locale,
        );
        addTearDown(c.dispose);

        await c.read(bareDioProvider).get<dynamic>('/api/v1/terms-of-use');
        await c.read(apiDioProvider).get<dynamic>('/api/v1/conversations');

        expect(
          adapter.requests.map((request) => request.headers['Accept-Language']),
          [expected, expected],
        );
      });
    }
  });

  group('interface en anglais', () {
    testWidgets('l\'écran de bienvenue est traduit', (tester) async {
      tester.view.physicalSize = const Size(1170, 2532);
      tester.view.devicePixelRatio = 3;
      addTearDown(tester.view.reset);
      final c = buildContainer(
        InMemoryTokenStorage(),
        ScriptedAdapter((options) async => jsonResponse(200, termsOfUseJson)),
        locale: const Locale('en'),
      );
      addTearDown(c.dispose);

      await tester.pumpWidget(
        testApp(c, const WelcomeScreen(), locale: const Locale('en')),
      );
      await tester.pumpAndSettle();

      expect(find.text('Start without an account'), findsOneWidget);
      expect(find.text('I already have an account'), findsOneWidget);
      expect(find.text('Safety first'), findsOneWidget);
      expect(find.text('Commencer sans compte'), findsNothing);
    });
  });
}
