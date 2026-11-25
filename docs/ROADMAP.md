# Hector AI — Feuille de route

> **Statut : proposition à valider** (15 novembre 2026). Aucun lot n'est commencé.
> À lire avec : [PRODUCT.md](PRODUCT.md) · [ARCHITECTURE.md](ARCHITECTURE.md) · [DOMAIN.md](DOMAIN.md) · [SECURITY.md](SECURITY.md)

## 1. Règles de découpage

- Un lot se livre seul : il laisse `make check` et `make test` au vert et apporte quelque chose de vérifiable.
- Un lot suit la recette de `CLAUDE.md` : domaine, persistance, cas d'usage, contrôleur, tests.
- Les critères d'acceptation sont des faits observables. Les seuils chiffrés qui dépendent d'une première mesure sont fixés à ce moment-là, pas inventés ici.
- Les règles citées (CV1, DG6, PR4…) sont celles de DOMAIN.md, § 4. Les décisions (D1…) sont celles d'ARCHITECTURE.md, § 13. Les questions (P1…) sont celles de PRODUCT.md, § 7.
- « Expert » désigne l'électricien qualifié de la question P4.

## 2. Vue d'ensemble

| Lot | Titre | Dépend de | Objectif du MVP |
|---|---|---|---|
| **Phase 0 — Préparation** | | | |
| L0 | Validation du cadrage | — | — |
| L1 | Passage à Symfony 8 | — | — |
| L2 | Garde-fous d'architecture et intégration continue | L1 | — |
| **Phase 1 — Socle conversationnel** | | | |
| L3 | Essai sans compte | L2 | — |
| L4 | Conversations et messages, sans IA | L3 | 1 |
| L5 | Port du modèle de langage et premier tour réel | L4 | 1, 2 |
| L6 | Écran de conversation (mobile) | L4 | 1 |
| L7 | Réponse structurée et contrôle des réponses | L5 | 2 |
| L8 | Jeu d'évaluation et outil de mesure | L7 | — |
| **Phase 2 — Base documentaire** | | | |
| L9 | Modèle documentaire et ingestion | L2 | 3 |
| L10 | Recherche par mots et réponses sourcées | L7, L9 | 3, 4 |
| L11 | Registre normatif | L9 | 4 |
| L12 | Recherche par vecteurs et fusion | L8, L10 | 3 |
| **Phase 3 — Calculs** | | | |
| L13 | Noyau de calcul et premiers calculateurs | L2 | — |
| L14 | Calculs dans la conversation | L7, L13 | 2 |
| **Phase 4 — Diagnostic** | | | |
| L15 | Prévention : niveaux de risque, qualification, catalogue | L2 | 7 |
| L16 | Domaine du diagnostic, sans modèle de langage | L15 | 7 |
| L17 | Diagnostic guidé dans la conversation | L7, L16 | 5, 6, 7 |
| L18 | Conclusion argumentée et retour du terrain | L17 | 8 |
| **Phase 5 — Ouverture** | | | |
| L19 | Durcissement | L18 | — |
| L20 | Bêta fermée | L19 | — |
| **Phase 6 — Conseil en équipements (après le MVP)** | | | |
| L21 | Catalogue : modèle et import | L9 | — |
| L22 | Corpus produits et recherche de références | L10, L21 | — |
| L23 | Comparaison structurée | L22 | — |
| L24 | Compatibilité avec l'installation | L13, L21 | — |
| L25 | Recommandations et alternatives | L23, L24 | — |
| L26 | Prix, disponibilités et sélection présentable au client | L25 | — |
| **Phase 7 — Apprendre du terrain (après le MVP)** | | | |
| L27 | Relecture et publication des cas du terrain | L18 | — |
| L28 | Cas du terrain dans la conversation | L12, L27 | — |
| L29 | Fréquences de causes confirmées | L28 | — |

Le MVP correspond aux phases 0 à 5. Après le lot L2, quatre fils peuvent avancer indépendamment : le socle (L3 à L8), la base documentaire (L9), le calcul (L13), la prévention et le domaine du diagnostic (L15, L16). Ils se rejoignent aux lots L10, L14 et L17.

---

## Phase 0 — Préparation

### L0 — Validation du cadrage

**Objectif.** Partir de décisions écrites.
**Contenu.** Relecture des cinq documents. Réponse à chaque décision D1 à D17 et à chaque question P1 à P11. Désignation de l'expert. Liste du corpus de départ, avec le droit d'usage de chaque document.
**Critères d'acceptation.**
- Chaque décision et chaque question porte une réponse datée dans son document.
- L'expert est nommé et a accepté de relire catalogue, cas de référence et jeu d'évaluation.
- Chaque document du corpus de départ a une licence établie par écrit.

**Tests.** Aucun : ce lot ne contient pas de code.

### L1 — Passage à Symfony 8

**Objectif.** Monter de version avant d'ajouter des dépendances.
**Contenu.** Contraintes `symfony/*` et `extra.symfony.require` en 8.1, puis 8.2 dès sa sortie stable. Recettes Flex mises à jour. Corrections éventuelles. `README.md` et `CLAUDE.md` mis à jour.
**Constat préalable.** La simulation Composer du 15 novembre 2026 aboutit en 8.1.11 sans modifier d'autre dépendance. Elle prouve que les versions sont compatibles entre elles, pas que le code se comporte à l'identique : c'est la suite de tests qui le dira.
**Critères d'acceptation.**
- `composer.lock` ne contient plus aucun composant Symfony 7.
- `make check` et `make test` passent, sans dépréciation.
- La spécification OpenAPI servie sur `/api/v1/doc` est identique avant et après.
- `make mobile_test` et `make mobile_e2e` passent contre l'API montée.

**Tests.** Suite existante. Comparaison de la spécification OpenAPI avant et après.
**À savoir.** Les versions 8.1 à 8.3 ne sont pas à support long : une montée mineure sera à faire tous les six mois jusqu'à la 8.4.

### L2 — Garde-fous d'architecture et intégration continue

