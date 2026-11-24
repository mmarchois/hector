import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../l10n/app_localizations.dart';
import '../data/api_errors.dart';
import '../domain/account_validators.dart';
import '../state/auth_controller.dart';
import 'widgets/account_fields.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key, this.returnTo = '/'});

  /// Écran où revenir une fois la session ouverte (une conversation, ou l'accueil).
  final String returnTo;

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _controllers = AccountFormControllers();
  final _passwordController = TextEditingController();

  bool _professional = true;
  bool _submitting = false;
  String? _error;

  /// Erreurs de champ renvoyées par l'API (422), prioritaires à l'affichage.
  Map<String, String> _apiErrors = const {};

  @override
  void dispose() {
    _controllers.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final l10n = AppLocalizations.of(context);
    setState(() => _apiErrors = const {});
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      await ref
          .read(authControllerProvider.notifier)
          .register(
            fullName: _controllers.fullName.text.trim(),
            email: _controllers.email.text.trim(),
            password: _passwordController.text,
            professional: _professional,
            companyName: _professional
                ? _controllers.companyName.text.trim()
                : null,
            siret: _professional ? _controllers.siret.text.trim() : null,
            vatNumber: _professional ? _controllers.vatNumberValue : null,
          );
      // Session ouverte (ou compte invité converti). L'écran est empilé (push) :
      // le router ne redirige pas une route empilée, retour explicite à l'accueil.
      if (mounted) {
        GoRouter.maybeOf(context)?.go(widget.returnTo);
      }
    } on EmailAlreadyExistsException {
      setState(() => _error = l10n.registerEmailTaken);
    } on TermsOfUseOutdatedException {
      // Conditions modifiées depuis leur acceptation : retour à l'écran de bienvenue
      ref.read(authControllerProvider.notifier).withdrawTermsOfUse();
    } on ApiValidationException catch (e) {
      setState(() => _apiErrors = e.fieldErrors);
      _formKey.currentState!.validate();
    } on InvalidCredentialsException {
      setState(() => _error = l10n.unexpectedError);
    } on DioException {
      setState(() => _error = l10n.networkError);
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final auth = ref.watch(authControllerProvider);
    final guest = auth is AuthSignedIn && auth.user.guest;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.registerTitle)),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      guest
                          ? l10n.registerGuestSubtitle
                          : l10n.registerSubtitle,
                      textAlign: TextAlign.center,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 20),
                    AccountFields(
                      controllers: _controllers,
                      professional: _professional,
                      onProfessionalChanged: (value) =>
                          setState(() => _professional = value),
                      apiErrorFor: (property) => _apiErrors[property],
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      key: const Key('password'),
                      controller: _passwordController,
                      decoration: InputDecoration(
                        labelText: l10n.registerPasswordLabel,
                      ),
                      obscureText: true,
                      autofillHints: const [AutofillHints.newPassword],
                      textInputAction: TextInputAction.done,
                      onFieldSubmitted: (_) => _submit(),
                      validator: (value) =>
                          _apiErrors['password'] ??
                          (AccountValidators.isPasswordLongEnough(value ?? '')
                              ? null
                              : l10n.registerPasswordTooShort),
                    ),
                    if (_error != null) FormErrorText(_error!),
                    const SizedBox(height: 24),
                    SubmitButton(
                      label: l10n.registerSubmit,
                      submitting: _submitting,
                      onPressed: _submit,
                    ),
                    const SizedBox(height: 12),
                    Wrap(
                      alignment: WrapAlignment.center,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        Text(
                          l10n.registerHaveAccount,
                          style: TextStyle(
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        ),
                        TextButton(
                          onPressed: () => context.canPop()
                              ? context.pop()
                              : context.go('/connexion'),
                          child: Text(l10n.registerLoginLink),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
