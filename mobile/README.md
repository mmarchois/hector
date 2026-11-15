# Hector, application mobile

Application Flutter (iOS et Android) de Hector, branchée sur l'API Symfony à la racine du dépôt.

## Lancer l'application

L'API doit tourner (`make start` à la racine). Depuis la racine du dépôt :

```bash
make mobile_install
make mobile_run_dev                                   # API sur http://localhost:8000
make mobile_run_dev API_URL=http://10.0.2.2:8000      # émulateur Android
```

L'adresse de l'API est injectée à la compilation (`--dart-define=API_BASE_URL=…`, cf. `lib/src/config.dart`).

## Tests

```bash
make mobile_test    # flutter analyze + tests unitaires et de widgets (sans réseau)
make mobile_e2e     # parcours complet sur un simulateur ou un émulateur lancé, contre l'API locale
```

## Organisation

```
lib/
  main.dart                  point d'entrée, restauration de session
  l10n/                      textes de l'interface (app_fr.arb, fichiers générés par flutter gen-l10n)
  src/
    config.dart              configuration injectée à la compilation
    router.dart              routes et redirections selon la session
    theme.dart               thème clair / sombre
    domain/                  modèles et règles de saisie
    data/                    client de l'API (Dio), stockage sécurisé des tokens
    state/                   état applicatif (Riverpod)
    presentation/            écrans et widgets
test/                        tests unitaires et de widgets (API scriptée)
integration_test/            parcours de bout en bout sur appareil
```

Le client de l'API est écrit à la main dans `lib/src/data/`. Le contrat de référence est la spec OpenAPI servie par le backend sur `/api/v1/doc` (Swagger sur `/api/doc`).

Après une modification de `lib/l10n/app_fr.arb` : `flutter gen-l10n`.