**Objectif.** Faire respecter la séparation des couches et des contextes par un outil.
**Contenu.** Contrôle statique des dépendances dans `make check` (ARCHITECTURE.md, § 4). Suite PHPUnit `Critical` et cible `make test_critical`. Intégration continue qui exécute `make check`, `make test` et `make mobile_test`.
**Critères d'acceptation.**
- Une classe de `Domain` qui importe le framework ou `Infrastructure` fait échouer `make check`.
- Un contexte qui importe l'entité d'un autre sans lien permis fait échouer `make check`.
- Le lien existant entre `User` et `Company` reste permis.
- Une branche dont un contrôle échoue ne peut pas être fusionnée.

**Tests.** Vérification, à la mise en place, que chaque règle détecte bien une violation fabriquée pour l'occasion.

---

## Phase 1 — Socle conversationnel

### L3 — Essai sans compte

**Objectif.** Utiliser Hector dès l'ouverture de l'application, sans inscription, puis créer un compte sans rien perdre (décision D17).
**Contenu.** Compte invité : route qui crée un `User` sans nom, sans e-mail et sans mot de passe, et renvoie ses jetons. Inscription depuis un compte invité : le même compte reçoit ses identifiants. Acceptation des conditions d'utilisation enregistrée avec sa version (texte provisoire jusqu'au lot L19). Limite de création par adresse IP. Commande de purge des invités inactifs. Côté mobile : compte invité ouvert au premier lancement, accueil sans passer par la connexion, invitation à créer un compte depuis le profil.
**Critères d'acceptation.**
- Au premier lancement, l'application arrive sur l'accueil sans écran de connexion, une fois les conditions acceptées.
- Un compte invité ne peut pas se connecter par e-mail et mot de passe ; il n'a ni l'un ni l'autre (ID1).
- S'inscrire depuis un compte invité garde son uuid et tout ce qui lui est rattaché ; la session en cours reste valide (ID2).
- `/auth/me` indique qu'il s'agit d'un invité. La modification du profil lui est refusée avec un code que le mobile traduit en invitation à créer un compte.
- Un invité a la qualification la plus restrictive (ID3).
- Au-delà de la limite de création par adresse IP, l'API renvoie une 429 avec un message clair.
- La commande de purge supprime les invités inactifs depuis la durée fixée (P7), avec leurs données (ID4).
- Un premier lancement sans réseau affiche un message clair et réessaie au retour du réseau.

**Tests.** Unitaires des handlers : création, conversion, purge. Intégration : création, limite par IP, refus de la connexion par identifiants, conversion avec conservation des données et de la session, purge. Mobile : premier lancement, conversion, démarrage hors ligne.
**Réalisé (24 novembre 2026).** Écarts et précisions :
- Les conditions d'utilisation s'acceptent sur l'écran de bienvenue, avant l'essai sans compte comme avant la connexion ou l'inscription. Elles sont servies par l'API (`GET /api/v1/terms-of-use`) et versionnées dans `resources/legal/` ; le texte est provisoire jusqu'au lot L19.
- Un invité est purgé quand il n'a plus de session valide, c'est-à-dire après 30 jours sans activité (DOMAIN.md, ID4).
- Le refus de modifier le profil d'un invité est une 403 avec `reason: guest_account`. Des conditions acceptées mais périmées donnent une 409 avec `reason: terms_of_use_outdated`.
- Sans réseau au premier lancement, l'écran de bienvenue réessaie seul toutes les 10 secondes.

### L4 — Conversations et messages, sans IA

**Objectif.** Poser le contexte Conversation et son contrat d'API.
**Contenu.** `Conversation`, `Message`, `Turn`, migrations. Routes : créer une conversation, lister les siennes, lire les messages par pages, envoyer un message, suivre un tour. L'assistant est un bouchon qui renvoie une réponse fixe, derrière le contrat définitif.
**Critères d'acceptation.**
- Un utilisateur ne peut ni lire ni alimenter la conversation d'un autre : réponse 404 (CV1).
- L'envoi d'un message renvoie le tour avec son statut et, s'il est terminé, la réponse.
- Un message vide ou trop long renvoie une 422 avec `violations`.
- La suppression d'un compte supprime ses conversations.
- Un invité qui se connecte à un compte existant retrouve ses conversations d'invité dans ce compte, puis le compte invité est supprimé (selon la réponse à P11).
- La spécification OpenAPI décrit les nouvelles routes.

**Tests.** Unitaires des handlers. Intégration : propriété, pagination, validation, suppression en cascade, rattachement des conversations d'un invité.
**Réalisé (24 novembre 2026).** Précisions :
- Messages lus par pages en remontant le temps : `?before=<uuid>&limit=`, et `hasMore`. L'ordre des messages est celui de leurs uuid v7, croissants dans le temps.
- Suppression du compte et rattachement passent par le port `UserOwnedDataInterface` du contexte User, implémenté dans l'infrastructure pour chaque contexte qui rattache des données à un utilisateur. Ni User ni Conversation ne dépendent l'un de l'autre.
- Rattachement d'un invité : `POST /api/v1/auth/merge-guest`, avec le jeton de rafraîchissement de l'invité comme preuve. L'application l'appelle après la connexion. S'il échoue, la connexion reste valable et l'essai est purgé avec le compte invité.
- Le tour s'exécute en deux transactions courtes (`TurnRunner`). La réponse fixe occupe déjà la place de l'appel au modèle du lot L5.

### L5 — Port du modèle de langage et premier tour réel

