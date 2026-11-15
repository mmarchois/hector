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
| L3 | Conversations et messages, sans IA | L2 | 1 |
| L4 | Port du modèle de langage et premier tour réel | L3 | 1, 2 |
| L5 | Écran de conversation (mobile) | L3 | 1 |
| L6 | Réponse structurée et contrôle des réponses | L4 | 2 |
| L7 | Jeu d'évaluation et outil de mesure | L6 | — |
| **Phase 2 — Base documentaire** | | | |
| L8 | Modèle documentaire et ingestion | L2 | 3 |
| L9 | Recherche par mots et réponses sourcées | L6, L8 | 3, 4 |
| L10 | Registre normatif | L8 | 4 |
| L11 | Recherche par vecteurs et fusion | L7, L9 | 3 |
| **Phase 3 — Calculs** | | | |
| L12 | Noyau de calcul et premiers calculateurs | L2 | — |
| L13 | Calculs dans la conversation | L6, L12 | 2 |
| **Phase 4 — Diagnostic** | | | |
| L14 | Prévention : niveaux de risque, qualification, catalogue | L2 | 7 |
| L15 | Domaine du diagnostic, sans modèle de langage | L14 | 7 |
| L16 | Diagnostic guidé dans la conversation | L6, L15 | 5, 6, 7 |
| L17 | Conclusion argumentée et retour du terrain | L16 | 8 |
| **Phase 5 — Ouverture** | | | |
| L18 | Durcissement | L17 | — |
| L19 | Bêta fermée | L18 | — |
| **Phase 6 — Conseil en équipements (après le MVP)** | | | |
| L20 | Catalogue : modèle et import | L8 | — |
| L21 | Corpus produits et recherche de références | L9, L20 | — |
| L22 | Comparaison structurée | L21 | — |
| L23 | Compatibilité avec l'installation | L12, L20 | — |
| L24 | Recommandations et alternatives | L22, L23 | — |
| L25 | Prix, disponibilités et sélection présentable au client | L24 | — |

Le MVP correspond aux phases 0 à 5. Après le lot L2, quatre fils peuvent avancer indépendamment : le socle (L3 à L7), la base documentaire (L8), le calcul (L12), la prévention et le domaine du diagnostic (L14, L15). Ils se rejoignent aux lots L9, L13 et L16.

---

## Phase 0 — Préparation

### L0 — Validation du cadrage

**Objectif.** Partir de décisions écrites.
**Contenu.** Relecture des cinq documents. Réponse à chaque décision D1 à D16 et à chaque question P1 à P9. Désignation de l'expert. Liste du corpus de départ, avec le droit d'usage de chaque document.
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

### L3 — Conversations et messages, sans IA

**Objectif.** Poser le contexte Conversation et son contrat d'API.
**Contenu.** `Conversation`, `Message`, `Turn`, migrations. Routes : créer une conversation, lister les siennes, lire les messages par pages, envoyer un message, suivre un tour. L'assistant est un bouchon qui renvoie une réponse fixe, derrière le contrat définitif.
**Critères d'acceptation.**
- Un utilisateur ne peut ni lire ni alimenter la conversation d'un autre : réponse 404 (CV1).
- L'envoi d'un message renvoie le tour avec son statut et, s'il est terminé, la réponse.
- Un message vide ou trop long renvoie une 422 avec `violations`.
- La suppression d'un compte supprime ses conversations.
- La spécification OpenAPI décrit les nouvelles routes.

**Tests.** Unitaires des handlers. Intégration : propriété, pagination, validation, suppression en cascade.

### L4 — Port du modèle de langage et premier tour réel

**Objectif.** Obtenir une vraie réponse sans lier le code à un fournisseur.
**Contenu.** `ChatModelInterface` et ses objets neutres. Adaptateur du fournisseur retenu pour démarrer (D3). `FakeChatModel` scriptable. Exécution du tour hors transaction. Consignes versionnées dans `resources/prompts`. Trace du tour. Échecs traités : délai dépassé, refus du fournisseur, quota, panne de réseau. Limite de débit par utilisateur.
**Critères d'acceptation.**
- Aucune classe de `Domain` ou d'`Application` ne référence le SDK du fournisseur (vérifié par L2).
- Aucune transaction n'est ouverte en base pendant l'appel au modèle.
- Chaque tour terminé a une trace : modèle, version des consignes, jetons, durée, issue.
- En cas d'échec du fournisseur, le tour passe à « échoué », l'utilisateur reçoit un message clair en français et peut relancer.
- Aucune donnée d'identité (nom, e-mail, SIRET) ne figure dans une requête au fournisseur.
- Le tour se termine et s'enregistre même si le client se déconnecte.

