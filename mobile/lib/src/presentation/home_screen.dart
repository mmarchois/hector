import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../l10n/app_localizations.dart';
import '../data/api.dart';
import '../domain/conversation.dart';
import '../domain/user_profile.dart';
import '../state/auth_controller.dart';
import '../state/chat_controller.dart';
import '../theme.dart';
import 'widgets/account_fields.dart';

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  bool _creating = false;
  String? _error;

  Future<void> _newConversation() async {
    final l10n = AppLocalizations.of(context);
    setState(() {
      _creating = true;
      _error = null;
    });
    try {
      final conversation = await ref
          .read(conversationRepositoryProvider)
          .create();
      if (mounted) {
        await _open(conversation.uuid);
      }
    } on DioException {
      setState(() => _error = l10n.networkError);
    } finally {
      if (mounted) {
        setState(() => _creating = false);
      }
    }
  }

  Future<void> _open(String uuid) async {
    await context.push('/conversations/$uuid');
    // Au retour : titres et ordre ont pu changer
    ref.invalidate(conversationsProvider);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final auth = ref.watch(authControllerProvider);
    final user = auth is AuthSignedIn ? auth.user : UserProfile.empty;
    final firstName = user.fullName.trim().split(RegExp(r'\s+')).first;
    final conversations = ref.watch(conversationsProvider);

    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            const HectorMark(size: 32),
            const SizedBox(width: 12),
            Text(l10n.appTitle),
          ],
        ),
        actions: [
          IconButton(
            tooltip: l10n.homeProfileTooltip,
            icon: const Icon(Icons.account_circle_outlined),
            onPressed: () => context.push('/profil'),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () => ref.refresh(conversationsProvider.future),
          child: ListView(
            padding: const EdgeInsets.all(24),
            children: [
              Text(
                firstName.isEmpty
                    ? l10n.homeGreetingAnonymous
                    : l10n.homeGreeting(firstName),
                style: theme.textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
              if (user.guest) ...[
                const SizedBox(height: 4),
                Text(
                  l10n.homeGuestIntro,
                  style: theme.textTheme.bodyLarge?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ],
              const SizedBox(height: 24),
              FilledButton.icon(
                key: const Key('newConversation'),
                onPressed: _creating ? null : _newConversation,
                icon: const Icon(Icons.add_comment_outlined),
                label: Text(l10n.homeNewConversation),
              ),
              if (_error != null) FormErrorText(_error!),
              const SizedBox(height: 32),
              Text(
                l10n.homeConversationsTitle,
                style: theme.textTheme.titleMedium,
              ),
              const SizedBox(height: 8),
              ...switch (conversations) {
                AsyncData(:final value) when value.items.isEmpty => [
                  Text(
                    l10n.homeNoConversation,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
                AsyncData(:final value) => [
                  for (final conversation in value.items)
                    _ConversationTile(
                      conversation: conversation,
                      onTap: () => _open(conversation.uuid),
                    ),
                ],
                AsyncError() => [FormErrorText(l10n.homeConversationsError)],
                _ => [const Center(child: CircularProgressIndicator())],
              },
              if (!user.guest) ...[
                const SizedBox(height: 32),
                Card(
                  child: ListTile(
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 20,
                      vertical: 8,
                    ),
                    leading: Icon(
                      user.professional
                          ? Icons.business_outlined
                          : Icons.person_outline,
                    ),
                    title: Text(
                      user.professional
                          ? (user.companyName ?? user.fullName)
                          : l10n.homeAccountIndividual,
                    ),
                    subtitle: Text(user.email),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => context.push('/profil'),
                  ),
                ),
              ],
              if (user.guest) ...[
                const SizedBox(height: 32),
                Card(
                  child: ListTile(
                    key: const Key('createAccount'),
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 20,
                      vertical: 8,
                    ),
                    leading: const Icon(Icons.person_add_alt_1_outlined),
                    title: Text(l10n.homeCreateAccount),
                    subtitle: Text(l10n.homeCreateAccountHint),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => context.push('/inscription'),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _ConversationTile extends StatelessWidget {
  const _ConversationTile({required this.conversation, required this.onTap});

  final Conversation conversation;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final localizations = MaterialLocalizations.of(context);

    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: const Icon(Icons.chat_bubble_outline),
      title: Text(
        conversation.title ?? l10n.conversationUntitled,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      ),
      subtitle: Text(
        localizations.formatShortDate(conversation.updatedAt.toLocal()),
      ),
      trailing: const Icon(Icons.chevron_right),
      onTap: onTap,
    );
  }
}