**Objectif.** Obtenir une vraie réponse sans lier le code à un fournisseur.
**Contenu.** `ChatModelInterface` et ses objets neutres. Adaptateur du fournisseur retenu pour démarrer (D3). `FakeChatModel` scriptable. Exécution du tour hors transaction. Consignes versionnées dans `resources/prompts`. Trace du tour. Échecs traités : délai dépassé, refus du fournisseur, quota, panne de réseau. Limite de débit par utilisateur. Allocation gratuite selon le type de compte (P10).
**Critères d'acceptation.**
- Un invité qui a épuisé son allocation reçoit, avant tout appel au modèle, une erreur au code `signup_required` et un message en français (CV8). Un compte complet a sa propre allocation ; le code qui annoncera le paiement est réservé, pas développé.
- Allocation épuisée ou non, un signal de danger repéré par les règles fixes reçoit le message de sécurité prévu (SECURITY.md, § 3).
- Aucune classe de `Domain` ou d'`Application` ne référence le SDK du fournisseur (vérifié par L2).
- Aucune transaction n'est ouverte en base pendant l'appel au modèle.
- Chaque tour terminé a une trace : modèle, version des consignes, jetons, durée, issue.
- En cas d'échec du fournisseur, le tour passe à « échoué », l'utilisateur reçoit un message clair en français et peut relancer.
- Aucune donnée d'identité (nom, e-mail, SIRET) ne figure dans une requête au fournisseur.
- Le tour se termine et s'enregistre même si le client se déconnecte.

**Tests.** Unitaires de l'exécution du tour avec le faux modèle : succès, délai, refus, boucle bornée. Contrat de l'adaptateur sur des réponses enregistrées. Intégration du parcours complet avec le faux modèle. Test vivant à la main, hors `make test`.
**Réalisé (24 novembre 2026).** Précisions et écarts :
- Fournisseur de départ : Anthropic, modèle `claude-opus-5-5`, effort `medium`, via le SDK officiel `anthropic-ai/sdk` (D3 reste à trancher par la mesure du lot L8). Réglages dans `.env` (`CHAT_*`), clé dans `.env.local`.
- Les replis côté serveur en cas de refus sont activés (`fallbacks: default`, bêta `server-side-fallback-2026-07-01`) : le fournisseur réessaie sur un autre modèle, et la trace garde le modèle qui a réellement répondu. Un refus qui persiste donne une réponse dégradée fixe.
- Pas encore d'appel d'outils ni de sortie structurée : ils arrivent avec les capacités et le contrôle des réponses (lots L7, L10). Le port est fait pour les accueillir sans changer d'appelant.
- Allocation gratuite **provisoire** : 10 messages pour un invité, 30 par 24 heures pour un compte (`config/services.yaml`). Refus en 429, `reason: signup_required` ou `free_allowance_exhausted`.
- Limite de débit : 10 messages par minute et par utilisateur.
- **Reporté au lot L15 :** le critère « un signal de danger reçoit le message de sécurité même allocation épuisée ». Il suppose les règles fixes de Prévention, qui n'existent pas encore. D'ici là, les consignes demandent au modèle de traiter le danger en premier, ce qui n'est pas un mécanisme vérifiable.
- Réponses du test de contrat : construites d'après le format documenté de l'API, à remplacer par des réponses réellement enregistrées au premier `make test_live`.
- Délais : 90 secondes par appel, une seule nouvelle tentative, `fastcgi_read_timeout` de nginx à 240 secondes. Le mobile reste à 30 secondes jusqu'au lot L6.

### L6 — Écran de conversation (mobile)

**Objectif.** Dialoguer depuis l'application.
**Contenu.** Liste des conversations, écran de discussion, saisie, états d'envoi, d'attente, d'échec et de relance, rendu des blocs, repli pour un type inconnu, mention « assistant IA ».
**Critères d'acceptation.**
- Le parcours créer, envoyer, lire fonctionne contre l'API locale.
- Après une coupure de réseau pendant l'attente, l'application retrouve la réponse en rouvrant la conversation, sans message en double.
- Un bloc de type inconnu s'affiche par son texte de repli (CV5).
- L'écran indique en permanence qu'on parle à une IA.
- Quand l'allocation d'un invité est épuisée, l'application propose de créer un compte ou de se connecter, puis revient dans la conversation, message en attente compris.
- Tous les textes sont dans `app_fr.arb`.

**Tests.** Tests d'écrans avec l'adaptateur scripté : états, repli, relance, reprise, allocation épuisée. Parcours de bout en bout.
**Réalisé (24 novembre 2026).** Précisions :
- L'accueil liste les conversations et en ouvre une nouvelle ; l'écran de conversation porte en permanence la mention « assistant IA ».
- Réseau coupé pendant l'attente : l'application ne renvoie pas le message. Elle propose de recharger la conversation, et retire le message en attente s'il y figure déjà (pas de doublon). Rouvrir la conversation donne le même résultat.
- Tour échoué : le message de l'API s'affiche sous la question, avec « Réessayer », qui ouvre un nouveau tour.
- Essai épuisé : le message refusé revient dans la zone de saisie. « Créer un compte » et « J'ai déjà un compte » ramènent ensuite dans la conversation (`?retour=`).
- Délai de réception de 4 minutes pour l'envoi d'un message seulement. Un tour encore « en cours » est suivi toutes les 2 secondes.
- Les consignes demandent du texte simple, sans Markdown : l'application affiche le texte tel quel.

### L7 — Réponse structurée et contrôle des réponses

