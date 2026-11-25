import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hector_mobile/src/data/api.dart';
import 'package:hector_mobile/src/data/api_errors.dart';
import 'package:hector_mobile/src/data/auth/token_storage.dart';
import 'package:hector_mobile/src/domain/calculation.dart';

import 'support/fakes.dart';

const _calculatorJson = <String, dynamic>{
  'key': 'single_phase_current',
  'version': 1,
  'title': 'Courant absorbé en monophasé',
  'description': 'Calcule le courant absorbé par un récepteur.',
  'inputs': [
    {
      'key': 'power',
      'label': 'Puissance active',
      'symbol': 'P',
      'unit': 'W',
      'units': ['W', 'kW'],
      'above': 0,
      'max': 10000000,
    },
    {
      'key': 'powerFactor',
      'label': 'Facteur de puissance',
      'symbol': 'cos φ',
      'unit': '',
      'units': [''],
      'above': 0,
      'max': 1,
    },
  ],
  'output': {
    'key': 'current',
    'label': 'Courant absorbé',
    'symbol': 'I',
    'unit': 'A',
    'rounding': 'up',
    'roundingRule': 'Arrondi par excès, à quatre chiffres significatifs.',
    'max': 10000,
  },
  'assumptions': ['Régime sinusoïdal établi.'],
  'formula': 'I = P / (U × cos φ)',
  'source': 'Loi physique : puissance active en régime sinusoïdal monophasé.',
};

