import 'package:flutter/material.dart';

import '../../../l10n/app_localizations.dart';
import '../../domain/account_validators.dart';
import '../../domain/user_profile.dart';

/// Contrôleurs des champs « compte », partagés par l'inscription et le profil.
class AccountFormControllers {
  AccountFormControllers({UserProfile? initial})
    : fullName = TextEditingController(text: initial?.fullName ?? ''),
      email = TextEditingController(text: initial?.email ?? ''),
      companyName = TextEditingController(text: initial?.companyName ?? ''),
      siret = TextEditingController(text: initial?.siret ?? ''),
      vatNumber = TextEditingController(text: initial?.vatNumber ?? '');

  final TextEditingController fullName;
  final TextEditingController email;
  final TextEditingController companyName;
  final TextEditingController siret;
  final TextEditingController vatNumber;

  String get vatNumberValue => vatNumber.text.trim().toUpperCase();

  void dispose() {
    fullName.dispose();
    email.dispose();
    companyName.dispose();
    siret.dispose();
    vatNumber.dispose();
  }
}

/// Champs d'identité du compte : type (pro / particulier), entreprise, SIRET,
/// TVA, nom et email. Les erreurs renvoyées par l'API (422) priment sur la
/// validation locale.
class AccountFields extends StatelessWidget {
  const AccountFields({
    super.key,
    required this.controllers,
    required this.professional,
    required this.onProfessionalChanged,
    required this.apiErrorFor,
  });

  final AccountFormControllers controllers;
  final bool professional;
  final ValueChanged<bool> onProfessionalChanged;

  /// Erreur renvoyée par l'API pour un champ. Lue au moment de la validation :
  /// une Map passée en paramètre serait figée à la dernière construction du
  /// widget et ignorerait les erreurs reçues entre-temps.
  final String? Function(String property) apiErrorFor;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SegmentedButton<bool>(
          showSelectedIcon: false,
          segments: [
            ButtonSegment(value: true, label: Text(l10n.accountTypePro)),
            ButtonSegment(
              value: false,
              label: Text(l10n.accountTypeIndividual),
            ),
          ],
          selected: {professional},
          onSelectionChanged: (selection) =>
              onProfessionalChanged(selection.first),
        ),
        const SizedBox(height: 20),
        if (professional) ...[
          TextFormField(
            key: const Key('companyName'),
            controller: controllers.companyName,
            decoration: InputDecoration(
              labelText: l10n.accountCompanyNameLabel,
            ),
            textInputAction: TextInputAction.next,
            validator: (value) =>
                apiErrorFor('companyName') ??
                ((value?.trim().isEmpty ?? true)
                    ? l10n.accountCompanyNameRequired
                    : null),
          ),
          const SizedBox(height: 16),
          TextFormField(
            key: const Key('siret'),
            controller: controllers.siret,
            decoration: InputDecoration(labelText: l10n.accountSiretLabel),
            keyboardType: TextInputType.number,
            textInputAction: TextInputAction.next,
            validator: (value) {
              final apiError = apiErrorFor('siret');
              if (apiError != null) {
                return apiError;
              }
              final v = value?.trim() ?? '';
              if (v.isEmpty) {
                return l10n.accountSiretRequired;
              }

              return AccountValidators.isSiret(v)
                  ? null
                  : l10n.accountSiretInvalid;
            },
          ),
          const SizedBox(height: 16),
          TextFormField(
            key: const Key('vatNumber'),
            controller: controllers.vatNumber,
            decoration: InputDecoration(labelText: l10n.accountVatLabel),
            textCapitalization: TextCapitalization.characters,
            textInputAction: TextInputAction.next,
            validator: (value) {
              final apiError = apiErrorFor('vatNumber');
              if (apiError != null) {
                return apiError;
              }
              final v = (value ?? '').trim().toUpperCase();
              if (v.isEmpty) {
                return l10n.accountVatRequired;
              }

              return AccountValidators.isVatNumber(v)
                  ? null
                  : l10n.accountVatInvalid;
            },
          ),
          const SizedBox(height: 16),
        ],
        TextFormField(
          key: const Key('fullName'),
          controller: controllers.fullName,
          decoration: InputDecoration(labelText: l10n.accountFullNameLabel),
          autofillHints: const [AutofillHints.name],
          textCapitalization: TextCapitalization.words,
          textInputAction: TextInputAction.next,
          validator: (value) =>
              apiErrorFor('fullName') ??
              ((value?.trim().isEmpty ?? true)
                  ? l10n.accountFullNameRequired
                  : null),
        ),
        const SizedBox(height: 16),
        TextFormField(
          key: const Key('email'),
          controller: controllers.email,
          decoration: InputDecoration(labelText: l10n.loginEmailLabel),
          keyboardType: TextInputType.emailAddress,
          autofillHints: const [AutofillHints.email],
          autocorrect: false,
          textInputAction: TextInputAction.next,
          validator: (value) {
            final apiError = apiErrorFor('email');
            if (apiError != null) {
              return apiError;
            }
            final v = value?.trim() ?? '';
            if (v.isEmpty) {
              return l10n.loginEmailRequired;
            }

            return AccountValidators.isEmail(v) ? null : l10n.loginEmailInvalid;
          },
        ),
      ],
    );
  }
}

/// Message d'erreur global d'un formulaire (réseau, identifiants…).
class FormErrorText extends StatelessWidget {
  const FormErrorText(this.message, {super.key});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 16),
      child: Text(
        message,
        style: TextStyle(color: Theme.of(context).colorScheme.error),
      ),
    );
  }
}

/// Bouton principal d'un formulaire, avec indicateur pendant l'envoi.
class SubmitButton extends StatelessWidget {
  const SubmitButton({
    super.key,
    required this.label,
    required this.submitting,
    required this.onPressed,
  });

  final String label;
  final bool submitting;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return FilledButton(
      onPressed: submitting ? null : onPressed,
      child: submitting
          ? const SizedBox(
              width: 20,
              height: 20,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          : Text(label),
    );
  }
}
