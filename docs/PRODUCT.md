# Hector AI — Produit

> **Statut : proposition à valider** (15 novembre 2026). Rien de ce qui est décrit ici n'est développé.
> À lire avec : [ARCHITECTURE.md](ARCHITECTURE.md) · [DOMAIN.md](DOMAIN.md) · [ROADMAP.md](ROADMAP.md) · [SECURITY.md](SECURITY.md)

## 1. Vision

Hector est un copilote technique pour les métiers de l'électricité. Sur un chantier ou à l'atelier, l'utilisateur pose une question, demande un calcul ou décrit une panne. Hector répond en citant ses sources, calcule sans approximation et guide un diagnostic pas à pas, en séparant ce qui est établi, ce qui est supposé et ce qui manque.

Hector s'adresse aux professionnels et aussi aux particuliers. Une personne qui a une panne chez elle peut être guidée, dans la limite des gestes qu'un non-professionnel peut faire sans risque, puis orientée vers un électricien avec un résumé de ce qui a été constaté.

Hector conseille aussi dans le choix d'équipements électriques et électroménagers. Il ne s'en tient pas à l'électricité et à la réglementation : il aide à choisir un produit adapté à un besoin concret, compare des marques et des modèles, vérifie leur compatibilité avec l'installation et argumente ses recommandations. Par exemple :

- conseiller des plaques de cuisson adaptées à une cuisine et à l'installation existante ;
- comparer des ventilateurs de plafond selon leur efficacité, leur niveau sonore, leur diamètre, leur éclairage et leurs fonctions ;
- comparer des marques et des gammes ;
- conseiller des prises, interrupteurs, disjoncteurs, tableaux et appareillages ;
- vérifier qu'un équipement convient à une installation monophasée ou triphasée ;
- signaler les caractéristiques à regarder avant un achat ;
- proposer plusieurs alternatives selon le budget, la qualité et le besoin du client.

Le dépannage est le premier métier traité, pas le seul. Le produit repose sur un socle conversationnel auquel se branchent des domaines fonctionnels :

| Domaine | Dans le MVP | Ensuite |
|---|---|---|
| Questions techniques générales | Oui | Approfondi par domaine |
| Recherche documentaire sourcée | Oui, sur un corpus restreint | Corpus élargi, documentation fabricants |
| Calculs | Quelques calculs de base | Dimensionnement complet |
| Dépannage | Oui, périmètre restreint (§ 4.2) | Autres types d'installations |
| Réglementation et conformité | Références datées, pas d'avis de conformité | Aide à la vérification de conformité |
| Installation et rénovation | Non | Oui |
| Conseil et recommandation de produits (équipements électriques et électroménagers) | Non | Oui, phase dédiée (ROADMAP, phase 6) |
| Comparaison de marques et de modèles | Non | Oui |
| Analyse des caractéristiques, compatibilités et performances | Non | Oui |
| Aide au choix selon le budget et les besoins du client | Non | Oui |
| Lecture de schémas, conception | Non | Oui (demande l'analyse d'images) |
| IRVE, domotique, moteurs | Non | Oui, un domaine à la fois |
| Voix, photo, vidéo | Non | Voix d'abord, puis photo |

## 2. Utilisateurs

| Profil | Ce qu'il attend | Conséquence pour le produit |
|---|---|---|
| Électricien, artisan ou salarié | Une réponse rapide et vérifiable, un second avis sur une panne et, après le MVP, une sélection de produits à présenter à son client | Réponses courtes, sources consultables, accès aux vérifications selon son habilitation |
| Apprenti | Comprendre avant d'agir | Explications plus détaillées, vérifications limitées à ce qu'il a le droit de faire |
| Autre professionnel du bâtiment | Savoir quoi faire et quand appeler un électricien | Orientation, pas de guidage sur l'installation |
| Particulier | Être guidé lors d'une panne chez lui, comprendre son installation, choisir un équipement | Cible à part entière (ce type de compte existe déjà dans le dépôt). Guidage limité aux gestes sans risque, vocabulaire courant, orientation vers un électricien dès qu'un geste réservé serait nécessaire, avec un résumé à lui transmettre. Limites exactes : question P1 |

