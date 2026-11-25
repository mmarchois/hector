import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../l10n/app_localizations.dart';
import '../domain/conversation.dart';
import '../state/chat_controller.dart';
import '../theme.dart';
import 'widgets/calculation.dart';
import 'widgets/kit.dart';
import 'widgets/sources.dart';

/// Une conversation avec Hector.
class ChatScreen extends ConsumerStatefulWidget {
  const ChatScreen({super.key, required this.conversationUuid});

  final String conversationUuid;

  /// Longueur maximale d'un message (validation de l'API).
  static const maxLength = 4000;

  @override
  ConsumerState<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends ConsumerState<ChatScreen> {
  late final TextEditingController _composer;

  String get _uuid => widget.conversationUuid;

  @override
  void initState() {
    super.initState();
    _composer = TextEditingController(
      text: ref.read(chatDraftsProvider)[_uuid] ?? '',
    );
  }

  @override
  void dispose() {
    _composer.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final text = _composer.text;
    if (text.trim().isEmpty) {
      return;
    }
    _composer.clear();
    ref.read(chatDraftsProvider.notifier).clear(_uuid);
    await ref.read(chatControllerProvider(_uuid).notifier).send(text);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final chat = ref.watch(chatControllerProvider(_uuid));
    final controller = ref.read(chatControllerProvider(_uuid).notifier);

    // Un message refusé faute d'allocation revient dans la zone de saisie
    ref.listen(chatDraftsProvider.select((drafts) => drafts[_uuid]), (_, next) {
      if (next != null && next != _composer.text) {
        _composer.text = next;
      }
    });

    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            const HectorMark(size: 36),
            const SizedBox(width: 10),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(l10n.appTitle, style: theme.textTheme.titleMedium),
                Text(
                  l10n.chatAssistantSubtitle,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: HectorTokens.of(context).muted,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(child: _messages(l10n, chat, controller)),
            if (chat.limit != null)
              _LimitCard(
                notice: chat.limit!,
                message: chat.limitMessage ?? '',
                conversationUuid: _uuid,
              ),
            _Composer(
              controller: _composer,
              sending: chat.sending,
              onChanged: (text) =>
                  ref.read(chatDraftsProvider.notifier).save(_uuid, text),
              onSend: _send,
            ),
            _AiNotice(l10n.chatAiNotice),
          ],
        ),
      ),
    );
  }

  Widget _messages(
    AppLocalizations l10n,
    ChatState chat,
    ChatController controller,
  ) {
    if (chat.loading && chat.messages.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (chat.loadError && chat.messages.isEmpty) {
      return _Notice(
        text: l10n.chatLoadError,
        action: OutlinedButton(
          onPressed: controller.load,
          child: Text(l10n.chatReload),
        ),
      );
    }

    final pending = chat.pending;
    if (chat.messages.isEmpty && pending == null && !chat.hasMore) {
      return _Notice(
        mark: true,
        text: l10n.chatEmpty,
        detail: l10n.chatEmptyHint,
      );
    }

    final items = <Widget>[
      if (chat.hasMore)
        Center(
          child: TextButton(
            onPressed: chat.loadingOlder ? null : controller.loadOlder,
            child: Text(l10n.chatLoadOlder),
          ),
        ),
      for (final message in chat.messages) ...[
        if (message.role == MessageRole.user)
          _UserMessage(message.text)
        else
          _AssistantMessage(message),
        if (chat.failures[message.uuid] case final failure?)
          _Status(
            text: failure,
            action: TextButton(
              onPressed: chat.sending ? null : () => controller.retry(message),
              child: Text(l10n.chatRetry),
            ),
          ),
      ],
      if (pending != null) ...[
        _UserMessage(pending.text, faded: true),
        switch (pending.status) {
          PendingStatus.sending => const _Thinking(),
          PendingStatus.connectionLost => _Status(
            text: l10n.chatConnectionLost,
            action: TextButton(
              onPressed: controller.load,
              child: Text(l10n.chatReload),
            ),
          ),
          PendingStatus.rejected => _Status(
            text: pending.error ?? l10n.chatNotSent,
            action: Wrap(
              children: [
                TextButton(
                  onPressed: () {
                    controller.dismissPending();
                    controller.send(pending.text);
                  },
                  child: Text(l10n.chatRetry),
                ),
                TextButton(
                  onPressed: controller.dismissPending,
                  child: Text(l10n.chatDismiss),
                ),
              ],
            ),
          ),
        },
      ],
    ];

    // Liste inversée : les messages les plus récents restent en bas, à portée
    return ListView.builder(
      key: const Key('messages'),
      reverse: true,
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
      itemCount: items.length,
      itemBuilder: (context, index) => items[items.length - 1 - index],
    );
  }
}