**Objectif.** Faire de la nature de chaque énoncé une donnée contrôlée.
**Contenu.** Format de blocs versionné (D12) : énoncés typés, question, avertissement. Sortie structurée demandée au modèle. Contrôle déterministe du brouillon. Une reprise, puis réponse dégradée. Détection, dans le texte libre, des renvois à une disposition précise.
**Critères d'acceptation.**
- Un brouillon contenant un « fait sourcé » sans citation est rejeté (CV4).
- Un brouillon qui renvoie à une disposition précise (numéro d'article, de tableau) sans citation valide est rejeté.
- Après un rejet, il y a une seule reprise. Si elle échoue, l'utilisateur reçoit une réponse dégradée qui le dit ; il ne reçoit jamais le brouillon rejeté.
- Chaque rejet figure dans la trace du tour, avec son motif.
- Le mobile distingue visuellement fait, hypothèse, recommandation et information manquante.

**Tests.** Suite critique : pour chaque règle du contrôle, au moins un cas accepté et un cas rejeté. Intégration avec le faux modèle : brouillon invalide puis valide, brouillon invalide deux fois. Tests d'écrans.

**Réalisé (25 novembre 2026).** Précisions et écarts :
- Le modèle répond par une sortie structurée (schéma JSON) : blocs `statement` avec leur nature, `question`, `warning`. Le contrôle (`DraftValidator`, domaine) rejette un fait sourcé ou un résultat de calcul sans source lue pendant le tour, une citation inconnue, un avis rapporté sans source, et tout renvoi à une disposition précise sans citation valide.
- Tant qu'aucune base documentaire ni aucun calculateur n'existe (lots L10 et L14), aucune source n'est lue pendant un tour : un « fait sourcé » ou un « résultat de calcul » est donc toujours rejeté.
- La détection des renvois (`ProvisionReference`) repose sur des formulations connues ; ses cas de référence sont dans `tests/Critical/Conversation/provision-references.json`, à compléter et à faire relire par l'expert. Elle a déjà écarté un faux positif (« section 2.5 », la section d'un conducteur).
- Écart : un « fait déclaré » n'est pas encore relié au message de l'utilisateur dont il vient (CV4). À traiter avec les observations du diagnostic (lot L16).
- Les valeurs chiffrées tirées d'une norme ne sont pas contrôlées par ce lot : seules les consignes demandent au modèle de ne pas en citer. Le registre normatif (lot L11) et les calculateurs (lot L14) fermeront ce point.
- Mobile : chaque nature d'énoncé a sa pastille (icône, mot, teinte) ; recommandation, question et mise en garde sont en encart. Une nature inconnue de l'application s'affiche par son texte de repli, qui la nomme.
- Design de l'application refait à cette occasion (demande du 25 novembre 2026 : une application moderne, dans les codes des applications actuelles) : voir `mobile/lib/src/theme.dart` et `make mobile_preview`.
- Non vérifié : le parcours de bout en bout sur simulateur n'a pas été rejoué après le changement de design, et aucune réponse réelle du modèle n'a encore été observée au format structuré (pas de clé d'API).

### L8 — Jeu d'évaluation et outil de mesure

**Objectif.** Mesurer la qualité avant de choisir un modèle et avant chaque changement de consignes.
**Contenu.** Format des cas : question, éléments attendus, sources attendues, interdits. Premiers cas rédigés avec l'expert, en trois familles : questions techniques, pièges normatifs (article ou édition qui n'existe pas), demandes dangereuses. Commande `make eval`. Rapport daté : modèle, version des consignes, résultats par famille, coût. Comparaison de deux exécutions.
**Critères d'acceptation.**
- `make eval` produit un rapport qui identifie le modèle et la version des consignes.
- Les familles « pièges normatifs » et « demandes dangereuses » ont un critère binaire, vérifié automatiquement.
- Le jeu couvre les trois familles avec un nombre de cas arrêté avec l'expert.
- L'évaluation n'est pas lancée par `make test`, et son coût est affiché à la fin.
- Deux ou trois modèles candidats ont été mesurés et la décision D3 est prise sur ces résultats.

**Tests.** Unitaires de l'outil (lecture des cas, calcul des résultats) avec le faux modèle.

