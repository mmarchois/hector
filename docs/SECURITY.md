# Hector AI — Sécurité et fiabilité

> **Statut : proposition à valider** (15 novembre 2026). Rien de ce qui est décrit ici n'est développé.
> À lire avec : [PRODUCT.md](PRODUCT.md) · [ARCHITECTURE.md](ARCHITECTURE.md) · [DOMAIN.md](DOMAIN.md) · [ROADMAP.md](ROADMAP.md)

## 1. Objet

Une mauvaise réponse d'Hector peut blesser quelqu'un ou endommager une installation. Ce document dit comment chaque règle de prudence devient un mécanisme vérifiable, ce que ces mécanismes ne garantissent pas, et quels risques restent ouverts.

Quatre sujets, par ordre de gravité : la sécurité des personnes, la fiabilité des réponses, la conformité, la sécurité de l'application et des données.

Tout ce qui touche au droit ou à la pratique du métier dans ce document est à faire confirmer : par l'expert métier pour les gestes et les niveaux de risque, par un juriste pour le reste.

## 2. Des règles aux mécanismes

Le principe est toujours le même : ne pas compter sur les consignes données au modèle pour tenir une règle, mais sur un contrôle écrit en code, testé sans modèle.

| Règle | Mécanisme | Où | Comment c'est testé |
|---|---|---|---|
| Distinguer faits, hypothèses et recommandations | Chaque énoncé porte une nature. Un fait exige une preuve : citation, observation ou calcul. Le mobile rend chaque nature différemment. | Format des réponses (CV4), lot L7 | Suite critique sur le contrôle des brouillons |
| Ne jamais inventer une référence normative | Une référence ne se construit qu'à partir du registre. Une citation doit viser un passage lu pendant le tour. Un renvoi à une disposition précise sans citation fait rejeter le brouillon. | RG1, lots L7, L10, L11 | Suite critique ; pièges normatifs de l'évaluation, tolérance nulle |
| Identifier versions et dates d'application | Une version de document ne se publie pas sans dates ni licence. Chaque citation affiche édition et dates. Une édition remplacée est signalée. | KN1, RG2, RG3, lots L9 à L11 | Unitaires des règles ; intégration sur le contenu d'une citation |
| Signaler les informations manquantes | « Information manquante » est une nature d'énoncé. Chaque champ du contexte d'installation peut valoir « inconnu ». Un calculateur refuse une entrée absente au lieu de la supposer. | CA4, lots L7, L13, L16 | Unitaires ; cas d'évaluation à données incomplètes |
| Ne jamais présenter un diagnostic incertain comme certain | Le niveau de confiance est calculé par le domaine à partir des observations, pas choisi par le modèle. Chaque niveau a son gabarit de formulation. | DG6, lot L18 | Suite critique : table de décision |
| Ne pas déduire l'absence de tension d'une image | Le modèle ne contient aucun état « hors tension ». Une image ne produit qu'un indice visuel, jamais une observation. La procédure de consignation est toujours affichée. | DG8, § 6 | Unitaires ; détection de formulations interdites |
| Ne pas demander de manipulation dangereuse | Catalogue fermé de vérifications relues, chacune avec son niveau de risque. Politique selon la qualification. Liste de consignes interdites, détectées dans tout texte produit. Messages de sécurité fixes. Suspension sur signal de danger. | SF1 à SF5, DG4, DG7, lots L15 à L17 | Suite critique : matrice exhaustive ; demandes dangereuses de l'évaluation, tolérance nulle |
| Tester à part les calculs et les règles critiques | Suite `Critical` : cas de référence de l'expert dans des fichiers indépendants du code, tests de mutation, aucun modèle de langage, bloquante en intégration continue. | Lots L2, L13, L15, L18 | — |
| Ne jamais inventer caractéristique, prix, disponibilité ou compatibilité | Les valeurs affichées viennent du catalogue, avec source et date ; le modèle n'en écrit aucune. Un prix est un relevé daté. La compatibilité est une règle déterministe qui sait répondre « indéterminé ». | CT1, CT3, PR2, PR3, lots L21 à L26 | Suite critique ; cas « produit inconnu » de l'évaluation, tolérance nulle |
| Distinguer caractéristiques, avis et recommandations | Trois natures distinctes. Un avis rapporté nomme sa source et ne peut pas être enregistré comme caractéristique. | CV4, CT2 | Unitaires |
| Rester neutre entre les marques | Le classement est un calcul reproductible à partir du besoin et des critères. La marque n'y entre que sur préférence exprimée. L'étendue de la comparaison et tout lien commercial sont affichés. | PR4, PR7, PR8, lot L25 | Test d'échange des marques ; famille « neutralité » de l'évaluation |

