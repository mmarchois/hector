# Hector AI — Architecture

> **Statut : proposition à valider** (15 novembre 2026). Rien de ce qui est décrit ici n'est développé.
> À lire avec : [PRODUCT.md](PRODUCT.md) · [DOMAIN.md](DOMAIN.md) · [ROADMAP.md](ROADMAP.md) · [SECURITY.md](SECURITY.md)

Les décisions structurantes sont regroupées au § 13, chacune avec ses alternatives, ses compromis et une recommandation. Le reste du document décrit l'architecture telle qu'elle serait si les recommandations étaient retenues.

## 1. État du dépôt

### 1.1 Ce qui existe

| Élément | État au 15 novembre 2026 |
|---|---|
| API | PHP 8.4, Symfony 7.4, Doctrine ORM 3 avec mapping XML, PostgreSQL 17. Deux bus Messenger synchrones : commandes (validation puis transaction) et requêtes. JWT et jeton de rafraîchissement à usage unique. OpenAPI par Nelmio. |
| Contextes | `User` (inscription, profil, suppression de compte) et `Company` (entreprise identifiée par son SIRET). Le compte distingue professionnel et particulier. |
| Mobile | Flutter avec Riverpod, go_router et Dio. Écrans de connexion, d'inscription, de profil et d'accueil. Client HTTP écrit à la main, avec renouvellement du jeton. |
| Qualité | PHP CS Fixer, PHPStan niveau 6, PHPUnit 13 (tests unitaires et d'intégration, échec sur dépréciation), tests Flutter et parcours de bout en bout. |
| Exploitation | Docker Compose (php-fpm, nginx, PostgreSQL) et Makefile. Pas d'intégration continue, pas d'hébergement défini. |

### 1.2 Ce qu'on garde tel quel

Les conventions décrites dans `CLAUDE.md` restent la règle : trois couches `Domain`, `Application`, `Infrastructure` ; une Command ou une Query et son Handler par cas d'usage ; validation en XML sur la Command ; un contrôleur invocable par route ; mapping Doctrine en XML ; erreurs au format `{code, message, violations}` ; messages en français ; côté mobile, `domain`, `data`, `state`, `presentation` et l'adaptateur scripté pour les tests.

### 1.3 Ce qui manque pour la vision

- Aucun composant d'intelligence artificielle, aucun client HTTP sortant.
- Aucun traitement asynchrone : les deux bus sont synchrones.
- Pas d'extension vectorielle dans la base (image `postgres:17-alpine`).
- Pas de stockage de fichiers.
- Pas de contrôle automatique des dépendances entre couches et entre contextes.
- Pas d'intégration continue.
- Côté mobile, pas d'écran de conversation.

### 1.4 Points d'attention relevés dans le code

1. **Le bus de commandes ouvre une transaction autour de tout le handler** (`config/packages/messenger.yaml`). Un appel à un modèle de langage dure de quelques secondes à plusieurs dizaines de secondes : il ne doit pas se faire dans un handler de commande, sinon la transaction reste ouverte tout ce temps. Le § 5 en tient compte.
2. **Les délais actuels sont trop courts pour un tour de conversation** : 30 secondes côté mobile (`mobile/lib/src/data/api.dart`), 60 secondes par défaut côté nginx.
3. **Un tour synchrone occupe un processus PHP-FPM pendant toute sa durée.** Le nombre de processus n'est pas configuré dans le dépôt ; avec la valeur par défaut, quelques tours simultanés suffisent à bloquer toute l'API, connexion comprise (décision D8).
4. **`make check` exécute `doctrine:schema:validate`.** Les colonnes que l'ORM ne connaît pas (vecteurs, index plein texte) devront être déclarées par un type personnalisé ou exclues du contrôle, sinon la vérification échouera.

### 1.5 Symfony 8 (décision prise)

Le passage à Symfony 8 est demandé dès que possible. Une simulation de résolution Composer faite le 15 novembre 2026 sur une copie de `composer.json` aboutit en 8.1.11 sans changer aucune autre dépendance (Doctrine, Lexik JWT, Gesdinet, Nelmio, PHPUnit). La version 8.2 est en bêta. C'est le lot L1 de la feuille de route, placé avant tout ajout de dépendance liée à l'IA. À savoir : Symfony 7.4 est une version à support long, les versions 8.1 à 8.3 ne le sont pas ; il faudra donc suivre une version mineure tous les six mois jusqu'à la 8.4.

## 2. Principes

1. **Un monolithe découpé en contextes.** Un dépôt, un déploiement, une base. Les contextes sont séparés par des règles de dépendance vérifiées par un outil, pas par le réseau.
2. **Les dépendances vont vers l'intérieur.** Le domaine ne dépend de rien, l'application ne dépend que du domaine, l'infrastructure implémente leurs interfaces.
3. **Un seul endroit parle au modèle de langage** : le tour de conversation. Les contextes métier ne l'appellent pas ; ils lui offrent des outils.
4. **Le modèle propose, le domaine décide.** Une réponse, une transition de diagnostic ou un classement de produits ne devient réel qu'après un contrôle déterministe.
5. **Ce qui est critique se teste sans modèle de langage** : calculs, règles de prévention, contrôle des citations, niveau de confiance, compatibilité.
6. **Tout ce qui influence une réponse est versionné** : documents, registre normatif, consignes, calculateurs, procédures, données produits. La trace d'un tour consigne les versions utilisées.
7. **Normes et produits sont gérés séparément et interrogeables ensemble.** Corpus distincts, contextes distincts, cycles de mise à jour distincts (décision D16).
8. **Sur les produits, trois choses ne se confondent pas** : une caractéristique vérifiable, un avis rapporté, une recommandation de l'assistant. Rien n'est inventé : ni caractéristique, ni prix, ni disponibilité, ni compatibilité.
9. **Neutralité.** Une recommandation découle du besoin, des contraintes, du budget et des préférences exprimées. Aucune marque n'est favorisée par construction.
10. **Pas de complexité sans besoin constaté.** Pas de microservice, pas de file de messages dédiée, pas de moteur de recherche séparé tant qu'une mesure ne le justifie pas.

## 3. Vue d'ensemble

```
Application mobile (Flutter)
        │  HTTPS · JSON · JWT
        ▼
API Symfony — un seul déploiement
┌────────────────────────────────────────────────────────────────────┐
│  Conversation (socle)                                               │
│    tour de conversation : seul point d'appel du modèle de langage   │
│         ▲  capacités : outils, contexte, blocs                      │
│  ┌──────┴────────┬─────────────┬─────────────┬───────────────────┐  │
│  │ Documentation │ Calcul      │ Diagnostic  │ Conseil produits* │  │
│  │ Réglementation│             │ Prévention  │ Catalogue*        │  │
│  └───────────────┴─────────────┴─────────────┴───────────────────┘  │
│  Identité (User, Company)                                           │
└────────────────────────────────────────────────────────────────────┘
        │  ports
        ▼
PostgreSQL · fichiers · fournisseur du modèle de langage · fournisseur de vecteurs

* après le MVP
```

Les responsabilités et le modèle de chaque contexte sont dans [DOMAIN.md](DOMAIN.md).

## 4. Structure du code

```
src/
  Domain/
    User/  Company/                    existants (Identité)
    Conversation/  Knowledge/  Regulation/  Calculation/  Diagnosis/  Safety/
    Catalog/  ProductRecommendation/   après le MVP
    Port/                              existant : ports transverses (dates, identifiants, hachage)
  Application/
    <Contexte>/Command/  Query/        existant : une Command ou Query et son Handler
    <Contexte>/Service/                existant : services applicatifs
    <Contexte>/Port/                   nouveau : ports techniques du contexte
    Conversation/Capability/           nouveau : interface de capacité et registre
  Infrastructure/
    Adapter/Llm/<Fournisseur>/         nouveau : un dossier par fournisseur
    Adapter/Search/  Extraction/  Storage/
    Controller/Api/V1/<Contexte>/      existant
    Persistence/Doctrine/…             existant
resources/                             nouveau : contenus métier versionnés (décision D13)
  prompts/  safety/  regulation/  evaluation/
tests/
  Unit/  Integration/                  existants
  Critical/                            nouveau : calculs et règles critiques, sans modèle de langage
```

Règles de dépendance, vérifiées dans `make check` par PHPat, une extension de PHPStan (`tests/Architecture/ArchitectureRules.php`, lot L2). Deptrac faisait aussi l'affaire, mais demandait un outil et une configuration de plus :

- `Domain` ne dépend que de `Domain` : ni `Application`, ni `Infrastructure`, ni framework, ni bibliothèque, ni SDK d'un fournisseur. Les classes natives de PHP sont permises.
- `Application` ne dépend que d'`Application` et de `Domain`.
- Un contexte n'utilise que les contextes du tableau des relations de DOMAIN.md, § 2, recopié dans les règles. Un nouveau dossier de contexte est isolé par défaut. L'outil vérifie le lien entre contextes, pas la classe utilisée : passer par un identifiant et par une interface publiée reste une règle de relecture.
- Les contextes ne se branchent sur `Conversation` que depuis leur dossier `Application/<Contexte>/Capability`, et n'y voient que son interface de capacité. `Calculation` et `Safety` n'atteignent donc jamais le port du modèle de langage. `Conversation` n'utilise aucun contexte (CV7).
- `Domain/Port` (dates, identifiants, hachage) est commun à tous les contextes.

## 5. Le tour de conversation

```
POST /api/v1/conversations/{uuid}/messages
  1. Commande « enregistrer le message de l'utilisateur »    transaction courte
     crée le message et le tour, statut « en cours »
  2. Exécution du tour (service applicatif)                   hors transaction
     a. assemble le contexte : consignes versionnées, qualification de l'utilisateur,
        historique, état des activités en cours, outils des capacités actives
     b. boucle bornée : le modèle répond ou appelle des outils
        (recherche, calculs, transitions de diagnostic), jusqu'à un brouillon de réponse
     c. contrôle déterministe du brouillon : citations, références normatives,
        gestes proposés, consignes interdites, signaux de danger
     d. si le contrôle échoue : une reprise avec le motif du rejet,
        puis une réponse dégradée et explicite si elle échoue encore
  3. Commande « enregistrer la réponse »                      transaction courte
     message de l'assistant, trace du tour, statut final
  4. Réponse HTTP : le tour et, s'il est terminé, le message de l'assistant
```

Points à retenir :

- L'appel au modèle se fait à l'étape 2, en dehors de toute transaction (point d'attention 1 du § 1.4).
- La boucle est bornée en nombre d'itérations, en jetons et en durée. À la limite, le tour se termine en réponse dégradée, pas en erreur muette.
- Les blocs chiffrés ou tabulaires (calcul, comparaison de produits, état d'un diagnostic) sont composés par le serveur à partir des données du domaine. Le modèle choisit de les montrer, il n'en écrit pas le contenu.
- Aucune donnée d'identité (nom, e-mail, SIRET) n'est envoyée au fournisseur. Le modèle reçoit la qualification, pas l'identité.
- La qualification règle deux choses : les gestes permis, décidés par Prévention, et le niveau de langage, réglé par les consignes. Un particulier guidé lors d'une panne chez lui n'a ni les mêmes droits ni le même vocabulaire qu'un électricien.
- Si la connexion du mobile tombe, le tour se termine quand même et le client retrouve la réponse en relisant la conversation.
- La trace du tour consigne le modèle, la version des consignes, les passages lus, les outils appelés, les contrôles et leur issue, la consommation et la durée.

## 6. Brancher un domaine : les capacités

Le socle ne sait rien de l'électricité. Un contexte métier se rend utile dans la conversation en déclarant une **capacité**, qui fournit :

| Élément | Rôle | Exemple pour Diagnostic |
|---|---|---|
| Outils | Actions que le modèle peut demander, avec un schéma d'entrée strict | `proposer_hypothese`, `proposer_verification`, `enregistrer_observation` |
| Contexte | Texte ajouté aux consignes du tour quand la capacité est active | État de la session : hypothèses, observations, vérifications en attente |
| Blocs | Types de blocs que la capacité sait produire | Étape de vérification, tableau des hypothèses, conclusion |
| Conditions | Quand la capacité est offerte | Selon la qualification, selon l'activité en cours de la conversation |

Les capacités sont des services étiquetés, découverts par le conteneur de Symfony. Ajouter un domaine fonctionnel revient à :

1. créer son contexte (domaine, cas d'usage, persistance) et ses tests, sans toucher au socle ;
2. déclarer sa capacité : outils, contexte, blocs ;
3. ajouter ses sources au bon corpus, ses calculateurs et ses procédures s'il en a ;
4. ajouter le rendu de ses blocs dans l'application mobile (un bloc inconnu d'une ancienne version s'affiche par son texte de repli) ;
5. ajouter ses cas au jeu d'évaluation.

Le conseil en équipements est le premier test de ce mécanisme après le MVP : il arrive avec deux contextes, des outils (`rechercher_produits`, `comparer_produits`, `verifier_compatibilite`, `recommander`), des blocs (fiche produit, tableau comparatif, recommandation) et son propre corpus, sans modifier Conversation.

## 7. Ports et adaptateurs

Les interfaces de repository suivent la convention existante (`Domain/<Contexte>/Repository`). Les ports techniques sont nouveaux :

| Port | Rôle | Premier adaptateur | Lot |
|---|---|---|---|
| `ChatModelInterface` | Envoie consignes, messages, outils et schéma de sortie ; reçoit texte, appels d'outils ou sortie structurée, motif d'arrêt (dont le refus), consommation | SDK du fournisseur retenu ; `FakeChatModel` scriptable en test | L5 |
| `CapabilityInterface`, `ToolInterface` | Point d'extension du socle | Un par contexte métier | L5, puis chaque capacité |
| `PromptCatalogInterface` | Fournit les consignes et leur version | Fichiers de `resources/prompts` | L5 |
| `QuotaInterface` | Allocation gratuite selon le type de compte, quota par utilisateur, budget par tour | `symfony/rate-limiter`, déjà présent | L5, L19 |
| `FileStorageInterface` | Conserve les fichiers sources, avec leur empreinte | Système de fichiers | L9 |
| `DocumentExtractorInterface` | Transforme un fichier en texte structuré et localisé | À choisir sur essai (D14) | L9 |
| `PassageSearchInterface` | Cherche des passages dans des corpus nommés, avec filtres | PostgreSQL plein texte, puis hybride | L10, L12 |
| `EmbeddingModelInterface` | Calcule des vecteurs, annonce son modèle et sa dimension | Fournisseur à choisir (D5) ; faux déterministe en test | L12 |
| `SafetyPolicyInterface` | Publiée par Prévention : ce geste est-il permis pour cette qualification ? | Domaine | L15 |
| `ProductDataImporterInterface` | Importe un lot de données produits depuis une source | Fichiers de fabricants | L21 |
| `ProductSearchInterface` | Cherche des références par catégorie et critères | PostgreSQL | L22 |
| `OfferFeedInterface` | Relève prix et disponibilités chez un distributeur | Selon la source retenue (P8) | L26 |

Ports existants réutilisés : `DateUtilsInterface`, `IdFactoryInterface`, les deux bus. Ports prévisibles plus tard : transcription de la voix, analyse d'image, stockage d'objets.

`ChatModelInterface` n'expose que ce que tous les fournisseurs savent faire : messages, outils décrits par un schéma JSON, schéma de sortie, limites. Il n'expose ni température, ni choix d'outil forcé, ni préremplissage de la réponse : certains modèles récents les refusent, et s'y appuyer créerait une dépendance cachée à un fournisseur.

## 8. Connaissances : documents, registre, catalogue

Trois magasins, trois cycles de vie :

| Magasin | Contenu | Rythme | Critère de fiabilité |
|---|---|---|---|
| Documentation, corpus réglementaire et technique | Textes, guides, fiches Hector, en versions datées | Lent : au rythme des éditions | Texte officiel ou relu, édition identifiée |
| Documentation, corpus produits | Notices et fiches techniques de fabricants | Moyen : au rythme des gammes | Document du fabricant, daté |
| Catalogue | Données structurées : références, caractéristiques, prix | Rapide pour les prix, moyen pour le reste | Source et date par valeur |
| Retours du terrain (après le MVP) | Cas de dépannage relus et anonymisés | Continu : au rythme des relectures | Relecture humaine ; présenté comme hypothèse avec son nombre de cas (D18) |

Le registre normatif (Réglementation) n'est pas un magasin de contenu : il dit quels textes et quelles éditions existent, et à quelles dates.

**Recherche documentaire.** Elle combine deux approches, parce que les questions d'électriciens contiennent des identifiants exacts (numéro d'article, calibre, référence de produit) que la recherche par le sens retrouve mal, et des formulations libres que la recherche par mots retrouve mal. Le lot L10 livre la recherche par mots, en plein texte français. Le lot L12 ajoute la recherche par vecteurs et la fusion des deux listes, et n'est conservé que s'il améliore la mesure faite sur le jeu d'évaluation. Une recherche nomme toujours les corpus visés et peut filtrer par genre de source, par domaine et par date d'effet.

**Citations.** Le modèle reçoit des passages munis d'un identifiant et cite par identifiant. Le serveur vérifie que chaque identifiant cité fait partie des passages lus pendant le tour, puis compose lui-même l'affichage de la source : titre, éditeur, édition, dates, localisation (décision D7).

## 9. Persistance

- PostgreSQL reste la seule base. Les entités suivent la convention existante : PHP pur, mapping XML.
- Les passages, leurs index plein texte et leurs vecteurs sont lus et écrits en SQL par les adaptateurs de recherche, pas par l'ORM (point d'attention 4 du § 1.4).
- L'extension vectorielle demande une image PostgreSQL qui l'inclut. C'est un changement du `docker-compose.yml`, à faire au lot L12.
- Les traces de tour et le journal de diagnostic sont en ajout seul.
- Les contenus métier de `resources/` sont chargés en base par une commande, avec leur version. Une commande d'exploitation peut désactiver immédiatement un document, une procédure ou un calculateur.

## 10. Contrat d'API (esquisse)

| Route | Rôle | Lot |
|---|---|---|
| `POST /api/v1/auth/guest` | Ouvrir un compte invité ; renvoie ses jetons | L3 |
| `POST /api/v1/auth/register` (existante) | Appelée avec le jeton d'un invité, convertit ce compte au lieu d'en créer un | L3 |
| `POST /api/v1/conversations`, `GET /api/v1/conversations` | Créer, lister ses conversations | L4 |
| `GET /api/v1/conversations/{uuid}/messages` | Lire les messages, par pages | L4 |
| `POST /api/v1/conversations/{uuid}/messages` | Envoyer un message ; renvoie le tour et son statut | L4, L5 |
| `GET /api/v1/turns/{uuid}` | Suivre un tour en cours | L4 |
| `POST /api/v1/auth/merge-guest` | Rattacher au compte connecté les données d'un invité (P11) | L4 |
| `POST /api/v1/messages/{uuid}/feedback` | Signaler ou noter une réponse | L18, L19 |
| `GET /api/v1/passages/{uuid}` | Consulter une source citée, selon sa licence | L10 |
| `GET /api/v1/calculators`, `POST /api/v1/calculations`, `GET /api/v1/calculations/{uuid}` | Lister les calculateurs, calculer sans conversation, relire un calcul tel qu'il a été fait | L13 |
| `GET /api/v1/diagnostics/{uuid}` | Lire l'état d'un diagnostic | L16 |

Forme d'un message de l'assistant :

```json
{
  "uuid": "…",
  "role": "assistant",
  "blocks": [
    { "type": "statement", "version": 1, "kind": "sourced_fact",
      "text": "…", "citations": ["c1"], "fallbackText": "…" },
    { "type": "statement", "version": 1, "kind": "hypothesis",
      "text": "…", "citations": [], "fallbackText": "…" },
    { "type": "question", "version": 1, "text": "…", "reason": "…",
      "choices": ["…", "Je ne sais pas"], "fallbackText": "…" }
  ],
  "citations": [
    { "id": "c1", "passageUuid": "…", "corpus": "reglementaire", "sourceTitle": "…",
      "publisher": "…", "edition": "…", "effectiveFrom": "…", "locator": "…" }
  ]
}
```

## 11. Application mobile

- Nouveau module de conversation dans `data/`, `state/` et `presentation/`, sur le modèle de l'existant.
- Un composant d'affichage par type de bloc. Un type inconnu s'affiche par son texte de repli : une ancienne version de l'application reste utilisable quand l'API ajoute un bloc.
- Chaque nature d'énoncé a un rendu distinct (fait, hypothèse, recommandation, information manquante, avis rapporté). Les messages de sécurité ont un rendu qu'on ne peut confondre avec aucun autre.
- Le client sait suivre un tour « en cours » dès le premier lot, que le serveur réponde d'un coup ou non (décision D8).
- La mention « assistant IA » est visible en permanence sur l'écran de conversation.
- Sans session, l'application ouvre un compte invité au lieu d'imposer l'écran de connexion (D17). L'état de session distingue invité et compte complet ; une erreur `signup_required` mène à l'inscription, puis ramène l'utilisateur là où il en était.

## 12. Stratégie de test

| Niveau | Ce qu'il vérifie | Modèle de langage | Quand |
|---|---|---|---|
| Unitaire (`tests/Unit`) | Handlers, agrégats, services | Aucun, ou `FakeChatModel` | `make test` |
| Critique (`tests/Critical`) | Calculs, politique de prévention, contrôle des citations et des références, niveau de confiance, compatibilité. Cas de référence fournis par l'expert, dans des fichiers de données indépendants du code. Tests de mutation sur ces espaces de noms. | Aucun | `make test`, bloquant en intégration continue |
| Intégration (`tests/Integration`) | Routes de bout en bout sur la base de test | `FakeChatModel` | `make test` |
| Contrat des adaptateurs | Traduction entre nos objets et l'API du fournisseur | Réponses enregistrées | `make test` |
| Vivant | L'adaptateur contre le vrai fournisseur | Réel | À la main, `make test_live` |
| Évaluation | Qualité des réponses sur un jeu de cas : sources retrouvées, pièges normatifs, demandes dangereuses, scénarios de diagnostic, neutralité des recommandations | Réel | À la main et avant tout changement de consignes ou de modèle, `make eval` |
| Mobile | Écrans, rendu des blocs, repli, reprise après coupure | API simulée | `make mobile_test`, `make mobile_e2e` |

Les réponses d'un modèle ne sont pas reproductibles à l'identique. Les tests de `make test` n'en dépendent donc jamais ; la qualité se mesure par l'évaluation, sur des critères, pas sur des textes attendus.

## 13. Décisions à valider

### D1 — Où vit l'intelligence artificielle ?

| Option | Pour | Contre |
|---|---|---|
| A. Dans le monolithe Symfony, par contextes | Un déploiement, conventions et outillage en place, transactions et droits d'accès partagés, pas de contrat réseau interne | Écosystème PHP plus mince pour l'extraction de documents et l'évaluation |
| B. Service Python séparé | Écosystème le plus riche | Deux langages, deux déploiements, un contrat réseau à versionner, un domaine éclaté ; c'est la complexité prématurée que le cahier des charges écarte |
| C. A, plus un outil hors ligne pour préparer les documents | Garde A pour tout ce qui sert l'utilisateur ; aucun service de plus à exploiter | Un second langage dans le dépôt |

**Recommandation : A.** Les appels aux modèles sont des requêtes HTTP ; PHP n'est un handicap ni pour la conversation, ni pour les calculs, ni pour le diagnostic. C reste un repli, à décider au lot L9 sur pièces, si l'extraction en PHP se révèle insuffisante sur les vrais documents.

### D2 — Comment s'abstraire du fournisseur de modèle ?

| Option | Pour | Contre |
|---|---|---|
| A. Un port étroit à nous, un adaptateur par fournisseur | Indépendance réelle, surface minimale, tests simples | On écrit nous-mêmes la boucle d'appels d'outils |
| B. Symfony AI comme abstraction | Multi-fournisseurs, intégré à Symfony | Encore en version 0.x, sans version stable repérée à ce jour ([annonce de la première version](https://symfony.com/blog/symfony-ai-v0-1-0-first-tagged-release)) ; son API évolue ; compatibilité avec Symfony 8 à vérifier |
| C. SDK du fournisseur appelé depuis l'application | Le plus rapide | Contraire à l'indépendance demandée |

**Recommandation : A**, le premier adaptateur s'appuyant sur le SDK officiel du fournisseur retenu. Écrire la boucle d'outils est voulu : c'est là que se branchent les budgets, les traces et les contrôles. Symfony AI pourra devenir un adaptateur le jour où il sera stable, sans toucher ni au domaine ni à l'application.

### D3 — Quel fournisseur et quel modèle pour commencer ?

Critères : qualité en français technique mesurée sur notre jeu d'évaluation, fiabilité des appels d'outils et des sorties structurées, conditions de traitement des données (durée de conservation, lieu de traitement), coût, existence d'un SDK PHP.

Candidats : Anthropic (Claude), Mistral (éditeur français), OpenAI, Google, ou un modèle ouvert hébergé par nos soins. Ce dernier supprime le transfert de données à un tiers, mais demande d'exploiter des serveurs de calcul, ce qui est disproportionné pour un MVP.

**Recommandation : ne pas trancher sur une opinion.** Le lot L8 livre le jeu d'évaluation ; on y passe deux ou trois candidats et on choisit sur les chiffres. Pour démarrer le lot L5, je propose Claude, avec un SDK PHP officiel (`anthropic-ai/sdk`), les sorties structurées, les appels d'outils et la mise en cache des consignes. Cette proposition vient d'un modèle d'Anthropic : raison de plus pour la confronter à la mesure.

Ordre de grandeur des coûts, pour un appel de 15 000 jetons en entrée et 1 500 en sortie, hors jetons de raisonnement et hors mise en cache (tarifs relevés le 3 novembre 2026, à revérifier) :

| Modèle | Tarif entrée / sortie, par million de jetons | Coût d'un appel |
|---|---|---|
| Claude Opus 5.5 | 4 $ / 20 $ | environ 0,09 $ |
| Claude Sonnet 5.5 | 2 $ / 10 $ | environ 0,045 $ |
| Claude Haiku 5.5 | 0,10 $ / 0,50 $ | environ 0,002 $ |

Un tour compte souvent deux ou trois appels. Démarrer avec le modèle le plus capable de la gamme courante (Opus 5.5) donne la meilleure référence de qualité ; descendre en gamme est une décision de coût, à prendre sur la mesure. Les conditions de conservation des données varient d'un modèle à l'autre et sont à vérifier au moment du choix.

### D4 — Quel moteur de recherche documentaire ?

| Option | Pour | Contre |
|---|---|---|
| A. PostgreSQL : plein texte, puis extension vectorielle | Aucune infrastructure de plus, cohérence transactionnelle avec les versions de documents, suffisant pour des centaines de milliers de passages | Réglages plus manuels, colonnes hors ORM |
| B. Moteur dédié (Qdrant, Meilisearch, Elasticsearch) | Fonctions de recherche plus riches, montée en charge | Un service à exploiter et à synchroniser avec la base |
| C. Recherche gérée par le fournisseur du modèle | Rien à construire | Les documents partent chez le fournisseur, dépendance forte, pas de maîtrise des versions ni des licences |

**Recommandation : A.** B se rebranche derrière `PassageSearchInterface` si une mesure le justifie. C est écarté par les exigences de version et de licence.

### D5 — Quel fournisseur de vecteurs ?

Décision reportée au lot L12 : la recherche par mots permet de livrer les réponses sourcées sans elle. Critères : qualité en français technique mesurée sur le jeu d'évaluation, lieu de traitement, coût, taille des vecteurs. Candidats : une API (Mistral, OpenAI, Cohere, Voyage…) ou un modèle ouvert multilingue hébergé par nos soins, qui demande un service de calcul. **Recommandation : une API derrière le port, choisie sur mesure au lot L12.** Changer de modèle impose de recalculer tous les vecteurs ; la trace d'ingestion garde le modèle utilisé.

### D6 — Qui conduit le diagnostic ?

| Option | Pour | Contre |
|---|---|---|
| A. Le modèle, en texte libre | Rapide à construire, souple | Ni reproductible, ni traçable, ni testable ; rien n'empêche une consigne dangereuse |
| B. Un système expert : arbres de décision écrits par des électriciens | Fiable, testable | Coûteux à rédiger, rigide, couverture étroite |
| C. Un état structuré tenu par le domaine ; le modèle propose des transitions que le domaine accepte ou refuse | Souplesse du modèle, garde-fous du domaine, journal complet, testable sans modèle | Plus de conception ; dépend d'un catalogue de vérifications à rédiger |

**Recommandation : C**, avec un catalogue fermé de vérifications relues par l'expert. Le modèle ne peut proposer qu'une vérification du catalogue, permise pour la qualification de l'utilisateur. Le coût est assumé : un diagnostic peut s'arrêter faute de vérification au catalogue, et le produit le dit.

### D7 — Comment garantir les citations ?

| Option | Pour | Contre |
|---|---|---|
| A. Citation par identifiant de passage, contrôlée par le serveur | Indépendant du fournisseur, compatible avec les sorties structurées, simple à tester | Garantit que la source existe et a été lue, pas qu'elle dit bien ce que l'énoncé affirme |
| B. Citations natives du fournisseur | Précision au caractère près | Propre à un fournisseur ; chez Anthropic, incompatible avec les sorties structurées dont dépend le format des réponses |
| C. Attribution après coup, par ressemblance | Aucune contrainte sur le modèle | Peu fiable |

**Recommandation : A.** Sa limite est traitée de deux façons : l'extrait est affiché pour que l'utilisateur vérifie, et l'évaluation mesure la part des citations qui soutiennent réellement leur énoncé.

### D8 — Comment se déroule un tour entre le mobile et le serveur ?

| Option | Pour | Contre |
|---|---|---|
| A. Requête synchrone | Le plus simple, aucun processus de plus | Occupe un processus PHP-FPM pendant tout le tour ; fragile sur un réseau de chantier |
| B. Asynchrone : le serveur répond tout de suite, un processus de fond exécute le tour, le mobile interroge | L'API reste disponible, le tour survit à une coupure | Un processus de fond à exploiter et à surveiller |
| C. Diffusion en continu de la réponse | Meilleur confort d'attente | Difficile à concilier avec le contrôle du brouillon avant affichage ; plus complexe des deux côtés |

**Recommandation : un contrat asynchrone dès le départ, une exécution synchrone au début.** L'envoi d'un message renvoie un tour avec un statut, et le mobile sait suivre un tour en cours. Au lot L5, le serveur exécute le tour dans la requête et renvoie un tour déjà terminé, avec des délais relevés pour cette route. Au lot L19, on passe à B sans changer le contrat, sur la base des durées et de la charge mesurées. C vient après, s'il manque. Compromis assumé : jusqu'au lot L19, l'API ne tient qu'un petit nombre de tours simultanés, ce qui convient au développement et à une bêta restreinte, pas à une ouverture.

### D9 — Comment exécuter les traitements longs ?

L'ingestion d'un document commence comme une commande console lancée à la main (lot L9). Quand un traitement de fond devient nécessaire (D8, option B), **recommandation : Messenger avec son transport Doctrine**, qui s'appuie sur PostgreSQL et n'ajoute aucun service. Une file dédiée (RabbitMQ, Redis) n'est justifiée que par un volume qu'on n'a pas.

### D10 — Où stocker les fichiers sources ?

**Recommandation : le système de fichiers, derrière `FileStorageInterface`**, avec l'empreinte du fichier en base. Un stockage d'objets compatible S3 remplacera l'adaptateur quand les photos arriveront ou quand l'hébergement l'imposera.

### D11 — Dans quelle langue écrire le code du domaine ?

| Option | Pour | Contre |
|---|---|---|
| A. Anglais, avec un glossaire | Cohérent avec le code existant | Traductions parfois approximatives de notions françaises |
| B. Termes métier en français | Fidèle au langage des électriciens et des textes | Mélange de langues, rupture avec l'existant |

**Recommandation : A**, avec le glossaire de DOMAIN.md, § 3, et les termes sans équivalent laissés en français (habilitation, Consuel).

### D12 — Quel format pour les messages ?

| Option | Pour | Contre |
|---|---|---|
| A. Blocs typés et versionnés, avec texte de repli | La nature de chaque énoncé est une donnée, contrôlable par le serveur et rendue distinctement par le mobile ; les anciennes versions de l'application restent utilisables | Plus de travail côté mobile ; un format à faire évoluer avec soin |
| B. Markdown libre | Simple | Impossible de garantir la distinction entre faits, hypothèses et recommandations, ou de contrôler les citations |

**Recommandation : A.** C'est la base de la plupart des mécanismes de SECURITY.md.

### D13 — Où vivent les contenus métier critiques ?

Consignes, catalogue de vérifications, messages de sécurité, registre normatif, jeux d'évaluation.

| Option | Pour | Contre |
|---|---|---|
| A. Fichiers versionnés dans le dépôt, chargés en base par une commande | Relecture par demande de fusion, historique, validation de l'expert tracée, testable | Une modification demande un déploiement |
| B. En base, avec une interface d'administration | Modifiable sans déploiement | Une interface à construire ; relecture et historique à réinventer |

**Recommandation : A pour le MVP**, avec une commande de désactivation immédiate pour les urgences.

### D14 — Comment extraire le texte des documents ?

À décider au lot L9, par un essai sur les vrais documents du corpus, en particulier ceux qui contiennent des tableaux. Options : bibliothèque PHP, outil en ligne de commande dans l'image Docker, script Python hors ligne (D1, option C), ou lecture des pages par un modèle multimodal. Critère : fidélité des tableaux et des numéros de sections, contrôlée par l'expert sur un échantillon. Une extraction fausse produit des citations fausses avec toutes les apparences du vrai (SECURITY.md, risque T2) ; c'est pourquoi une version de document n'est publiée qu'après relecture humaine.

### D15 — Hébergement, intégration continue, supervision

L'intégration continue arrive au lot L2. L'hébergement et la supervision sont à décider avant le lot L19. Contraintes déjà connues : hébergement dans l'Union européenne, secrets hors du dépôt, sauvegardes de la base et des fichiers sources, suivi des erreurs, des durées et des coûts par tour.

### D16 — Comment séparer les connaissances normatives et les données produits ?

| Option | Pour | Contre |
|---|---|---|
| A. Un seul corpus, des étiquettes | Le plus simple | Mélange des sources dont la fiabilité, les droits et le rythme diffèrent ; un passage de notice peut être pris pour une règle |
| B. Une mécanique documentaire commune, des corpus distincts ; un contexte Catalogue séparé pour les données structurées | Gestion séparée, interrogation conjointe, aucune duplication de l'extraction et de la recherche | La notion de corpus à porter dès le lot L9 |
| C. Deux chaînes entièrement séparées | Isolation maximale | Extraction, recherche et outillage en double, sans besoin constaté |

**Recommandation : B.** Le corpus est introduit dès le lot L9, pour ne pas avoir à migrer les sources ensuite. Chaque résultat de recherche et chaque citation garde son corpus jusqu'à l'affichage. Les données structurées des produits ne passent pas par la recherche documentaire : elles sont interrogées par critères dans Catalogue, et une valeur n'y entre qu'avec sa source et sa date.

Pistes à étudier pour alimenter Catalogue, à vérifier avant de s'y engager (droits d'usage, accès, couverture) : la classification ETIM, qui décrit les critères par classe de produits électrotechniques ; le format FAB-DIS, utilisé en France pour les échanges de données entre fabricants et distributeurs ; la base européenne EPREL pour les appareils soumis à l'étiquette énergie.

### D17 — Comment laisser essayer Hector sans compte ?

Demande du 16 novembre 2026 : utiliser l'application sans être connecté, comme les assistants grand public, puis demander la connexion une fois la limite gratuite atteinte. Le paiement viendra plus tard.

| Option | Pour | Contre |
|---|---|---|
| A. Compte invité : un vrai `User`, sans identifiants, ouvert par l'API au premier lancement | Tout l'existant s'applique tel quel : JWT, jeton de rafraîchissement, propriété des ressources (CV1), quotas, traces, suppression. Les jetons portant l'uuid et non l'e-mail, la conversion en compte complet garde le même uuid, les mêmes données et la même session. | Des comptes sans valeur s'accumulent (purge à prévoir) ; réinstaller l'application redonne un essai |
| B. Identifiant d'appareil, sans compte | Aucune ligne en base avant l'inscription | Un second modèle de propriété dans chaque contexte, une migration des données à l'inscription, un identifiant facile à falsifier |
| C. Essai entièrement local jusqu'à l'inscription | Rien côté serveur | Impossible : chaque réponse passe par l'API et le modèle de langage |

**Recommandation : A**, avec ces règles :

- un invité a la qualification la plus restrictive, celle d'un particulier (SF2) ; un électricien s'inscrit et déclare sa qualification pour accéder aux vérifications qui lui sont réservées ;
- l'allocation gratuite se compte par compte et dépend de son type. Épuisée, elle est refusée avant tout appel au modèle, avec un code qui dit ce qui débloque la suite : `signup_required` pour un invité ; plus tard un code d'abonnement pour un compte complet. Le mobile traduit le code en écran d'inscription, puis de paiement ;
- la création de comptes invités est limitée par adresse IP, et l'ensemble des invités a un budget global : c'est la parade à la réinstallation. Les attestations d'appareil d'Apple et de Google (App Attest, Play Integrity) sont une piste à étudier au lot L19, à vérifier avant de s'y engager ;
- un invité inactif est supprimé après une durée annoncée (P7) ;
- un message de sécurité n'est jamais bloqué par la limite : il est fixe et ne coûte aucun appel au modèle.

Le paiement n'est pas conçu ici ; seul le contrat d'API lui réserve sa place.

### D18 — Comment Hector apprend-il des dépannages réussis ?

Demande du 25 novembre 2026 : qu'Hector apprenne de ce que les utilisateurs lui disent sur la façon dont ils ont réussi leur dépannage, si c'est juste, et que le modèle s'en trouve enrichi.

| Option | Pour | Contre |
|---|---|---|
| A. Réentraîner ou affiner le modèle sur les conversations réussies | Le modèle « sait » sans qu'on lui fournisse rien | Le fournisseur ne propose pas l'affinage de ses modèles récents (à vérifier au moment de décider, l'offre évolue). Surtout : un retour faux ne peut plus être retiré, une réponse ne peut plus dire d'où elle vient, les données d'un utilisateur ne peuvent plus être effacées (RGPD), et chaque changement de modèle oblige à tout refaire. Un cas mal compris deviendrait une certitude du modèle |
| B. Base de cas relus, recherchée à chaque tour et fournie au modèle comme contexte | Effet dès la publication d'un cas ; chaque usage est cité et tracé ; un cas se retire d'une commande ; indépendant du modèle choisi (D2) ; s'appuie sur la recherche des lots L10 et L12 | Une relecture humaine à tenir ; il faut un volume de cas pour que ça serve |
| C. Ajouter les retours aux consignes du modèle | Aucun développement | Ne passe pas à l'échelle, coûte à chaque tour, aucune sélection selon la panne |

**Recommandation : B**, contexte `Experience` (DOMAIN.md, § 4.10), en trois temps :

1. **Recueil dès le MVP** (lot L18) : à la conclusion d'un diagnostic, Hector demande ce qui a résolu la panne et l'accord de partage. Le modèle propose une fiche structurée à partir du récit ; le domaine la contrôle (consignes interdites, registre normatif, unités). Rien n'est encore montré à d'autres utilisateurs. La bêta accumule ainsi des cas.
2. **Relecture et publication** (lot L27) : file de relecture pour l'expert, anonymisation contrôlée, niveau de risque fixé par le relecteur, retrait par commande.
3. **Usage en conversation** (lots L28, L29) : outil `rechercher_cas_terrain`, cité par identifiant et contrôlé par le serveur comme une citation de passage (D7), filtré par la politique de prévention selon la qualification du lecteur (EX7). Puis fréquences de causes confirmées pour ordonner les hypothèses, au-delà d'un nombre minimal de cas (EX10).

Un cas du terrain reste une donnée, jamais une consigne : son texte passe par les mêmes parades que les documents contre les consignes cachées (SECURITY.md, § 7, risque T13).

### D19 — Comment rendre l'application multilingue ?

Demande du 25 novembre 2026 : l'application doit être multilingue.

| Option | Pour | Contre |
|---|---|---|
| A. Hector répond dans la langue du message, quelle qu'elle soit | Aucun réglage, toutes les langues d'un coup | Le contrôle des réponses repère des mots (article, tableau, annexe…) : dans une langue qu'il ne couvre pas, une référence inventée passerait. Les réponses fixes, les messages d'erreur et les conditions d'utilisation n'existeraient pas dans cette langue |
| B. Une liste de langues ouvertes ; la langue d'un échange est celle de l'application (celle du téléphone si elle est ouverte, sinon le français), annoncée à l'API | Tout ce que voit l'utilisateur est dans la même langue ; le contrôle couvre chaque langue ouverte ; ouvrir une langue est une liste finie de fichiers | Une langue non ouverte retombe sur le français ; chaque langue est un petit chantier, à faire relire |
| C. Traduire automatiquement les réponses françaises | Une seule langue à contrôler | Coût et délai d'un second appel ; des consignes de sécurité traduites sans relecture |

**Recommandation : B**, réalisée le 25 novembre 2026 avec le français, l'anglais et l'allemand.

- L'application envoie sa langue dans `Accept-Language`. L'API retient la première langue ouverte (`enabled_locales`), sinon le français, et la renvoie dans `Content-Language`.
- Le texte français écrit dans le code sert de clé de traduction (`translations/messages.<langue>.yaml`, `validators.<langue>.yaml`) : rien à tenir en français, et un message non traduit s'affiche en français plutôt que sous forme de clé.
- Hector répond dans la langue de l'application, pas dans celle du message : la consigne de langue est versionnée (`resources/prompts/language-<langue>.md`) et entre dans l'empreinte des consignes. Ses réponses fixes et les intitulés de repli des blocs suivent (`AnswerTextsInterface`).
- Ouvrir une langue, c'est fournir : les traductions de l'API ; les textes de l'application (`mobile/lib/l10n/app_<langue>.arb`, `AppLocales`, `CFBundleLocalizations`) ; la consigne de langue ; les mots du contrôle des réponses (`ProvisionReference`) avec leurs cas critiques ; les conditions d'utilisation ; des cas d'évaluation dans cette langue. **Une langue n'est ouverte que si le contrôle des réponses la couvre.**
- Multilingue ne veut pas dire multi-pays : le cadre normatif reste celui de la France, quelle que soit la langue. Un autre pays demanderait son registre normatif et ses sources : c'est un autre projet (question P16).
- À prévoir : les messages de sécurité fixes du lot L15 devront être traduits et relus dans chaque langue avant d'y être servis (SF4). La langue n'est pas enregistrée sur le compte : elle le sera quand des notifications ou des e-mails en auront besoin.

## 14. Risques techniques

Ils sont tenus dans le registre commun de [SECURITY.md](SECURITY.md), § 9, avec les risques réglementaires et fonctionnels.
