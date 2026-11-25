/// Calculs (réponses de /api/v1/calculators et /api/v1/calculations).
///
/// Un calcul est fait par l'API, jamais par l'application ni par le modèle de
/// langage : l'application envoie des valeurs avec leur unité et affiche ce
/// qu'elle reçoit (entrées, hypothèses, étapes, résultat, version).
library;

double _number(Object? value) => (value as num? ?? 0).toDouble();

List<String> _texts(Object? value) =>
    (value as List<dynamic>? ?? const []).whereType<String>().toList();

/// Entrée attendue par un calculateur. Toutes sont obligatoires : l'API ne
/// suppose aucune valeur.
class CalculatorInput {
  const CalculatorInput({
    required this.key,
    required this.label,
    required this.symbol,
    required this.unit,
    required this.units,
    required this.above,
    required this.max,
  });

  factory CalculatorInput.fromJson(Map<String, dynamic> json) {
    return CalculatorInput(
      key: json['key'] as String,
      label: json['label'] as String? ?? '',
      symbol: json['symbol'] as String? ?? '',
      unit: json['unit'] as String? ?? '',
      units: _texts(json['units']),
      above: _number(json['above']),
      max: _number(json['max']),
    );
  }

  final String key;
  final String label;
  final String symbol;

  /// Unité de base ; vide pour un rapport sans unité (facteur de puissance).
  final String unit;

  /// Unités acceptées, l'unité de base en premier.
  final List<String> units;

  /// Borne basse, exclue, dans l'unité de base.
  final double above;

  /// Borne haute, incluse, dans l'unité de base.
  final double max;

  /// Faux pour un rapport, qui se donne sans unité.
  bool get hasUnit => unit.isNotEmpty;
}

/// Résultat annoncé par un calculateur.
class CalculatorOutput {
  const CalculatorOutput({
    required this.key,
    required this.label,
    required this.symbol,
    required this.unit,
    required this.roundingRule,
  });

  factory CalculatorOutput.fromJson(Map<String, dynamic> json) {
    return CalculatorOutput(
      key: json['key'] as String? ?? '',
      label: json['label'] as String? ?? '',
      symbol: json['symbol'] as String? ?? '',
      unit: json['unit'] as String? ?? '',
      roundingRule: json['roundingRule'] as String? ?? '',
    );
  }

  final String key;
  final String label;
  final String symbol;
  final String unit;

  /// Règle d'arrondi, en clair.
  final String roundingRule;
}

/// Un calcul proposé : ce qu'il demande, ce qu'il suppose, sa formule et la
/// source de celle-ci.
class Calculator {
  const Calculator({
    required this.key,
    required this.version,
    required this.title,
    required this.description,
    required this.inputs,
    required this.output,
    required this.assumptions,
    required this.formula,
    required this.source,
  });

  factory Calculator.fromJson(Map<String, dynamic> json) {
    return Calculator(
      key: json['key'] as String,
      version: json['version'] as int? ?? 1,
      title: json['title'] as String? ?? '',
      description: json['description'] as String? ?? '',
      inputs: (json['inputs'] as List<dynamic>? ?? const [])
          .whereType<Map<String, dynamic>>()
          .map(CalculatorInput.fromJson)
          .toList(),
      output: CalculatorOutput.fromJson(
        json['output'] as Map<String, dynamic>? ?? const {},
      ),
      assumptions: _texts(json['assumptions']),
      formula: json['formula'] as String? ?? '',
      source: json['source'] as String? ?? '',
    );
  }

  final String key;
  final int version;
  final String title;
  final String description;
  final List<CalculatorInput> inputs;
  final CalculatorOutput output;
  final List<String> assumptions;
  final String formula;
  final String source;
}

/// Valeur donnée pour une entrée, avec son unité. Sans unité pour un rapport.
class QuantityInput {
  const QuantityInput(this.value, [this.unit]);

  final double value;
  final String? unit;

  Map<String, dynamic> toJson() => {'value': value, 'unit': ?unit};
}

/// Entrée d'un calcul fait, telle qu'elle a été donnée.
class CalculationInput {
  const CalculationInput({
    required this.key,
    required this.label,
    required this.symbol,
    required this.value,
    required this.unit,
  });

  factory CalculationInput.fromJson(Map<String, dynamic> json) {
    return CalculationInput(
      key: json['key'] as String? ?? '',
      label: json['label'] as String? ?? '',
      symbol: json['symbol'] as String? ?? '',
      value: _number(json['value']),
      unit: json['unit'] as String? ?? '',
    );
  }

  final String key;
  final String label;
  final String symbol;
  final double value;
  final String unit;
}