/// Mention permanente : on parle à une IA (règlement européen sur l'IA, art. 50).
/// Sous la zone de saisie, là où les assistants grand public la placent ; le
/// sous-titre de la barre du haut la répète.
class _AiNotice extends StatelessWidget {
  const _AiNotice(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      key: const Key('aiNotice'),
      padding: const EdgeInsets.fromLTRB(28, 6, 28, 8),
      child: Text(
        text,
        textAlign: TextAlign.center,
        style: Theme.of(
          context,
        ).textTheme.labelSmall?.copyWith(color: HectorTokens.of(context).muted),
      ),
    );
  }
}

/// Message de l'utilisateur : une bulle douce, à droite.
class _UserMessage extends StatelessWidget {
  const _UserMessage(this.text, {this.faded = false});

  final String text;
  final bool faded;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Align(
      alignment: Alignment.centerRight,
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: MediaQuery.sizeOf(context).width * 0.8,
        ),
        child: Opacity(
          opacity: faded ? 0.55 : 1,
          child: Container(
            margin: const EdgeInsets.symmetric(vertical: 10),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
            decoration: BoxDecoration(
              color: HectorTokens.of(context).soft,
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(22),
                topRight: Radius.circular(22),
                bottomLeft: Radius.circular(22),
                bottomRight: Radius.circular(6),
              ),
            ),
            child: SelectableText(text, style: theme.textTheme.bodyLarge),
          ),
        ),
      ),
    );
  }
}

/// Réponse d'Hector : pas de bulle, ses blocs les uns sous les autres, chacun
/// avec sa nature.
class _AssistantMessage extends StatelessWidget {
  const _AssistantMessage(this.message);

  final ChatMessage message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final (index, block) in message.blocks.indexed) ...[
            if (index > 0) const SizedBox(height: 18),
            _BlockView(block),
            // Renvois de l'énoncé à ses sources
            if (message.sourceNumbersOf(block) case final numbers
                when numbers.isNotEmpty) ...[
              const SizedBox(height: 8),
              SourceMarkers(
                numbers: numbers,
                onTap: (number) =>
                    showSource(context, message.citations[number - 1], number),
              ),
            ],
          ],
          if (message.citations.isNotEmpty) ...[
            const SizedBox(height: 20),
            SourceList(
              citations: message.citations,
              onTap: (number) =>
                  showSource(context, message.citations[number - 1], number),
            ),
          ],
        ],
      ),
    );
  }
}

/// Un bloc de réponse. Une pastille dit la nature de chaque énoncé ; ce qui
/// appelle une action (recommandation, question, mise en garde) est mis en
/// encart.
class _BlockView extends StatelessWidget {
  const _BlockView(this.block);

  final MessageBlock block;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tokens = HectorTokens.of(context);
    final l10n = AppLocalizations.of(context);
    final body = theme.textTheme.bodyLarge;
    final strong = theme.textTheme.titleMedium?.copyWith(height: 1.45);

    // Un calcul, ou son refus : composés par l'API, affichés tels quels
    if (block.calculation case final calculation?) {
      return CalculationCard(calculation: calculation, caption: block.caption);
    }
    if (block.calculationRefusal case final refusal?) {
      return CalculationRefusalCard(refusal: refusal);
    }

    // Type ou nature inconnus de cette version : le texte de repli (CV5)
    if (!block.known || block.type == MessageBlock.textType) {
      return SelectableText(block.displayText, style: body);
    }

    if (block.type == MessageBlock.questionType) {
      return SoftCard(
        child: _Labelled(
          label: KindChip(
            icon: Icons.forum_outlined,
            label: l10n.blockQuestion,
            tone: Tone.info,
          ),
          text: block.displayText,
          style: strong,
        ),
      );
    }

