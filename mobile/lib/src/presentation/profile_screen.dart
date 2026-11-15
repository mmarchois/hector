import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../l10n/app_localizations.dart';
import '../data/api_errors.dart';
import '../domain/account_validators.dart';
import '../domain/user_profile.dart';
import '../state/auth_controller.dart';
import 'widgets/account_fields.dart';

class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  late final AccountFormControllers _controllers;
  final _currentPasswordController = TextEditingController();
  final _newPasswordController = TextEditingController();

  late bool _professional;
  bool _submitting = false;
  String? _error;
  Map<String, String> _apiErrors = const {};

  @override
  void initState() {
    super.initState();
    final auth = ref.read(authControllerProvider);
    final user = auth is AuthSignedIn ? auth.user : UserProfile.empty;
    _controllers = AccountFormControllers(initial: user);
    _professional = user.professional;
  }

  @override
  void dispose() {
    _controllers.dispose();
    _currentPasswordController.dispose();
    _newPasswordController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final l10n = AppLocalizations.of(context);
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _apiErrors = const {});
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });

    final newPassword = _newPasswordController.text;
    try {
      await ref
          .read(authControllerProvider.notifier)
          .updateProfile(
            fullName: _controllers.fullName.text.trim(),
            email: _controllers.email.text.trim(),
            professional: _professional,
            companyName: _professional
                ? _controllers.companyName.text.trim()
                : null,
            siret: _professional ? _controllers.siret.text.trim() : null,
            vatNumber: _professional ? _controllers.vatNumberValue : null,
            currentPassword: newPassword.isEmpty
                ? null
                : _currentPasswordController.text,
            newPassword: newPassword.isEmpty ? null : newPassword,
          );
      _currentPasswordController.clear();
      _newPasswordController.clear();
      messenger.showSnackBar(SnackBar(content: Text(l10n.profileSaved)));
    } on EmailAlreadyExistsException {
      setState(() => _error = l10n.registerEmailTaken);
    } on ApiValidationException catch (e) {
      setState(() => _apiErrors = e.fieldErrors);
      _formKey.currentState!.validate();
    } on DioException {
      setState(() => _error = l10n.networkError);
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  Future<void> _confirmDelete() async {
    final l10n = AppLocalizations.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l10n.profileDeleteConfirmTitle),
        content: Text(l10n.profileDeleteConfirmBody),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: Text(l10n.cancel),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: TextButton.styleFrom(
              foregroundColor: Theme.of(context).colorScheme.error,
            ),
            child: Text(l10n.profileDeleteConfirmAction),
          ),
        ],
      ),
    );
    if (confirmed != true) {
      return;
    }

    try {
      await ref.read(authControllerProvider.notifier).deleteAccount();
      // Le router ramène à l'écran de connexion (session fermée).
    } on DioException {
      if (mounted) {
        setState(() => _error = l10n.networkError);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.profileTitle)),
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
                    AccountFields(
                      controllers: _controllers,
                      professional: _professional,
                      onProfessionalChanged: (value) =>
                          setState(() => _professional = value),
                      apiErrorFor: (property) => _apiErrors[property],
                    ),
                    const SizedBox(height: 32),
                    Text(
                      l10n.profilePasswordSection,
                      style: theme.textTheme.titleMedium,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      key: const Key('currentPassword'),
                      controller: _currentPasswordController,
                      decoration: InputDecoration(
                        labelText: l10n.profileCurrentPasswordLabel,
                      ),
                      obscureText: true,
                      autofillHints: const [AutofillHints.password],
                      textInputAction: TextInputAction.next,
                      validator: (value) {
                        final apiError = _apiErrors['currentPassword'];
                        if (apiError != null) {
                          return apiError;
                        }

                        return _newPasswordController.text.isNotEmpty &&
                                (value ?? '').isEmpty
                            ? l10n.profileCurrentPasswordRequired
                            : null;
                      },
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      key: const Key('newPassword'),
                      controller: _newPasswordController,
                      decoration: InputDecoration(
                        labelText: l10n.profileNewPasswordLabel,
                        helperText: l10n.registerPasswordLabel,
                      ),
                      obscureText: true,
                      autofillHints: const [AutofillHints.newPassword],
                      textInputAction: TextInputAction.done,
                      validator: (value) {
                        final apiError = _apiErrors['newPassword'];
                        if (apiError != null) {
                          return apiError;
                        }
                        final v = value ?? '';

                        return v.isEmpty ||
                                AccountValidators.isPasswordLongEnough(v)
                            ? null
                            : l10n.registerPasswordTooShort;
                      },
                    ),
                    if (_error != null) FormErrorText(_error!),
                    const SizedBox(height: 24),
                    SubmitButton(
                      label: l10n.profileSave,
                      submitting: _submitting,
                      onPressed: _save,
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton(
                      onPressed: () =>
                          ref.read(authControllerProvider.notifier).signOut(),
                      child: Text(l10n.profileSignOut),
                    ),
                    const SizedBox(height: 24),
                    TextButton(
                      onPressed: _confirmDelete,
                      style: TextButton.styleFrom(
                        foregroundColor: theme.colorScheme.error,
                      ),
                      child: Text(l10n.profileDeleteAccount),
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
