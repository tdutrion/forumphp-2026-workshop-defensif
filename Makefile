COMPOSE = docker compose
PHP     = $(COMPOSE) exec -T php
CONSOLE = $(PHP) bin/console
c ?=

# Catalog dumps are assets of the "catalog" GitHub release, named after the day they were made
# (catalog-2026-10-05.sql.gz). make db-load lists the published ones and offers the pinned one by
# default (the only one used without a terminal); make db-dump moves the pin.
CATALOG_REPO      ?= tdutrion/forumphp-2026-workshop-defensif
CATALOG_RELEASE   ?= catalog
CATALOG_DUMP_DATE ?= 2026-10-05
CATALOG_DUMP      = data/catalog-$(CATALOG_DUMP_DATE).sql.gz

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

lint: ## Validates composer.json, the dependencies (audit), the container, the Twig templates and the YAML files
	$(PHP) composer validate --strict --no-check-publish
	$(PHP) composer audit
	$(CONSOLE) lint:container
	$(CONSOLE) lint:twig templates
	$(CONSOLE) lint:yaml config translations --parse-tags

sync: ## Synchronizes the catalog from pathe.fr, e.g. make sync c="--city=dijon"
	$(CONSOLE) catalog:sync --no-debug $(c) # debug keeps every SQL backtrace: out of memory on 3 cities

db-dump: ## Writes data/catalog-<today>.sql.gz (catalog data, no schema or users) and pins it
	@mkdir -p data
	$(COMPOSE) exec -T database sh -c 'mysqldump --no-create-info --skip-triggers --complete-insert --no-tablespaces -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE" city cinema work film showtime' | gzip -9 > data/catalog-$$(date +%F).sql.gz
	@sed "s/^CATALOG_DUMP_DATE ?= .*/CATALOG_DUMP_DATE ?= $$(date +%F)/" Makefile > Makefile.tmp && mv Makefile.tmp Makefile
	@echo "Pinned data/catalog-$$(date +%F).sql.gz: run make db-upload, then commit the Makefile."

db-upload: ## Publishes the pinned catalog dump on the GitHub release (gh CLI, signed in)
	@test -f $(CATALOG_DUMP) || { echo "$(CATALOG_DUMP) is missing: run make db-dump first"; exit 1; }
	gh release view $(CATALOG_RELEASE) --repo $(CATALOG_REPO) >/dev/null 2>&1 || \
		gh release create $(CATALOG_RELEASE) --repo $(CATALOG_REPO) --title 'Catalog dumps' --latest=false \
			--notes 'Pathé catalog dumps (cities, cinemas, works, films, showtimes), one asset per day of creation. Loaded by make db-load.'
	gh release upload $(CATALOG_RELEASE) $(CATALOG_DUMP) --repo $(CATALOG_REPO) --clobber

db-load: ## Asks which dump of the GitHub releases to load (downloaded if missing), resets the database (all data!) and imports it, e.g. make db-load dump=catalog-2026-10-05.sql.gz
	@# The dump is chosen (and downloaded) before anything is dropped.
	@dump=$$(CATALOG_REPO='$(CATALOG_REPO)' CATALOG_RELEASE='$(CATALOG_RELEASE)' CATALOG_DUMP_DATE='$(CATALOG_DUMP_DATE)' bin/catalog-dump $(dump)) && \
	set -x && \
	$(CONSOLE) doctrine:database:drop --force --if-exists && \
	$(CONSOLE) doctrine:database:create && \
	$(CONSOLE) doctrine:migrations:migrate --no-interaction && \
	$(CONSOLE) cache:pool:clear cache.catalog && \
	gunzip -c "$$dump" | $(COMPOSE) exec -T database sh -c 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'

phpstan-max: ## PHPStan max level (workshop progress measure)
	$(PHP) vendor/bin/phpstan analyse -c phpstan-max.neon --memory-limit=1G

phpstan-baseline: ## Regenerates the max-level baseline
	$(PHP) vendor/bin/phpstan analyse -c phpstan-max.neon --memory-limit=1G --generate-baseline phpstan-baseline.neon

.PHONY: help up css css-watch down logs sh composer console test phpstan cs cs-check lint sync db-dump db-load phpstan-max phpstan-baseline
