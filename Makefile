COMPOSE = docker compose
PHP     = $(COMPOSE) exec -T php
CONSOLE = $(PHP) bin/console
c ?=

.DEFAULT_GOAL := help

help: ## Lists the commands
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-18s\033[0m %s\n", $$1, $$2}'

up: ## Builds and starts the containers
	$(COMPOSE) up --build --wait

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

db-load: ## Resets the database (all data!) then imports data/catalog.sql.gz
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate --no-interaction
	gunzip -c data/catalog.sql.gz | $(COMPOSE) exec -T database sh -c 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'

.PHONY: help up down logs sh composer console test phpstan cs sync db-dump db-load