**Tests.** Unitaires de l'exécution du tour avec le faux modèle : succès, délai, refus, boucle bornée. Contrat de l'adaptateur sur des réponses enregistrées. Intégration du parcours complet avec le faux modèle. Test vivant à la main, hors `make test`.

### L5 — Écran de conversation (mobile)

**Objectif.** Dialoguer depuis l'application.
**Contenu.** Liste des conversations, écran de discussion, saisie, états d'envoi, d'attente, d'échec et de relance, rendu des blocs, repli pour un type inconnu, mention « assistant IA ».
**Critères d'acceptation.**
- Le parcours créer, envoyer, lire fonctionne contre l'API locale.
- Après une coupure de réseau pendant l'attente, l'application retrouve la réponse en rouvrant la conversation, sans message en double.
- Un bloc de type inconnu s'affiche par son texte de repli (CV5).
- L'écran indique en permanence qu'on parle à une IA.
- Tous les textes sont dans `app_fr.arb`.

**Tests.** Tests d'écrans avec l'adaptateur scripté : états, repli, relance, reprise. Parcours de bout en bout.

### L6 — Réponse structurée et contrôle des réponses

**Objectif.** Faire de la nature de chaque énoncé une donnée contrôlée.
**Contenu.** Format de blocs versionné (D12) : énoncés typés, question, avertissement. Sortie structurée demandée au modèle. Contrôle déterministe du brouillon. Une reprise, puis réponse dégradée. Détection, dans le texte libre, des renvois à une disposition précise.
**Critères d'acceptation.**
- Un brouillon contenant un « fait sourcé » sans citation est rejeté (CV4).
- Un brouillon qui renvoie à une disposition précise (numéro d'article, de tableau) sans citation valide est rejeté.
- Après un rejet, il y a une seule reprise. Si elle échoue, l'utilisateur reçoit une réponse dégradée qui le dit ; il ne reçoit jamais le brouillon rejeté.
- Chaque rejet figure dans la trace du tour, avec son motif.
- Le mobile distingue visuellement fait, hypothèse, recommandation et information manquante.

**Tests.** Suite critique : pour chaque règle du contrôle, au moins un cas accepté et un cas rejeté. Intégration avec le faux modèle : brouillon invalide puis valide, brouillon invalide deux fois. Tests d'écrans.

### L7 — Jeu d'évaluation et outil de mesure

**Objectif.** Mesurer la qualité avant de choisir un modèle et avant chaque changement de consignes.
**Contenu.** Format des cas : question, éléments attendus, sources attendues, interdits. Premiers cas rédigés avec l'expert, en trois familles : questions techniques, pièges normatifs (article ou édition qui n'existe pas), demandes dangereuses. Commande `make eval`. Rapport daté : modèle, version des consignes, résultats par famille, coût. Comparaison de deux exécutions.
**Critères d'acceptation.**
- `make eval` produit un rapport qui identifie le modèle et la version des consignes.
- Les familles « pièges normatifs » et « demandes dangereuses » ont un critère binaire, vérifié automatiquement.
- Le jeu couvre les trois familles avec un nombre de cas arrêté avec l'expert.
- L'évaluation n'est pas lancée par `make test`, et son coût est affiché à la fin.
- Deux ou trois modèles candidats ont été mesurés et la décision D3 est prise sur ces résultats.

**Tests.** Unitaires de l'outil (lecture des cas, calcul des résultats) avec le faux modèle.

---

## Phase 2 — Base documentaire

### L8 — Modèle documentaire et ingestion

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

### L9 — Recherche par mots et réponses sourcées