Conditions d'usage à garder en tête pour les professionnels : mains occupées ou gantées, bruit, écran en plein soleil, réseau médiocre. D'où des messages courts, une étape à la fois, de grandes zones tactiles et une reprise sans perte après une coupure de réseau. La saisie au clavier est le point faible du MVP sur un chantier ; c'est la raison pour laquelle la voix vient en premier après lui.

**Essayer avant de s'inscrire.** Comme les assistants grand public, Hector s'utilise dès l'ouverture de l'application, sans compte. Une fois l'essai gratuit épuisé, il faut créer un compte ou se connecter, sans perdre les conversations commencées ; plus tard, un abonnement prendra le relais (P6, P10). Sans compte, l'utilisateur est traité comme un particulier : un électricien s'inscrit et déclare sa qualification pour accéder aux vérifications qui lui sont réservées.

Le particulier, lui, ne connaît ni le vocabulaire ni son installation. Il faut lui faire décrire ce qu'il voit, pas ce qu'il sait, et ne jamais supposer qu'il a compris un terme technique.

## 3. Principes produit

1. **Sourcer ou le dire.** Une affirmation réglementaire ou technique précise s'appuie sur un passage de la base documentaire, avec son édition et sa date. Sinon, la réponse dit qu'elle n'est pas sourcée.
2. **Séparer l'établi du supposé.** Chaque élément d'une réponse porte sa nature : fait sourcé, fait rapporté par l'utilisateur, résultat de calcul, explication générale, hypothèse, recommandation, information manquante.
3. **La sécurité des personnes passe avant l'utilité de la réponse.** Hector ne propose que des gestes adaptés à la qualification déclarée, et préfère dire « faites intervenir une personne habilitée » que guider un geste risqué.
4. **Les nombres viennent d'un calcul, pas du modèle.** Les résultats chiffrés sont produits par du code testé, avec leurs hypothèses.
5. **Hector assiste, il ne remplace pas.** Il ne certifie rien, ne remplace ni une mesure, ni un contrôle, ni l'intervention d'un électricien, et n'affirme jamais qu'une installation est hors tension.
6. **Tout se retrace.** On doit pouvoir expliquer pourquoi une réponse a été donnée : sources lues, calculs faits, version des consignes et du modèle.
7. **Conseiller sans parti pris.** Une recommandation de produit part du besoin, des contraintes de l'installation, du budget et des préférences exprimées. Aucune marque n'est favorisée par défaut, et tout lien commercial est affiché.
8. **Ne rien inventer sur un produit.** Caractéristiques, prix, disponibilités et compatibilités viennent d'une source identifiée et datée, ou sont déclarés inconnus. Une caractéristique vérifiable, un avis rapporté et une recommandation de l'assistant ne se présentent pas de la même façon.

Ces principes sont traduits en mécanismes vérifiables dans [SECURITY.md](SECURITY.md).

## 4. Périmètre du MVP

### 4.1 Ce que le MVP fait

| # | Objectif | Ce que cela veut dire précisément | Limite assumée | Lots |
|---|---|---|---|---|
| 1 | Dialoguer avec un assistant spécialisé | Conversations en texte, en français, avec historique conservé | Pas de voix, pas d'image | L4 à L6 |
| 2 | Poser des questions techniques libres | Réponse structurée par nature d'énoncé ; une réponse sans source est signalée comme telle | Hors de la base documentaire, l'assistant explique mais ne cite aucune disposition précise | L5, L7 |
| 3 | Consulter une base documentaire | Recherche dans un corpus sélectionné, géré en versions par ligne de commande | Corpus restreint (question P3) ; pas d'interface d'administration | L9, L10, L12 |
| 4 | Obtenir des sources précises | Chaque citation indique titre, éditeur, édition, dates, localisation dans le document | L'extrait affiché dépend de la licence du document | L10, L11 |
| 5 | Décrire un problème électrique | Description libre, reformulée en symptômes et contexte d'installation | Texte uniquement | L17 |
| 6 | Être guidé par des questions adaptatives | Une question à la fois, avec sa raison ; « je ne sais pas » est une réponse acceptée | — | L17 |
| 7 | Construire un diagnostic | Hypothèses, vérifications et mesures suivies dans un état structuré et consultable | Vérifications tirées d'un catalogue fermé et relu | L15 à L17 |
| 8 | Obtenir une conclusion argumentée | Cause retenue, observations à l'appui, hypothèses non écartées, niveau de confiance calculé, suite recommandée | La conclusion peut être « indéterminé » | L18 |

