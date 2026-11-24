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
    final terms = ref.watch(termsOfUseProvider);

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Center(child: HectorMark()),
                  const SizedBox(height: 24),
                  Text(
                    l10n.appTitle,
                    textAlign: TextAlign.center,
                    style: theme.textTheme.headlineMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    l10n.welcomeIntro,
                    textAlign: TextAlign.center,
                    style: theme.textTheme.bodyLarge?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(height: 32),
                  ...switch (terms) {
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
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  List<Widget> _actions(AppLocalizations l10n, TermsOfUse terms) {
    return [
      CheckboxListTile(
        key: const Key('acceptTermsOfUse'),
        value: _accepted,
        onChanged: (value) => setState(() => _accepted = value ?? false),
        controlAffinity: ListTileControlAffinity.leading,
        contentPadding: EdgeInsets.zero,
        title: Text(l10n.welcomeAcceptTerms),
      ),
      Align(
        alignment: Alignment.centerLeft,
        child: TextButton(
          onPressed: () => _showTerms(terms),
          child: Text(l10n.welcomeReadTerms),
        ),
      ),
      if (_error != null) FormErrorText(_error!),
      const SizedBox(height: 16),
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
      const SizedBox(height: 8),
      TextButton(
        key: const Key('haveAccount'),
        onPressed: _accepted && !_starting ? () => _logIn(terms) : null,
        child: Text(l10n.welcomeHaveAccount),
      ),
    ];
  }
}
