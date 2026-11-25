import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hector_mobile/src/data/api.dart';
import 'package:hector_mobile/src/data/api_errors.dart';
import 'package:hector_mobile/src/data/auth/token_storage.dart';
import 'package:hector_mobile/src/data/conversation/conversation_repository.dart';
import 'package:hector_mobile/src/domain/conversation.dart';
import 'package:hector_mobile/src/presentation/chat_screen.dart';
import 'package:hector_mobile/src/presentation/widgets/kit.dart';
import 'package:hector_mobile/src/presentation/widgets/sources.dart';
import 'package:hector_mobile/src/state/chat_controller.dart';
import 'package:hector_mobile/src/theme.dart';

import 'support/fakes.dart';

/// Lot L6 : écran de conversation.
void main() {
  const conversation = '0b507871-8b5e-4575-b297-a630310fc06e';
  const tokens = AuthTokens(accessToken: 'access-1', refreshToken: 'r-1');

  Map<String, dynamic> message(
    String uuid,
    String role,
    String text, {
    String type = 'text',
  }) {
    return {
      'uuid': uuid,
      'role': role,
      'blocks': [
        {'type': type, 'version': 1, 'text': text, 'fallbackText': text},
      ],
      'citations': [],
      'createdAt': '2026-11-24T10:00:00+00:00',
    };
  }

  Map<String, dynamic> turn(
    String status, {
    Map<String, dynamic>? answer,
    String question = 'Mon différentiel saute',
    Map<String, dynamic>? failure,
  }) {
    return {
      'uuid': 't1',
      'conversationUuid': conversation,
      'status': status,
      'userMessage': message('m1', 'user', question),
      'assistantMessage': answer,
      'createdAt': '2026-11-24T10:00:00+00:00',
      'finishedAt': status == 'in_progress'
          ? null
          : '2026-11-24T10:00:05+00:00',
      'failure': failure,
    };
  }

  Map<String, dynamic> page(List<Map<String, dynamic>> items) {
    return {'items': items, 'hasMore': false};
  }

  ProviderContainer container(ScriptedAdapter adapter) {
    final c = buildContainer(InMemoryTokenStorage()..tokens = tokens, adapter);
    addTearDown(c.dispose);

    return c;
  }

  group('client des conversations', () {
    test('envoi : délai de réception propre au tour', () async {
      final adapter = ScriptedAdapter(
        (options) async => jsonResponse(
          201,
          turn('completed', answer: message('m2', 'assistant', 'Réponse')),
        ),
      );
      final c = container(adapter);

      final result = await c
          .read(conversationRepositoryProvider)
          .send(conversation, 'Mon différentiel saute');

      expect(result.status, TurnStatus.completed);
      expect(result.assistantMessage?.text, 'Réponse');
      expect(
        adapter.requests.single.receiveTimeout,
        ConversationRepository.sendTimeout,
      );
    });

    test('429 : le motif dit ce qui débloque la suite', () async {
      for (final (reason, matcher) in [
        ('signup_required', isA<SignupRequiredException>()),
        ('free_allowance_exhausted', isA<FreeAllowanceExhaustedException>()),
        (null, isA<TooManyMessagesException>()),
      ]) {
        final c = container(
          ScriptedAdapter(
            (options) async => jsonResponse(429, {
              'code': 429,
              'reason': ?reason,
              'message': 'Limite atteinte.',
            }),
          ),
        );

        await expectLater(
          c.read(conversationRepositoryProvider).send(conversation, 'Bonjour'),
          throwsA(matcher),
        );
      }
    });

    test('503 service_saturated : réessayer plus tard', () async {
      final c = container(
        ScriptedAdapter(
          (options) async => jsonResponse(503, {
            'code': 503,
            'reason': 'service_saturated',
            'message': 'Hector est très sollicité en ce moment.',
          }),
        ),
      );

      await expectLater(
        c.read(conversationRepositoryProvider).send(conversation, 'Bonjour'),
        throwsA(
          isA<ServiceSaturatedException>().having(
            (e) => e.message,
            'message',
            'Hector est très sollicité en ce moment.',
          ),
        ),
      );
    });
  });

  group('conversation', () {
    Future<ProviderContainer> opened(ScriptedAdapter adapter) async {
      final c = container(adapter);
      // Le contrôleur charge les messages à sa création
      c.listen(chatControllerProvider(conversation), (_, _) {});
      await c.read(chatControllerProvider(conversation).notifier).load();

      return c;
    }

    test('un message envoyé reçoit sa réponse', () async {
      final adapter = ScriptedAdapter((options) async {
        return options.method == 'POST'
            ? jsonResponse(
                201,
                turn(
                  'completed',
                  answer: message(
                    'm2',
                    'assistant',
                    'Notez quand il déclenche.',
                  ),
                ),
              )
            : jsonResponse(200, page([]));
      });
      final c = await opened(adapter);

      await c
          .read(chatControllerProvider(conversation).notifier)
          .send('  Mon différentiel saute  ');

      final state = c.read(chatControllerProvider(conversation));
      expect(state.pending, isNull);
      expect(state.messages.map((m) => m.text), [
        'Mon différentiel saute',
        'Notez quand il déclenche.',
      ]);
      expect(adapter.requests.last.data, {'text': 'Mon différentiel saute'});
    });

    test(
      'tour échoué : le message de l\'API, et une relance possible',
      () async {
        final adapter = ScriptedAdapter((options) async {
          return options.method == 'POST'
              ? jsonResponse(
                  201,
                  turn(
                    'failed',
                    failure: {
                      'reason': 'unavailable',
                      'message': 'Service momentanément indisponible.',
                    },
                  ),
                )
              : jsonResponse(200, page([]));
        });
        final c = await opened(adapter);

        await c
            .read(chatControllerProvider(conversation).notifier)
            .send('Bonjour');

        final state = c.read(chatControllerProvider(conversation));
        expect(state.messages.single.role, MessageRole.user);
        expect(state.failures['m1'], 'Service momentanément indisponible.');
      },
    );

    test(
      'essai épuisé : rien n\'est envoyé, le message revient dans la saisie',
      () async {
        final adapter = ScriptedAdapter((options) async {
          return options.method == 'POST'
              ? jsonResponse(429, {
                  'code': 429,
                  'reason': 'signup_required',
                  'message': 'Créez un compte gratuit pour continuer.',
                })
              : jsonResponse(200, page([]));
        });
        final c = await opened(adapter);

        await c
            .read(chatControllerProvider(conversation).notifier)
            .send('Ma quatrième question');

        final state = c.read(chatControllerProvider(conversation));
        expect(state.pending, isNull);
        expect(state.limit, LimitNotice.signupRequired);
        expect(state.limitMessage, 'Créez un compte gratuit pour continuer.');
        expect(
          c.read(chatDraftsProvider)[conversation],
          'Ma quatrième question',
        );
      },
    );

    test(
      'coupure pendant l\'attente : on relit au lieu de renvoyer, sans doublon',
      () async {
        var serverAnswered = false;
        final adapter = ScriptedAdapter((options) async {
          if (options.method == 'POST') {
            // Le serveur reçoit et répond, mais la réponse se perd en route
            serverAnswered = true;
            throw DioException.connectionTimeout(
              timeout: ConversationRepository.sendTimeout,
              requestOptions: options,
            );
          }

          return jsonResponse(
            200,
            page(
              serverAnswered
                  ? [
                      message('m1', 'user', 'Bonjour'),
                      message('m2', 'assistant', 'Réponse arrivée'),
                    ]
                  : [],
            ),
          );
        });
        final c = await opened(adapter);
        final controller = c.read(
          chatControllerProvider(conversation).notifier,
        );

        await controller.send('Bonjour');
        expect(
          c.read(chatControllerProvider(conversation)).pending?.status,
          PendingStatus.connectionLost,
        );

        await controller.load();

        final state = c.read(chatControllerProvider(conversation));
        expect(state.pending, isNull);
        expect(state.messages.map((m) => m.text), [
          'Bonjour',
          'Réponse arrivée',
        ]);
        expect(
          adapter.calls.where((call) => call.startsWith('POST')),
          hasLength(1),
        );
      },
    );

    test('tour encore en cours : suivi jusqu\'à sa réponse', () async {
      final adapter = ScriptedAdapter((options) async {
        if (options.method == 'POST') {
          return jsonResponse(201, turn('in_progress'));
        }
        if (options.path.endsWith('/turns/t1')) {
          return jsonResponse(
            200,
            turn('completed', answer: message('m2', 'assistant', 'Plus tard')),
          );
        }

        return jsonResponse(200, page([]));
      });
      final c = await opened(adapter);

      await c
          .read(chatControllerProvider(conversation).notifier)
          .send('Bonjour');

      expect(
        c.read(chatControllerProvider(conversation)).messages.last.text,
        'Plus tard',
      );
      expect(adapter.calls, contains('GET /api/v1/turns/t1'));
    });
  });

  group('écran de conversation', () {
    testWidgets('mention IA permanente et bloc inconnu affiché par son repli', (
      tester,
    ) async {
      final adapter = ScriptedAdapter(
        (options) async => jsonResponse(
          200,
          page([
            message('m1', 'user', 'Bonjour'),
            {
              ...message('m2', 'assistant', ''),
              'blocks': [
                {
                  'type': 'diagnostic_step',
                  'version': 3,
                  'fallbackText': 'Étape 2 : coupez le circuit de la cuisine.',
                },
              ],
            },
          ]),
        ),
      );
      final c = container(adapter);
      await tester.pumpWidget(
        testApp(c, const ChatScreen(conversationUuid: conversation)),
      );
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('aiNotice')), findsOneWidget);
      expect(find.textContaining('assistant IA'), findsOneWidget);
      expect(
        find.text('Étape 2 : coupez le circuit de la cuisine.'),
        findsOneWidget,
      );
    });

    testWidgets('chaque nature d\'énoncé a sa pastille', (tester) async {
      Map<String, dynamic> block(String type, String text, {String? kind}) => {
        'type': type,
        'version': 1,
        'kind': ?kind,
        'text': text,
        'citations': <String>[],
        'fallbackText': text,
      };
      final adapter = ScriptedAdapter(
        (options) async => jsonResponse(
          200,
          page([
            {
              ...message('m2', 'assistant', ''),
              'blocks': [
                block(
                  'statement',
                  'Vous indiquez que ça saute la nuit.',
                  kind: 'declared_fact',
                ),
                block(
                  'statement',
                  'Un défaut d\'isolement est possible.',
                  kind: 'hypothesis',
                ),
                block(
                  'statement',
                  'Débranchez les appareils un à un.',
                  kind: 'recommendation',
                ),
                block(
                  'statement',
                  'Je ne connais pas l\'âge de l\'installation.',
                  kind: 'missing_information',
                ),
                block('question', 'Depuis quand ?'),
                block('warning', 'Ne touchez pas la prise.'),
                // Nature inconnue de cette version : texte de repli (CV5)
                {
                  ...block(
                    'statement',
                    'Trois cas semblables.',
                    kind: 'field_experience',
                  ),
                  'fallbackText': 'Retour du terrain : Trois cas semblables.',
                },
              ],
            },
          ]),
        ),
      );
      final c = container(adapter);
      await tester.pumpWidget(
        testApp(c, const ChatScreen(conversationUuid: conversation)),
      );
      await tester.pumpAndSettle();

      for (final label in [
        'Vous avez indiqué',
        'Hypothèse',
        'Recommandation',
        'Information manquante',
        'Question',
        'Attention',
      ]) {
        expect(find.text(label), findsOneWidget, reason: label);
      }
      expect(find.text('Un défaut d\'isolement est possible.'), findsOneWidget);
      expect(
        find.text('Retour du terrain : Trois cas semblables.'),
        findsOneWidget,
      );
      // Une teinte par nature ; seule la recommandation a une pastille pleine
      final chips = tester.widgetList<KindChip>(find.byType(KindChip)).toList();
      expect(chips.map((chip) => chip.tone), [
        Tone.neutral,
        Tone.caution,
        Tone.accent,
        Tone.info,
        Tone.info,
      ]);
      expect(chips.map((chip) => chip.solid), [
        false,
        false,
        true,
        false,
        false,
      ]);
    });

    testWidgets('une réponse sourcée montre ses sources, consultables', (
      tester,
    ) async {
      tester.view.physicalSize = const Size(1170, 2532);
      tester.view.devicePixelRatio = 3;
      addTearDown(tester.view.reset);
      Map<String, dynamic> statement(
        String kind,
        String text,
        List<String> citations,
      ) => {
        'type': 'statement',
        'version': 1,
        'kind': kind,
        'text': text,
        'citations': citations,
        'fallbackText': text,
      };
      final adapter = ScriptedAdapter((options) async {
        // État actuel du passage cité : sa version a été retirée depuis
        if (options.path.contains('/passages/')) {
          return jsonResponse(200, {
            'uuid': 'p1',
            'withdrawn': options.path.endsWith('/p1'),
          });
        }

        return jsonResponse(
          200,
          page([
            {
              ...message('m2', 'assistant', ''),
              'blocks': [
                statement(
                  'sourced_fact',
                  'Le bouton de test vérifie le mécanisme.',
                  ['p1', 'p2'],
                ),
                statement('recommendation', 'Essayez-le régulièrement.', []),
              ],
              'citations': [
                {
                  'id': 'p1',
                  'corpus': 'regulatory',
                  'title': 'Fiche différentiel',
                  'publisher': 'Hector',
                  'version': 'v1',
                  'publishedOn': '2026-11-01',
                  'effectiveFrom': '2026-12-01',
                  'effectiveUntil': null,
                  'page': 12,
                  'section': 'Vérifications',
                  'article': '4',
                  'excerpt': 'Le bouton de test vérifie le déclenchement.',
                },
                {
                  'id': 'p2',
                  'corpus': 'regulatory',
                  'title': 'Norme protégée',
                  'publisher': 'Éditeur',
                  'version': 'édition 2024',
                  'excerpt': null,
                },
              ],
            },
          ]),
        );
      });
      final c = container(adapter);
      await tester.pumpWidget(
        testApp(c, const ChatScreen(conversationUuid: conversation)),
      );
      await tester.pumpAndSettle();

      // Renvois sous l'énoncé sourcé seulement, et liste des sources sous la réponse
      expect(find.text('Fait sourcé'), findsOneWidget);
      expect(find.byType(SourceMarkers), findsOneWidget);
      expect(find.byKey(const Key('sourceMarker2')), findsOneWidget);
      expect(find.text('Sources'), findsOneWidget);
      expect(find.text('Fiche différentiel'), findsOneWidget);
      expect(find.text('Hector · version v1'), findsOneWidget);
      expect(find.text('Éditeur · version édition 2024'), findsOneWidget);

      await tester.tap(find.byKey(const Key('sourceMarker1')));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('sourceDetail')), findsOneWidget);
      expect(
        find.text('Le bouton de test vérifie le déclenchement.'),
        findsOneWidget,
      );
      expect(find.text('Publié le 1 nov. 2026'), findsOneWidget);
      expect(find.text('En vigueur à partir du 1 déc. 2026'), findsOneWidget);
      expect(find.text('Page 12'), findsOneWidget);
      expect(find.text('Article 4'), findsOneWidget);
      expect(find.text('Version retirée depuis'), findsOneWidget);
      expect(adapter.calls, contains('GET /api/v1/passages/p1'));

      // Une source dont la licence interdit l'extrait : un simple renvoi
      await tester.tapAt(const Offset(20, 20));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('source2')));
      await tester.pumpAndSettle();

      expect(find.textContaining('ne peut pas être affiché'), findsOneWidget);
      expect(find.text('Version retirée depuis'), findsNothing);
    });

    testWidgets('envoyer un message affiche la réponse', (tester) async {
      final adapter = ScriptedAdapter((options) async {
        return options.method == 'POST'
            ? jsonResponse(
                201,
                turn(
                  'completed',
                  question: 'Mon différentiel saute',
                  answer: message(
                    'm2',
                    'assistant',
                    'Notez quand il déclenche.',
                  ),
                ),
              )
            : jsonResponse(200, page([]));
      });
      final c = container(adapter);
      await tester.pumpWidget(
        testApp(c, const ChatScreen(conversationUuid: conversation)),
      );
      await tester.pumpAndSettle();

      expect(
        tester.widget<IconButton>(find.byKey(const Key('send'))).onPressed,
        isNull,
      );
      await tester.enterText(
        find.byKey(const Key('composer')),
        'Mon différentiel saute',
      );
      await tester.pump();
      await tester.tap(find.byKey(const Key('send')));
      await tester.pumpAndSettle();

      expect(find.text('Mon différentiel saute'), findsOneWidget);
      expect(find.text('Notez quand il déclenche.'), findsOneWidget);
      expect(
        tester
            .widget<TextField>(find.byKey(const Key('composer')))
            .controller
            ?.text,
        '',
      );
    });

    testWidgets('essai épuisé : créer un compte ou se connecter', (
      tester,
    ) async {
      final adapter = ScriptedAdapter((options) async {
        return options.method == 'POST'
            ? jsonResponse(429, {
                'code': 429,
                'reason': 'signup_required',
                'message': 'Créez un compte gratuit pour continuer.',
              })
            : jsonResponse(200, page([]));
      });
      final c = container(adapter);
      await tester.pumpWidget(
        testApp(c, const ChatScreen(conversationUuid: conversation)),
      );
      await tester.pumpAndSettle();

      await tester.enterText(find.byKey(const Key('composer')), 'Ma question');
      await tester.pump();
      await tester.tap(find.byKey(const Key('send')));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('limitCard')), findsOneWidget);
      expect(find.byKey(const Key('limitCreateAccount')), findsOneWidget);
      expect(find.byKey(const Key('limitLogIn')), findsOneWidget);
      // Le message attend dans la zone de saisie
      expect(
        tester
            .widget<TextField>(find.byKey(const Key('composer')))
            .controller
            ?.text,
        'Ma question',
      );
    });
  });
}
