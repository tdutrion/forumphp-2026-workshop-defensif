COMPOSE = docker compose
PHP     = $(COMPOSE) exec -T php
CONSOLE = $(PHP) bin/console
c ?=

# Catalog dumps are assets of the "catalog" GitHub release, named after the day they were made
# (catalog-2026-10-05.sql.gz). make db-load lists the published ones and loads the newest by default.
CATALOG_REPO    ?= tdutrion/forumphp-2026-workshop-defensif
CATALOG_RELEASE ?= catalog

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

cs-check: ## Checks the code style without changing anything (CI)
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

lint: ## Validates composer.json, the dependencies (audit), the container, the Twig templates, the YAML files and the database schema
	$(PHP) composer validate --strict --no-check-publish
	$(PHP) composer audit
	$(CONSOLE) lint:container
	$(CONSOLE) lint:twig templates
	$(CONSOLE) lint:yaml config translations --parse-tags
	@# The mapping against the migrated database: a forgotten migration fails here.
	$(CONSOLE) doctrine:schema:validate

sync: ## Synchronizes the catalog from pathe.fr, e.g. make sync c="--city=dijon"
	$(CONSOLE) catalog:sync --no-debug $(c) # debug keeps every SQL backtrace: out of memory on 3 cities

db-dump: ## Writes data/catalog-<today>.sql.gz (catalog data, no schema or users)
	@mkdir -p data
	$(COMPOSE) exec -T database sh -c 'mysqldump --no-create-info --skip-triggers --complete-insert --no-tablespaces -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE" city cinema work film showtime' | gzip -9 > data/catalog-$$(date +%F).sql.gz
	@echo "Wrote data/catalog-$$(date +%F).sql.gz: run make db-upload to publish it."

db-upload: ## Publishes the newest dump of data/ on the GitHub release (gh CLI, signed in), e.g. make db-upload dump=catalog-2026-10-05.sql.gz
	@file=data/$${dump:-$$(ls data 2>/dev/null | grep -E '^catalog-[0-9]{4}-[0-9]{2}-[0-9]{2}\.sql\.gz$$' | sort | tail -n 1)}; \
	test -f "$$file" && [ "$$file" != data/ ] || { echo "No catalog dump in data/: run make db-dump first"; exit 1; }; \
	set -x; \
	gh release view $(CATALOG_RELEASE) --repo $(CATALOG_REPO) >/dev/null 2>&1 || \
		gh release create $(CATALOG_RELEASE) --repo $(CATALOG_REPO) --title 'Catalog dumps' --latest=false \
			--notes 'Pathé catalog dumps (cities, cinemas, works, films, showtimes), one asset per day of creation. Loaded by make db-load.' && \
	gh release upload $(CATALOG_RELEASE) "$$file" --repo $(CATALOG_REPO) --clobber

db-load: ## Loads the newest dump of the GitHub releases, or the one picked in the list (downloaded if missing): resets the database (all data!) and imports it, e.g. make db-load dump=catalog-2026-10-05.sql.gz
	@# The dump is chosen (and downloaded) before anything is dropped.
	@# The catalog cache is cleared after the import: a page visited meanwhile would have cached the calendar of an empty catalog.
	@dump=$$(CATALOG_REPO='$(CATALOG_REPO)' CATALOG_RELEASE='$(CATALOG_RELEASE)' bin/catalog-dump $(dump)) && \
	set -x && \
	$(CONSOLE) doctrine:database:drop --force --if-exists && \
	$(CONSOLE) doctrine:database:create && \
	$(CONSOLE) doctrine:migrations:migrate --no-interaction && \
	gunzip -c "$$dump" | $(COMPOSE) exec -T database sh -c 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"' && \
	$(CONSOLE) cache:pool:clear cache.catalog

phpstan-max: ## PHPStan max level (workshop progress measure)
	$(PHP) vendor/bin/phpstan analyse -c phpstan-max.neon --memory-limit=1G

phpstan-baseline: ## Regenerates the max-level baseline
	$(PHP) vendor/bin/phpstan analyse -c phpstan-max.neon --memory-limit=1G --generate-baseline phpstan-baseline.neon

.PHONY: help up css css-watch down logs sh composer console test phpstan cs cs-check lint sync db-dump db-load phpstan-max phpstan-baseline
