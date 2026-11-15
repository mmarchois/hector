# Hector AI — Modèle de domaine

> **Statut : proposition à valider** (15 novembre 2026). Rien de ce qui est décrit ici n'est développé.
> À lire avec : [PRODUCT.md](PRODUCT.md) · [ARCHITECTURE.md](ARCHITECTURE.md) · [ROADMAP.md](ROADMAP.md) · [SECURITY.md](SECURITY.md)

## 1. Carte des contextes

Neuf contextes, dans un seul dépôt et un seul déploiement : sept pour le MVP, deux pour le conseil en équipements qui vient ensuite. Un contexte est un dossier de même nom sous `src/Domain`, `src/Application` et `src/Infrastructure`.

| Contexte | Nom dans le code | Nature | Responsabilité | Ce qu'il ne fait pas |
|---|---|---|---|---|
| Identité | `User`, `Company` (existants) | Générique | Comptes, entreprises, qualification déclarée | Aucune règle électrique |
| Conversation | `Conversation` | Socle | Conversations, messages, tours, format des réponses, trace de chaque tour | Ne connaît aucune notion électrique |
| Documentation | `Knowledge` | Support | Sources, versions de documents, passages, licences, recherche | Ne juge pas ce qui est applicable ou en vigueur |
| Réglementation | `Regulation` | Support | Registre des textes normatifs, de leurs éditions et de leurs dates | Ne stocke pas le contenu des textes (c'est Documentation) |
| Calcul | `Calculation` | Cœur | Grandeurs, calculateurs versionnés, traces d'exécution | N'appelle jamais un modèle de langage |
| Diagnostic | `Diagnosis` | Cœur | Session de diagnostic : contexte, observations, hypothèses, vérifications, conclusion | Ne décide pas seul si un geste est permis (c'est Prévention) |
| Prévention | `Safety` | Cœur | Niveaux de risque, catalogue de vérifications, politique selon la qualification, messages de sécurité | Ne conduit pas le diagnostic |
| Catalogue (après le MVP) | `Catalog` | Support | Marques, gammes, références, catégories et leurs critères, caractéristiques techniques, prix, sources produits | Ne recommande rien et ne juge pas un besoin |
| Conseil produits (après le MVP) | `ProductRecommendation` | Cœur | Besoin du client, critères de sélection, comparaison, compatibilité avec l'installation, recommandations et alternatives | Ne stocke aucune donnée produit (c'est Catalogue) |

La séparation demandée entre connaissances documentaires, réglementation, calculs et diagnostic correspond aux contextes Documentation, Réglementation, Calcul et Diagnostic. Prévention est isolé parce que ses règles servent à la fois au diagnostic et aux réponses libres, et parce qu'elles doivent être testées à part.

Alternative écartée : fusionner Réglementation dans Documentation. C'est plus simple, mais la question « quelle édition s'applique à ce chantier » n'est pas une question de stockage de documents, et le registre doit exister même pour des textes dont on n'a pas le droit d'indexer le contenu.

Les connaissances normatives et les données produits sont gérées séparément : elles n'ont ni les mêmes sources, ni le même rythme de mise à jour, ni les mêmes critères de fiabilité. Les données structurées des produits vivent dans Catalogue. Les documents de fabricants (notices, fiches techniques) passent par la même mécanique documentaire que les autres, mais dans un corpus distinct qui a ses propres règles de publication. La conversation peut interroger les deux ; rien ne les mélange dans le stockage ni dans l'affichage (décision D16).

## 2. Relations entre contextes

Règle générale : un contexte ne manipule jamais les entités d'un autre. Il en connaît les identifiants et passe par une interface publiée par l'autre contexte.

| De | Vers | Nature du lien |
|---|---|---|
| Conversation | tous les contextes métier | Ne dépend d'aucun. Les contextes se branchent sur lui en implémentant son interface de capacité (ARCHITECTURE.md, § 6). |
| Documentation | Réglementation | Une version de document peut être rattachée à une édition du registre, par identifiant. |
| Diagnostic | Prévention | Interroge la politique (« ce geste est-il permis pour cette qualification ? ») et le catalogue. |
| Diagnostic | Calcul | Peut référencer une exécution de calcul comme élément d'appui, par identifiant. |
| Prévention | Identité | Lit la qualification déclarée de l'utilisateur. |
| Calcul | Réglementation | Une constante ou une formule cite sa source par une référence du registre. |
| Conseil produits | Catalogue | Lit les références, leurs caractéristiques et leur provenance. |
| Conseil produits | Calcul | S'appuie sur les calculateurs pour vérifier une compatibilité. |
| Catalogue | Documentation | Une caractéristique peut renvoyer, comme preuve, à un passage d'une fiche technique du corpus produits. |

## 3. Langage commun

Le code existant est en anglais, les messages destinés à l'utilisateur en français. La proposition (décision D11) est de garder l'anglais dans le code, avec ce glossaire comme référence, et de conserver tels quels les termes français sans équivalent fidèle (habilitation, Consuel).

| Terme métier | Dans le code | Définition |
|---|---|---|
| Tour | `Turn` | Un message de l'utilisateur et la réponse qu'il déclenche |
| Bloc | `Block` | Élément typé d'un message (énoncé, question, calcul, étape…) |
| Énoncé | `Statement` | Phrase ou paragraphe d'une réponse, porteur d'une nature |
| Nature d'un énoncé | `StatementKind` | Fait sourcé, fait déclaré, résultat de calcul, explication générale, hypothèse, recommandation, information manquante, avis rapporté |
| Citation | `Citation` | Lien entre un énoncé et un passage précis |
| Capacité | `Capability` | Ce qu'un contexte métier apporte à la conversation : outils, contexte, blocs |
| Source | `Source` | Un document de référence, toutes versions confondues |
| Version de document | `DocumentVersion` | Une édition précise et datée d'une source |
| Passage | `Passage` | Extrait localisé d'une version, unité de recherche et de citation |
| Texte normatif | `NormativeText` | Norme, arrêté, décret ou guide identifié de façon canonique |
| Édition | `Edition` | Millésime d'un texte normatif, avec ses dates d'effet |
| Référence normative | `NormativeReference` | Désignation d'un texte, d'une édition et éventuellement d'une disposition |
| Grandeur | `Quantity` | Valeur accompagnée de son unité (tension, courant, section…) |
| Calculateur | `Calculator` | Calcul défini, versionné, à entrées et sorties typées |
| Exécution de calcul | `CalculationRun` | Trace immuable d'un calcul : entrées, étapes, résultat |
| Session de diagnostic | `DiagnosticSession` | Le dossier d'une panne, du symptôme à la conclusion |
| Contexte d'installation | `InstallationContext` | Type de local, alimentation, schéma de liaison à la terre, équipement concerné |
| Symptôme | `Symptom` | Ce qui ne va pas, tel que décrit |
| Observation | `Observation` | Fait rapporté par l'utilisateur : déclaration, constat, mesure |
| Mesure | `Measurement` | Grandeur, valeur, unité, point de mesure, conditions |
| Hypothèse | `Hypothesis` | Cause possible, avec son statut et sa justification |
| Vérification | `Check` | Geste proposé pour départager des hypothèses |
| Conclusion | `Conclusion` | Cause retenue, appuis, incertitudes, niveau de confiance |
| Procédure | `Procedure` | Fiche de vérification du catalogue, relue par l'expert |
| Niveau de risque | `RiskLevel` | Classement d'un geste, de N0 à N4 |
| Qualification | `Qualification` | Ce que l'utilisateur déclare être en droit de faire |
| Signal de danger | `DangerSignal` | Indice d'un danger immédiat (odeur de brûlé, étincelles, eau, choc ressenti…) |
| Message de sécurité | `SafetyNotice` | Texte fixe, rédigé et relu par un humain, jamais généré |
| Indice visuel | `VisualClue` | Ce qu'une image suggère (plus tard) ; ce n'est jamais une observation |
| Corpus | `Corpus` | Ensemble de sources géré selon ses propres règles : réglementaire et technique d'un côté, produits de l'autre |
| Avis rapporté | `ReportedOpinion` | Avis ou retour d'expérience issu d'une source nommée ; ni une caractéristique, ni une recommandation de l'assistant |
| Marque, gamme, référence | `Brand`, `ProductRange`, `Product` | Les trois niveaux du catalogue |
| Catégorie | `ProductCategory` | Famille de produits comparables, avec ses critères |
| Critère | `Criterion` | Caractéristique comparable d'une catégorie : nom, unité, sens de lecture |
| Caractéristique | `Specification` | Valeur d'un critère pour une référence, avec sa source et sa date |
| Offre | `Offer` | Prix ou disponibilité relevés chez un distributeur, à une date |
| Besoin client | `CustomerNeed` | Usage visé, contraintes, budget, préférences |
| Comparaison | `Comparison` | Tableau de plusieurs références selon les critères de leur catégorie |
| Compatibilité | `CompatibilityAssessment` | Verdict motivé : compatible, incompatible ou indéterminé |

## 4. Modèle initial par contexte

Les règles sont numérotées pour être reprises dans les critères d'acceptation de [ROADMAP.md](ROADMAP.md).

### 4.1 Conversation

| Objet | Type | Contenu |
|---|---|---|
| `Conversation` | Agrégat | Propriétaire, titre, dates, activité en cours éventuelle sous la forme (capacité, identifiant) |
| `Message` | Agrégat | Conversation, rôle (utilisateur ou assistant), liste ordonnée de blocs, citations, date |
| `Turn` | Agrégat | Message de l'utilisateur, message de l'assistant, statut (en cours, terminé, dégradé, échoué) |
| `TurnTrace` | Entité de `Turn` | Modèle utilisé, version des consignes, passages lus, outils appelés, contrôles passés ou échoués, jetons, durée |
| `Block` | Objet-valeur | Type, version du schéma, contenu, texte de repli |
| `Statement` | Bloc | Nature, texte, citations |
| `Citation` | Objet-valeur | Passage visé et, pour l'affichage, titre, éditeur, édition, dates, localisation |

`Message` est un agrégat distinct de `Conversation` : une conversation grossit sans limite, et on ne veut pas charger tous ses messages pour en ajouter un.

Règles :

- **CV1.** Une conversation appartient à un seul utilisateur, qui est le seul à y accéder.
- **CV2.** Un message enregistré ne change plus.
- **CV3.** Un tour contient exactement un message de l'utilisateur et au plus un message de l'assistant.
- **CV4.** Un énoncé « fait sourcé » porte au moins une citation. Un « fait déclaré » renvoie à un message de l'utilisateur ou à une observation. Un « résultat de calcul » renvoie à une exécution de calcul. Un « avis rapporté » nomme sa source.
- **CV5.** Tout bloc porte un texte de repli lisible, pour les versions de l'application qui ne connaissent pas son type.
- **CV6.** Tout message de l'assistant est relié à la trace de son tour.
- **CV7.** Le socle ne connaît aucune notion électrique. L'activité en cours d'une conversation est un simple couple (capacité, identifiant).

### 4.2 Documentation

| Objet | Type | Contenu |
|---|---|---|
| `Corpus` | Objet du domaine | Nom, règles de publication, délai de fraîcheur attendu, niveau de fiabilité affiché |
| `Source` | Agrégat | Corpus, titre, éditeur, genre (norme, texte réglementaire, guide, notice de fabricant, fiche technique, fiche Hector), licence, domaines couverts |
| `License` | Objet-valeur | Indexation permise ou non, extrait affichable ou non et dans quelle limite, transmission à un sous-traitant permise ou non, origine de l'autorisation |
| `DocumentVersion` | Agrégat | Source, libellé de version, date de publication, dates d'effet, version remplacée, statut, empreinte du fichier, relecteur et date de validation |
| `Passage` | Entité | Version, ordre, localisation (page, titre de section, article), texte, empreinte |
| `IngestionRun` | Entité | Version de la chaîne de traitement, outil d'extraction, paramètres de découpage, modèle de vecteurs et sa version |

Règles :

- **KN1.** Une version n'est publiée que si elle a un éditeur, un libellé de version, une date de publication, une licence renseignée et une validation humaine.
- **KN2.** Une version publiée ne change plus. Corriger, c'est publier une nouvelle version.
- **KN3.** Un passage est immuable, identifié et localisé. Une citation vise un passage, donc une version exacte.
- **KN4.** Une version retirée sort de la recherche mais reste consultable par les citations déjà émises.
- **KN5.** La licence gouverne ce qu'on fait d'une source : si l'indexation n'est pas permise, la source ne peut pas être ingérée ; si l'extrait n'est pas affichable, la citation renvoie au document sans le reproduire.
- **KN6.** Une ingestion est reproductible : même fichier et même chaîne de traitement donnent les mêmes passages.
- **KN7.** Une source appartient à un seul corpus. Une recherche nomme les corpus qu'elle interroge, et chaque résultat garde le sien jusqu'à l'affichage.

### 4.3 Réglementation

| Objet | Type | Contenu |
|---|---|---|
| `NormativeText` | Agrégat | Identifiant canonique, titre, organisme, genre |
| `Edition` | Entité | Libellé, date de publication, date d'application, date de retrait, statut, règle de transition en clair, relecteur, version de document associée s'il y en a une |
| `NormativeReference` | Objet-valeur | Texte, édition, disposition éventuelle |

Règles :

- **RG1.** Une référence normative ne se construit qu'à partir du registre. Ce qui n'y figure pas ne peut pas être cité.
- **RG2.** Chaque édition porte un statut daté : à venir, en vigueur, en période de transition, remplacée.
- **RG3.** Une édition remplacée reste au registre. Une installation existante a été réalisée sous l'édition de son époque, et un dépannage peut avoir à s'y référer.
- **RG4.** Chaque entrée du registre indique qui l'a validée et quand.
- **RG5.** Le choix de l'édition applicable à un projet dépend de sa date de référence et de sa nature (neuf, rénovation, existant). Dans le modèle initial, ce choix n'est pas automatisé : si la date manque, l'assistant la demande ou présente les deux cas.

Exemple qui motive ce contexte : la NF C 15-100 a été republiée en 2024 sous la forme d'une série de normes, d'application obligatoire à partir du 1er septembre 2025 avec des modalités de transition, l'édition de 2002 et ses amendements restant la référence des projets antérieurs ([Legrand](https://www.legrand.fr/pro/actualites/revision-de-la-norme-nf-c-15-100-quest-ce-qui-change), [Consuel](https://actualites.consuel.com/wp-content/uploads/2026/03/INFOCONSUEL-EVOLUTION-NFC15_100-AVRIL2026-WEB.pdf)). Ces dates sont à confirmer auprès de l'AFNOR et du Consuel avant d'entrer au registre.

### 4.4 Calcul

| Objet | Type | Contenu |
|---|---|---|
| `Quantity` et ses variantes (`Voltage`, `Current`, `Power`, `Length`, `CrossSection`, `PowerFactor`…) | Objets-valeurs | Valeur, unité, bornes admises |
| `CalculatorDefinition` | Objet du domaine | Clé, version, entrées et sorties typées, hypothèses, domaine de validité, source de la formule et des constantes |
| `CalculationRun` | Agrégat | Calculateur et version, entrées, étapes intermédiaires, résultat, avertissements, demandeur, date |

Règles :

- **CA1.** Un calcul est une fonction pure : mêmes entrées et même version donnent le même résultat. Aucun calculateur n'appelle un modèle de langage.
- **CA2.** Aucun nombre sans unité ne circule. Les conversions passent par les objets-valeurs.
- **CA3.** Hors de son domaine de validité, un calculateur refuse en expliquant pourquoi. Il n'extrapole pas.
- **CA4.** Toute hypothèse et toute valeur par défaut apparaît dans la trace. Aucune valeur par défaut silencieuse.
- **CA5.** Changer une formule ou une constante crée une nouvelle version. Les exécutions passées gardent la leur.
- **CA6.** La règle d'arrondi est explicite et, quand la sécurité est en jeu, orientée du côté sûr.
- **CA7.** Chaque constante et chaque formule a une source : loi physique ou référence du registre.

Périmètre initial : calculs qui ne reposent que sur des lois physiques (loi d'Ohm, puissance en monophasé et en triphasé, courant d'emploi, chute de tension). Les calculs qui reposent sur des tableaux de normes attendent la réponse à la question P3.

### 4.5 Diagnostic

| Objet | Type | Contenu |
|---|---|---|
| `DiagnosticSession` | Agrégat | Propriétaire, conversation liée, statut (ouverte, suspendue pour sécurité, conclue, abandonnée), et tout ce qui suit |
| `InstallationContext` | Objet-valeur | Type de local, alimentation, schéma de liaison à la terre, équipement concerné ; chaque champ peut valoir « inconnu » |
| `Symptom` | Entité | Description, circonstances, ancienneté |
| `Observation` | Entité | Genre (déclaration, constat, mesure), contenu, mesure éventuelle, message d'origine, date |
| `Hypothesis` | Entité | Énoncé, famille de cause, statut (ouverte, renforcée, affaiblie, écartée, retenue), justification, observations pour et contre |
| `Check` | Entité | Procédure du catalogue et sa version, hypothèses visées, statut (proposée, acceptée, réalisée, refusée, impossible), observation produite |
| `Conclusion` | Objet-valeur | Hypothèses retenues, niveau de confiance (établi, probable, indéterminé), observations à l'appui, incertitudes, hypothèses non écartées, suite recommandée, résumé transmissible à un professionnel |
| Journal | Liste d'événements | Chaque transition, avec son auteur, sa justification et le tour d'origine |

Règles :

- **DG1.** Toute observation indique son genre et le message dont elle provient.
- **DG2.** Une mesure porte grandeur, valeur, unité et point de mesure. Incomplète, elle est enregistrée comme simple déclaration.
- **DG3.** Une hypothèse ne change de statut qu'avec une justification et au moins une observation liée.
- **DG4.** Une vérification ne peut être proposée que si elle vient du catalogue et si la politique de prévention l'autorise pour la qualification de l'utilisateur.
- **DG5.** Le résultat d'une vérification devient une observation. « Refusée » et « impossible » sont des issues normales, qui réorientent le diagnostic.
- **DG6.** Le niveau de confiance est calculé par le domaine, pas choisi par le modèle. « Établi » exige une cause soutenue par au moins une mesure ou un constat et toutes les autres hypothèses écartées. « Probable » exige au moins une observation à l'appui et aucune contraire. Sinon, « indéterminé ».
- **DG7.** Un signal de danger suspend la session. Tant qu'il n'est pas levé, seuls des gestes de mise en sécurité peuvent être proposés.
- **DG8.** Le modèle ne contient aucun état « installation hors tension ». Il n'existe que l'observation « vérification d'absence de tension réalisée par l'utilisateur, résultat déclaré », qui ne dispense jamais d'afficher la procédure de consignation.
- **DG9.** Toute transition est journalisée avec son auteur (utilisateur, assistant, système) et son tour d'origine.
- **DG10.** Une session conclue ne change plus. Reprendre un diagnostic ouvre une nouvelle session liée à la précédente.
- **DG11.** Quand la suite du diagnostic exigerait un geste que la qualification de l'utilisateur ne permet pas, la session se conclut par une orientation vers un professionnel, avec un résumé transmissible : symptôme, observations, hypothèses restantes. C'est l'issue normale de nombreux diagnostics menés par un particulier.

Le modèle de langage ne modifie jamais cet état directement : il propose des transitions, que l'agrégat accepte ou refuse selon ces règles (décision D6).

### 4.6 Prévention

| Objet | Type | Contenu |
|---|---|---|
| `RiskLevel` | Énumération ordonnée | N0 à N4 (SECURITY.md, § 3) |
| `Qualification` | Objet-valeur | Type de compte, habilitations déclarées |
| `Procedure` | Agrégat | Clé, version, étapes rédigées par un humain, niveau de risque, prérequis, forme du résultat attendu, relecteur et date |
| `SafetyPolicy` | Service du domaine | Dit si un niveau de risque est permis pour une qualification |
| `DangerSignal` | Objet du domaine | Signal reconnu et message de sécurité associé |
| `SafetyNotice` | Objet du domaine | Texte fixe et versionné |
| `ForbiddenInstruction` | Objet du domaine | Consigne qui ne doit jamais être donnée (ponter une protection, travailler sous tension…) |

Règles :

- **SF1.** Une procédure sans niveau de risque, sans prérequis ou sans relecteur ne peut pas entrer au catalogue.
- **SF2.** La politique répond pour toute combinaison qualification × niveau. Une qualification inconnue est traitée comme la plus restrictive.
- **SF3.** Le niveau N4 n'est permis à personne.
- **SF4.** Un message de sécurité est affiché mot pour mot. Il n'est ni généré ni reformulé.
- **SF5.** Un signal de danger passe avant tout autre contenu de la réponse.

### 4.7 Catalogue (après le MVP)

| Objet | Type | Contenu |
|---|---|---|
| `Brand` | Agrégat | Nom, fabricant |
| `ProductRange` | Entité | Marque, nom de gamme, positionnement déclaré par le fabricant |
| `ProductCategory` | Agrégat | Nom, liste de critères |
| `Criterion` | Objet-valeur | Nom, type, unité, sens de lecture (plus grand préférable, plus petit préférable, neutre) |
| `Product` | Agrégat | Marque, gamme, référence du fabricant, catégorie, statut (commercialisé, arrêté) |
| `Specification` | Entité de `Product` | Critère, valeur, source, date de la source, passage de preuve éventuel |
| `Offer` | Entité | Référence, distributeur, prix, disponibilité, date du relevé |
| `ProductSource` | Agrégat | Origine d'un lot de données (fabricant, base publique, distributeur), droits d'usage, date d'import |

Règles :

- **CT1.** Toute caractéristique porte sa source et sa date. Une valeur absente vaut « non renseigné », jamais une estimation.
- **CT2.** Une caractéristique vient d'une source vérifiable. Un avis ou un retour d'expérience n'est jamais enregistré comme caractéristique.
- **CT3.** Un prix ou une disponibilité est un relevé daté chez un distributeur nommé. Passé le délai de fraîcheur de sa catégorie, il n'est plus présenté comme actuel.
- **CT4.** Deux références ne se comparent que sur les critères de leur catégorie commune.
- **CT5.** Un import est rattaché à une source produit dont les droits d'usage sont renseignés. Il est rejouable et conserve l'historique des valeurs.
- **CT6.** Une référence arrêtée reste au catalogue, signalée comme telle.

### 4.8 Conseil produits (après le MVP)

| Objet | Type | Contenu |
|---|---|---|
| `RecommendationSession` | Agrégat | Propriétaire, conversation liée, besoin, contraintes, catégorie, références considérées, comparaison, recommandation |
| `CustomerNeed` | Objet-valeur | Usage, contraintes du lieu, budget, préférences de marques, priorités entre critères |
| `InstallationConstraints` | Objet-valeur | Monophasé ou triphasé, puissance disponible, circuit et protection prévus ; chaque champ peut valoir « inconnu » |
| `Comparison` | Objet-valeur | Références comparées, critères retenus, valeurs avec leur provenance |
| `CompatibilityAssessment` | Objet-valeur | Verdict (compatible, incompatible, indéterminé), règle appliquée, données utilisées, informations manquantes |
| `Recommendation` | Objet-valeur | Références proposées et classées, arguments, alternatives par niveau de budget, limites |

Règles :

- **PR1.** Une recommandation ne porte que sur des références présentes au catalogue.
- **PR2.** Toute valeur affichée dans une comparaison ou un argument vient du catalogue, avec sa provenance. Le modèle de langage n'en fournit aucune.
- **PR3.** La compatibilité est établie par des règles déterministes, appuyées sur les calculateurs. S'il manque une donnée, le verdict est « indéterminé » et nomme ce qui manque.
- **PR4.** Le classement découle du besoin et des critères déclarés, par un calcul reproductible. La marque n'y entre que si l'utilisateur a exprimé une préférence.
- **PR5.** Chaque argument renvoie à une caractéristique sourcée ou à un élément du besoin.
- **PR6.** Quand le catalogue le permet, la recommandation propose plusieurs alternatives, à des niveaux de budget différents.
- **PR7.** Toute recommandation indique l'étendue de ce qui a été comparé : catégorie, marques et nombre de références présentes au catalogue.
- **PR8.** Tout lien commercial avec une marque ou un distributeur cité est affiché.

### 4.9 Identité (existant, à compléter)

`User` et `Company` restent tels quels. Le lot L14 ajoute au profil la qualification déclarée, selon la réponse à la question P5.

## 5. Ce que le modèle initial laisse volontairement de côté

- La structure fine des normes, article par article. Le registre s'arrête à l'édition ; une disposition n'existe que portée par la localisation d'un passage.
- Le choix automatique de l'édition applicable (règle RG5).
- Une base de connaissances de pannes sous forme d'arbres de décision. Les hypothèses sont proposées par le modèle et encadrées par les règles du domaine (décision D6).
- Le partage de conversations ou de diagnostics au sein d'une entreprise.
- Les images : `VisualClue` figure au glossaire pour fixer dès maintenant qu'une image ne produit jamais une observation, mais rien n'est modélisé au-delà.
- Un contexte « Installation » commun. La description de l'installation sert au diagnostic (`InstallationContext`) et à la compatibilité des produits (`InstallationConstraints`). Chaque contexte garde la sienne pour l'instant ; un modèle partagé sera extrait quand les deux existeront et que leurs besoins réels seront connus.
- Les avis d'utilisateurs sur les produits. La nature « avis rapporté » existe pour qu'un avis ne puisse jamais passer pour une caractéristique, mais la collecte d'avis n'est pas modélisée.
