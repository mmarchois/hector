import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../l10n/app_localizations.dart';
import '../../data/api.dart';
import '../../domain/conversation.dart';
import '../../theme.dart';
import 'kit.dart';

/// Renvois d'un énoncé à ses sources : une pastille numérotée par source.
class SourceMarkers extends StatelessWidget {
  const SourceMarkers({super.key, required this.numbers, required this.onTap});

  /// Numéro de chaque source citée, dans la liste des sources du message.
  final List<int> numbers;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tokens = HectorTokens.of(context);
    const radius = BorderRadius.all(Radius.circular(99));

    return Wrap(
      spacing: 6,
      runSpacing: 6,
      children: [
        for (final number in numbers)
          Material(
            color: tokens.background(Tone.positive),
            borderRadius: radius,
            child: InkWell(
              key: Key('sourceMarker$number'),
              borderRadius: radius,
              onTap: () => onTap(number),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(8, 5, 10, 5),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      Icons.menu_book_outlined,
                      size: 14,
                      color: tokens.foreground(Tone.positive),
                    ),
                    const SizedBox(width: 5),
                    Text(
                      '$number',
                      style: theme.textTheme.labelMedium?.copyWith(
                        color: tokens.foreground(Tone.positive),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
      ],
    );
  }
}

/// Sources d'une réponse, numérotées : titre, éditeur et version de chacune.
class SourceList extends StatelessWidget {
  const SourceList({super.key, required this.citations, required this.onTap});

  final List<Citation> citations;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final tokens = HectorTokens.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          l10n.chatSourcesTitle,
          style: theme.textTheme.titleSmall?.copyWith(color: tokens.muted),
        ),
        const SizedBox(height: 8),
        GroupedList(
          children: [
            for (final (index, citation) in citations.indexed)
              GroupedRow(
                key: Key('source${index + 1}'),
                leading: _Number(index + 1),
                title: citation.title,
                detail: l10n.sourceVersion(
                  citation.publisher,
                  citation.version,
                ),
                onTap: () => onTap(index + 1),
              ),
          ],
        ),
      ],
    );
  }
}

class _Number extends StatelessWidget {
  const _Number(this.number);

  final int number;

  @override
  Widget build(BuildContext context) {
    final tokens = HectorTokens.of(context);

    return Container(
      width: 28,
      height: 28,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: tokens.background(Tone.positive),
        shape: BoxShape.circle,
      ),
      child: Text(
        '$number',
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
          color: tokens.foreground(Tone.positive),
        ),
      ),
    );
  }
}

/// Ouvre le détail d'une source citée.
Future<void> showSource(BuildContext context, Citation citation, int number) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (context) => DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.6,
      maxChildSize: 0.92,
      builder: (context, controller) => SingleChildScrollView(
        controller: controller,
        padding: const EdgeInsets.fromLTRB(24, 0, 24, 32),
        child: SourceDetail(citation: citation, number: number),
      ),
    ),
  );
}

/// Détail d'une source : ce que le serveur en a enregistré avec la réponse
/// (titre, version, dates, localisation, extrait selon la licence), et son
/// état actuel si l'API répond.
class SourceDetail extends ConsumerWidget {
  const SourceDetail({super.key, required this.citation, required this.number});

  final Citation citation;
  final int number;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final tokens = HectorTokens.of(context);
    final localizations = MaterialLocalizations.of(context);
    String date(DateTime value) => localizations.formatShortDate(value);
    // Hors ligne ou en erreur, on montre ce qui a été enregistré avec la réponse
    final withdrawn =
        ref.watch(citedPassageProvider(citation.id)).value?.withdrawn ?? false;

    final facts = [
      if (citation.publishedOn case final value?)
        l10n.sourcePublishedOn(date(value)),
      if (citation.effectiveFrom case final value?)
        l10n.sourceEffectiveFrom(date(value)),
      if (citation.effectiveUntil case final value?)
        l10n.sourceEffectiveUntil(date(value)),
    ];
    final location = [
      if (citation.page case final value?) l10n.sourcePage(value),
      ?citation.section,
      if (citation.article case final value?) l10n.sourceArticle(value),
    ];

    return Column(
      key: const Key('sourceDetail'),
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            _Number(number),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                l10n.sourceVersion(citation.publisher, citation.version),
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: tokens.muted,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Text(citation.title, style: theme.textTheme.headlineSmall),
        if (withdrawn) ...[
          const SizedBox(height: 12),
          KindChip(
            icon: Icons.history_rounded,
            label: l10n.sourceWithdrawn,
            tone: Tone.caution,
          ),
        ],
        for (final line in [...facts, ...location]) ...[
          const SizedBox(height: 6),
          Text(
            line,
            style: theme.textTheme.bodyMedium?.copyWith(color: tokens.muted),
          ),
        ],
        const SizedBox(height: 18),
        SoftCard(
          child: citation.excerpt == null
              ? Text(
                  l10n.sourceNoExcerpt,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: tokens.muted,
                  ),
                )
              : SelectableText(
                  citation.excerpt!,
                  style: theme.textTheme.bodyLarge,
                ),
        ),
      ],
    );
  }
}