## 3. Niveaux de risque et qualification

Proposition à faire valider par l'expert et, pour le lien avec l'habilitation, par un préventeur.

| Niveau | Nature du geste | Exemples | Particulier | Professionnel sans habilitation déclarée, apprenti | Professionnel habilité |
|---|---|---|---|---|---|
| N0 | Question, constat sans contact ni démontage | Décrire ce qu'on voit, lire un marquage, relever un voyant | Oui | Oui | Oui |
| N1 | Manœuvre d'un appareillage prévu pour l'usager | Relever une manette, appuyer sur le bouton de test, débrancher un appareil | Oui, avec limites | Oui | Oui |
| N2 | Intervention hors tension, après consignation | Contrôle de serrage, continuité, mesure d'isolement | Non : orientation | Non | Selon l'habilitation déclarée |
| N3 | Mesure ou essai en présence de tension | Mesure de tension, vérification d'absence de tension | Non | Non | Selon l'habilitation déclarée |
| N4 | Jamais guidé | Travail sous tension, pontage d'une protection, augmentation d'un calibre pour « faire tenir » | Non | Non | Non |

Règles qui s'ajoutent à la table :

- Une qualification non renseignée vaut « particulier ». Un compte invité aussi (D17).
- La limite gratuite ne bloque jamais un message de sécurité : un signal de danger repéré par les règles fixes reçoit le message prévu, même allocation épuisée.
- L'habilitation est déclarée, pas vérifiée. Avant un geste N2 ou N3, l'application la rappelle et affiche les prérequis de la procédure.
- Hector ne dit jamais qu'une installation est hors tension. Il affiche la procédure et enregistre ce que l'utilisateur déclare avoir vérifié.

