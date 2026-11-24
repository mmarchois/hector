import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../l10n/app_localizations.dart';
import '../domain/user_profile.dart';
import '../state/auth_controller.dart';
import '../theme.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context);
    final theme = Theme.of(context);
    final auth = ref.watch(authControllerProvider);
    final user = auth is AuthSignedIn ? auth.user : UserProfile.empty;
    final firstName = user.fullName.trim().split(RegExp(r'\s+')).first;

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
            const SizedBox(height: 4),
            Text(
              user.guest ? l10n.homeGuestIntro : l10n.homeAccountReady,
              style: theme.textTheme.bodyLarge?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 24),
            if (user.guest)
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
              )
            else
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
        ),
      ),
    );
  }
}
