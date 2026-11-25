import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../l10n/app_localizations.dart';
import '../../domain/calculation.dart';
import '../../theme.dart';
import 'kit.dart';

/// Un calcul fait par l'API, montré tel qu'il a été fait : résultat, valeurs
/// données, étapes, hypothèses, formule, source et version du calculateur.
/// Aucun de ces nombres ne vient du modèle de langage.
class CalculationCard extends StatelessWidget {
  const CalculationCard({super.key, required this.calculation, this.caption});

  final Calculation calculation;

  /// Quelques mots de l'assistant sur ce qui est calculé.
  final String? caption;

  /// Chiffres significatifs de la valeur exacte.
  static const _exactDigits = 6;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tokens = HectorTokens.of(context);
    final l10n = AppLocalizations.of(context);
    final numbers = NumberFormat.decimalPattern(
      Localizations.localeOf(context).toLanguageTag(),
    )..maximumFractionDigits = 12;
    String quantity(double value, String unit) =>
        unit.isEmpty ? numbers.format(value) : '${numbers.format(value)} $unit';

    final result = calculation.result;
    final exact = _significant(result.exact, _exactDigits);
    final muted = theme.textTheme.bodyMedium?.copyWith(color: tokens.muted);
    final caption = this.caption?.trim() ?? '';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        KindChip(
          icon: Icons.calculate_outlined,
          label: l10n.statementCalculationResult,
          tone: Tone.positive,
        ),
        if (caption.isNotEmpty) ...[
          const SizedBox(height: 8),
          SelectableText(caption, style: theme.textTheme.bodyLarge),
        ],
        const SizedBox(height: 10),
        SoftCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(result.label, style: muted),
              const SizedBox(height: 2),
              SelectableText(
                '${result.symbol} = ${quantity(result.value, result.unit)}',
                key: const Key('calculationResult'),
                style: theme.textTheme.headlineMedium,
              ),
              if (exact != result.value) ...[
                const SizedBox(height: 4),
                Text(
                  l10n.calculationExact(quantity(exact, result.unit)),
                  style: muted,
                ),
              ],
              const SizedBox(height: 4),
              Text(result.roundingRule, style: muted),
              _Section(l10n.calculationInputs),
              for (final input in calculation.inputs)
                Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.baseline,
                    textBaseline: TextBaseline.alphabetic,
                    children: [
                      Expanded(
                        child: Text(
                          '${input.label} (${input.symbol})',
                          style: theme.textTheme.bodyLarge,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Text(
                        quantity(input.value, input.unit),
                        style: theme.textTheme.titleMedium,
                      ),
                    ],
                  ),
                ),
              if (calculation.steps.isNotEmpty) ...[
                _Section(l10n.calculationSteps),
                for (final step in calculation.steps)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(step.label, style: muted),
                        Text(
                          '${step.expression} = ${quantity(step.value, step.unit)}',
                          style: theme.textTheme.bodyLarge,
                        ),
                      ],
                    ),
                  ),
              ],
              if (calculation.assumptions.isNotEmpty) ...[
                _Section(l10n.calculationAssumptions),
                for (final assumption in calculation.assumptions)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 6),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('•  ', style: muted),
                        Expanded(
                          child: Text(
                            assumption,
                            style: theme.textTheme.bodyMedium,
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
              Divider(height: 28, color: tokens.softer),
              Text(calculation.formula, style: theme.textTheme.titleSmall),
              const SizedBox(height: 4),
              Text(l10n.calculationSource(calculation.source), style: muted),
              const SizedBox(height: 4),
              Text(
                l10n.calculationVersion(
                  calculation.title,
                  '${calculation.calculatorVersion}',
                ),
                key: const Key('calculationVersion'),
                style: muted,
              ),
            ],
          ),
        ),
      ],
    );
  }

  /// [value] ramenée à [digits] chiffres significatifs.
  static double _significant(double value, int digits) {
    if (value == 0 || !value.isFinite) {
      return value;
    }
    final decimals = math.max(
      0,
      digits - 1 - (math.log(value.abs()) / math.ln10).floor(),
    );
    final factor = math.pow(10, decimals);

    return (value * factor).round() / factor;
  }
}

/// Un calcul que l'API a refusé de faire : ses motifs, tels qu'elle les donne.
class CalculationRefusalCard extends StatelessWidget {
  const CalculationRefusalCard({super.key, required this.refusal});

  final CalculationRefusal refusal;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tokens = HectorTokens.of(context);
    final color = tokens.foreground(Tone.caution);

    return SoftCard(
      color: tokens.background(Tone.caution),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.calculate_outlined, size: 20, color: color),
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  AppLocalizations.of(context).calculationRefusedTitle,
                  style: theme.textTheme.titleSmall?.copyWith(
                    color: color,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
          if (refusal.title.isNotEmpty) ...[
            const SizedBox(height: 2),
            Text(
              refusal.title,
              style: theme.textTheme.bodyMedium?.copyWith(color: color),
            ),
          ],
          const SizedBox(height: 8),
          for (final (index, reason) in refusal.reasons.indexed) ...[
            if (index > 0) const SizedBox(height: 6),
            SelectableText(
              reason,
              style: theme.textTheme.titleMedium?.copyWith(height: 1.45),
            ),
          ],
        ],
      ),
    );
  }
}

/// Intitulé d'une partie du calcul.
class _Section extends StatelessWidget {
  const _Section(this.title);

  final String title;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 16, bottom: 8),
      child: Text(
        title,
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
          color: HectorTokens.of(context).muted,
        ),
      ),
    );
  }
}
