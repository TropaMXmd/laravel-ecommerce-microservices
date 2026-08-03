.PHONY: up down restart build logs ps shell-auth shell-inventory shell-order \
auth inventory order cache rabbitmq passport publish migrate fresh \
composer-auth composer-inventory composer-order

# -----------------------------------
# Docker
# -----------------------------------

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose down && docker compose up -d

build:
	docker compose build --no-cache

ps:
	docker compose ps

logs:
	docker compose logs -f

# -----------------------------------
# Shell
# -----------------------------------

shell-auth:
	docker exec -it auth-service sh

shell-inventory:
	docker exec -it inventory-service sh

shell-order:
	docker exec -it order-service sh

rabbitmq:
	docker exec -it rabbitmq sh

redis:
	docker exec -it redis redis-cli

# -----------------------------------
# Composer
# -----------------------------------

composer-auth:
	docker exec -it auth-service composer install

composer-inventory:
	docker exec -it inventory-service composer install

composer-order:
	docker exec -it order-service composer install

# -----------------------------------
# Laravel
# -----------------------------------

auth:
	docker exec -it auth-service php artisan

inventory:
	docker exec -it inventory-service php artisan

order:
	docker exec -it order-service php artisan

# -----------------------------------
# Migration
# -----------------------------------

migrate-auth:
	docker exec auth-service php artisan migrate

migrate-inventory:
	docker exec inventory-service php artisan migrate

migrate-order:
	docker exec order-service php artisan migrate

fresh-auth:
	docker exec auth-service php artisan migrate:fresh --seed

fresh-inventory:
	docker exec inventory-service php artisan migrate:fresh --seed

fresh-order:
	docker exec order-service php artisan migrate:fresh --seed

# -----------------------------------
# Cache
# -----------------------------------

cache-auth:
	docker exec auth-service php artisan optimize:clear

cache-inventory:
	docker exec inventory-service php artisan optimize:clear

cache-order:
	docker exec order-service php artisan optimize:clear

# -----------------------------------
# Tinker
# -----------------------------------

tinker-auth:
	docker exec auth-service php artisan tinker

tinker-inventory:
	docker exec inventory-service php artisan tinker

tinker-order:
	docker exec order-service php artisan tinker

# -----------------------------------
# Queue
# -----------------------------------

queue-auth:
	docker exec auth-service php artisan queue:work

queue-inventory:
	docker exec inventory-service php artisan queue:work

queue-order:
	docker exec order-service php artisan queue:work

# -----------------------------------
# Passport
# -----------------------------------

passport:
	docker exec auth-service composer require laravel/passport
	docker exec auth-service php artisan migrate
	docker exec auth-service php artisan passport:install

# -----------------------------------
# Publish Core Package
# -----------------------------------

publish-auth:
	docker exec auth-service php artisan vendor:publish --provider="Ecomstarter\Core\CoreServiceProvider"

publish-inventory:
	docker exec inventory-service php artisan vendor:publish --provider="Ecomstarter\Core\CoreServiceProvider"

publish-order:
	docker exec order-service php artisan vendor:publish --provider="Ecomstarter\Core\CoreServiceProvider"

# -----------------------------------
# RabbitMQ
# -----------------------------------

rabbit-users:
	docker exec rabbitmq rabbitmqctl list_users

rabbit-exchanges:
	docker exec rabbitmq rabbitmqctl list_exchanges

rabbit-queues:
	docker exec rabbitmq rabbitmqctl list_queues

rabbit-bindings:
	docker exec rabbitmq rabbitmqctl list_bindings

rabbit-permissions:
	docker exec rabbitmq rabbitmqctl list_permissions

# -----------------------------------
# Redis
# -----------------------------------

redis-cli:
	docker exec -it redis redis-cli

redis-db0:
	docker exec -it redis redis-cli SELECT 0

redis-db1:
	docker exec -it redis redis-cli SELECT 1