/// Étape d'un calcul. [value] est arrondie pour l'affichage.
class CalculationStep {
  const CalculationStep({
    required this.label,
    required this.expression,
    required this.value,
    required this.unit,
  });

  factory CalculationStep.fromJson(Map<String, dynamic> json) {
    return CalculationStep(
      label: json['label'] as String? ?? '',
      expression: json['expression'] as String? ?? '',
      value: _number(json['value']),
      unit: json['unit'] as String? ?? '',
    );
  }

  final String label;

  /// Expression écrite avec les symboles des entrées (« S = P / cos φ »).
  final String expression;
  final double value;
  final String unit;
}

/// Résultat d'un calcul.
class CalculationOutcome {
  const CalculationOutcome({
    required this.label,
    required this.symbol,
    required this.value,
    required this.unit,
    required this.exact,
    required this.roundingRule,
  });

  factory CalculationOutcome.fromJson(Map<String, dynamic> json) {
    return CalculationOutcome(
      label: json['label'] as String? ?? '',
      symbol: json['symbol'] as String? ?? '',
      value: _number(json['value']),
      unit: json['unit'] as String? ?? '',
      exact: _number(json['exact']),
      roundingRule: json['roundingRule'] as String? ?? '',
    );
  }

  final String label;
  final String symbol;

  /// Valeur arrondie selon [roundingRule].
  final double value;
  final String unit;

  /// Valeur avant arrondi.
  final double exact;
  final String roundingRule;
}

/// Un calcul que l'API a refusé de faire, avec ses motifs tels qu'elle les
/// donne : valeur manquante, sans unité, hors du domaine couvert…
class CalculationRefusal {
  const CalculationRefusal({required this.title, required this.reasons});

  /// Le refus d'un bloc de conversation, ou null s'il n'a pas la forme
  /// attendue.
  static CalculationRefusal? tryParse(Map<String, dynamic> json) {
    final reasons = _texts(json['reasons']);
    if (reasons.isEmpty) {
      return null;
    }
    final calculator = json['calculator'];

    return CalculationRefusal(
      title: calculator is Map<String, dynamic>
          ? calculator['title'] as String? ?? ''
          : '',
      reasons: reasons,
    );
  }

  /// Nom du calcul demandé.
  final String title;
  final List<String> reasons;
}

/// Un calcul fait et enregistré par l'API, tel qu'il a été fait.
class Calculation {
  const Calculation({
    required this.uuid,
    required this.calculatorKey,
    required this.calculatorVersion,
    required this.title,
    required this.formula,
    required this.source,
    required this.assumptions,
    required this.inputs,
    required this.steps,
    required this.result,
    required this.createdAt,
  });

  /// Le calcul d'un bloc de conversation, ou null s'il n'a pas la forme
  /// attendue : le bloc s'affiche alors par son texte de repli.
  static Calculation? tryParse(Object? json) {
    if (json is! Map<String, dynamic> ||
        json['uuid'] is! String ||
        json['calculator'] is! Map<String, dynamic> ||
        json['result'] is! Map<String, dynamic> ||
        DateTime.tryParse(json['createdAt'] as String? ?? '') == null) {
      return null;
    }

    return Calculation.fromJson(json);
  }

  factory Calculation.fromJson(Map<String, dynamic> json) {
    final calculator = json['calculator'] as Map<String, dynamic>? ?? const {};

    return Calculation(
      uuid: json['uuid'] as String,
      calculatorKey: calculator['key'] as String? ?? '',
      calculatorVersion: calculator['version'] as int? ?? 1,
      title: calculator['title'] as String? ?? '',
      formula: json['formula'] as String? ?? '',
      source: json['source'] as String? ?? '',
      assumptions: _texts(json['assumptions']),
      inputs: (json['inputs'] as List<dynamic>? ?? const [])
          .whereType<Map<String, dynamic>>()
          .map(CalculationInput.fromJson)
          .toList(),
      steps: (json['steps'] as List<dynamic>? ?? const [])
          .whereType<Map<String, dynamic>>()
          .map(CalculationStep.fromJson)
          .toList(),
      result: CalculationOutcome.fromJson(
        json['result'] as Map<String, dynamic>? ?? const {},
      ),
      createdAt: DateTime.parse(json['createdAt'] as String),
    );
  }

  final String uuid;
  final String calculatorKey;

  /// Version du calculateur lors du calcul : un calcul passé garde la sienne.
  final int calculatorVersion;
  final String title;
  final String formula;

  /// Source de la formule : loi physique, ou référence d'un texte.
  final String source;
  final List<String> assumptions;
  final List<CalculationInput> inputs;
  final List<CalculationStep> steps;
  final CalculationOutcome result;
  final DateTime createdAt;
}
