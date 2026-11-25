// Captures des écrans avec les vraies polices, en clair et en sombre, pour
// juger le design sans parcourir l'application :
//   make mobile_preview   (images dans mobile/build/preview)
// Hors du dossier test/ : `flutter test` ne les lance pas.
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hector_mobile/l10n/app_localizations.dart';
import 'package:hector_mobile/src/data/auth/token_storage.dart';
import 'package:hector_mobile/src/presentation/chat_screen.dart';
import 'package:hector_mobile/src/presentation/home_screen.dart';
import 'package:hector_mobile/src/presentation/login_screen.dart';
import 'package:hector_mobile/src/presentation/profile_screen.dart';
import 'package:hector_mobile/src/presentation/register_screen.dart';
import 'package:hector_mobile/src/presentation/welcome_screen.dart';
import 'package:hector_mobile/src/state/auth_controller.dart';
import 'package:hector_mobile/src/theme.dart';

import '../test/support/fakes.dart';

/// Dossier des captures, relatif à ce fichier.
const out = '../build/preview';

Future<void> loadFonts() async {
  Future<void> load(String family, List<String> files) async {
    final loader = FontLoader(family);
    for (final file in files) {
      loader.addFont(
        File(file).readAsBytes().then((b) => ByteData.sublistView(b)),
      );
    }
    await loader.load();
  }

  await load('Plus Jakarta Sans', [
    for (final weight in ['Regular', 'Medium', 'SemiBold', 'Bold', 'ExtraBold'])
      'assets/fonts/PlusJakartaSans-$weight.ttf',
  ]);
  final root = Platform.environment['FLUTTER_ROOT'];
  await load('MaterialIcons', [
    '$root/bin/cache/artifacts/material_fonts/MaterialIcons-Regular.otf',
  ]);
}

Widget app(ProviderContainer container, Widget screen, {bool dark = false}) {
  return UncontrolledProviderScope(
    container: container,
    child: MaterialApp(
      debugShowCheckedModeBanner: false,
      theme: dark ? HectorTheme.dark() : HectorTheme.light(),
      locale: const Locale('fr'),
      supportedLocales: const [Locale('fr')],
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: screen,
    ),
  );
}

Map<String, dynamic> block(String type, String text, {String? kind}) => {
  'type': type,
  'version': 1,
  'kind': ?kind,
  'text': text,
  'citations': <String>[],
  'fallbackText': text,
};

Map<String, dynamic> message(
  String uuid,
  String role,
  List<Map<String, dynamic>> blocks,
) => {
  'uuid': uuid,
  'role': role,
  'blocks': blocks,
  'citations': <dynamic>[],
  'createdAt': '2026-11-24T10:00:00+00:00',
};

final firstExchange = [
  message('m1', 'user', [
    block(
      'text',
      'Mon différentiel 30 mA saute dès que je branche le lave-linge.',
    ),
  ]),
  message('m2', 'assistant', [
    block(
      'statement',
      'Le différentiel déclenche au branchement du lave-linge, pas avant.',
      kind: 'declared_fact',
    ),
    block(
      'statement',
      'Un défaut d\'isolement du lave-linge est la cause la plus courante dans ce cas.',
      kind: 'hypothesis',
    ),
    block(
      'statement',
      'Un différentiel compare le courant qui part et celui qui revient. Un écart signale une fuite vers la terre.',
      kind: 'general_explanation',
    ),
    block(
      'statement',
      'Laissez le lave-linge débranché et réarmez le différentiel pour voir s\'il tient.',
      kind: 'recommendation',
    ),
    block(
      'statement',
      'Je ne sais pas si d\'autres appareils sont branchés sur le même circuit.',
      kind: 'missing_information',
    ),
    block(
      'question',
      'Le différentiel tient-il quand le lave-linge reste débranché ?',
    ),
  ]),
];

final secondExchange = [
  message('m3', 'user', [
    block('text', 'Oui il tient. Mais ça sent le brûlé près de la prise.'),
  ]),
  message('m4', 'assistant', [
    block(
      'warning',
      'Éloignez-vous de la prise et ne la touchez pas. En cas de fumée ou de flammes, appelez le 18 ou le 112.',
    ),
    block(
      'statement',
      'Faites intervenir un électricien avant toute remise en service de cette prise.',
      kind: 'recommendation',
    ),
  ]),
];

