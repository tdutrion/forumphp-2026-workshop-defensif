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

.PHONY: help up down logs sh composer console test phpstan cs sync