S'y ajoutent quelques calculs de base (loi d'Ohm, puissance, courant d'emploi, chute de tension), utilisables seuls ou depuis la conversation (L13, L14).

L'application s'utilise sans compte jusqu'à une limite gratuite, puis demande une inscription (L3, L5, L6). Le paiement ne fait pas partie du MVP.

### 4.2 Périmètre technique du diagnostic (proposition, question P2)

Installations basse tension de logements et de petits locaux professionnels, alimentées par le réseau public. Familles de pannes visées au départ : déclenchement d'un dispositif différentiel, déclenchement d'un disjoncteur, absence de tension sur un circuit ou un point d'utilisation. Chaque famille n'est ouverte que lorsque ses vérifications sont au catalogue et relues.

Le diagnostic est ouvert aux professionnels et aux particuliers, avec des limites différentes. Pour un particulier, le guidage s'arrête aux questions, aux constats sans démontage et aux manœuvres prévues pour l'usager (relever une manette une fois, débrancher un appareil, couper un départ). Dès qu'il faudrait ouvrir un appareillage, mesurer ou intervenir, le diagnostic se conclut par une orientation vers un électricien, avec un résumé à lui transmettre : symptôme, constats, hypothèses restantes.

### 4.3 Ce que le MVP ne fait pas

- Voix, photo, vidéo, lecture de schémas.
- Avis de conformité d'une installation, attestation, document à valeur de contrôle.
- Dimensionnement fondé sur des tableaux normatifs (courants admissibles, choix des protections), tant que la question des droits sur les normes n'est pas réglée (P3).
- Conception d'installation, devis.
- Conseil en équipements, comparaison de produits, catalogue : prévus dans l'architecture dès maintenant, développés après le MVP (§ 8).
- Haute tension, installations industrielles, photovoltaïque, IRVE, domotique, moteurs.
- Fonctionnement hors ligne, interface web, partage au sein d'une entreprise.
- Paiement et facturation : le contrat d'API leur réserve leur place dès l'essai sans compte (ARCHITECTURE.md, D17), ils sont développés ensuite.
- Interface d'administration : le corpus et les contenus métier sont gérés par des fichiers versionnés et des commandes.

## 5. Quatre parcours de référence

Ces parcours servent à valider le périmètre. Les détails techniques sont des illustrations à faire relire par l'expert métier.

**Question technique.** L'utilisateur demande à quoi sert la liaison équipotentielle dans une salle d'eau. Hector cherche dans la base, répond en trois énoncés : une explication générale, un fait sourcé avec sa citation (titre, édition, localisation), et une information manquante (« la date de votre chantier détermine l'édition applicable : est-ce du neuf, de la rénovation, de l'existant ? »).

**Calcul.** L'utilisateur demande la chute de tension sur une ligne. Il manque la longueur : Hector la demande au lieu de la supposer. Le résultat s'affiche dans un bloc de calcul avec les entrées, les hypothèses, les étapes et la version du calculateur.

**Dépannage par un professionnel.** L'utilisateur, électricien, écrit qu'un différentiel 30 mA déclenche de façon aléatoire chez un client.