void main() {
  const tokens = AuthTokens(accessToken: 'a', refreshToken: 'r');

  setUpAll(loadFonts);

  void phone(WidgetTester tester) {
    tester.view.physicalSize = const Size(1170, 2532);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
  }

  Future<void> shot(WidgetTester tester, String name) async {
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile('$out/$name.png'),
    );
  }

  ScriptedAdapter api({
    List<Map<String, dynamic>> messages = const [],
    Map<String, dynamic> profile = profileJson,
    int sendStatus = 201,
  }) {
    return ScriptedAdapter((options) async {
      final path = options.path;
      if (path.contains('terms-of-use')) {
        return jsonResponse(200, termsOfUseJson);
      }
      if (options.method == 'POST' && path.endsWith('/messages')) {
        return jsonResponse(sendStatus, {
          'code': 429,
          'reason': 'signup_required',
          'message':
              'Vous avez atteint la limite de l\'essai sans compte. Créez un compte gratuit pour continuer.',
        });
      }
      if (path.endsWith('/messages')) {
        return jsonResponse(200, {'items': messages, 'hasMore': false});
      }
      if (path.endsWith('/conversations')) {
        return jsonResponse(200, {
          'items': [
            {
              'uuid': 'c1',
              'title':
                  'Mon différentiel 30 mA saute dès que je branche le lave-linge.',
              'updatedAt': '2026-11-24T10:00:00+00:00',
            },
            {
              'uuid': 'c2',
              'title': 'Quelle section pour une plaque à induction ?',
              'updatedAt': '2026-11-21T16:30:00+00:00',
            },
            {
              'uuid': 'c3',
              'title': 'Tableau divisionnaire dans un garage',
              'updatedAt': '2026-11-18T09:12:00+00:00',
            },
          ],
          'total': 3,
        });
      }

      return jsonResponse(200, profile);
    });
  }

  testWidgets('bienvenue', (tester) async {
    phone(tester);
    final c = buildContainer(InMemoryTokenStorage(), api());
    addTearDown(c.dispose);
    await tester.pumpWidget(app(c, const WelcomeScreen()));
    await tester.pumpAndSettle();
    await shot(tester, '01-bienvenue');
    await tester.tap(find.byKey(const Key('acceptTermsOfUse')));
    await tester.pumpAndSettle();
    await shot(tester, '02-bienvenue-acceptee');
  });

  for (final dark in [false, true]) {
    final suffix = dark ? '-sombre' : '';

    testWidgets('accueil$suffix', (tester) async {
      phone(tester);
      final c = buildContainer(InMemoryTokenStorage()..tokens = tokens, api());
      addTearDown(c.dispose);
      await tester.runAsync(
        () => c.read(authControllerProvider.notifier).restoreSession(),
      );
      await tester.pumpWidget(app(c, const HomeScreen(), dark: dark));
      await tester.pumpAndSettle();
      await shot(tester, '03-accueil$suffix');
    });

    testWidgets('conversation$suffix', (tester) async {
      phone(tester);
      final c = buildContainer(
        InMemoryTokenStorage()..tokens = tokens,
        api(messages: firstExchange),
      );
      addTearDown(c.dispose);
      await tester.pumpWidget(
        app(c, const ChatScreen(conversationUuid: 'c1'), dark: dark),
      );
      await tester.pumpAndSettle();
      await shot(tester, '04-conversation$suffix');
    });

    testWidgets('alerte$suffix', (tester) async {
      phone(tester);
      final c = buildContainer(
        InMemoryTokenStorage()..tokens = tokens,
        api(messages: [...firstExchange, ...secondExchange]),
      );
      addTearDown(c.dispose);
      await tester.pumpWidget(
        app(c, const ChatScreen(conversationUuid: 'c1'), dark: dark),
      );
      await tester.pumpAndSettle();
      await shot(tester, '05-alerte$suffix');
    });
  }

  testWidgets('conversation vide puis limite', (tester) async {
    phone(tester);
    final c = buildContainer(
      InMemoryTokenStorage()..tokens = tokens,
      api(sendStatus: 429),
    );
    addTearDown(c.dispose);
    await tester.pumpWidget(app(c, const ChatScreen(conversationUuid: 'c1')));
    await tester.pumpAndSettle();
    await shot(tester, '06-conversation-vide');
    await tester.enterText(
      find.byKey(const Key('composer')),
      'Et pour le chauffe-eau ?',
    );
    await tester.pump();
    await shot(tester, '07-saisie');
    await tester.tap(find.byKey(const Key('send')));
    await tester.pumpAndSettle();
    await shot(tester, '08-limite');
  });

  testWidgets('inscription et profil', (tester) async {
    phone(tester);
    final c = buildContainer(InMemoryTokenStorage()..tokens = tokens, api());
    addTearDown(c.dispose);
    await tester.pumpWidget(app(c, const RegisterScreen()));
    await tester.pumpAndSettle();
    await shot(tester, '10-inscription');
    await tester.runAsync(
      () => c.read(authControllerProvider.notifier).restoreSession(),
    );
    await tester.pumpWidget(app(c, const ProfileScreen()));
    await tester.pumpAndSettle();
    await shot(tester, '11-profil');
  });

  testWidgets('connexion', (tester) async {
    phone(tester);
    final c = buildContainer(InMemoryTokenStorage(), api());
    addTearDown(c.dispose);
    await tester.pumpWidget(app(c, const LoginScreen()));
    await tester.pumpAndSettle();
    await shot(tester, '09-connexion');
  });
}