const _calculationJson = <String, dynamic>{
  'uuid': 'calc-1',
  'calculator': {
    'key': 'single_phase_current',
    'version': 1,
    'title': 'Courant absorbé en monophasé',
  },
  'formula': 'I = P / (U × cos φ)',
  'source': 'Loi physique : puissance active en régime sinusoïdal monophasé.',
  'assumptions': ['Régime sinusoïdal établi.'],
  'inputs': [
    {
      'key': 'power',
      'label': 'Puissance active',
      'symbol': 'P',
      'value': 3,
      'unit': 'kW',
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
  ],
  'result': {
    'key': 'current',
    'label': 'Courant absorbé',
    'symbol': 'I',
    'value': 14.5,
    'unit': 'A',
    'exact': 14.492753623188406,
    'roundingRule': 'Arrondi par excès, à quatre chiffres significatifs.',
  },
  'createdAt': '2026-11-25T10:00:00+00:00',
};

void main() {
  late InMemoryTokenStorage storage;

  setUp(() {
    storage = InMemoryTokenStorage()
      ..tokens = const AuthTokens(accessToken: 'a1', refreshToken: 'r1');
  });

  test(
    'calculators : les calculs proposés, avec ce qu\'ils annoncent',
    () async {
      final adapter = ScriptedAdapter(
        (options) async => jsonResponse(200, {
          'items': [_calculatorJson],
        }),
      );
      final container = buildContainer(storage, adapter);
      addTearDown(container.dispose);

      final calculators = await container
          .read(calculationRepositoryProvider)
          .calculators();

      expect(adapter.calls, ['GET /api/v1/calculators']);
      final calculator = calculators.single;
      expect(calculator.key, 'single_phase_current');
      expect(calculator.version, 1);
      expect(calculator.title, 'Courant absorbé en monophasé');
      expect(calculator.formula, 'I = P / (U × cos φ)');
      expect(calculator.assumptions, ['Régime sinusoïdal établi.']);
      expect(calculator.output.unit, 'A');
      expect(calculator.output.roundingRule, startsWith('Arrondi par excès'));
      // Un nombre entier de l'API reste un nombre décimal dans l'application
      expect(calculator.inputs.first.units, ['W', 'kW']);
      expect(calculator.inputs.first.above, 0.0);
      expect(calculator.inputs.first.max, 10000000.0);
      expect(calculator.inputs.first.hasUnit, isTrue);
      // Un rapport se donne sans unité
      expect(calculator.inputs.last.hasUnit, isFalse);
    },
  );

  test('run : chaque valeur part avec son unité, sauf un rapport', () async {
    final adapter = ScriptedAdapter(
      (options) async => jsonResponse(201, _calculationJson),
    );
    final container = buildContainer(storage, adapter);
    addTearDown(container.dispose);

    final calculation = await container
        .read(calculationRepositoryProvider)
        .run('single_phase_current', const {
          'power': QuantityInput(3, 'kW'),
          'voltage': QuantityInput(230, 'V'),
          'powerFactor': QuantityInput(0.9),
        });

    expect(adapter.calls, ['POST /api/v1/calculations']);
    expect(adapter.requests.single.headers['Authorization'], 'Bearer a1');
    expect(adapter.requests.single.data, {
      'calculator': 'single_phase_current',
      'inputs': {
        'power': {'value': 3.0, 'unit': 'kW'},
        'voltage': {'value': 230.0, 'unit': 'V'},
        'powerFactor': {'value': 0.9},
      },
    });

    expect(calculation.uuid, 'calc-1');
    expect(calculation.calculatorKey, 'single_phase_current');
    expect(calculation.calculatorVersion, 1);
    expect(calculation.title, 'Courant absorbé en monophasé');
    expect(calculation.inputs.first.value, 3.0);
    expect(calculation.inputs.first.unit, 'kW');
    expect(calculation.steps.single.expression, 'S = P / cos φ');
    expect(calculation.steps.single.value, 3333.0);
    expect(calculation.result.value, 14.5);
    expect(calculation.result.exact, closeTo(14.4928, 0.0001));
    expect(calculation.result.unit, 'A');
    expect(calculation.createdAt, DateTime.utc(2026, 11, 25, 10));
  });

  test('run : un refus donne son motif pour chaque entrée', () async {
    final adapter = ScriptedAdapter(
      (options) async => jsonResponse(422, {
        'code': 422,
        'message': 'Validation failed.',
        'violations': [
          {
            'property': 'inputs.voltage',
            'message': 'Tension (U) : indiquez l\'unité (V, mV, kV).',
          },
          {
            'property': 'inputs.powerFactor',
            'message': 'Facteur de puissance (cos φ) : valeur manquante.',
          },
        ],
      }),
    );
    final container = buildContainer(storage, adapter);
    addTearDown(container.dispose);

    await expectLater(
      container.read(calculationRepositoryProvider).run(
        'single_phase_current',
        const {'power': QuantityInput(3, 'kW'), 'voltage': QuantityInput(230)},
      ),
      throwsA(
        isA<CalculationRefusedException>()
            .having((e) => e.inputErrors, 'inputErrors', {
              'voltage': 'Tension (U) : indiquez l\'unité (V, mV, kV).',
              'powerFactor': 'Facteur de puissance (cos φ) : valeur manquante.',
            })
            .having((e) => e.calculatorError, 'calculatorError', isNull)
            .having((e) => e.resultError, 'resultError', isNull)
            .having((e) => e.reasons, 'reasons', hasLength(2)),
      ),
    );
  });

  test('run : calculateur inconnu, ou résultat hors du domaine', () async {
    var violations = <Map<String, dynamic>>[
      {'property': 'calculator', 'message': 'Calculateur inconnu.'},
    ];
    final adapter = ScriptedAdapter(
      (options) async => jsonResponse(422, {
        'code': 422,
        'message': 'Validation failed.',
        'violations': violations,
      }),
    );
    final container = buildContainer(storage, adapter);
    addTearDown(container.dispose);
    final repository = container.read(calculationRepositoryProvider);

    await expectLater(
      repository.run('voltage_drop', const {}),
      throwsA(
        isA<CalculationRefusedException>()
            .having(
              (e) => e.calculatorError,
              'calculatorError',
              'Calculateur inconnu.',
            )
            .having((e) => e.inputErrors, 'inputErrors', isEmpty),
      ),
    );

    violations = [
      {'property': 'inputs', 'message': 'Le résultat dépasse 1 000 V.'},
    ];
    await expectLater(
      repository.run('ohm_voltage', const {
        'resistance': QuantityInput(1, 'MΩ'),
        'current': QuantityInput(10, 'A'),
      }),
      throwsA(
        isA<CalculationRefusedException>()
            .having(
              (e) => e.resultError,
              'resultError',
              'Le résultat dépasse 1 000 V.',
            )
            .having((e) => e.reasons, 'reasons', [
              'Le résultat dépasse 1 000 V.',
            ]),
      ),
    );
  });

  test('run : trop de calculs à la suite', () async {
    final adapter = ScriptedAdapter(
      (options) async => jsonResponse(429, {
        'code': 429,
        'message': 'Vous lancez trop de calculs à la suite.',
      }),
    );
    final container = buildContainer(storage, adapter);
    addTearDown(container.dispose);

    await expectLater(
      container
          .read(calculationRepositoryProvider)
          .run('ohm_voltage', const {}),
      throwsA(
        isA<TooManyCalculationsException>().having(
          (e) => e.message,
          'message',
          'Vous lancez trop de calculs à la suite.',
        ),
      ),
    );
  });

  test('run : une panne réseau reste une DioException', () async {
    final adapter = ScriptedAdapter((options) async => throw offline(options));
    final container = buildContainer(storage, adapter);
    addTearDown(container.dispose);

    await expectLater(
      container
          .read(calculationRepositoryProvider)
          .run('ohm_voltage', const {}),
      throwsA(isA<DioException>()),
    );
  });

  test('calculation : relit un calcul, ou dit qu\'il n\'existe pas', () async {
    final adapter = ScriptedAdapter(
      (options) async => options.path.endsWith('/calc-1')
          ? jsonResponse(200, _calculationJson)
          : jsonResponse(404, {'code': 404, 'message': 'Calcul introuvable.'}),
    );
    final container = buildContainer(storage, adapter);
    addTearDown(container.dispose);
    final repository = container.read(calculationRepositoryProvider);

    final calculation = await repository.calculation('calc-1');
    expect(calculation.result.value, 14.5);
    expect(adapter.calls, ['GET /api/v1/calculations/calc-1']);

    await expectLater(
      repository.calculation('inconnu'),
      throwsA(isA<CalculationNotFoundException>()),
    );
  });
}
