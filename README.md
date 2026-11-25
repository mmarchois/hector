# Hector

**L'assistant des électriciens sur les chantiers.**

Le dépôt contient deux applications :

- l'**API** (Symfony, à la racine), architecture hexagonale / DDD / CQRS, documentée avec Swagger ;
- l'**application mobile** (Flutter, dans [`mobile/`](mobile/README.md)).

## Environnement technique

- [Docker](https://www.docker.com/) / [Compose](https://docs.docker.com/compose/)
- [PHP 8.4](https://www.php.net/), [Symfony 8.1](https://symfony.com/), [Doctrine ORM 3](https://www.doctrine-project.org/)
- [PostgreSQL 17](https://www.postgresql.org/)
- [Flutter](https://flutter.dev/) (Riverpod, go_router, Dio)

## Démarrage de l'API

> Seuls Docker et Docker Compose sont nécessaires sur la machine.

```bash
make install   # première fois : conteneurs, dépendances, clés JWT, bases dev + test
make start     # les fois suivantes
```

L'API répond sur http://localhost:8003 :

| URL | Contenu |
|-----|---------|
| http://localhost:8003/api/doc | Interface Swagger |
| http://localhost:8003/api/v1/doc | Spec OpenAPI (JSON) |

Si les ports 8003 ou 5432 sont déjà pris, créer un `docker-compose.override.yml` (ignoré par git) :

```yaml
services:
  database:
    ports: !override
      - '5435:5432'
  nginx:
    ports: !override
      - '8003:80'
```

`make help` liste toutes les commandes (base de données, qualité, tests, mobile).

### Comptes de test

Présents dans la base de test. Pour les avoir aussi en développement : `make dbfixtures ENV=dev` (⚠ vide d'abord la base de dev).

| Email | Mot de passe | Type |
|-------|--------------|------|
| electricien@hector.test | password123 | Professionnel |
| particulier@hector.test | password123 | Particulier |

## API

Toutes les routes sont sous `/api/v1` et répondent en JSON. L'authentification se fait par JWT (`Authorization: Bearer …`).

| Méthode | Route | Rôle |
|---------|-------|------|
| POST | `/api/v1/auth/register` | Création de compte |
| POST | `/api/v1/auth/login` | Connexion : `token` (15 min) + `refresh_token` (30 jours) |
| POST | `/api/v1/auth/token/refresh` | Renouvellement (le refresh token est à usage unique) |
| POST | `/api/v1/auth/token/invalidate` | Déconnexion : révoque le refresh token |
| GET | `/api/v1/auth/me` | Profil |
| PUT | `/api/v1/auth/me` | Mise à jour du profil et du mot de passe |
| DELETE | `/api/v1/auth/me` | Suppression du compte |

Format des erreurs : `{"code": 422, "message": "…"}`, complété pour une erreur de saisie par `"violations": [{"property": "siret", "message": "…"}]`.

## Architecture de l'API

```
src/
  Domain/            Métier pur : entités, exceptions, spécifications, interfaces
    User/ Company/     (repositories et ports), sans dépendance au framework
    Port/
  Application/       Cas d'usage : une Command ou une Query + son Handler
    User/Command/      CommandBusInterface / QueryBusInterface
    User/Query/
  Infrastructure/    Adaptateurs
    Adapter/           bus (Messenger), identifiants, dates, hachage
    Controller/Api/V1/ contrôleurs HTTP (un par route) et DTO
    Persistence/Doctrine/  mapping XML, repositories, migrations, fixtures
    Security/          utilisateur de sécurité, provider
config/validator/    validation des Commands (XML)
tests/Unit/          handlers et services, sans framework
tests/Integration/   API de bout en bout sur la base de test
```

Règles :

- le **domaine** ne dépend de rien ; l'**application** ne dépend que du domaine ; l'**infrastructure** implémente leurs interfaces ;
- un contrôleur transforme la requête en Command ou Query, l'envoie sur le bus et met la réponse en forme ;
- le bus de commandes valide la Command (`config/validator/`) puis exécute le handler dans une transaction ;
- le mapping Doctrine est en XML côté infrastructure, les entités restent du PHP pur.

## Qualité et tests

```bash
make test          # tests unitaires + intégration
make check         # style (PHP CS Fixer), PSR-4, PHPStan, conteneur, schéma Doctrine
make format        # corrige le style
make mobile_test   # analyse + tests Flutter
```

## Mise en production

Les valeurs du `.env` committé sont des valeurs de développement. En production, définir au minimum :

- `APP_ENV=prod` et `APP_SECRET` ;
- `DATABASE_URL` ;
- `JWT_PASSPHRASE`, `JWT_SECRET_KEY` et `JWT_PUBLIC_KEY` : une paire de clés dédiée, jamais celle de développement.