1. Hector reformule le symptôme et pose une première question, en expliquant pourquoi : le déclenchement survient-il à un moment particulier (mise en route d'un appareil, temps humide) ?
2. Il affiche les hypothèses ouvertes, par exemple défaut d'isolement d'un appareil, défaut d'isolement d'un circuit, cumul de courants de fuite, dispositif défectueux.
3. Il propose une vérification du catalogue adaptée au profil, par exemple isoler le circuit en cause en coupant les départs en aval puis en les réenclenchant un à un. L'utilisateur rapporte le résultat, qui devient une observation.
4. Selon le résultat, une hypothèse est renforcée, une autre écartée, avec la justification affichée.
5. Si une mesure hors tension est nécessaire, elle n'est proposée qu'à un utilisateur dont la qualification déclarée le permet, avec ses prérequis de sécurité.
6. Conclusion : « Cause probable : défaut d'isolement sur le circuit X. Fondé sur : observations 2 et 4. Non écarté : appareil raccordé sur ce circuit. Pour confirmer : mesure d'isolement du circuit, hors tension. »

**Panne chez un particulier.** Une personne écrit qu'elle n'a plus de courant dans une partie de son logement.

1. Hector vérifie d'abord qu'il n'y a pas de danger immédiat : odeur de brûlé, traces noires, eau, picotements. Au moindre signe, il affiche le message de sécurité prévu et s'arrête là.
2. Il lui fait décrire son tableau avec des mots simples : y a-t-il une manette abaissée, laquelle, qu'est-il écrit dessus ?
3. Il propose un geste permis à un particulier : débrancher les appareils de la zone concernée, puis relever la manette une seule fois.
4. Si le courant tient, il fait rebrancher les appareils un par un pour repérer celui qui provoque la coupure. Si la manette retombe aussitôt, il ne propose pas d'aller plus loin.
5. Conclusion, selon le cas : « L'appareil X provoque la coupure : ne le rebranchez pas, faites-le contrôler », ou « La cause est dans l'installation : faites intervenir un électricien », avec un résumé à lui transmettre.

## 6. Mesures de succès

Sur le jeu d'évaluation (voir ROADMAP, lot L8), avant toute ouverture :

- aucune référence normative inventée ;
- aucune consigne dangereuse sur le jeu de cas « sécurité » ;
- part des questions couvertes par le corpus qui reçoivent au moins une source pertinente : seuil fixé après la première mesure de référence, et non au hasard aujourd'hui.

Pendant la bêta :

- part des diagnostics menés jusqu'à une conclusion ;
- pour les particuliers, part des diagnostics qui se terminent par une résolution sans risque ou par une orientation claire vers un électricien ;
- part des conclusions confirmées sur le terrain (retour de l'utilisateur) ;
- part des utilisateurs sans compte qui s'inscrivent une fois l'essai épuisé ;
- retour des utilisateurs quatre semaines après leur premier usage ;
- délai de réponse médian et coût moyen par conversation.

## 7. Questions produit à trancher

| # | Question | Options | Recommandation |
|---|---|---|---|
| P1 | Jusqu'où guide-t-on un particulier lors d'une panne ? Les particuliers sont une cible retenue (indication du 15 novembre 2026). | (a) Questions et constats seulement. (b) Questions, constats et manœuvres prévues pour l'usager, puis orientation vers un électricien avec un résumé. (c) Les mêmes vérifications qu'un professionnel. | (b). L'option (c) est exclue par les règles de sécurité. La liste exacte des gestes permis est à valider avec l'expert (SECURITY.md, § 3). |
| P2 | Quel périmètre technique pour le diagnostic du MVP ? | Voir § 4.2 | Le périmètre du § 4.2, à ajuster avec l'expert. |
| P3 | Quel corpus de départ, avec quels droits ? | (a) Fiches rédigées pour Hector + textes publics réutilisables + renvois aux normes sans en reproduire le contenu. (b) Licence négociée avec l'AFNOR pour indexer les normes. (c) Documentation de fabricants avec leur accord. | (a) pour démarrer, (b) et (c) instruites en parallèle. Les normes AFNOR sont protégées ; les indexer sans accord est un risque juridique majeur (SECURITY.md, risque R1). |
| P4 | Qui est l'expert métier ? | Un électricien qualifié qui relit le catalogue de vérifications, les fiches, les cas de calcul de référence et le jeu d'évaluation. | À nommer avant le lot L8. Sans lui, les lots L8, L11, L13 et L15 ne peuvent pas être validés. |
| P5 | Que demande-t-on sur la qualification de l'utilisateur ? | (a) Type de compte seul. (b) Type + habilitations déclarées. (c) Justificatif. | (b). Un justificatif est lourd et ne prouve pas davantage la compétence sur le moment. La déclaration est rappelée avant les gestes sensibles. |
| P6 | Quel modèle économique ? Indication du 16 novembre 2026 : essai sans compte, puis inscription, puis paiement. | Abonnement individuel, abonnement par entreprise, paiement à l'usage ; mêmes conditions ou non pour professionnels et particuliers. | Prix et formule à décider avant l'ouverture publique, sur le coût mesuré d'un tour (lot L5) ; l'ordre de grandeur des coûts figure dans ARCHITECTURE.md (D3). |
| P7 | Combien de temps conserve-t-on les conversations, et servent-elles à améliorer le produit ? | Durée fixe, suppression à la demande, usage des conversations pour l'évaluation avec ou sans consentement. | Conservation limitée et annoncée, réutilisation pour l'évaluation uniquement avec accord explicite. |
| P8 | Conseil en équipements : par quelles catégories et quelles sources commencer ? | Catégories : appareillage (prises, interrupteurs, disjoncteurs, tableaux), électroménager (plaques de cuisson…), confort (ventilateurs de plafond…). Sources : données fournies par les fabricants, bases publiques, données de distributeurs. | Commencer par une ou deux catégories dont les données sont obtenues avec un droit d'usage clair, plutôt que par un catalogue large et mal sourcé. |
| P9 | Hector a-t-il des liens commerciaux avec des marques ou des distributeurs (affiliation, partenariat, mise en avant) ? | (a) Aucun. (b) Des liens affichés, sans effet sur le classement. (c) Une mise en avant payante. | (a) ou (b). L'option (c) contredit le principe de neutralité. Dans tous les cas, tout lien est affiché à l'utilisateur. |
| P10 | Quelle limite gratuite, pour un invité et pour un compte gratuit ? | Unité : messages, conversations ou coût. Période : au total ou par jour, semaine, mois. | Un nombre de messages, simple à comprendre : au total pour un invité, par période pour un compte gratuit. Valeurs fixées après la mesure du coût d'un tour au lot L5, pas avant. |
| P11 | Un invité qui se connecte à un compte existant : que deviennent ses conversations ? | (a) Rattachées au compte. (b) Abandonnées. (c) Choix laissé à l'utilisateur. | (a), sans demander à l'utilisateur : c'est ce qu'il attend. |
| P12 | Retours du terrain (§ 8, point 7) : qui peut en proposer, et que devient une fiche publiée quand son auteur supprime son compte ? | Auteurs : comptes complets seulement, ou invités aussi. Fiche publiée : conservée car anonymisée, ou supprimée avec le compte. | Comptes complets seulement : un invité ne laisse aucun moyen de le recontacter et ouvre la porte aux abus. Une fiche publiée, détachée de son auteur et anonymisée, est conservée ; l'utilisateur en est informé au moment du partage. Un retour non encore publié est supprimé avec le compte. À faire confirmer par un juriste. |
| P13 | Qui relit les retours du terrain, et à quel rythme ? | L'expert seul ; plusieurs électriciens relecteurs ; des professionnels utilisateurs volontaires. | L'expert seul au départ, puis un second relecteur (risque F4). Le volume relu chaque semaine fixe le nombre de retours qu'on peut recueillir. |
| P14 | Carnet de chantier (§ 8, point 8) : à quelle place après le MVP ? | Avant le conseil en équipements, après, ou en même temps que la voix. | Avec la voix, en première suite du MVP : les deux répondent au même besoin du terrain (saisir vite, sans clavier), et le carnet donne aux conversations un contexte qui manque aujourd'hui. Le conseil en équipements passerait ensuite. |
| P15 | Que gagne un utilisateur dont les retours du terrain sont publiés (§ 8, point 9) ? | Messages gratuits en plus ; remise sur l'abonnement ; reconnaissance visible ; rôle de relecteur pour les professionnels vérifiés. | Messages gratuits en plus, plafonnés, puis remise une fois le paiement en place. Le rôle de relecteur, seulement sur décision humaine et qualification vérifiée. Jamais de geste supplémentaire permis. Le montant dépend du coût d'un tour (P10). |

## 8. Après le MVP

L'ordre proposé suit ce que le socle rend facile et ce que le terrain réclame :

1. **Voix** (dictée puis lecture des réponses) : lève le principal frein d'usage sur chantier.
2. **Conseil en équipements**, pour les particuliers comme pour les professionnels (ROADMAP, phase 6) :
   - catalogue de marques, de gammes et de références, d'abord sur une ou deux catégories, puis multi-fabricants et multi-distributeurs ;
   - analyse de fiches techniques et de notices de fabricants ;
   - comparateur de produits et de marques, selon des critères propres à chaque catégorie ;
   - vérification de la compatibilité avec l'installation électrique ;
   - recommandations personnalisées selon le besoin et le budget, avec des produits alternatifs ;
   - sélection de produits présentable au client.
3. **Dimensionnement complet**, une fois les droits sur les données normatives obtenus.
4. **Photo** : lecture de plaques signalétiques et de références de matériel d'abord. Une image ne sert jamais à conclure sur l'état électrique d'une installation.
5. **Nouveaux domaines**, un par un : IRVE, puis les autres selon la demande. Chaque domaine apporte ses documents, ses calculs et ses vérifications sans modifier le socle (ARCHITECTURE.md, § 6).
6. **Lecture de schémas, conception, vidéo.**
7. **Apprendre des retours du terrain** (idée du 24 novembre 2026, précisée le 25 novembre 2026 : « qu'Hector apprenne de ce que les utilisateurs lui disent sur la façon dont ils ont réussi leur dépannage, si c'est juste »). Planifié en phase 7 (ROADMAP, lots L27 à L29), la collecte commençant dès le MVP (lot L18) pour que la bêta produise des cas. Le parcours d'un retour :
   1. **Recueil.** À la fin d'un diagnostic, ou quand l'utilisateur dit avoir réglé sa panne, Hector demande ce qui l'a résolue et si l'utilisateur accepte de partager ce retour, anonymisé, pour aider d'autres utilisateurs. Sans accord explicite, rien ne sort de sa conversation.
   2. **Mise en forme.** Le retour devient une fiche : symptôme, contexte d'installation, cause trouvée, ce qui a été fait, par qui (particulier ou professionnel), résultat. Les noms, adresses et autres données personnelles en sont retirés.
   3. **Contrôles automatiques.** Une fiche qui contient une consigne interdite, une référence normative absente du registre ou un geste sans niveau de risque n'avance pas.
   4. **Relecture humaine.** L'expert accepte, corrige ou refuse chaque fiche, et classe le geste décrit par niveau de risque. C'est elle qui dit si un retour « est juste » : la confirmation de l'utilisateur ne suffit pas.
   5. **Usage.** Une fiche publiée est retrouvée par la recherche quand une panne semblable se présente. Hector la présente comme un retour du terrain, avec le nombre de cas semblables, jamais comme un fait ni comme une règle. Plus tard, les fréquences de causes confirmées servent à ordonner les hypothèses et à repérer les manques du catalogue.

   Ce qui ne change pas : un retour n'élargit jamais les gestes permis (un particulier ne reçoit pas la manipulation qu'un électricien a décrite) ; il ne remplace ni une source ni le registre ; il peut être retiré à tout moment, avec effet immédiat.

   Il ne s'agit pas de réentraîner le modèle de langage. Le fournisseur ne le propose pas pour ses modèles récents (à vérifier au moment de décider), et surtout un modèle réentraîné ne permet ni de retirer un retour faux, ni de dire d'où vient une réponse, ni d'effacer les données d'un utilisateur. Une base de cas relus, fournie au modèle comme contexte, améliore les réponses dès la publication d'une fiche et reste contrôlable (ARCHITECTURE.md, D18).
8. **Carnet de chantier** (idée du 25 novembre 2026) : réunir au même endroit tout ce qui est utile sur un chantier, et s'en servir dans la conversation. D'abord pour les professionnels ; pour un particulier, c'est le carnet de son logement.
   - **Contenu.** Notes écrites et vocales (transcrites), photos, mesures relevées, documents (devis, schémas, notices), conversations et diagnostics du chantier. Chaque élément est daté et rattaché à un chantier.
   - **Rendez-vous.** Prise de rendez-vous inscrite dans le calendrier de l'utilisateur. Proposition : passer par le calendrier du téléphone, avec l'autorisation du système, plutôt que par un accès serveur à son compte Google ou Microsoft. Hector n'a alors jamais accès à l'agenda complet, et rien n'est à synchroniser. Une synchronisation côté serveur ne viendrait que sur un besoin constaté.
   - **Dans la conversation.** Quand on parle d'un chantier, Hector reprend son contexte (installation, observations, historique), ce qui évite de tout redécrire.
   - **Règles déjà posées qui s'appliquent** (SECURITY.md, § 6) : une photo ne produit jamais une observation ni un constat d'absence de tension ; une transcription vocale est relue par l'utilisateur avant de devenir une mesure.
   - **Données personnelles.** Un carnet contient les nom, adresse et téléphone des clients de l'électricien : des tiers. Ces données restent dans le carnet et ne sont jamais transmises au fournisseur du modèle. Le rôle de chacun au sens du RGPD (l'électricien responsable du traitement de ses clients, Hector sous-traitant) est à faire confirmer par un juriste.
   - **Ordre proposé** : notes et photos d'abord (avec la voix, point 1), puis le contexte du chantier dans la conversation, puis les rendez-vous. À découper en lots une fois la question P14 tranchée.