**Réalisé (25 novembre 2026).** Précisions et écarts :
- `make eval` (`app:eval`) passe chaque cas dans le moteur de l'application (`AnswerEngine`, extrait de `TurnRunner` : consignes, modèle, contrôle, reprise), sans base de données. Rapport JSON daté dans `var/evaluation` : modèle, effort, version des consignes, juge, résultats par famille et par cas, jetons. `make eval_compare` compare deux rapports et signale les cas qui ont régressé.
- Critère binaire des deux familles à tolérance nulle : aucun interdit observé. Il combine des contrôles sans modèle (le contrôle des réponses repassé sur la réponse finale, des motifs interdits) et un juge, un modèle de langage aux consignes versionnées (`resources/prompts/evaluation-judge.md`, modèle `EVAL_JUDGE_MODEL`). Un cas que le juge n'a pas tranché n'est pas un cas réussi. La commande sort en erreur dès qu'un cas de ces familles n'est pas réussi.
- Coût affiché à la fin : jetons, et montant d'après `resources/evaluation/pricing.json` (prix sourcés et datés). Un modèle sans prix connu laisse le coût « non calculé ».
- Jeu de départ : 18 cas (6 questions techniques, 5 pièges normatifs, 7 demandes dangereuses), dans `resources/evaluation/cases`.
- Première mesure réelle, sur la seule famille « pièges normatifs » (claude-opus-5-5, effort medium) : 5 cas réussis sur 5, un brouillon rejeté par le contrôle puis corrigé à la reprise, 0,22 USD.
- Écart : les cas ont été rédigés sans expert et ne sont pas relus (`review` vide ; le rapport le signale à chaque exécution). Leur nombre n'est pas arrêté avec l'expert (question P4).
- Écart : les modèles candidats n'ont pas été mesurés et la décision D3 n'est pas prise. Il faut un accord sur la dépense (de l'ordre de 1 USD par modèle sur ce jeu) et des cas relus.
- Limite : le juge est lui-même un modèle. Son verdict n'est pas une preuve : les cas en défaut, et un échantillon de cas réussis, sont à relire par un humain. Les réponses observées sont longues pour un téléphone : à régler dans les consignes, puis à remesurer.

### Hors lot — Application multilingue

**Réalisé (25 novembre 2026)**, à la demande, selon la décision D19 : français, anglais et allemand. Messages de l'API, réponses d'Hector, contrôle des réponses, conditions d'utilisation et application mobile. Reste à faire : relecture des textes anglais et allemands par un locuteur du métier, mesure des cas d'évaluation dans ces deux langues, choix des langues suivantes (question P16).

---

## Phase 2 — Base documentaire

### L9 — Modèle documentaire et ingestion

**Objectif.** Gérer des documents en versions, avec leurs droits.
**Contenu.** Contexte `Knowledge` : `Corpus`, `Source`, `License`, `DocumentVersion`, `Passage`, `IngestionRun`. Stockage du fichier d'origine avec son empreinte. Extraction et découpage derrière leurs ports (D14). Commandes d'ingestion, de publication, de retrait et de consultation.
**Critères d'acceptation.**
- Une version ne peut pas être publiée sans éditeur, libellé de version, date de publication, licence et validation humaine (KN1).
- Une version publiée ne peut plus être modifiée (KN2).
- Réingérer le même fichier avec la même chaîne donne les mêmes passages, mêmes empreintes (KN6).
- Chaque passage garde sa page et sa section d'origine (KN3).
- Une source dont la licence interdit l'indexation ne peut pas être ingérée (KN5).
- Chaque source appartient à un corpus (KN7).
- La méthode d'extraction a été choisie sur un échantillon relu par l'expert, tableaux compris.

**Tests.** Unitaires du cycle de vie et des licences. Intégration des commandes sur des fichiers d'exemple, dont un document à tableaux. Test de reproductibilité.

**Réalisé (25 novembre 2026).** Précisions et écarts :
- Contexte `Knowledge` : sources (corpus, genre, licence), versions (brouillon, publiée, retirée), passages localisés, trace d'ingestion. Les règles KN1 à KN7 sont portées par le domaine, chacune avec ses tests.
- Commandes : `app:knowledge:source:create`, `app:knowledge:ingest`, `app:knowledge:show` (relecture des passages), `app:knowledge:publish`, `app:knowledge:withdraw`.
- Les fichiers d'origine sont conservés sous leur empreinte SHA-256 (`KNOWLEDGE_STORAGE_DIR`) : à sauvegarder avec la base.
- Formats lus : Markdown (le format des fiches Hector : les titres donnent les sections, les tableaux arrivent intacts) et texte brut, paginé par ses sauts de page. Le découpage suit titres et paragraphes et ne coupe jamais un tableau.
- Écart : pas d'extraction directe des PDF, et la méthode n'a pas été choisie sur un échantillon relu par l'expert (D14). Il n'y a encore ni corpus réel ni expert (questions P3 et P4). Un PDF converti en texte paginé par un outil externe peut être ingéré, mais ses tableaux sont alors à relire un par un.
- Écart : la version remplacée est un identifiant libre, sans contrôle qu'elle appartient à la même source. Le rattachement à une édition du registre normatif viendra avec le lot L11.
- Rien de ce qui est ingéré n'arrive encore dans les réponses : la recherche est le lot L10.

### L10 — Recherche par mots et réponses sourcées

**Objectif.** Répondre en citant des passages réels.
**Contenu.** `PassageSearchInterface` et son adaptateur plein texte français. Outil `rechercher_documentation`. Citations par identifiant, contrôlées par le serveur (D7). Route de consultation d'une source citée. Affichage des sources sur mobile.
**Critères d'acceptation.**
- Toute citation d'une réponse désigne un passage renvoyé par la recherche pendant ce tour ; sinon le brouillon est rejeté.
- Chaque citation affichée comporte titre, éditeur, version, date d'effet si elle est connue, et localisation.
- Une question hors corpus reçoit une réponse qui dit que la base ne couvre pas le sujet.
- La recherche d'un identifiant exact présent dans le corpus fait remonter son passage en tête.
- L'extrait affiché respecte la licence de la source : extrait, ou simple renvoi (KN5).
- Une recherche nomme ses corpus, et chaque résultat indique le sien (KN7).
- La part des cas d'évaluation où la source attendue est retrouvée est mesurée et notée : c'est la référence du lot L12.

**Tests.** Suite critique : contrôle des citations. Intégration de la recherche sur un corpus de test. Évaluation. Tests d'écrans.

### L11 — Registre normatif

**Objectif.** Ne citer que des textes et des éditions qui existent, avec leurs dates.
**Contenu.** Contexte `Regulation`. Registre chargé depuis `resources/regulation`, relu par l'expert. Contrôle des références normatives. Signalement d'une édition remplacée.
**Critères d'acceptation.**
- Une réponse ne peut mentionner ni un texte ni une édition absents du registre (RG1).
- Une citation tirée d'une édition remplacée s'affiche avec cette mention et sa date (RG2, RG3).
- Quand deux éditions peuvent s'appliquer et que la date de référence du projet est inconnue, la réponse la demande ou présente les deux cas en le disant (RG5).
- Chaque entrée du registre indique son relecteur et sa date de validation (RG4).
- Aucun piège normatif du jeu d'évaluation n'aboutit à une référence inventée.

**Tests.** Suite critique : contrôle des références. Évaluation, famille « pièges normatifs », tolérance nulle.

### L12 — Recherche par vecteurs et fusion

**Objectif.** Retrouver aussi les passages formulés autrement que la question.
**Contenu.** `EmbeddingModelInterface` et son adaptateur (D5). Extension vectorielle de PostgreSQL. Calcul des vecteurs à l'ingestion. Fusion des deux recherches.
**Critères d'acceptation.**
- La part des sources attendues retrouvées est supérieure ou égale à la référence du lot L10. La recherche hybride n'est conservée que sur ce chiffre.
- Changer de modèle de vecteurs impose un recalcul complet, explicite et tracé ; une recherche ne mélange jamais des vecteurs de modèles différents.
- Si le service de vecteurs est indisponible, la recherche par mots continue de fonctionner.
- `make check` passe, contrôle du schéma compris.

**Tests.** Unitaires de la fusion. Intégration avec un faux modèle de vecteurs déterministe. Évaluation comparée avant et après.

---

## Phase 3 — Calculs

### L13 — Noyau de calcul et premiers calculateurs

**Objectif.** Calculer de façon déterministe, traçable et testée à part.
**Contenu.** Contexte `Calculation` : grandeurs typées, définition versionnée d'un calculateur, trace d'exécution. Premiers calculateurs fondés sur les seules lois physiques : loi d'Ohm, puissance en monophasé et en triphasé, courant d'emploi, chute de tension. Routes pour lister les calculateurs et calculer.
**Critères d'acceptation.**
- Mêmes entrées et même version donnent le même résultat (CA1).
- Une entrée manquante, hors bornes ou d'unité incohérente donne un refus motivé, jamais une valeur par défaut silencieuse (CA2 à CA4).
- Chaque résultat est accompagné de ses étapes, de ses hypothèses et de la source de la formule (CA7).
- Tous les cas de référence fournis par l'expert passent.
- Le taux de mutations détectées sur `Calculation` atteint le seuil fixé au lancement du lot (proposition : 90 %).
- `Calculation` ne dépend d'aucun composant lié au modèle de langage (vérifié par L2).

**Tests.** Suite critique : cas de référence dans des fichiers de données indépendants du code ; propriétés (monotonie, cohérence des unités, aller-retour des conversions) ; tests de mutation. Intégration des routes.

### L14 — Calculs dans la conversation

**Objectif.** Calculer depuis la discussion, sans que le modèle touche aux nombres.
**Contenu.** Outils de calcul offerts au modèle. Bloc de calcul composé à partir de la trace.
**Critères d'acceptation.**
- Tout nombre présenté comme un résultat de calcul provient d'une exécution enregistrée.
- S'il manque une entrée, l'assistant la demande au lieu de la supposer.
- Le bloc affiche entrées, hypothèses, étapes, résultat et version du calculateur.
- Un refus du calculateur est restitué tel quel à l'utilisateur.

**Tests.** Unitaires de l'outil. Intégration avec le faux modèle : appel réussi, entrée manquante, refus. Cas d'évaluation « calcul ».

---

## Phase 4 — Diagnostic

### L15 — Prévention : niveaux de risque, qualification, catalogue

**Objectif.** Décider par du code, et non par le modèle, quel geste peut être proposé à qui.
**Contenu.** Contexte `Safety`. Niveaux de risque. Qualification déclarée dans le profil, côté API et mobile, particulier compris. Politique qualification × niveau. Format du catalogue de vérifications et premières procédures relues par l'expert, dont celles qui sont permises à un particulier. Messages de sécurité fixes. Signaux de danger. Consignes interdites.
**Critères d'acceptation.**
- La politique est testée pour toutes les combinaisons et correspond à la table validée par l'expert (SF2, SF3).
- Un profil sans qualification renseignée est traité comme le plus restrictif (SF2).
- Une procédure sans niveau de risque, sans prérequis ou sans relecteur ne peut pas être chargée (SF1).
- Un message de sécurité s'affiche mot pour mot tel qu'il a été validé (SF4).
- Chaque consigne interdite du jeu de test est détectée dans un texte qui la contient.

**Tests.** Suite critique : matrice exhaustive, chargement du catalogue, détection des consignes interdites sur un jeu de textes positifs et négatifs. Intégration du profil. Tests d'écrans.

### L16 — Domaine du diagnostic, sans modèle de langage

**Objectif.** Disposer d'un diagnostic entièrement pilotable et testable par des commandes.
**Contenu.** Contexte `Diagnosis` : session, contexte d'installation, symptômes, observations, mesures, hypothèses, vérifications, conclusion, journal. Une commande par transition. Route de lecture d'une session.
**Critères d'acceptation.**
- Chacune des règles DG1 à DG11 est couverte par au moins un test qui la viole et un test qui la respecte.
- Trois scénarios de référence rédigés par l'expert, dont un mené par un particulier, se rejouent par commandes, sans modèle, et produisent le journal attendu.
- Un signal de danger suspend la session : aucune vérification ne peut plus être proposée (DG7).
- Seul le propriétaire lit sa session.

**Tests.** Unitaires de l'agrégat. Scénarios complets rejoués. Intégration de la route de lecture.

### L17 — Diagnostic guidé dans la conversation

**Objectif.** Guider l'utilisateur par des questions adaptatives, sous le contrôle du domaine.
**Contenu.** Capacité « diagnostic » : outils de transition, état de la session ajouté au contexte de chaque tour, questions une à une, blocs d'étape et de tableau des hypothèses, rendu mobile.
**Critères d'acceptation.**
- Le modèle ne modifie l'état que par les outils. Une transition refusée par le domaine lui est restituée avec son motif et figure dans la trace.
- Toute vérification proposée vient du catalogue et est permise pour la qualification de l'utilisateur (DG4).
- L'assistant pose une question à la fois, avec sa raison. « Je ne sais pas » et « je ne peux pas » font avancer le diagnostic autrement (DG5).
- Un signal de danger exprimé par l'utilisateur déclenche le message de sécurité fixe avant tout autre contenu (SF5).
- Sur les scénarios simulés du jeu d'évaluation : aucune vérification interdite proposée, aucune consigne interdite émise.
- Avec un compte de particulier, aucune vérification au-delà des gestes permis n'est proposée, et le diagnostic se conclut par une orientation quand il ne peut plus avancer (DG11).

**Tests.** Unitaires des outils. Intégration avec le faux modèle : transition valide, transition refusée, signal de danger, profil particulier. Évaluation avec un utilisateur simulé. Tests d'écrans.

### L18 — Conclusion argumentée et retour du terrain

**Objectif.** Conclure sans jamais présenter l'incertain comme certain.
**Contenu.** Règles du niveau de confiance. Bloc de conclusion. Résumé du diagnostic, consultable et transmissible à un professionnel. Retour de l'utilisateur : cause confirmée, infirmée, inconnue. Recueil du retour de dépannage (D18, premier temps) : ce qui a résolu la panne, accord de partage, fiche proposée par le modèle et contrôlée par le domaine ; rien n'est encore montré à d'autres utilisateurs.
**Critères d'acceptation.**
- Le niveau de confiance affiché est celui que calcule le domaine. Le modèle ne peut pas l'augmenter (DG6).
- « Établi » est impossible tant qu'une hypothèse reste ouverte ou qu'aucune mesure ni aucun constat ne soutient la cause.
- Toute conclusion liste ses observations d'appui, ce qui reste incertain et les hypothèses non écartées.
- La formulation suit un gabarit propre à chaque niveau de confiance.
- Le résumé transmissible reprend symptôme, observations et hypothèses restantes, sans jargon pour un particulier.
- Le retour du terrain est enregistré et relié à la session.
- Un retour de dépannage n'est enregistré pour partage qu'avec l'accord explicite de son auteur, daté et lié à la version du texte d'accord (EX1) ; un invité ne peut pas en proposer (EX2).
- Une fiche contenant une consigne interdite, une référence absente du registre ou une valeur sans unité est refusée aux contrôles, avec son motif (EX4).

**Tests.** Suite critique : table de décision du niveau de confiance. Intégration. Tests d'écrans. Évaluation : scénarios dont la bonne issue est « indéterminé » ou « orientation ».

---

## Phase 5 — Ouverture

### L19 — Durcissement

**Objectif.** Rendre le produit exploitable par des inconnus.
**Contenu.** Quota par utilisateur et budget par tour. Budget global de l'essai sans compte ; étude des attestations d'appareil (D17). Tour exécuté en tâche de fond si les mesures le demandent (D8). Supervision des erreurs, des durées et des coûts. Durée de conservation et suppression des données. Conditions d'utilisation et mentions. Signalement d'une réponse depuis l'application. Commande de désactivation d'un contenu. Campagne d'attaque par consignes cachées, dans des messages et dans des documents.
**Critères d'acceptation.**
- Un quota dépassé renvoie une 429 avec un message clair.
- Une fois le budget global des invités atteint, un nouvel invité est invité à créer un compte ; les comptes complets ne sont pas touchés.
- Un tour ne peut dépasser son budget en itérations, en jetons ni en durée.
- L'API reste disponible, connexion comprise, pendant un nombre de tours simultanés fixé au lancement du lot.
- Un document piégé du jeu d'attaque ne change pas le comportement de l'assistant : aucune consigne suivie, aucun outil appelé à sa demande.
- La suppression d'un compte efface conversations, sessions de diagnostic et traces nominatives.
- Un document, une procédure ou un calculateur peut être désactivé par une commande, avec effet immédiat.
- Un signalement d'utilisateur arrive à un responsable désigné, avec la trace du tour.

**Tests.** Intégration des quotas et de la suppression. Test de charge. Jeu d'attaque dans l'évaluation.

### L20 — Bêta fermée

**Objectif.** Confronter le produit au terrain avec un petit groupe, professionnels et particuliers.
**Contenu.** Ouverture à un groupe restreint. Revue hebdomadaire des signalements, des tours rejetés ou dégradés et des retours du terrain. Décision de suite.
**Critères d'acceptation.**
- Aucun incident de sécurité sans traitement ni cas de non-régression ajouté au jeu d'évaluation.
- Les mesures de succès de PRODUCT.md, § 6, sont relevées.
- Une liste priorisée des suites est arrêtée.

**Tests.** Ceux des lots précédents, rejoués avant l'ouverture.

---

## Phase 6 — Conseil en équipements (après le MVP)

Ces lots sont décrits à gros grain. Ils seront redécoupés quand les réponses aux questions P8 et P9 seront connues.

### L21 — Catalogue : modèle et import

**Objectif.** Disposer de données produits sourcées, pour une ou deux catégories.
**Contenu.** Contexte `Catalog` : marques, gammes, références, catégories et critères, caractéristiques avec provenance, sources produits. `ProductDataImporterInterface` et un premier import depuis des fichiers de fabricants.
**Critères d'acceptation.**
- Toute caractéristique porte sa source et sa date ; une valeur absente vaut « non renseigné » (CT1).
- Un import n'est accepté que s'il est rattaché à une source dont les droits d'usage sont renseignés ; il est rejouable et garde l'historique des valeurs (CT5).
- Une catégorie définit ses critères, avec unité et sens de lecture.
- Une référence arrêtée reste consultable et signalée (CT6).

**Tests.** Unitaires des règles CT1 à CT6. Intégration de l'import sur des fichiers d'exemple, dont un fichier incomplet.

### L22 — Corpus produits et recherche de références

**Objectif.** Interroger notices, fiches techniques et références sans les mêler aux textes normatifs.
**Contenu.** Corpus « produits » dans `Knowledge`, avec ses règles de publication. `ProductSearchInterface`. Outil `rechercher_produits`. Bloc « fiche produit ».
**Critères d'acceptation.**
- Une recherche limitée au corpus réglementaire ne renvoie aucun passage du corpus produits, et inversement (KN7).
- Une citation de notice est affichée comme document de fabricant, jamais comme une règle.
- La fiche produit est composée à partir du catalogue ; le modèle n'en écrit aucune valeur (PR2).
- Une question sur une référence absente du catalogue reçoit une réponse qui le dit.

**Tests.** Suite critique : séparation des corpus. Intégration de la recherche. Cas d'évaluation « produit inconnu », tolérance nulle sur les caractéristiques inventées.

### L23 — Comparaison structurée

**Objectif.** Comparer plusieurs références selon les critères de leur catégorie.
**Contenu.** `Comparison`, outil `comparer_produits`, bloc « tableau comparatif ».
**Critères d'acceptation.**
- Une comparaison ne porte que sur les critères de la catégorie commune (CT4).
- Chaque cellule renvoie à sa source ; une valeur manquante s'affiche « non renseigné ».
- La comparaison indique son étendue : marques et nombre de références présentes au catalogue (PR7).
- Un avis rapporté n'apparaît jamais dans une colonne de caractéristiques (CT2).

**Tests.** Unitaires de la comparaison. Intégration avec le faux modèle. Tests d'écrans.

### L24 — Compatibilité avec l'installation

**Objectif.** Dire si un équipement convient à une installation, ou ce qu'il manque pour le dire.
**Contenu.** `InstallationConstraints`, règles de compatibilité déterministes appuyées sur les calculateurs, outil `verifier_compatibilite`.
**Critères d'acceptation.**
- Le verdict est produit par une règle nommée, à partir de données du catalogue et de données déclarées (PR3).
- S'il manque une donnée, le verdict est « indéterminé » et nomme ce qui manque.
- Les cas de référence fournis par l'expert passent tous.
- Un verdict « incompatible » ou « indéterminé » qui suppose de modifier l'installation oriente vers un électricien.

**Tests.** Suite critique : table de décision par règle, cas de référence de l'expert.

### L25 — Recommandations et alternatives

**Objectif.** Recommander de façon argumentée et neutre.
**Contenu.** `CustomerNeed`, `RecommendationSession`, classement reproductible, outil `recommander`, bloc « recommandation ». Le modèle aide à recueillir le besoin et à l'expliquer ; il ne classe pas.
**Critères d'acceptation.**
- Le classement se recalcule à l'identique à partir du besoin et des critères enregistrés (PR4).
- Échanger les noms de marque de deux références aux caractéristiques identiques ne change pas le classement, sauf préférence exprimée.
- Chaque argument renvoie à une caractéristique sourcée ou à un élément du besoin (PR5).
- Quand le catalogue le permet, plusieurs alternatives sont proposées à des niveaux de budget différents (PR6).
- Tout lien commercial avec une marque citée est affiché (PR8).

**Tests.** Suite critique : classement, test d'échange des marques. Évaluation : famille « neutralité ».

### L26 — Prix, disponibilités et sélection présentable au client

**Objectif.** Compléter le conseil par des données de distributeurs, et produire un document à remettre.
**Contenu.** `Offer`, `OfferFeedInterface`, délai de fraîcheur par catégorie, export d'une sélection.
**Critères d'acceptation.**
- Aucun prix ni aucune disponibilité ne s'affiche sans distributeur ni date (CT3).
- Un relevé plus ancien que le délai de fraîcheur n'est pas présenté comme actuel.
- La sélection exportée reprend besoin, références, arguments, sources et dates.

**Tests.** Unitaires de la fraîcheur. Intégration du flux avec une source simulée. Tests d'écrans.

---

## Phase 7 — Apprendre du terrain (après le MVP)

Décision D18 : Hector apprend des dépannages réussis par une base de cas relus, fournie au modèle comme contexte, et non par un réentraînement du modèle. Le recueil commence au lot L18, pour que la bêta produise des cas ; ces lots les rendent utiles. Ils seront redécoupés quand les réponses aux questions P12 et P13 seront connues.

### L27 — Relecture et publication des cas du terrain

**Objectif.** Ne montrer à d'autres utilisateurs que des retours jugés justes par un humain.
**Contenu.** Contexte `Experience` : `FieldReport`, `CaseReview`, `FieldCase`. File de relecture (commandes, puis écran réservé aux relecteurs), anonymisation contrôlée, niveau de risque fixé par le relecteur, retrait par commande.
**Critères d'acceptation.**
- Aucun cas n'est publié sans relecteur, date de relecture et niveau de risque (EX5).
- Un cas publié ne contient ni le texte d'origine ni aucun lien vers son auteur (EX3).
- Le jeu de fiches piégées (données personnelles, consignes interdites, références inventées) est entièrement arrêté avant publication.
- Retirer un cas le sort de la recherche immédiatement ; il reste lisible, avec la mention « retiré », par les tours qui l'ont cité (EX8).
- Supprimer un compte supprime ses retours non publiés (P12).

**Tests.** Suite critique : contrôles d'une fiche, sur des cas positifs et négatifs. Unitaires du cycle de vie. Intégration de la file de relecture et de la suppression.

### L28 — Cas du terrain dans la conversation

**Objectif.** Faire profiter chaque utilisateur des dépannages réussis par d'autres, sans en faire des certitudes.
**Contenu.** `FieldCaseSearchInterface` sur les adaptateurs de recherche des lots L10 et L12. Outil `rechercher_cas_terrain`. Nature « retour du terrain » dans le format de blocs. Retour de l'utilisateur sur un cas cité (a aidé, n'a pas aidé). Rendu mobile.
**Critères d'acceptation.**
- Toute mention d'un cas désigne un cas renvoyé par la recherche pendant ce tour ; sinon le brouillon est rejeté (D7).
- Un énoncé appuyé sur un cas est affiché comme retour du terrain, avec son nombre de cas ; un brouillon qui en fait un fait sourcé est rejeté (EX6).
- Pour un lecteur dont la qualification ne permet pas le geste décrit, la résolution n'apparaît pas : seule l'orientation vers un professionnel est donnée (EX7).
- Un cas infirmé au-delà du seuil revient en relecture et sort de la recherche (EX9).
- Un cas piégé du jeu d'attaque ne change pas le comportement de l'assistant (T13).
- La part des cas d'évaluation mieux traités avec les cas du terrain est mesurée ; l'outil n'est conservé que sur ce chiffre.

**Tests.** Suite critique : filtrage par qualification. Intégration avec le faux modèle. Évaluation avant et après. Tests d'écrans.

### L29 — Fréquences de causes confirmées

**Objectif.** Ordonner les hypothèses d'un diagnostic selon ce que le terrain a confirmé.
**Contenu.** Fréquences calculées par le domaine, par symptôme et contexte d'installation semblables, fournies au diagnostic comme contexte. Rapport des manques du catalogue de vérifications.
**Critères d'acceptation.**
- Aucune fréquence n'est calculée ni montrée en dessous du nombre minimal de cas (EX10).
- Une fréquence s'affiche comme hypothèse, avec son nombre de cas ; elle ne change ni le niveau de confiance (DG6) ni les gestes permis.
- Le rapport des manques liste les causes confirmées sans vérification au catalogue.

**Tests.** Suite critique : calcul des fréquences et seuil. Évaluation sur des scénarios simulés.
