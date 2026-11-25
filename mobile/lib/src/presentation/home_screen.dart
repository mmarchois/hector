import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../l10n/app_localizations.dart';
import '../data/api.dart';
import '../domain/user_profile.dart';
import '../state/auth_controller.dart';
import '../state/chat_controller.dart';
import '../theme.dart';
import 'widgets/account_fields.dart';
import 'widgets/kit.dart';

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  bool _creating = false;
  String? _error;

  /// Ouvre une nouvelle conversation ; [question] y attend dans la saisie.
  Future<void> _newConversation([String? question]) async {
    final l10n = AppLocalizations.of(context);
    setState(() {
      _creating = true;
      _error = null;
    });
    try {
      final conversation = await ref
          .read(conversationRepositoryProvider)
          .create();
      if (question != null) {
        ref.read(chatDraftsProvider.notifier).save(conversation.uuid, question);
      }
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
    final tokens = HectorTokens.of(context);
    final auth = ref.watch(authControllerProvider);
    final user = auth is AuthSignedIn ? auth.user : UserProfile.empty;
    final firstName = user.fullName.trim().split(RegExp(r'\s+')).first;
    final conversations = ref.watch(conversationsProvider);
    final localizations = MaterialLocalizations.of(context);

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 20,
        title: Row(
          children: [
            const HectorMark(size: 34),
            const SizedBox(width: 10),
            Text(l10n.appTitle),
          ],
        ),
        actions: [
          IconButton(
            tooltip: l10n.homeProfileTooltip,
            icon: Avatar(name: user.fullName, size: 38),
            onPressed: () => context.push('/profil'),
          ),
          const SizedBox(width: 12),
        ],
      ),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () => ref.refresh(conversationsProvider.future),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
            children: [
              Text(
                firstName.isEmpty
                    ? l10n.homeGreetingAnonymous
                    : l10n.homeGreeting(firstName),
                style: theme.textTheme.headlineLarge,
              ),
              if (user.guest) ...[
                const SizedBox(height: 6),
                Text(
                  l10n.homeGuestIntro,
                  style: theme.textTheme.bodyLarge?.copyWith(
                    color: tokens.muted,
                  ),
                ),
              ],
              const SizedBox(height: 22),
              _AskBar(
                key: const Key('newConversation'),
                hint: l10n.homeAskHint,
                tooltip: l10n.homeNewConversation,
                onTap: _creating ? null : _newConversation,
              ),
              const SizedBox(height: 12),
              // Une ligne qui défile jusqu'aux bords de l'écran
              SizedBox(
                height: 44,
                child: OverflowBox(
                  maxWidth: MediaQuery.sizeOf(context).width,
                  child: ListView(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    children: [
                      for (final suggestion in [
                        l10n.homeSuggestion1,
                        l10n.homeSuggestion2,
                        l10n.homeSuggestion3,
                        l10n.homeSuggestion4,
                      ])
                        Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: _Suggestion(
                            suggestion,
                            onTap: _creating
                                ? null
                                : () => _newConversation(suggestion),
                          ),
                        ),
                    ],
                  ),
                ),
              ),
              if (_error != null) FormErrorText(_error!),
              const SizedBox(height: 34),
              Text(
                l10n.homeConversationsTitle,
                style: theme.textTheme.titleLarge,
              ),
              const SizedBox(height: 12),
              switch (conversations) {
                AsyncData(:final value) when value.items.isEmpty => SoftCard(
                  padding: const EdgeInsets.all(18),
                  child: Text(
                    l10n.homeNoConversation,
                    style: theme.textTheme.bodyLarge?.copyWith(
                      color: tokens.muted,
                    ),
                  ),
                ),
                AsyncData(:final value) => GroupedList(
                  children: [
                    for (final conversation in value.items)
                      GroupedRow(
                        title: conversation.title ?? l10n.conversationUntitled,
                        detail: localizations.formatMediumDate(
                          conversation.updatedAt.toLocal(),
                        ),
                        onTap: () => _open(conversation.uuid),
                      ),
                  ],
                ),
                AsyncError() => FormErrorText(l10n.homeConversationsError),
                _ => const Padding(
                  padding: EdgeInsets.only(top: 24),
                  child: Center(child: CircularProgressIndicator()),
                ),
              },
              const SizedBox(height: 34),
              Text(l10n.homeAccountTitle, style: theme.textTheme.titleLarge),
              const SizedBox(height: 12),
              GroupedList(
                children: [
                  if (!user.guest)
                    GroupedRow(
                      leading: Avatar(name: user.fullName, onSoft: true),
                      title: user.professional
                          ? (user.companyName ?? user.fullName)
                          : l10n.homeAccountIndividual,
                      detail: user.email,
                      onTap: () => context.push('/profil'),
                    ),
                  if (user.guest)
                    GroupedRow(
                      key: const Key('createAccount'),
                      leading: const Avatar(onSoft: true),
                      title: l10n.homeCreateAccount,
                      detail: l10n.homeCreateAccountHint,
                      onTap: () => context.push('/inscription'),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Entrée principale de l'accueil : une grande barre qui ouvre une nouvelle
/// conversation, avec le bouton d'envoi de la zone de saisie.
class _AskBar extends StatelessWidget {
  const _AskBar({
    super.key,
    required this.hint,
    required this.tooltip,
    required this.onTap,
  });

  final String hint;
  final String tooltip;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tokens = HectorTokens.of(context);
    const radius = BorderRadius.all(Radius.circular(32));

    return Semantics(
      button: true,
      label: tooltip,
      child: Opacity(
        opacity: onTap == null ? 0.6 : 1,
        child: Material(
          color: tokens.soft,
          borderRadius: radius,
          child: InkWell(
            borderRadius: radius,
            onTap: onTap,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(22, 8, 8, 8),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      hint,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodyLarge?.copyWith(
                        color: tokens.muted,
                      ),
                    ),
                  ),
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      color: tokens.accent,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      Icons.arrow_upward_rounded,
                      color: tokens.onAccent,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Question toute prête : ouvre une conversation où elle attend d'être envoyée.
class _Suggestion extends StatelessWidget {
  const _Suggestion(this.text, {required this.onTap});

  final String text;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tokens = HectorTokens.of(context);
    const radius = BorderRadius.all(Radius.circular(99));

    return Material(
      color: Colors.transparent,
      shape: RoundedRectangleBorder(
        borderRadius: radius,
        side: BorderSide(color: tokens.hairline, width: 1.5),
      ),
      child: InkWell(
        borderRadius: radius,
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Text(text, style: theme.textTheme.titleSmall),
        ),
      ),
    );
  }
}
