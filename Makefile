# Allow non-Docker overrides for CI.
BIN_SHELL = docker compose exec php
BIN_PHP = ${BIN_SHELL} php
BIN_CONSOLE = ${BIN_PHP} bin/console
BIN_COMPOSER = ${BIN_SHELL} composer

# URL de l'API utilisée par l'application mobile en local
# (émulateur Android : http://10.0.2.2:8000).
API_URL ?= http://localhost:8000

##
## ----------------
## General
## ----------------
##

all: help

help: ## Display this message
	@grep -E '(^[a-zA-Z0-9_\-\.]+:.*?##.*$$)|(^##)' Makefile | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m## /[33m/'

install: build start install_deps jwt_keys dbinstall ## Bootstrap project

jwt_keys: ## Generate JWT keypair (idempotent)
	${BIN_CONSOLE} lexik:jwt:generate-keypair --skip-if-exists

install_deps: ## Install dependencies
	${BIN_COMPOSER} install -n --prefer-dist

start: ## Start containers
	docker compose up -d

stop: ## Stop containers
	docker compose stop

ps: ## Display containers
	docker compose ps

restart: stop start ## Restart containers

build: ## Build containers
	docker compose build

rm: ## Remove containers
	make stop
	docker compose rm

##
## ----------------
## Database
## ----------------
##

dbinstall: ## Setup databases (dev + test) and load test fixtures
	make dbmigrate
	${BIN_CONSOLE} doctrine:database:create --env=test --if-not-exists
	make dbmigrate ARGS="--env=test"
	make dbfixtures

dbmigration: ## Generate new db migration
	${BIN_CONSOLE} doctrine:migrations:diff

dbmigrate: ## Run db migration
	${BIN_CONSOLE} doctrine:migrations:migrate -n --all-or-nothing ${ARGS}

dbfixtures: ## Load fixtures, EMPTYING the database first (test by default, ENV=dev for the dev one)
	${BIN_CONSOLE} doctrine:fixtures:load --env=$(or ${ENV},test) -n --purge-with-truncate

dbshell: ## Connect to the database
	docker compose exec database psql postgresql://hector:hector@database:5432/hector

##
## ----------------
## Executable
## ----------------
##

composer: ## Run composer commands
	${BIN_COMPOSER} ${CMD}

console: ## Run console command
	${BIN_CONSOLE} ${CMD}

cc: ## Run clear cache command
	${BIN_CONSOLE} cache:clear

shell: ## Connect to the container
	docker compose exec php bash

##
## ----------------
## Quality
## ----------------
##

phpstan: ## PHP Stan
	${BIN_PHP} ./vendor/bin/phpstan analyse

php_lint: ## PHP linter
	${BIN_PHP} ./vendor/bin/php-cs-fixer fix -n ${ARGS}

psr_lint: ## Check PSR autoloading
	${BIN_COMPOSER} dump-autoload --optimize --strict-psr --dry-run

check: ## Run checks
	make php_lint ARGS="--dry-run"
	make psr_lint
	make phpstan
	${BIN_CONSOLE} lint:container
	${BIN_CONSOLE} doctrine:schema:validate

format: php_lint ## Format code

##
## ----------------
## Tests
## ----------------
##

test: ## Run the test suite
	${BIN_PHP} ./bin/phpunit ${ARGS}

test_unit: ## Run unit tests only
	${BIN_PHP} ./bin/phpunit --testsuite=Unit ${ARGS}

test_integration: ## Run integration tests only
	${BIN_PHP} ./bin/phpunit --testsuite=Integration ${ARGS}

# Suite vide jusqu'aux premiers calculateurs (lot L13) : retirer alors --do-not-fail-on-empty-test-suite
test_critical: ## Run critical tests only (calculations and safety rules, no LLM)
	${BIN_PHP} ./bin/phpunit --testsuite=Critical --do-not-fail-on-empty-test-suite ${ARGS}

##
## ----------------
## Mobile (Flutter)
## ----------------
##

# Le client API mobile est écrit à la main dans mobile/lib/src/data/.
# La spec OpenAPI vivante est servie par le backend sur /api/v1/doc.

mobile_install: ## Install Flutter dependencies
	cd mobile && flutter pub get

mobile_test: ## Analyse + tests Flutter
	cd mobile && flutter analyze && flutter test

mobile_run_dev: ## Run the app against the local API (API_URL=http://localhost:8000)
	cd mobile && flutter run --dart-define=API_BASE_URL=${API_URL}

mobile_e2e: ## End-to-end journey on a running simulator/emulator against the local API
	cd mobile && flutter test integration_test --dart-define=API_BASE_URL=${API_URL}
