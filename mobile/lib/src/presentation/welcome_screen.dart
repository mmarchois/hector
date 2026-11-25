import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../l10n/app_localizations.dart';
import '../data/api.dart';
import '../data/api_errors.dart';
import '../domain/terms_of_use.dart';
import '../state/auth_controller.dart';
import '../theme.dart';
import 'widgets/account_fields.dart';
import 'widgets/terms_of_use_text.dart';

/// Premier écran sans session : conditions d'utilisation à accepter, puis essai
/// sans compte (compte invité) ou connexion à un compte existant.
class WelcomeScreen extends ConsumerStatefulWidget {
  const WelcomeScreen({super.key});

  /// Délai entre deux essais de chargement des conditions sans réseau.
  static const retryDelay = Duration(seconds: 10);

  @override
  ConsumerState<WelcomeScreen> createState() => _WelcomeScreenState();
}

class _WelcomeScreenState extends ConsumerState<WelcomeScreen> {
  bool _accepted = false;
  bool _starting = false;
  String? _error;
  Timer? _retryTimer;

  @override
  void initState() {
    super.initState();
    // Sans réseau au premier lancement : nouvel essai automatique, sans action
    ref.listenManual(termsOfUseProvider, (previous, next) {
      _retryTimer?.cancel();
      if (next.hasError) {
        _retryTimer = Timer(
          WelcomeScreen.retryDelay,
          () => ref.invalidate(termsOfUseProvider),
        );
      }
    });
  }

  @override
  void dispose() {
    _retryTimer?.cancel();
    super.dispose();
  }

  Future<void> _startAsGuest(TermsOfUse terms) async {
    final l10n = AppLocalizations.of(context);
    final auth = ref.read(authControllerProvider.notifier);
    setState(() {
      _starting = true;
      _error = null;
    });

    try {
      auth.acceptTermsOfUse(terms.version);
      await auth.startAsGuest();
      // La redirection vers l'accueil est gérée par le router (session ouverte).
    } on TermsOfUseOutdatedException {
      auth.withdrawTermsOfUse();
      ref.invalidate(termsOfUseProvider);
      setState(() {
        _accepted = false;
        _error = l10n.termsOfUseOutdated;
      });
    } on TooManyGuestAccountsException {
      setState(() => _error = l10n.tooManyGuestAccounts);
    } on DioException {
      setState(() => _error = l10n.networkError);
    } finally {
      if (mounted) {
        setState(() => _starting = false);
      }
    }
  }

  void _logIn(TermsOfUse terms) {
    ref.read(authControllerProvider.notifier).acceptTermsOfUse(terms.version);
    context.push('/connexion');
  }

  void _showTerms(TermsOfUse terms) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.8,
        maxChildSize: 0.95,
        builder: (context, controller) => SingleChildScrollView(
          controller: controller,
          padding: const EdgeInsets.fromLTRB(24, 0, 24, 32),
          child: TermsOfUseText(terms.content),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final tokens = HectorTokens.of(context);
    final terms = ref.watch(termsOfUseProvider);

    // Les actions sont épinglées en bas, à portée de pouce ; la présentation
    // défile au-dessus sur un petit écran ou avec un texte agrandi.
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 480),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(24, 28, 24, 8),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const Align(
                          alignment: Alignment.centerLeft,
                          child: HectorMark(size: 64),
                        ),
                        const SizedBox(height: 28),
                        Text(
                          l10n.appTitle,
                          style: theme.textTheme.displaySmall,
                        ),
                        const SizedBox(height: 10),
                        Text(
                          l10n.appTagline,
                          style: theme.textTheme.bodyLarge?.copyWith(
                            color: tokens.muted,
                            fontSize: 17,
                          ),
                        ),
                        const SizedBox(height: 32),
                        _Feature(
                          icon: Icons.bolt_rounded,
                          title: l10n.welcomeFeature1Title,
                          body: l10n.welcomeFeature1Body,
                        ),
                        _Feature(
                          icon: Icons.fact_check_outlined,
                          title: l10n.welcomeFeature2Title,
                          body: l10n.welcomeFeature2Body,
                        ),
                        _Feature(
                          icon: Icons.health_and_safety_outlined,
                          title: l10n.welcomeFeature3Title,
                          body: l10n.welcomeFeature3Body,
                        ),
                      ],
                    ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(24, 8, 24, 16),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: switch (terms) {
                      AsyncData(:final value) => _actions(l10n, value),
                      AsyncError() => [
                        FormErrorText(l10n.welcomeTermsUnavailable),
                        const SizedBox(height: 16),
                        OutlinedButton(
                          onPressed: () => ref.invalidate(termsOfUseProvider),
                          child: Text(l10n.retry),
                        ),
                      ],
                      _ => [const Center(child: CircularProgressIndicator())],
                    },
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  List<Widget> _actions(AppLocalizations l10n, TermsOfUse terms) {
    final theme = Theme.of(context);

    return [
      // La case a sa zone de toucher autour d'elle : on la ramène sur la marge
      Transform.translate(
        offset: const Offset(-11, 0),
        child: CheckboxListTile(
          key: const Key('acceptTermsOfUse'),
          value: _accepted,
          onChanged: (value) => setState(() => _accepted = value ?? false),
          controlAffinity: ListTileControlAffinity.leading,
          contentPadding: EdgeInsets.zero,
          horizontalTitleGap: 4,
          title: Text(
            l10n.welcomeAcceptTerms,
            style: theme.textTheme.bodyMedium,
          ),
        ),
      ),
      Align(
        alignment: Alignment.centerLeft,
        child: TextButton(
          style: TextButton.styleFrom(
            padding: EdgeInsets.zero,
            textStyle: theme.textTheme.titleSmall?.copyWith(
              decoration: TextDecoration.underline,
            ),
          ),
          onPressed: () => _showTerms(terms),
          child: Text(l10n.welcomeReadTerms),
        ),
      ),
      if (_error != null) FormErrorText(_error!),
      const SizedBox(height: 12),
      FilledButton(
        key: const Key('startAsGuest'),
        onPressed: _accepted && !_starting ? () => _startAsGuest(terms) : null,
        child: _starting
            ? const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : Text(l10n.welcomeStart),
      ),
      const SizedBox(height: 10),
      OutlinedButton(
        key: const Key('haveAccount'),
        onPressed: _accepted && !_starting ? () => _logIn(terms) : null,
        child: Text(l10n.welcomeHaveAccount),
      ),
    ];
  }
}

/// Ce qu'Hector apporte, en une ligne : une icône, un titre, une phrase.
class _Feature extends StatelessWidget {
  const _Feature({required this.icon, required this.title, required this.body});

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tokens = HectorTokens.of(context);

    return Padding(
      padding: const EdgeInsets.only(bottom: 18),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: tokens.soft,
              shape: BoxShape.circle,
            ),
            child: Icon(icon, size: 22),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: theme.textTheme.titleMedium),
                const SizedBox(height: 2),
                Text(
                  body,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: tokens.muted,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