    if (block.type == MessageBlock.warningType) {
      final color = tokens.foreground(Tone.danger);

      return SoftCard(
        color: tokens.background(Tone.danger),
        child: _Labelled(
          label: Row(
            children: [
              Icon(Icons.warning_rounded, size: 20, color: color),
              const SizedBox(width: 6),
              Text(
                l10n.blockWarning,
                style: theme.textTheme.titleSmall?.copyWith(
                  color: color,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          text: block.displayText,
          style: strong,
        ),
      );
    }

    final (icon, label, tone) = switch (block.kind!) {
      StatementKind.sourcedFact => (
        Icons.verified_outlined,
        l10n.statementSourcedFact,
        Tone.positive,
      ),
      StatementKind.calculationResult => (
        Icons.calculate_outlined,
        l10n.statementCalculationResult,
        Tone.positive,
      ),
      StatementKind.declaredFact => (
        Icons.person_outline_rounded,
        l10n.statementDeclaredFact,
        Tone.neutral,
      ),
      StatementKind.generalExplanation => (
        Icons.menu_book_outlined,
        l10n.statementGeneralExplanation,
        Tone.neutral,
      ),
      StatementKind.reportedOpinion => (
        Icons.format_quote_rounded,
        block.source == null
            ? l10n.statementReportedOpinion
            : '${l10n.statementReportedOpinion} · ${block.source}',
        Tone.neutral,
      ),
      StatementKind.hypothesis => (
        Icons.lightbulb_outline_rounded,
        l10n.statementHypothesis,
        Tone.caution,
      ),
      StatementKind.missingInformation => (
        Icons.help_outline_rounded,
        l10n.statementMissingInformation,
        Tone.info,
      ),
      StatementKind.recommendation => (
        Icons.task_alt_rounded,
        l10n.statementRecommendation,
        Tone.accent,
      ),
    };

    // Une recommandation ressort : encart teinté, pastille pleine
    if (block.kind == StatementKind.recommendation) {
      return SoftCard(
        color: tokens.background(Tone.accent),
        child: _Labelled(
          label: KindChip(icon: icon, label: label, tone: tone, solid: true),
          text: block.displayText,
          style: body,
        ),
      );
    }

    return _Labelled(
      label: KindChip(icon: icon, label: label, tone: tone),
      text: block.displayText,
      style: body,
    );
  }
}

class _Labelled extends StatelessWidget {
  const _Labelled({required this.label, required this.text, this.style});

  final Widget label;
  final String text;
  final TextStyle? style;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        label,
        const SizedBox(height: 8),
        SelectableText(text, style: style),
      ],
    );
  }
}

class _Thinking extends StatelessWidget {
  const _Thinking();

  @override
  Widget build(BuildContext context) {
    final tokens = HectorTokens.of(context);

    return Align(
      key: const Key('thinking'),
      alignment: Alignment.centerLeft,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
              decoration: BoxDecoration(
                color: tokens.soft,
                borderRadius: const BorderRadius.all(Radius.circular(22)),
              ),
              child: const TypingDots(),
            ),
            const SizedBox(width: 10),
            Text(
              AppLocalizations.of(context).chatThinking,
              style: Theme.of(
                context,
              ).textTheme.bodyMedium?.copyWith(color: tokens.muted),
            ),
          ],
        ),
      ),
    );
  }
}

/// État d'un message resté sans réponse, sous ce message.
class _Status extends StatelessWidget {
  const _Status({required this.text, required this.action});

  final String text;
  final Widget action;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final color = theme.colorScheme.error;

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.only(top: 2),
                child: Icon(
                  Icons.error_outline_rounded,
                  size: 17,
                  color: color,
                ),
              ),
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  text,
                  textAlign: TextAlign.end,
                  style: theme.textTheme.bodyMedium?.copyWith(color: color),
                ),
              ),
            ],
          ),
          action,
        ],
      ),
    );
  }
}

/// Conversation vide ou illisible : un message au centre de l'écran.
class _Notice extends StatelessWidget {
  const _Notice({
    required this.text,
    this.detail,
    this.action,
    this.mark = false,
  });

