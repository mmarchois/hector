# Hector AI

Assistant des électriciens sur les chantiers. API Symfony à la racine (aucun front web), application Flutter dans `mobile/`. Architecture et conventions reprises du projet Lumo.

## Commandes

PHP et Composer ne sont pas installés sur la machine : tout passe par Docker (`make help` pour la liste).

| Besoin | Commande |
|--------|----------|
| Démarrer | `make start` |
| Tests API | `make test`, `make test_unit`, `make test_integration`, `make test_critical` (`ARGS="--filter Nom"`) |
| Qualité API | `make check` (CS Fixer, PSR-4, PHPStan niveau 6 avec les règles d'architecture, conteneur, schéma) ; `make format` corrige le style |
| Console / Composer | `make console CMD="debug:router"`, `make composer CMD="require x/y"` |
| Migration | `make dbmigration` puis `make dbmigrate` (et `make dbmigrate ARGS="--env=test"`) |
| Fixtures de test | `make dbfixtures` (vide la base visée) |
| Évaluation | `make eval` (appels réels et payants au modèle ; `MODEL=…`, `ARGS="--family=normative_trap"`), `make eval_compare BEFORE=… AFTER=…` |
| Mobile | `make mobile_test` ; `make mobile_e2e` (simulateur lancé + API locale) |

Sans TTY (agent, intégration continue), passer `BIN_SHELL="docker compose exec -T php"` aux cibles `make`.

Ports locaux : 8003 (API) et 5432 (base) par défaut ; un `docker-compose.override.yml` ignoré par git peut les déplacer : `docker compose ps` donne les ports réels du poste. `make mobile_e2e` vise `API_URL=http://localhost:8003` par défaut : le passer explicitement si le port diffère.

Symfony 8.1 : version sans support long, maintenue jusqu'en janvier 2027. Passer en 8.2 dès sa sortie stable, puis suivre chaque version mineure jusqu'à la 8.4 (support long).

## Architecture de l'API (hexagonale, DDD, CQRS)

- `src/Domain` : entités (setters fluides, constructeur `uuid`), exceptions, spécifications, interfaces de repository, ports (`Domain/Port`). Aucune dépendance au framework.
- `src/Application` : une `XxxCommand` / `XxxQuery` (`final readonly`, implémente `CommandInterface` / `QueryInterface`) et son `XxxHandler` (`__invoke`). Les handlers sont enregistrés par convention de nom et de dossier (`config/services.yaml`), sans attribut.
- `src/Infrastructure` : adaptateurs, contrôleurs (`Controller/Api/V1/<Contexte>/`, un contrôleur invocable par route), DTO (`Dto/`), Doctrine (mapping XML dans `Persistence/Doctrine/Mapping`, fichier `<Contexte>.<Entité>.orm.xml`), sécurité.
- Dépendances vérifiées par PHPStan (phpat, `tests/Architecture/ArchitectureRules.php`) : `Domain` ne dépend que de `Domain` (aucun framework, aucune bibliothèque), `Application` que d'`Application` et de `Domain`. Un contexte (dossier de `src/Domain` ou `src/Application`) est isolé par défaut : un lien vers un autre se déclare dans `ALLOWED_LINKS`, d'après `docs/DOMAIN.md`, § 2. Les contextes ne se branchent sur `Conversation` que depuis leur dossier `Application/<Contexte>/Capability`. `Domain/Port` est commun à tous.
- Une entité purement technique (ex. `Infrastructure/Security/RefreshToken`) vit dans `Infrastructure`, son mapping dans `Persistence/Doctrine/InfrastructureMapping`.
- Validation : jamais dans les DTO ni les entités, toujours en XML sur la Command (`config/validator/Application/<Contexte>/Command/`). Le middleware `validation` du bus la déclenche ; `ApiExceptionListener` la rend en 422 `{code, message, violations: [{property, message}]}`. Les propriétés d'une Command portent le même nom que les champs de la requête, pour que `property` désigne un champ connu du client.
- Les handlers lèvent des exceptions du domaine ; le contrôleur les traduit en exception HTTP (409, 404…) ou en `ViolationHttpException` (422 sur un champ).
- Messages destinés à l'utilisateur : écrits en français dans le code, lisibles tels quels (l'application mobile les affiche), et traduits dans la langue de la requête (`Accept-Language`, décision D19). Le texte français est la clé : tout nouveau message s'ajoute à `translations/messages.<langue>.yaml` de chaque langue ouverte (ou `validators.<langue>.yaml` pour une contrainte). Langues ouvertes : français, anglais, allemand ; la marche à suivre pour en ouvrir une est dans `docs/ARCHITECTURE.md`, D19. En test d'intégration, une requête est en français sauf mention contraire (`apiRequest(..., language: 'en')`).
- Essai sans compte (`docs/ARCHITECTURE.md`, D17) : un invité est un `User` sans nom, email ni mot de passe (`isGuest()`), ouvert par `POST /api/v1/auth/guest`. `POST /api/v1/auth/register` appelée avec son jeton convertit ce même compte. Une erreur qui appelle une action du client porte un `reason` (`ReasonHttpException`). `app:guests:purge` supprime les invités sans session.
- Modèle de langage : seuls `AnswerEngine` (le tour, pour l'application comme pour l'évaluation) et le juge de l'évaluation l'appellent, par le port `ChatModelInterface` (`Application/Conversation/Port`) ; l'adaptateur Anthropic vit dans `Infrastructure/Adapter/Llm/Anthropic` et aucune classe du SDK n'en sort. En test, `tests/Support/FakeChatModel` le remplace (programmable, `disableReboot()` pour le garder d'une requête à l'autre) ; `make test` n'appelle jamais le fournisseur. `make test_live` fait un vrai appel payant, avec la clé de `.env.local`. Consignes versionnées dans `resources/prompts`.
- Réponse de l'assistant (lot L7) : le modèle renvoie un JSON contraint par `AnswerFormat::schema()` ; `DraftValidator` (domaine) contrôle le brouillon avant qu'il soit montré, `TurnRunner` fait une seule reprise avec les motifs puis répond par un texte dégradé fixe. Toute règle du contrôle a ses cas acceptés et rejetés dans `tests/Critical`. En test, `FakeChatModel::statement()` et `::draft()` fabriquent un brouillon valide.
- Évaluation (lot L8, `src/Infrastructure/Evaluation`) : cas en JSON dans `resources/evaluation/cases` (un fichier par famille), jugés par des contrôles sans modèle puis par un juge. À lancer avant tout changement de modèle ou de consignes, et à comparer avec la mesure précédente. Elle coûte de l'argent : ne jamais la lancer sans l'accord de l'utilisateur. Tout cas ajouté sans relecture d'un expert garde `review` vide.
- Base documentaire (lot L9, contexte `Knowledge`) : une source a un corpus et une licence ; une version naît en brouillon à l'ingestion (`app:knowledge:ingest`), se relit (`app:knowledge:show <version>`), n'est publiée qu'avec éditeur, libellé, date de publication, licence renseignée et relecteur (`app:knowledge:publish`), puis ne change plus. Extraction et découpage sont des ports (`TextExtractorInterface`, tagué `app.text_extractor` ; `ChunkerInterface`) : tout changement qui modifie les passages produits change le nom versionné de la méthode (`markdown@1`, `heading@1`) ou `IngestDocumentCommandHandler::PIPELINE_VERSION`.
- Capacités (lot L10) : un contexte métier se branche sur la conversation en implémentant `CapabilityInterface` (contrat dans `Application/Conversation/Capability`, service tagué `app.capability`) depuis son dossier `Application/<Contexte>/Capability` : des outils (`ToolInterface`) et des consignes pour le tour. Un outil déclare ce qu'il donne à lire dans `Evidence` ; seul ce qui y figure peut être cité (références S1, S2… dans le brouillon, identifiants durables dans le message enregistré). La source affichée est composée par le serveur, jamais par le modèle.
- Calcul (lot L13, contexte `Calculation`, sans lien avec le modèle de langage) : un calculateur (`Domain/Calculation/Calculator`, inscrit dans `CalculatorCatalog`) est une fonction pure, construite sans dépendance, qui annonce entrées, sortie, hypothèses, formule et source (`CalculatorDefinition`). `CalculationEngine` contrôle puis calcule : aucun nombre sans unité, aucune valeur supposée, un refus motivé par entrée (`Refusal`, gabarit français traduit par `CalculationTexts`). Changer une formule, une borne ou l'arrondi change `version` ; un `CalculationRun` garde la copie du calcul tel qu'il a été fait. Tout calculateur a ses cas dans `tests/Critical/Calculation/reference-cases.json` (valeurs posées hors du code ; `review` vide tant qu'un expert ne les a pas relues), son sens de variation dans `CalculatorPropertiesTest` et ses textes traduits. Ne jamais y écrire une valeur tirée d'une norme sans sa source.
- Limites de consommation : allocation par compte en messages et en jetons (`FreeAllowancePolicy`), budget global sur 24 heures (`GlobalTokenBudget`, `CHAT_DAILY_TOKEN_BUDGET`), historique borné (`TurnContextBuilder`). Tout refus a lieu avant l'appel au modèle.
- Identité : les JWT et refresh tokens portent l'uuid de l'utilisateur (`SecurityUser::getUserIdentifier()`), pas l'email ; `UserProvider` accepte un email (connexion) ou un uuid (token).

## Ajouter une fonctionnalité à l'API

1. Domaine : entité, interface de repository, exceptions.
2. Mapping XML + repository Doctrine, puis `make dbmigration` et migrations dev + test.
3. Command ou Query + Handler, validation XML, test unitaire du handler (`tests/Unit`, stubs et mocks PHPUnit).
4. Contrôleur + DTO avec attributs OpenAPI (`#[OA\…]`), test d'intégration (`tests/Integration`, hérite de `AbstractApiTestCase`).
5. `make check` et `make test` avant de considérer le travail terminé.

## Application mobile

- `mobile/lib/src` : `domain/` (modèles, règles de saisie), `data/` (client Dio écrit à la main, stockage sécurisé), `state/` (Riverpod), `presentation/` (écrans), `router.dart`, `theme.dart`.
- Textes de l'interface dans `lib/l10n/app_fr.arb` et, traduits, dans `app_en.arb` et `app_de.arb` (un test vérifie que chaque langue a toutes les clés), puis `flutter gen-l10n` (fichiers générés versionnés). La langue de l'application est celle du téléphone si elle est ouverte, sinon le français (`lib/src/locale.dart`) ; elle part à l'API dans `Accept-Language`. Les tests parlent français (`buildContainer`, `testApp`), sauf `locale:` explicite.
- Tests : API simulée par `ScriptedAdapter` (`test/support/fakes.dart`) injecté via `httpClientAdapterProvider`, ce qui exerce le vrai câblage (Bearer, refresh, rejeu).
- Toute route ajoutée à l'API est répercutée à la main dans `data/` ; la spec de référence est `/api/v1/doc`.
- Sources d'une réponse : `ChatMessage.citations` (composées par l'API), affichées par `presentation/widgets/sources.dart` ; l'état actuel d'un passage se lit par `GET /api/v1/passages/{uuid}`.
- Calculs : `domain/calculation.dart` et `data/calculation/calculation_repository.dart` répercutent les routes de calcul ; aucun écran ne les utilise encore. L'application ne calcule jamais elle-même.
- Sans session : écran de bienvenue (conditions d'utilisation), puis compte invité ou connexion. Un écran empilé par `push` n'est pas redirigé par le router : après une connexion ou une inscription, revenir à l'accueil explicitement.
- Tests d'écran : un appel réseau attendu hors des `pump` passe par `tester.runAsync`, sinon les minuteries de Dio ne s'écoulent pas.
- Design : fond net, formes très arrondies, surfaces douces sans contour, boutons en pilule, un seul accent (jaune `HectorColors.volt`, toujours avec un contenu noir), police Plus Jakarta Sans embarquée (`assets/fonts`). Tout passe par `theme.dart` (`HectorTheme`, `HectorTokens`) et `presentation/widgets/kit.dart` (`KindChip`, `SoftCard`, `GroupedList`…) : pas de couleur ni de rayon en dur dans un écran. `make mobile_preview` produit des captures de tous les écrans, en clair et en sombre, avec les vraies polices (`mobile/build/preview`) : à regarder avant de valider un changement visuel.
- Un écran peut dépasser la fenêtre de test (800 × 600, police de test plus large) : amener un élément à l'écran (`ensureVisible`) avant de le toucher.
