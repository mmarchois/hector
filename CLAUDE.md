# Hector AI

Assistant des électriciens sur les chantiers. API Symfony à la racine (aucun front web), application Flutter dans `mobile/`. Architecture et conventions reprises du projet Lumo.

## Commandes

PHP et Composer ne sont pas installés sur la machine : tout passe par Docker (`make help` pour la liste).

| Besoin | Commande |
|--------|----------|
| Démarrer | `make start` |
| Tests API | `make test`, `make test_unit`, `make test_integration` (`ARGS="--filter Nom"`) |
| Qualité API | `make check` (CS Fixer, PSR-4, PHPStan niveau 6, conteneur, schéma) ; `make format` corrige le style |
| Console / Composer | `make console CMD="debug:router"`, `make composer CMD="require x/y"` |
| Migration | `make dbmigration` puis `make dbmigrate` (et `make dbmigrate ARGS="--env=test"`) |
| Fixtures de test | `make dbfixtures` (vide la base visée) |
| Mobile | `make mobile_test` ; `make mobile_e2e` (simulateur lancé + API locale) |

Sans TTY (agent), remplacer `docker compose exec php` par `docker compose exec -T php`.

Ports locaux : 8000 (API) et 5432 (base) par défaut ; un `docker-compose.override.yml` ignoré par git peut les déplacer : `docker compose ps` donne les ports réels du poste.

## Architecture de l'API (hexagonale, DDD, CQRS)

- `src/Domain` : entités (setters fluides, constructeur `uuid`), exceptions, spécifications, interfaces de repository, ports (`Domain/Port`). Aucune dépendance au framework.
- `src/Application` : une `XxxCommand` / `XxxQuery` (`final readonly`, implémente `CommandInterface` / `QueryInterface`) et son `XxxHandler` (`__invoke`). Les handlers sont enregistrés par convention de nom et de dossier (`config/services.yaml`), sans attribut.
- `src/Infrastructure` : adaptateurs, contrôleurs (`Controller/Api/V1/<Contexte>/`, un contrôleur invocable par route), DTO (`Dto/`), Doctrine (mapping XML dans `Persistence/Doctrine/Mapping`, fichier `<Contexte>.<Entité>.orm.xml`), sécurité.
- Validation : jamais dans les DTO ni les entités, toujours en XML sur la Command (`config/validator/Application/<Contexte>/Command/`). Le middleware `validation` du bus la déclenche ; `ApiExceptionListener` la rend en 422 `{code, message, violations: [{property, message}]}`. Les propriétés d'une Command portent le même nom que les champs de la requête, pour que `property` désigne un champ connu du client.
- Les handlers lèvent des exceptions du domaine ; le contrôleur les traduit en exception HTTP (409, 404…) ou en `ViolationHttpException` (422 sur un champ).
- Messages destinés à l'utilisateur : en français, lisibles tels quels (l'application mobile les affiche).
- Identité : les JWT et refresh tokens portent l'uuid de l'utilisateur (`SecurityUser::getUserIdentifier()`), pas l'email ; `UserProvider` accepte un email (connexion) ou un uuid (token).

## Ajouter une fonctionnalité à l'API

1. Domaine : entité, interface de repository, exceptions.
2. Mapping XML + repository Doctrine, puis `make dbmigration` et migrations dev + test.
3. Command ou Query + Handler, validation XML, test unitaire du handler (`tests/Unit`, stubs et mocks PHPUnit).
4. Contrôleur + DTO avec attributs OpenAPI (`#[OA\…]`), test d'intégration (`tests/Integration`, hérite de `AbstractApiTestCase`).
5. `make check` et `make test` avant de considérer le travail terminé.

## Application mobile

- `mobile/lib/src` : `domain/` (modèles, règles de saisie), `data/` (client Dio écrit à la main, stockage sécurisé), `state/` (Riverpod), `presentation/` (écrans), `router.dart`, `theme.dart`.
- Textes de l'interface dans `lib/l10n/app_fr.arb`, puis `flutter gen-l10n` (fichiers générés versionnés).
- Tests : API simulée par `ScriptedAdapter` (`test/support/fakes.dart`) injecté via `httpClientAdapterProvider`, ce qui exerce le vrai câblage (Bearer, refresh, rejeu).
- Toute route ajoutée à l'API est répercutée à la main dans `data/` ; la spec de référence est `/api/v1/doc`.
