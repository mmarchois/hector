import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../l10n/app_localizations.dart';
import '../domain/conversation.dart';
import '../state/chat_controller.dart';
import '../theme.dart';

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
            const HectorMark(size: 28),
            const SizedBox(width: 12),
            Text(l10n.appTitle),
          ],
        ),
        bottom: _AiNotice(l10n.chatAiNotice),
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
      return _Centered(
        text: l10n.chatLoadError,
        action: OutlinedButton(
          onPressed: controller.load,
          child: Text(l10n.chatReload),
        ),
      );
    }

    final pending = chat.pending;
    final items = <Widget>[
      if (chat.hasMore)
        Center(
          child: TextButton(
            onPressed: chat.loadingOlder ? null : controller.loadOlder,
            child: Text(l10n.chatLoadOlder),
          ),
        ),
      if (chat.messages.isEmpty && pending == null)
        _Centered(text: l10n.chatEmpty),
      for (final message in chat.messages) ...[
        _Bubble(message.role, message.text),
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
        _Bubble(MessageRole.user, pending.text, faded: true),
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
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
      itemCount: items.length,
      itemBuilder: (context, index) => items[items.length - 1 - index],
    );
  }
}

/// Mention permanente : on parle à une IA (règlement européen sur l'IA, art. 50).
class _AiNotice extends StatelessWidget implements PreferredSizeWidget {
  const _AiNotice(this.text);

  final String text;

  @override
  Size get preferredSize => const Size.fromHeight(40);

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      key: const Key('aiNotice'),
      width: double.infinity,
      color: theme.colorScheme.secondaryContainer,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          Icon(
            Icons.auto_awesome_outlined,
            size: 18,
            color: theme.colorScheme.onSecondaryContainer,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSecondaryContainer,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble(this.role, this.text, {this.faded = false});

  final MessageRole role;
  final String text;
  final bool faded;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final mine = role == MessageRole.user;

    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: MediaQuery.sizeOf(context).width * 0.85,
        ),
        child: Opacity(
          opacity: faded ? 0.6 : 1,
          child: Container(
            margin: const EdgeInsets.symmetric(vertical: 6),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: mine
                  ? theme.colorScheme.primaryContainer
                  : theme.colorScheme.surfaceContainerHighest,
              borderRadius: BorderRadius.circular(16),
            ),
            child: SelectableText(
              text,
              style: theme.textTheme.bodyLarge?.copyWith(
                color: mine
                    ? theme.colorScheme.onPrimaryContainer
                    : theme.colorScheme.onSurface,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _Thinking extends StatelessWidget {
  const _Thinking();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      key: const Key('thinking'),
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          const SizedBox(
            width: 16,
            height: 16,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
          const SizedBox(width: 12),
          Text(
            AppLocalizations.of(context).chatThinking,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        ],
      ),
    );
  }
}

class _Status extends StatelessWidget {
  const _Status({required this.text, required this.action});

  final String text;
  final Widget action;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Text(
            text,
            textAlign: TextAlign.end,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.error,
            ),
          ),
          action,
        ],
      ),
    );
  }
}

class _Centered extends StatelessWidget {
  const _Centered({required this.text, this.action});

  final String text;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            text,
            textAlign: TextAlign.center,
            style: theme.textTheme.bodyLarge?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          if (action != null) ...[const SizedBox(height: 16), action!],
        ],
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
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (notice == LimitNotice.signupRequired)
                Text(l10n.chatSignupTitle, style: theme.textTheme.titleMedium),
              const SizedBox(height: 4),
              Text(message),
              if (notice == LimitNotice.signupRequired) ...[
                const SizedBox(height: 16),
                FilledButton(
                  key: const Key('limitCreateAccount'),
                  onPressed: () => context.push('/inscription?retour=$back'),
                  child: Text(l10n.homeCreateAccount),
                ),
                const SizedBox(height: 8),
                OutlinedButton(
                  key: const Key('limitLogIn'),
                  onPressed: () => context.push('/connexion?retour=$back'),
                  child: Text(l10n.welcomeHaveAccount),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

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

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 8, 12),
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
              decoration: InputDecoration(hintText: l10n.chatComposerHint),
              onChanged: onChanged,
            ),
          ),
          const SizedBox(width: 4),
          ListenableBuilder(
            listenable: controller,
            builder: (context, _) => IconButton.filled(
              key: const Key('send'),
              tooltip: l10n.chatSendTooltip,
              iconSize: 28,
              onPressed: sending || controller.text.trim().isEmpty
                  ? null
                  : onSend,
              icon: const Icon(Icons.send_rounded),
            ),
          ),
        ],
      ),
    );
  }
}