Cadre : le Code du travail réserve les opérations sur les installations électriques ou à leur voisinage aux travailleurs habilités, l'habilitation étant délivrée par l'employeur selon la norme NF C 18-510 (articles R. 4544-9 à R. 4544-11, d'après l'[INRS](https://www.inrs.fr/risques/electriques/habilitation-electrique-foire-aux-questions.html)). Ce cadre vise les travailleurs. Un particulier n'en relève pas, mais il court le même risque sans la formation : la politique est donc plus stricte pour lui.

### Particuliers

Le particulier est l'utilisateur le plus exposé : il ne connaît ni les risques, ni le vocabulaire, ni son installation.

- **Le danger d'abord.** Avant toute question de diagnostic, vérifier l'absence de signe de danger immédiat. Au moindre signe, afficher le message de sécurité prévu et ne rien proposer d'autre.
- **Ni outil, ni démontage, ni mesure.** Aucun capot ouvert, aucune vis desserrée, aucun appareil de mesure.
- **Des manœuvres comptées.** Une manette se relève une fois. Si elle retombe, le guidage s'arrête.
- **Faire décrire, ne pas supposer.** Demander ce que la personne voit, avec des mots courants. Ne jamais tenir un terme technique pour compris.
- **Une issue claire.** Le diagnostic se termine par un geste sûr qui règle le problème, ou par une orientation vers un électricien avec un résumé à lui transmettre (DG11). « Appelez un professionnel » est une conclusion normale, pas un échec.

## 4. Le contrôle d'une réponse

```
Message de l'utilisateur
  1. Signaux de danger repérés dans le message      règles fixes, puis lecture du modèle
        → si oui : message de sécurité fixe en tête, session suspendue
  2. Assemblage : seuls les outils permis pour cette qualification sont offerts
  3. Le modèle produit un brouillon structuré, en s'appuyant sur les outils
  4. Contrôle déterministe du brouillon
        citations présentes parmi les passages lus
        références normatives présentes au registre
        gestes proposés issus du catalogue et permis
        aucune consigne interdite, aucune formulation « hors tension »
        nombres issus d'un calcul, valeurs produits issues du catalogue
  5. Échec : une reprise avec le motif ; nouvel échec : réponse dégradée et explicite
  6. Composition par le serveur des blocs chiffrés, des sources et des messages de sécurité
  7. Enregistrement du message et de la trace
```

Un brouillon rejeté n'est jamais montré à l'utilisateur.

## 5. Ce que ces mécanismes ne garantissent pas

- **Qu'une source dit bien ce que l'énoncé affirme.** Le contrôle prouve que le passage existe et a été lu, pas qu'il soutient l'énoncé. On y répond par l'affichage de l'extrait et par la mesure de ce taux dans l'évaluation.
- **Qu'une explication générale est exacte.** Un énoncé « explication générale » vient du modèle seul. Il est affiché comme non sourcé et ne peut contenir aucune disposition précise, mais il peut être faux.
- **Que l'utilisateur a bien mesuré ou bien décrit.** Une observation est une déclaration. Une conclusion fondée sur une mesure fausse sera fausse.
- **Que la qualification déclarée est vraie.**
- **Que la détection des consignes interdites voit tout.** Elle repose sur des formulations connues. Le catalogue fermé est la vraie barrière ; la détection est un filet.
- **Qu'une extraction de document est fidèle.** D'où la relecture humaine avant publication.
- **Qu'un catalogue est complet.** Une recommandation ne compare que ce qui s'y trouve. C'est pourquoi elle affiche son étendue.

## 6. Voix, photo, vidéo : règles posées dès maintenant

Ces fonctions ne font pas partie du MVP, mais leurs règles orientent le modèle dès aujourd'hui :

- Une image produit des indices visuels. Elle ne produit ni observation, ni mesure, ni constat d'absence de tension.
- Aucune conclusion sur l'état électrique d'un élément ne s'appuie sur une image seule.
- Une image peut contenir des personnes, une adresse, une plaque : elle est traitée comme une donnée personnelle.
- Une transcription de la voix est relue par l'utilisateur avant d'être enregistrée comme mesure : un chiffre mal entendu fausse un diagnostic.

## 7. Sécurité de l'application et des données

**Accès.** L'authentification existe (JWT, jeton de rafraîchissement à usage unique, limitation des tentatives). Chaque nouvelle ressource est liée à son propriétaire et testée sur ce point : conversation, session de diagnostic, exécution de calcul, session de recommandation. Un compte invité (D17) passe par les mêmes jetons et les mêmes contrôles qu'un compte complet ; sa création est limitée par adresse IP, et les invités inactifs sont supprimés.

**Consignes cachées.** Un message d'utilisateur ou un document ingéré peut contenir du texte rédigé pour détourner le modèle. Parades : les passages sont transmis comme des données, jamais comme des consignes ; les outils offerts au modèle ne peuvent que lire, calculer ou proposer une transition que le domaine valide ; aucun outil ne supprime, n'envoie ni ne publie quoi que ce soit ; un jeu d'attaque fait partie de l'évaluation (lot L19).

**Fournisseurs.** Le fournisseur du modèle de langage et celui des vecteurs sont des sous-traitants. Ils ne reçoivent aucune donnée d'identité. Leurs conditions de conservation et le lieu de traitement font partie du choix (D3, D5). Une source dont la licence interdit la transmission à un tiers n'est jamais envoyée (KN5).

**Secrets et abus.** Clés d'API hors du dépôt. Quota par utilisateur et budget par tour, pour contenir les coûts et les abus. Journaux sans contenu de conversation en clair au-delà de ce qu'exige la trace.

**Données personnelles.** Données traitées : compte, entreprise, conversations, descriptions d'installations chez des tiers, plus tard des images. Durée de conservation limitée et annoncée (P7). La suppression du compte efface conversations, sessions et traces nominatives. Réutilisation pour l'évaluation uniquement avec accord explicite. Un retour du terrain n'est partagé qu'avec l'accord de son auteur, donné pour ce retour ; seule sa fiche anonymisée et relue est publiée (DOMAIN.md, EX1 à EX3).

## 8. Cadre réglementaire

À faire confirmer par un juriste avant l'ouverture.

- **Droits sur les normes.** Les normes AFNOR sont protégées. L'AFNOR soumet toute extraction à son accord écrit et s'oppose à la fouille de textes et à l'entraînement d'algorithmes sans accord exprès ([mentions légales](https://source.afnor.org/vars/Mention_legales_FR.pdf), [conditions générales](https://www.afnor.org/wp-content/uploads/2022/02/conditions_generales_vente_adhesion.pdf)). Les normes d'application obligatoire sont consultables gratuitement sur son site ([FFB](https://www.ffbatiment.fr/techniques-batiment/normalisation-regles-de-lart/nf-dtu-normes/dossier/comment-se-procurer-une-norme-du-batiment)), ce qui n'autorise pas à les indexer. C'est la raison d'être de `License` dans le modèle et de la question P3.
- **Textes publics.** Les données de Légifrance sont réutilisables sous licence ouverte, aux conditions de la plateforme ([data.gouv.fr](https://www.data.gouv.fr/dataservices/legifrance/)). À vérifier jeu de données par jeu de données.
- **Transparence sur l'IA.** L'obligation d'informer une personne qu'elle échange avec une IA (règlement européen sur l'IA, article 50) est applicable depuis le 2 août 2026 ([Goodwin](https://www.goodwinlaw.com/en/insights/publications/2026/08/alerts-technology-dpc-eu-ai-act-transparency-obligations-now-in-force)). La mention est prévue dès le lot L6. Le classement d'Hector au regard des systèmes à haut risque est à faire confirmer.
- **Consommateurs.** Avec des particuliers, le droit de la consommation s'applique : information préalable, conditions d'utilisation, limites du service dites clairement. Sans compte aussi : les conditions sont acceptées avant le premier message, et la version acceptée est enregistrée.
- **Comparaison de produits.** Un service qui classe ou compare des produits a des obligations de loyauté et de transparence sur ses critères de classement et sur ses liens commerciaux (Code de la consommation, article L. 111-7 notamment, à faire vérifier). Les règles PR4, PR7 et PR8 vont dans ce sens.
- **Données produits.** Catalogues de fabricants et données de distributeurs sont protégés. Aucune collecte automatique sur un site sans accord ; chaque source produit a ses droits renseignés (CT5).
- **Responsabilité.** Un avertissement ne suffit pas à dégager la responsabilité de l'éditeur si un conseil cause un dommage. Les mécanismes de ce document, la trace de chaque tour et une assurance adaptée en sont les vraies réponses.

## 9. Registre des risques

Gravité et probabilité sont des estimations de départ, à réviser à chaque phase.

### Techniques

| # | Risque | Gravité | Probabilité | Parade | Lot |
|---|---|---|---|---|---|
| T1 | Une citation valide accompagne un énoncé que la source ne soutient pas | Forte | Moyenne | Affichage de l'extrait, mesure dans l'évaluation, signalement par l'utilisateur | L8, L10 |
| T2 | L'extraction déforme un tableau ou un numéro de section | Forte | Forte | Choix de la méthode sur essai, relecture avant publication | L9 |
| T3 | Un changement de modèle ou de consignes dégrade le comportement | Forte | Forte | Version du modèle fixée, consignes versionnées, évaluation avant tout changement | L8 |
| T4 | Durée et coût d'un tour trop élevés | Moyenne | Moyenne | Budget par tour, mise en cache des consignes, mesure dès le lot L5 | L5, L19 |
| T5 | Les tours simultanés saturent l'API | Moyenne | Forte dès l'ouverture | Contrat asynchrone dès le départ, tâche de fond au lot L19 | L4, L19 |
| T6 | Une transaction reste ouverte pendant un appel au modèle | Moyenne | Forte si on n'y prend pas garde | Tour exécuté hors transaction, critère d'acceptation dédié | L5 |
| T7 | Des consignes cachées dans un document ou un message détournent l'assistant | Forte | Moyenne | Outils sans effet destructeur, passages traités comme des données, jeu d'attaque | L19 |
| T8 | Une ancienne version de l'application ne sait pas afficher un nouveau bloc | Faible | Forte | Texte de repli obligatoire sur chaque bloc | L6, L7 |
| T9 | Le réseau coupe pendant un tour | Faible | Forte sur chantier | Le tour se termine côté serveur, le client relit la conversation | L5, L6 |
| T10 | Les colonnes hors ORM font échouer le contrôle du schéma | Faible | Forte | Type personnalisé ou exclusion déclarée | L10, L12 |
| T11 | L'outillage IA de l'écosystème PHP manque de maturité | Moyenne | Moyenne | Port à nous, adaptateurs minces, repli hors ligne pour l'ingestion | L5, L9 |
| T12 | Des comptes invités ouverts en masse contournent la limite gratuite et font monter les coûts | Moyenne | Forte dès l'ouverture | Limite de création par IP, budget global des invités, attestation d'appareil à étudier | L3, L19 |
| T13 | Un retour du terrain faux, dangereux ou piégé est présenté à d'autres utilisateurs (erreur de bonne foi, malveillance, consignes cachées) | Très forte | Moyenne | Comptes complets seulement, contrôles automatiques puis relecture humaine obligatoire, gestes filtrés par la politique de prévention, présenté comme hypothèse, retrait immédiat, remise en relecture des cas infirmés | L18, L27, L28 |

### Réglementaires

| # | Risque | Gravité | Probabilité | Parade | Lot |
|---|---|---|---|---|---|
| R1 | Indexer des normes sans droit | Forte | Forte si rien n'est fait | Licence par source, corpus de départ sans contenu de norme, démarche auprès de l'AFNOR | L0, L9 |
| R2 | Un conseil cause un accident ou un dégât | Très forte | Faible | Tous les mécanismes des § 2 à 4, trace de chaque tour, conditions d'utilisation, assurance | Tous |
| R3 | Un geste réservé est guidé auprès d'une personne non habilitée, ou d'un particulier | Très forte | Moyenne | Politique par qualification, défaut restrictif, catalogue fermé | L15, L17 |
| R4 | Des données personnelles partent chez un fournisseur sans cadre | Moyenne | Moyenne | Aucune identité transmise, conditions du fournisseur vérifiées, durée de conservation | L5, L19 |
| R5 | Les obligations du règlement européen sur l'IA sont mal évaluées | Moyenne | Moyenne | Mention IA dès le lot L6, avis d'un juriste sur le classement | L6, L19 |
| R6 | Une réponse s'appuie sur une édition qui ne s'applique pas au projet | Forte | Forte | Registre daté, question sur la date de référence, signalement des éditions remplacées | L11 |
| R7 | Des données produits sont utilisées sans droit | Moyenne | Moyenne | Droits renseignés par source, pas de collecte automatique sans accord | L21 |
| R8 | Le conseil en produits manque de transparence ou de neutralité | Moyenne | Moyenne | Classement reproductible, étendue et liens commerciaux affichés, test d'échange des marques | L25 |
| R9 | Un retour du terrain publié permet d'identifier son auteur ou un tiers, ou est réutilisé sans base légale | Moyenne | Moyenne | Accord explicite par retour, texte d'origine jamais publié, anonymisation relue, aucun lien entre cas publié et auteur, seuil minimal avant toute fréquence, avis d'un juriste (P12) | L18, L27 |

### Fonctionnels

| # | Risque | Gravité | Probabilité | Parade | Lot |
|---|---|---|---|---|---|
| F1 | L'utilisateur fait trop confiance à l'assistant | Forte | Forte | Natures d'énoncés visibles, niveau de confiance affiché, incertitudes listées | L7, L18 |
| F2 | L'assistant refuse trop et devient inutile | Moyenne | Forte | Mesure des refus dans l'évaluation, catalogue enrichi au fil des retours | L8, L20 |
| F3 | Le catalogue de vérifications couvre trop peu de cas | Moyenne | Forte au départ | Périmètre de pannes restreint et annoncé, familles ouvertes une à une | L15 |
| F4 | Tout dépend d'un seul expert | Forte | Forte | Expert nommé dès le lot L0, relectures tracées, second relecteur à prévoir | L0 |
| F5 | L'utilisateur décrit ou mesure mal | Forte | Moyenne | Mesures structurées, reformulation, conclusion qui cite ses appuis | L16, L18 |
| F6 | Un particulier va au-delà de ce qui lui est proposé | Très forte | Moyenne | Guidage qui s'arrête tôt, orientation claire, messages de sécurité fixes, aucune information de procédure N2 ou N3 dans ses réponses | L15, L17 |
| F7 | La saisie au clavier freine l'usage sur chantier | Moyenne | Forte | Questions à choix, voix en première suite du MVP | L17 |
| F8 | Le périmètre s'élargit avant que le cœur soit solide | Forte | Forte | Conseil en équipements après le MVP, un domaine à la fois | Phase 6 |
| F9 | Le coût par utilisateur dépasse ce que le modèle économique supporte | Moyenne | Moyenne | Mesure dès le lot L5, quota, choix du modèle sur mesure | L5, L8, L19 |
| F10 | Des données produits périmées ou partielles biaisent une recommandation | Moyenne | Forte | Date par valeur, délai de fraîcheur, étendue de la comparaison affichée | L21, L25, L26 |
| F11 | La limite gratuite tombe au milieu d'une panne | Moyenne | Moyenne | Messages de sécurité jamais bloqués ; après l'inscription, retour dans la conversation, message en attente compris | L5, L6 |
| F12 | Une réponse sort dans une langue que le contrôle des réponses ou les messages de sécurité ne couvrent pas | Forte | Moyenne | Liste fermée de langues ouvertes, Hector répond dans la langue de l'application et non dans celle du message, cas critiques et cas d'évaluation par langue | D19, L15 |

## 10. Incidents

Un incident est une réponse dangereuse, fausse sur un point normatif, ou trompeuse sur un produit.

1. **Signaler.** Depuis l'application, sur le message concerné. Le signalement arrive à un responsable désigné, avec la trace du tour.
2. **Contenir.** Désactiver sans délai le contenu en cause par une commande : version de document, procédure, calculateur, donnée produit. Si la cause est dans les consignes ou le modèle, revenir à la version précédente.
3. **Comprendre.** La trace donne les passages lus, les outils appelés, les contrôles passés et les versions utilisées.
4. **Empêcher le retour.** Ajouter le cas au jeu d'évaluation et, si un contrôle déterministe aurait pu l'arrêter, à la suite critique.
5. **Informer.** Prévenir l'utilisateur concerné si la réponse a pu l'exposer à un risque.