9. **Encourager les contributions** (idée du 25 novembre 2026 : que les « meilleurs utilisateurs » aient plus de privilèges). Proposition : on récompense ce qui rend Hector meilleur pour tous, c'est-à-dire un retour du terrain publié après relecture (point 7). L'usage seul n'est pas récompensé : chaque message coûte un appel au modèle, et le but est une panne résolue, pas une conversation longue.
   - **Ce qui peut se gagner.** Des messages gratuits en plus de l'allocation (P10), pour chaque retour publié, dans une limite mensuelle. Plus tard, une remise sur l'abonnement (P6). Un bilan visible par l'utilisateur seul, par exemple « vos retours ont aidé 37 personnes » : les cas étant anonymes, la reconnaissance ne peut pas être publique. Un parrainage, qui offre des messages aux deux comptes.
   - **Ce qui ne se gagne jamais.** Les gestes permis, qui dépendent de la qualification et de rien d'autre (SF2). Le passage d'un retour sans relecture (EX5). Un poids plus grand d'un cas dans les fréquences : chaque cas compte pour un. Les messages de sécurité, gratuits pour tous.
   - **Le seul vrai privilège envisagé.** Pour un professionnel dont la qualification est vérifiée (justificatif, P5) et dont les retours sont régulièrement publiés : devenir relecteur des retours des autres, aux côtés de l'expert (P13). C'est un rôle qui engage la sécurité : il se donne et se retire par une décision humaine, jamais par un score.
   - **Parades aux abus.** Seuls les retours publiés comptent, pas les retours proposés. Plafond mensuel. Les retours refusés à répétition suspendent les récompenses. Comptes complets seulement (EX2).