  final String text;
  final String? detail;
  final Widget? action;
  final bool mark;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (mark) ...[
              const HectorMark(size: 60),
              const SizedBox(height: 20),
            ],
            Text(
              text,
              textAlign: TextAlign.center,
              style: theme.textTheme.headlineSmall,
            ),
            if (detail != null) ...[
              const SizedBox(height: 8),
              Text(
                detail!,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyLarge?.copyWith(
                  color: HectorTokens.of(context).muted,
                ),
              ),
            ],
            if (action != null) ...[
              const SizedBox(height: 20),
              SizedBox(width: 200, child: action),
            ],
          ],
        ),
      ),
    );
  }
}

/// Allocation gratuite épuisée : ce qui débloque la suite (décision D17).
class _LimitCard extends StatelessWidget {
  const _LimitCard({
    required this.notice,
    required this.message,
    required this.conversationUuid,
  });

  final LimitNotice notice;
  final String message;
  final String conversationUuid;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    // Après l'inscription ou la connexion, retour dans cette conversation
    final back = Uri.encodeQueryComponent('/conversations/$conversationUuid');

    return Padding(
      key: const Key('limitCard'),
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
      child: SoftCard(
        padding: const EdgeInsets.all(20),
        radius: const BorderRadius.all(Radius.circular(24)),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (notice == LimitNotice.signupRequired) ...[
              Text(l10n.chatSignupTitle, style: theme.textTheme.headlineSmall),
              const SizedBox(height: 6),
            ],
            Text(
              message,
              style: theme.textTheme.bodyLarge?.copyWith(
                color: notice == LimitNotice.signupRequired
                    ? HectorTokens.of(context).muted
                    : null,
              ),
            ),
            if (notice == LimitNotice.signupRequired) ...[
              const SizedBox(height: 18),
              FilledButton(
                key: const Key('limitCreateAccount'),
                onPressed: () => context.push('/inscription?retour=$back'),
                child: Text(l10n.homeCreateAccount),
              ),
              TextButton(
                key: const Key('limitLogIn'),
                onPressed: () => context.push('/connexion?retour=$back'),
                child: Text(l10n.welcomeHaveAccount),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Zone de saisie : une pilule flottante, avec son bouton d'envoi rond.
class _Composer extends StatelessWidget {
  const _Composer({
    required this.controller,
    required this.sending,
    required this.onChanged,
    required this.onSend,
  });

  final TextEditingController controller;
  final bool sending;
  final ValueChanged<String> onChanged;
  final VoidCallback onSend;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final tokens = HectorTokens.of(context);

    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 4, 12, 0),
      child: Container(
        decoration: BoxDecoration(
          color: tokens.soft,
          borderRadius: const BorderRadius.all(Radius.circular(28)),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: TextField(
                key: const Key('composer'),
                controller: controller,
                minLines: 1,
                maxLines: 5,
                maxLength: ChatScreen.maxLength,
                buildCounter:
                    (
                      _, {
                      required currentLength,
                      required isFocused,
                      maxLength,
                    }) => null,
                textCapitalization: TextCapitalization.sentences,
                style: theme.textTheme.bodyLarge,
                decoration: InputDecoration(
                  hintText: l10n.chatComposerHint,
                  filled: false,
                  border: InputBorder.none,
                  enabledBorder: InputBorder.none,
                  focusedBorder: InputBorder.none,
                  contentPadding: const EdgeInsets.fromLTRB(20, 16, 8, 16),
                ),
                onChanged: onChanged,
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(6),
              child: ListenableBuilder(
                listenable: controller,
                builder: (context, _) => IconButton(
                  key: const Key('send'),
                  tooltip: l10n.chatSendTooltip,
                  iconSize: 24,
                  onPressed: sending || controller.text.trim().isEmpty
                      ? null
                      : onSend,
                  style: IconButton.styleFrom(
                    minimumSize: const Size.square(44),
                    backgroundColor: tokens.accent,
                    foregroundColor: tokens.onAccent,
                    disabledBackgroundColor: tokens.softer,
                    disabledForegroundColor: tokens.muted.withValues(
                      alpha: 0.7,
                    ),
                    shape: const CircleBorder(),
                  ),
                  icon: const Icon(Icons.arrow_upward_rounded),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
