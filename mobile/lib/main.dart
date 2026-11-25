import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'l10n/app_localizations.dart';
import 'src/router.dart';
import 'src/state/auth_controller.dart';
import 'src/theme.dart';

void main() {
  // La police embarquée est sous licence OFL : son texte accompagne
  // l'application et figure dans sa page des licences.
  LicenseRegistry.addLicense(() async* {
    yield LicenseEntryWithLineBreaks([
      'Plus Jakarta Sans',
    ], await rootBundle.loadString('assets/fonts/OFL-PlusJakartaSans.txt'));
  });

  runApp(const ProviderScope(child: HectorApp()));
}

class HectorApp extends ConsumerStatefulWidget {
  const HectorApp({super.key});

  @override
  ConsumerState<HectorApp> createState() => _HectorAppState();
}

class _HectorAppState extends ConsumerState<HectorApp> {
  @override
  void initState() {
    super.initState();
    // Restauration de session au démarrage (tokens en stockage sécurisé).
    Future.microtask(
      () => ref.read(authControllerProvider.notifier).restoreSession(),
    );
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'Hector AI',
      theme: HectorTheme.light(),
      darkTheme: HectorTheme.dark(),
      routerConfig: ref.watch(routerProvider),
      locale: const Locale('fr'),
      supportedLocales: const [Locale('fr')],
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
    );
  }
}
