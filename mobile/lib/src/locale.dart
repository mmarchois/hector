import 'dart:ui';

import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Langues ouvertes (décision D19) : les mêmes que celles de l'API. En ouvrir
/// une, c'est ajouter son fichier `lib/l10n/app_<langue>.arb`, sa ligne ici et
/// dans `CFBundleLocalizations` (ios/Runner/Info.plist), une fois l'API prête.
abstract final class AppLocales {
  static const fallback = Locale('fr');

  static const supported = [Locale('fr'), Locale('en'), Locale('de')];

  /// Langue de l'application pour un téléphone réglé sur [device], par ordre
  /// de préférence : la première langue ouverte, sinon le français.
  static Locale resolve(Iterable<Locale> device) {
    for (final wanted in device) {
      for (final locale in supported) {
        if (locale.languageCode == wanted.languageCode) {
          return locale;
        }
      }
    }

    return fallback;
  }
}

/// Langue de l'application : celle de l'interface, et celle annoncée à l'API
/// (`Accept-Language`), dans laquelle Hector répond.
final appLocaleProvider = Provider<Locale>(
  (ref) => AppLocales.resolve(PlatformDispatcher.instance.locales),
);
