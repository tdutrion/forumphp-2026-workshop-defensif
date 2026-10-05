COMPOSE = docker compose
PHP     = $(COMPOSE) exec -T php
CONSOLE = $(PHP) bin/console
c ?=

# Synology C2 Object Storage (S3-compatible) hosting the catalog dump, publicly readable.
# The upload keys (C2_ACCESS_KEY_ID, C2_SECRET_ACCESS_KEY) only live in .env.local.
C2_ENDPOINT      ?= https://eu-005.s3.synologyc2.net
C2_BUCKET        ?= forumphp2026
CATALOG_DUMP_URL ?= $(C2_ENDPOINT)/$(C2_BUCKET)/catalog.sql.gz

.DEFAULT_GOAL := help

help: ## Lists the commands
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-18s\033[0m %s\n", $$1, $$2}'

up: ## Builds and starts the containers, then builds the CSS
	$(COMPOSE) up --build --wait
	$(CONSOLE) tailwind:build

css: ## Builds the CSS once (Tailwind)
	$(CONSOLE) tailwind:build

css-watch: ## Rebuilds the CSS on every template change
	$(CONSOLE) tailwind:build --watch --poll

down: ## Stops the containers
	$(COMPOSE) down --remove-orphans

logs: ## Follows the logs
	$(COMPOSE) logs -f

sh: ## Opens a shell in the PHP container
	$(COMPOSE) exec php sh

composer: ## Runs Composer, e.g. make composer c="require foo/bar"
	$(PHP) composer $(c)

console: ## Runs the Symfony console, e.g. make console c="cache:clear"
	$(CONSOLE) $(c)

test: ## Runs PHPUnit, e.g. make test c="--filter Planner"
	@# The production-like kernel of the error page tests (debug off) never rebuilds its cache by itself.
	$(CONSOLE) cache:clear --env=test --no-warmup
	$(CONSOLE) doctrine:database:create --env=test --if-not-exists
	$(CONSOLE) doctrine:migrations:migrate --env=test --no-interaction --allow-no-migration
	$(PHP) bin/phpunit $(c)

phpstan: ## Static analysis (level 5)
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

cs: ## Fixes the code style (@Symfony)
	$(PHP) vendor/bin/php-cs-fixer fix

sync: ## Synchronizes the catalog from pathe.fr, e.g. make sync c="--city=dijon"
	$(CONSOLE) catalog:sync $(c)

db-dump: ## Writes data/catalog.sql.gz: catalog data, without schema or users
	@mkdir -p data
	$(COMPOSE) exec -T database sh -c 'mysqldump --no-create-info --skip-triggers --complete-insert --no-tablespaces -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE" city cinema film showtime' | gzip -9 > data/catalog.sql.gz

data/catalog.sql.gz:
	@test -n "$(C2_ENDPOINT)" -a -n "$(C2_BUCKET)" || { echo "C2_ENDPOINT and C2_BUCKET are not set in the Makefile"; exit 1; }
	@mkdir -p data
	curl -fSL --proto '=https' -o $@.part '$(CATALOG_DUMP_URL)'
	@mv $@.part $@

db-download: ## Downloads the latest data/catalog.sql.gz from Synology C2
	rm -f data/catalog.sql.gz
	$(MAKE) data/catalog.sql.gz

db-upload: ## Uploads data/catalog.sql.gz to Synology C2 (keys in .env.local; the bucket must be public)
	@test -n "$(C2_ENDPOINT)" -a -n "$(C2_BUCKET)" || { echo "C2_ENDPOINT and C2_BUCKET are not set in the Makefile"; exit 1; }
	@test -f .env.local || { echo ".env.local is missing (C2_ACCESS_KEY_ID, C2_SECRET_ACCESS_KEY)"; exit 1; }
	@set -a; . ./.env.local; set +a; \
	AWS_ACCESS_KEY_ID="$$C2_ACCESS_KEY_ID" AWS_SECRET_ACCESS_KEY="$$C2_SECRET_ACCESS_KEY" \
	aws s3 cp data/catalog.sql.gz 's3://$(C2_BUCKET)/catalog.sql.gz' --endpoint-url '$(C2_ENDPOINT)' --content-type application/gzip

db-load: data/catalog.sql.gz ## Resets the database (all data!) then imports data/catalog.sql.gz (downloaded if missing)
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate --no-interaction
	$(CONSOLE) cache:pool:clear cache.catalog
	gunzip -c data/catalog.sql.gz | $(COMPOSE) exec -T database sh -c 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'

phpstan-max: ## PHPStan max level (workshop progress measure)
	$(PHP) vendor/bin/phpstan analyse -c phpstan-max.neon --memory-limit=1G

phpstan-baseline: ## Regenerates the max-level baseline
	$(PHP) vendor/bin/phpstan analyse -c phpstan-max.neon --memory-limit=1G --generate-baseline phpstan-baseline.neon

.PHONY: help up css css-watch down logs sh composer console test phpstan cs sync db-dump db-load phpstan-max phpstan-baseline
