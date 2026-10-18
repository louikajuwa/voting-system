.DEFAULT_GOAL := help
COMPOSE := docker compose
APP := $(COMPOSE) exec -T app

.PHONY: help build up down restart setup migrate fresh seed key shell logs ps test pint

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-12s\033[0m %s\n",$$1,$$2}'

build: ## Build all images
	$(COMPOSE) build

up: ## Start the stack
	$(COMPOSE) up -d

down: ## Stop the stack
	$(COMPOSE) down

restart: down up ## Restart the stack

setup: ## One-time bootstrap: deps, key, socket secret, migrate + seed, storage link
	@test -f .env || cp .env.example .env
	@grep -q '^SOCKET_SECRET=' .env || (echo "SOCKET_SECRET=$$(openssl rand -hex 32)" >> .env && echo "generated SOCKET_SECRET")
	$(COMPOSE) up -d --build
	$(APP) composer install
	$(APP) php artisan key:generate --force
	@# .env values are snapshotted into container env at start, so recreate to load the new key/secret
	$(COMPOSE) up -d --force-recreate app queue scheduler socket
	$(COMPOSE) restart nginx
	$(APP) sh -c "chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache"
	$(APP) php artisan storage:link || true
	$(APP) php artisan migrate --seed --force
	$(APP) php artisan permission:cache-reset || true
	@echo "Setup complete -> http://localhost:8080  (phpMyAdmin: docker compose --profile tools up -d -> http://127.0.0.1:8081)"

migrate: ## Run migrations
	$(APP) php artisan migrate

fresh: ## Drop + re-migrate + seed
	$(APP) php artisan migrate:fresh --seed

seed: ## Run seeders
	$(APP) php artisan db:seed

key: ## Generate app key
	$(APP) php artisan key:generate

shell: ## Shell into the app container
	$(COMPOSE) exec app bash

logs: ## Tail logs
	$(COMPOSE) logs -f --tail=100

ps: ## Show containers
	$(COMPOSE) ps

TEST_ENV := -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: \
	-e CACHE_STORE=array -e SESSION_DRIVER=array -e QUEUE_CONNECTION=sync -e MAIL_MAILER=array

test: ## Run the test suite (isolated: sqlite memory, array drivers)
	$(COMPOSE) exec -T $(TEST_ENV) app php artisan test

pint: ## Run Laravel Pint (format)
	$(APP) ./vendor/bin/pint