**Objectif.** Répondre en citant des passages réels.
**Contenu.** `PassageSearchInterface` et son adaptateur plein texte français. Outil `rechercher_documentation`. Citations par identifiant, contrôlées par le serveur (D7). Route de consultation d'une source citée. Affichage des sources sur mobile.
**Critères d'acceptation.**
- Toute citation d'une réponse désigne un passage renvoyé par la recherche pendant ce tour ; sinon le brouillon est rejeté.
- Chaque citation affichée comporte titre, éditeur, version, date d'effet si elle est connue, et localisation.
- Une question hors corpus reçoit une réponse qui dit que la base ne couvre pas le sujet.
- La recherche d'un identifiant exact présent dans le corpus fait remonter son passage en tête.
- L'extrait affiché respecte la licence de la source : extrait, ou simple renvoi (KN5).
- Une recherche nomme ses corpus, et chaque résultat indique le sien (KN7).
- La part des cas d'évaluation où la source attendue est retrouvée est mesurée et notée : c'est la référence du lot L11.

**Tests.** Suite critique : contrôle des citations. Intégration de la recherche sur un corpus de test. Évaluation. Tests d'écrans.

### L10 — Registre normatif

**Objectif.** Ne citer que des textes et des éditions qui existent, avec leurs dates.
**Contenu.** Contexte `Regulation`. Registre chargé depuis `resources/regulation`, relu par l'expert. Contrôle des références normatives. Signalement d'une édition remplacée.
**Critères d'acceptation.**
- Une réponse ne peut mentionner ni un texte ni une édition absents du registre (RG1).
- Une citation tirée d'une édition remplacée s'affiche avec cette mention et sa date (RG2, RG3).
- Quand deux éditions peuvent s'appliquer et que la date de référence du projet est inconnue, la réponse la demande ou présente les deux cas en le disant (RG5).
- Chaque entrée du registre indique son relecteur et sa date de validation (RG4).
- Aucun piège normatif du jeu d'évaluation n'aboutit à une référence inventée.

**Tests.** Suite critique : contrôle des références. Évaluation, famille « pièges normatifs », tolérance nulle.

### L11 — Recherche par vecteurs et fusion

**Objectif.** Retrouver aussi les passages formulés autrement que la question.
**Contenu.** `EmbeddingModelInterface` et son adaptateur (D5). Extension vectorielle de PostgreSQL. Calcul des vecteurs à l'ingestion. Fusion des deux recherches.
**Critères d'acceptation.**
- La part des sources attendues retrouvées est supérieure ou égale à la référence du lot L9. La recherche hybride n'est conservée que sur ce chiffre.
- Changer de modèle de vecteurs impose un recalcul complet, explicite et tracé ; une recherche ne mélange jamais des vecteurs de modèles différents.
- Si le service de vecteurs est indisponible, la recherche par mots continue de fonctionner.
- `make check` passe, contrôle du schéma compris.

**Tests.** Unitaires de la fusion. Intégration avec un faux modèle de vecteurs déterministe. Évaluation comparée avant et après.

---

## Phase 3 — Calculs

### L12 — Noyau de calcul et premiers calculateurs

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

### L13 — Calculs dans la conversation

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

### L14 — Prévention : niveaux de risque, qualification, catalogue

**Objectif.** Décider par du code, et non par le modèle, quel geste peut être proposé à qui.
**Contenu.** Contexte `Safety`. Niveaux de risque. Qualification déclarée dans le profil, côté API et mobile, particulier compris. Politique qualification × niveau. Format du catalogue de vérifications et premières procédures relues par l'expert, dont celles qui sont permises à un particulier. Messages de sécurité fixes. Signaux de danger. Consignes interdites.
**Critères d'acceptation.**
- La politique est testée pour toutes les combinaisons et correspond à la table validée par l'expert (SF2, SF3).
- Un profil sans qualification renseignée est traité comme le plus restrictif (SF2).
- Une procédure sans niveau de risque, sans prérequis ou sans relecteur ne peut pas être chargée (SF1).
- Un message de sécurité s'affiche mot pour mot tel qu'il a été validé (SF4).
- Chaque consigne interdite du jeu de test est détectée dans un texte qui la contient.

**Tests.** Suite critique : matrice exhaustive, chargement du catalogue, détection des consignes interdites sur un jeu de textes positifs et négatifs. Intégration du profil. Tests d'écrans.

