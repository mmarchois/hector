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
  {
    ...message('m2', 'assistant', []),
    'citations': [
      {
        'id': 'p1',
        'corpus': 'regulatory',
        'title': 'Fiche Hector : le dispositif différentiel',
        'publisher': 'Hector',
        'version': 'v1',
        'publishedOn': '2026-11-01',
        'section': 'Rôle',
        'excerpt':
            'Un dispositif différentiel compare le courant qui part et celui qui revient.',
      },
    ],
    'blocks': [
      block(
        'statement',
        'Le différentiel déclenche au branchement du lave-linge, pas avant.',
        kind: 'declared_fact',
      ),
      {
        ...block(
          'statement',
          'Un différentiel compare le courant qui part et celui qui revient.',
          kind: 'sourced_fact',
        ),
        'citations': ['p1'],
      },
      block(
        'statement',
        'Un défaut d\'isolement du lave-linge est la cause la plus courante dans ce cas.',
        kind: 'hypothesis',
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
    ],
  },
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

/// Un calcul demandé dans la conversation, puis un calcul refusé (lot L14).
final calculationExchange = [
  message('m5', 'user', [
    block('text', 'Quel courant pour un moteur de 3 kW en 230 V, cos φ 0,9 ?'),
  ]),
  message('m6', 'assistant', [
    {
      'type': 'calculation',
      'version': 1,
      'text': 'Courant absorbé par le moteur.',
      'calculation': {
        'uuid': '01a213b2-b1ab-7791-a4a3-14f205b52ef0',
        'calculator': {
          'key': 'single_phase_current',
          'version': 1,
          'title': 'Courant absorbé en monophasé',
        },
        'formula': 'I = P / (U × cos φ)',
        'source':
            'Loi physique : puissance active en régime sinusoïdal monophasé.',
        'assumptions': [
          'Régime sinusoïdal établi ; tension et courant en valeurs efficaces.',
          'Le facteur de puissance est celui que vous indiquez : aucune valeur n\'est supposée.',
          'La puissance est la puissance active absorbée : ni rendement, ni coefficient d\'utilisation ou de simultanéité ne sont appliqués.',
        ],
        'inputs': [
          {
            'key': 'power',
            'label': 'Puissance active',
            'symbol': 'P',
            'value': 3,
            'unit': 'kW',
          },
          {
            'key': 'voltage',
            'label': 'Tension',
            'symbol': 'U',
            'value': 230,
            'unit': 'V',
          },
          {
            'key': 'powerFactor',
            'label': 'Facteur de puissance',
            'symbol': 'cos φ',
            'value': 0.9,
            'unit': '',
          },
        ],
        'steps': [
          {
            'label': 'Puissance apparente',
            'expression': 'S = P / cos φ',
            'value': 3333,
            'unit': 'VA',
          },
          {
            'label': 'Courant absorbé',
            'expression': 'I = S / U',
            'value': 14.49,
            'unit': 'A',
          },
        ],
        'result': {
          'key': 'current',
          'label': 'Courant absorbé',
          'symbol': 'I',
          'value': 14.5,
          'unit': 'A',
          'exact': 14.492753623188406,
          'roundingRule':
              'Arrondi par excès, à quatre chiffres significatifs ; la partie entière n\'est jamais arrondie. Un courant à prévoir ne se sous-estime pas.',
        },
        'createdAt': '2026-11-25T10:00:00+00:00',
      },
      'fallbackText': 'Résultat : I = 14,5 A',
    },
    block(
      'statement',
      'Faites confirmer le choix de la protection par un électricien : il ne se déduit pas de ce seul courant.',
      kind: 'recommendation',
    ),
  ]),
];

final refusalExchange = [
  message('m7', 'user', [
    block('text', 'Et pour un moteur de 5 kW en 230 V ?'),
  ]),
  message('m8', 'assistant', [
    block(
      'statement',
      'Il me manque le facteur de puissance de ce moteur pour faire le calcul.',
      kind: 'missing_information',
    ),
    {
      'type': 'calculation_refusal',
      'version': 1,
      'calculator': {
        'key': 'single_phase_current',
        'title': 'Courant absorbé en monophasé',
      },
      'reasons': ['Facteur de puissance (cos φ) : valeur manquante.'],
      'fallbackText': 'Calcul non effectué',
    },
    block('question', 'Quel facteur de puissance indique sa plaque ?'),
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

    testWidgets('calcul$suffix', (tester) async {
      phone(tester);
      final c = buildContainer(
        InMemoryTokenStorage()..tokens = tokens,
        api(messages: calculationExchange),
      );
      addTearDown(c.dispose);
      await tester.pumpWidget(
        app(c, const ChatScreen(conversationUuid: 'c1'), dark: dark),
      );
      await tester.pumpAndSettle();
      await shot(tester, '04b-calcul$suffix');
      // La carte dépasse l'écran : le haut (résultat, données) se lit en remontant
      await tester.drag(find.byType(Scrollable).first, const Offset(0, 420));
      await tester.pumpAndSettle();
      await shot(tester, '04b-calcul-haut$suffix');
    });

    testWidgets('calcul refusé$suffix', (tester) async {
      phone(tester);
      final c = buildContainer(
        InMemoryTokenStorage()..tokens = tokens,
        api(messages: refusalExchange),
      );
      addTearDown(c.dispose);
      await tester.pumpWidget(
        app(c, const ChatScreen(conversationUuid: 'c1'), dark: dark),
      );
      await tester.pumpAndSettle();
      await shot(tester, '04c-calcul-refuse$suffix');
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