### L15 — Domaine du diagnostic, sans modèle de langage

**Objectif.** Disposer d'un diagnostic entièrement pilotable et testable par des commandes.
**Contenu.** Contexte `Diagnosis` : session, contexte d'installation, symptômes, observations, mesures, hypothèses, vérifications, conclusion, journal. Une commande par transition. Route de lecture d'une session.
**Critères d'acceptation.**
- Chacune des règles DG1 à DG11 est couverte par au moins un test qui la viole et un test qui la respecte.
- Trois scénarios de référence rédigés par l'expert, dont un mené par un particulier, se rejouent par commandes, sans modèle, et produisent le journal attendu.
- Un signal de danger suspend la session : aucune vérification ne peut plus être proposée (DG7).
- Seul le propriétaire lit sa session.

**Tests.** Unitaires de l'agrégat. Scénarios complets rejoués. Intégration de la route de lecture.

### L16 — Diagnostic guidé dans la conversation

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

### L17 — Conclusion argumentée et retour du terrain

**Objectif.** Conclure sans jamais présenter l'incertain comme certain.
**Contenu.** Règles du niveau de confiance. Bloc de conclusion. Résumé du diagnostic, consultable et transmissible à un professionnel. Retour de l'utilisateur : cause confirmée, infirmée, inconnue.
**Critères d'acceptation.**
- Le niveau de confiance affiché est celui que calcule le domaine. Le modèle ne peut pas l'augmenter (DG6).
- « Établi » est impossible tant qu'une hypothèse reste ouverte ou qu'aucune mesure ni aucun constat ne soutient la cause.
- Toute conclusion liste ses observations d'appui, ce qui reste incertain et les hypothèses non écartées.
- La formulation suit un gabarit propre à chaque niveau de confiance.
- Le résumé transmissible reprend symptôme, observations et hypothèses restantes, sans jargon pour un particulier.
- Le retour du terrain est enregistré et relié à la session.

**Tests.** Suite critique : table de décision du niveau de confiance. Intégration. Tests d'écrans. Évaluation : scénarios dont la bonne issue est « indéterminé » ou « orientation ».

---

## Phase 5 — Ouverture

### L18 — Durcissement

**Objectif.** Rendre le produit exploitable par des inconnus.
**Contenu.** Quota par utilisateur et budget par tour. Tour exécuté en tâche de fond si les mesures le demandent (D8). Supervision des erreurs, des durées et des coûts. Durée de conservation et suppression des données. Conditions d'utilisation et mentions. Signalement d'une réponse depuis l'application. Commande de désactivation d'un contenu. Campagne d'attaque par consignes cachées, dans des messages et dans des documents.
**Critères d'acceptation.**
- Un quota dépassé renvoie une 429 avec un message clair.
- Un tour ne peut dépasser son budget en itérations, en jetons ni en durée.
- L'API reste disponible, connexion comprise, pendant un nombre de tours simultanés fixé au lancement du lot.
- Un document piégé du jeu d'attaque ne change pas le comportement de l'assistant : aucune consigne suivie, aucun outil appelé à sa demande.
- La suppression d'un compte efface conversations, sessions de diagnostic et traces nominatives.
- Un document, une procédure ou un calculateur peut être désactivé par une commande, avec effet immédiat.
- Un signalement d'utilisateur arrive à un responsable désigné, avec la trace du tour.

**Tests.** Intégration des quotas et de la suppression. Test de charge. Jeu d'attaque dans l'évaluation.

### L19 — Bêta fermée

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

### L20 — Catalogue : modèle et import

**Objectif.** Disposer de données produits sourcées, pour une ou deux catégories.
**Contenu.** Contexte `Catalog` : marques, gammes, références, catégories et critères, caractéristiques avec provenance, sources produits. `ProductDataImporterInterface` et un premier import depuis des fichiers de fabricants.
**Critères d'acceptation.**
- Toute caractéristique porte sa source et sa date ; une valeur absente vaut « non renseigné » (CT1).
- Un import n'est accepté que s'il est rattaché à une source dont les droits d'usage sont renseignés ; il est rejouable et garde l'historique des valeurs (CT5).
- Une catégorie définit ses critères, avec unité et sens de lecture.
- Une référence arrêtée reste consultable et signalée (CT6).

**Tests.** Unitaires des règles CT1 à CT6. Intégration de l'import sur des fichiers d'exemple, dont un fichier incomplet.

### L21 — Corpus produits et recherche de références

**Objectif.** Interroger notices, fiches techniques et références sans les mêler aux textes normatifs.
**Contenu.** Corpus « produits » dans `Knowledge`, avec ses règles de publication. `ProductSearchInterface`. Outil `rechercher_produits`. Bloc « fiche produit ».
**Critères d'acceptation.**
- Une recherche limitée au corpus réglementaire ne renvoie aucun passage du corpus produits, et inversement (KN7).
- Une citation de notice est affichée comme document de fabricant, jamais comme une règle.
- La fiche produit est composée à partir du catalogue ; le modèle n'en écrit aucune valeur (PR2).
- Une question sur une référence absente du catalogue reçoit une réponse qui le dit.

**Tests.** Suite critique : séparation des corpus. Intégration de la recherche. Cas d'évaluation « produit inconnu », tolérance nulle sur les caractéristiques inventées.

### L22 — Comparaison structurée

**Objectif.** Comparer plusieurs références selon les critères de leur catégorie.
**Contenu.** `Comparison`, outil `comparer_produits`, bloc « tableau comparatif ».
**Critères d'acceptation.**
- Une comparaison ne porte que sur les critères de la catégorie commune (CT4).
- Chaque cellule renvoie à sa source ; une valeur manquante s'affiche « non renseigné ».
- La comparaison indique son étendue : marques et nombre de références présentes au catalogue (PR7).
- Un avis rapporté n'apparaît jamais dans une colonne de caractéristiques (CT2).

**Tests.** Unitaires de la comparaison. Intégration avec le faux modèle. Tests d'écrans.

### L23 — Compatibilité avec l'installation

**Objectif.** Dire si un équipement convient à une installation, ou ce qu'il manque pour le dire.
**Contenu.** `InstallationConstraints`, règles de compatibilité déterministes appuyées sur les calculateurs, outil `verifier_compatibilite`.
**Critères d'acceptation.**
- Le verdict est produit par une règle nommée, à partir de données du catalogue et de données déclarées (PR3).
- S'il manque une donnée, le verdict est « indéterminé » et nomme ce qui manque.
- Les cas de référence fournis par l'expert passent tous.
- Un verdict « incompatible » ou « indéterminé » qui suppose de modifier l'installation oriente vers un électricien.

**Tests.** Suite critique : table de décision par règle, cas de référence de l'expert.

### L24 — Recommandations et alternatives

**Objectif.** Recommander de façon argumentée et neutre.
**Contenu.** `CustomerNeed`, `RecommendationSession`, classement reproductible, outil `recommander`, bloc « recommandation ». Le modèle aide à recueillir le besoin et à l'expliquer ; il ne classe pas.
**Critères d'acceptation.**
- Le classement se recalcule à l'identique à partir du besoin et des critères enregistrés (PR4).
- Échanger les noms de marque de deux références aux caractéristiques identiques ne change pas le classement, sauf préférence exprimée.
- Chaque argument renvoie à une caractéristique sourcée ou à un élément du besoin (PR5).
- Quand le catalogue le permet, plusieurs alternatives sont proposées à des niveaux de budget différents (PR6).
- Tout lien commercial avec une marque citée est affiché (PR8).

**Tests.** Suite critique : classement, test d'échange des marques. Évaluation : famille « neutralité ».

### L25 — Prix, disponibilités et sélection présentable au client

**Objectif.** Compléter le conseil par des données de distributeurs, et produire un document à remettre.
**Contenu.** `Offer`, `OfferFeedInterface`, délai de fraîcheur par catégorie, export d'une sélection.
**Critères d'acceptation.**
- Aucun prix ni aucune disponibilité ne s'affiche sans distributeur ni date (CT3).
- Un relevé plus ancien que le délai de fraîcheur n'est pas présenté comme actuel.
- La sélection exportée reprend besoin, références, arguments, sources et dates.

**Tests.** Unitaires de la fraîcheur. Intégration du flux avec une source simulée. Tests d'écrans.
