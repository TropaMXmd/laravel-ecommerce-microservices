# Inventory Service

Owns the product catalog and stock levels for the ecomstarter platform. Validates JWTs **locally** using a cached copy of Auth Service's RS256 public key — makes zero runtime HTTP calls to Auth Service on the request path. Participates in the order-placement saga as a choreographed RabbitMQ consumer, with no central orchestrator.

---

## Contents

- [Architecture overview](#architecture-overview)
- [Authentication](#authentication)
- [API reference](#api-reference)
- [RabbitMQ / saga workflow](#rabbitmq--saga-workflow)
- [The outbox pattern](#the-outbox-pattern)
- [Scheduled jobs](#scheduled-jobs)
- [Setup](#setup)
- [Operational commands](#operational-commands)
- [Known gaps / next steps](#known-gaps--next-steps)

---

## Architecture overview

```
inventory-service      HTTP (nginx + php-fpm) — products & stock-adjust endpoints
inventory-consumer     long-running worker — php artisan inventory:consume
inventory-scheduler    long-running worker — php artisan schedule:run loop
inventory-db           MySQL — products, stocks, stock_reservations, inventory_outbox
```

Three separate containers, one shared codebase (same image, different commands). This split exists because `inventory-consumer`'s job is to block forever on an open AMQP socket (`$channel->wait()`), and `inventory-scheduler` needs to tick every 60 seconds — neither of those can share a process with the HTTP server, which needs to stay responsive to requests.

**No payment step.** Stock reservation → `order.confirmed` is currently a direct transition with no payment gateway or intermediate "pending payment" state. This is a deliberate simplification for portfolio scope, not an oversight.

---

## Authentication

Every protected endpoint validates a bearer token locally against Auth Service's RS256 public key — never by calling Auth Service per-request.

- The public key is fetched once from `GET {auth-service}/api/v1/auth/public-key` and cached in Redis for 24h
- **Proactive refresh**: `RefreshAuthPublicKeyJob` re-fetches it hourly, well inside the TTL, so the cache should never actually go stale in practice
- **Reactive refresh**: if a signature check ever fails (e.g. Auth Service rotated its key faster than the hourly job caught it), the middleware busts the cache and retries once before giving up
- Required permissions are checked against the token's embedded `scopes`/`scp` claim, using the same dot-notation permission names as Auth Service's Spatie/Passport setup (`products.view`, `products.create`, `inventory.create`, etc.) — **not** a separate colon-notation OAuth-scope convention

---

## API reference

| Method | Path                     | Permission required | Notes                                                                   |
| ------ | ------------------------ | ------------------- | ----------------------------------------------------------------------- |
| `GET`  | `/api/v1/health`         | — (public)          | Liveness check                                                          |
| `GET`  | `/api/v1/products`       | `products.view`     | Paginated list, filters: `search`, `is_active`                          |
| `GET`  | `/api/v1/products/{sku}` | `products.view`     | Single product + stock levels                                           |
| `POST` | `/api/v1/products`       | `products.create`   | Creates a product + its initial `stocks` row in one transaction         |
| `POST` | `/api/v1/stock/adjust`   | `inventory.create`  | Manual admin adjustment (restock, write-off) — **not** part of the saga |

### `POST /products` — sample payload

```json
{
    "sku": "BIB-0004",
    "name": "Waterproof Baby Bib - Set of 3",
    "price": 14.99,
    "currency": "USD",
    "attributes": { "color": "sage green", "pack_size": 3 },
    "initial_quantity": 75
}
```

### `POST /stock/adjust` — sample payload

```json
{ "sku": "BIB-0004", "delta": -5, "reason": "Damaged in warehouse" }
```

`delta` is signed — positive restocks, negative writes off. Blocked if it would drop `quantity` below `reserved_quantity` (can't shrink on-hand stock below what's already promised to pending orders).

---

## RabbitMQ / saga workflow

Inventory-service is a **choreographed saga participant** — there's no central orchestrator deciding what happens next. Each service reacts to events published by another and publishes its own outcome in return.

### Exchanges

| Exchange        | Type   | Owned/published by   | Routing keys                                             |
| --------------- | ------ | -------------------- | -------------------------------------------------------- |
| `orders`        | topic  | order-service        | `order.placed`, `order.confirmed`, `order.cancelled`     |
| `inventory`     | topic  | inventory-service    | `stock.reserved`, `stock.insufficient`, `stock.released` |
| `notifications` | topic  | notification-service | `notify.order.*`                                         |
| `dead_letters`  | direct | shared               | dead-lettered messages from any queue                    |

All exchanges (not just `orders`) are declared idempotently at `inventory-consumer` boot (`exchange_declare` is a no-op if the exchange already exists) — this means the RabbitMQ topology bootstraps reliably regardless of which service happens to start first.

### Inventory-service's queues (bound to the `orders` exchange)

| Queue                       | Routing key       | Handler                  |
| --------------------------- | ----------------- | ------------------------ |
| `inventory.order.placed`    | `order.placed`    | `OrderPlacedConsumer`    |
| `inventory.order.confirmed` | `order.confirmed` | `OrderConfirmedConsumer` |
| `inventory.order.cancelled` | `order.cancelled` | `OrderCancelledConsumer` |

Each queue is declared with a 24h TTL and routes to the `dead_letters` exchange on rejection/expiry.

### The saga, end to end

1. **`order.placed`** arrives → `OrderPlacedConsumer` calls `StockReservationService::reserve()` per line item
    - Checks `available = quantity - reserved_quantity` (not raw `quantity`) against a Redis lock (`stock-lock:{sku}`) + `SELECT ... FOR UPDATE`, defense-in-depth against oversell
    - **Success**: increments `stocks.reserved_quantity`, creates a `held` row in `stock_reservations` (15-min TTL), records a `stock.reserved` row in `inventory_outbox`
    - **Failure** (insufficient stock): records `stock.insufficient` in the outbox; any lines already reserved earlier in the same order are compensated (released) before acking
2. **(async, ≤5s later)** `PublishOutboxMessagesJob` flushes unpublished outbox rows to the `inventory` exchange
3. **`order.confirmed`** arrives (once order-service exists and publishes it) → `OrderConfirmedConsumer` calls `commit()` — the `held` reservation becomes permanent: `quantity` decreases, `reserved_quantity` is released back down
4. **`order.cancelled`** arrives → `OrderCancelledConsumer` calls `release()` — the hold is given back with no deduction to `quantity`
5. **Abandoned holds** (nobody ever confirmed or cancelled) are swept every minute by `ReleaseExpiredReservationsJob`, flipping expired `held` rows to `expired` and returning the reserved units

All three consumers are **idempotent by design** — re-delivering the same message twice (RabbitMQ's at-least-once default) is a safe no-op, since `commit()`/`release()` only act on reservations still in `held` status.

---

## The outbox pattern

`inventory_outbox` exists to solve the dual-write problem: MySQL and RabbitMQ can't commit atomically together, so writing a reservation to MySQL _and_ publishing to RabbitMQ in the same code path risks a crash between the two leaving one system out of sync with the other.

Instead, the outbox row is written **inside the same DB transaction** as the reservation itself — either both exist or neither does, guaranteed by MySQL's own atomicity. `PublishOutboxMessagesJob` then does the actual RabbitMQ publish separately, on its own 5-second cadence, with retry (`attempts` column) if RabbitMQ is temporarily unreachable. Nothing is lost if the publish fails; the row just waits for the next tick.

---

## Scheduled jobs

Registered in `bootstrap/app.php`'s `withSchedule()`, run by `inventory-scheduler`'s `schedule:run` loop:

| Job                             | Cadence      | Purpose                                          |
| ------------------------------- | ------------ | ------------------------------------------------ |
| `PublishOutboxMessagesJob`      | every 5s     | Flushes `inventory_outbox` → RabbitMQ            |
| `ReleaseExpiredReservationsJob` | every minute | Sweeps abandoned `held` reservations             |
| `RefreshAuthPublicKeyJob`       | hourly       | Proactively refreshes the cached auth public key |

None of these implement `ShouldQueue` — there's no queue worker running in this project, so they execute synchronously inline when the scheduler tick fires them, rather than being enqueued to a nonexistent worker.

---

## Setup

```bash
# PHP dependencies
composer require firebase/php-jwt php-amqplib/php-amqplib:^3.6 predis/predis
composer config repositories.core path ../packages/core
composer require ecomstarter/core:@dev

# Required PHP extensions (add to Dockerfile if missing):
#   docker-php-ext-install pdo pdo_mysql bcmath sockets
# mbstring is usually bundled by default — verify with `php -m`

php artisan migrate
php artisan db:seed --class=Database\\Seeders\\ProductSeeder
```

### `.env` essentials

```env
CACHE_STORE=redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_CLIENT=predis
REDIS_DB=1

AUTH_SERVICE_URL=http://auth-nginx   # the nginx container, not php-fpm — nothing listens on :80 there

RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=ecomstarter
RABBITMQ_PASSWORD=ecomstarter_dev_pw
```

### Docker Compose services this depends on

`rabbitmq`, `redis`, `inventory-db`, `inventory-nginx`, `inventory-consumer`, `inventory-scheduler` — see root `docker-compose.yml`.

---

## Operational commands

### Redis — inspecting cached data

```bash
docker compose exec redis redis-cli
127.0.0.1:6379> select 1                          # this project's Redis DB index
OK
127.0.0.1:6379[1]> keys *
1) "laravel-database-laravel-cache-auth:public-key"
2) "laravel-database-laravel-cache-foo"
127.0.0.1:6379[1]> GET laravel-database-laravel-cache-auth:public-key
127.0.0.1:6379[1]> TTL laravel-database-laravel-cache-auth:public-key   # seconds until expiry
127.0.0.1:6379[1]> exit
```

Or as one-off commands without an interactive session:

```bash
docker compose exec redis redis-cli -n 1 KEYS "*"
docker compose exec redis redis-cli -n 1 GET "laravel-database-laravel-cache-auth:public-key"
```

Prefer `SCAN` over `KEYS` outside local dev — `KEYS` blocks Redis while it scans the whole keyspace.

### RabbitMQ consumer — checking it's alive

```bash
docker compose ps inventory-consumer                 # should show "Up", not "Restarting"
docker compose logs -f inventory-consumer             # live tail
docker compose logs --tail=50 inventory-consumer      # last 50 lines only
```

On a healthy boot you should see:

```
Bound inventory.order.placed to routing key order.placed
Bound inventory.order.confirmed to routing key order.confirmed
Bound inventory.order.cancelled to routing key order.cancelled
```

with no further output afterward — silence means it's idle and correctly blocked on `$channel->wait()`, not crashed.

Cross-check bindings actually exist in RabbitMQ itself: `http://localhost:15672` → **Queues** tab.

### RabbitMQ consumer — restarting after a code change

```bash
docker compose restart inventory-consumer   # code change only, same image
docker compose build inventory-consumer     # Dockerfile/extension change — rebuild first
docker compose up -d inventory-consumer
```

### RabbitMQ user/credential check

```bash
docker compose exec rabbitmq rabbitmqctl list_users
```

`RABBITMQ_DEFAULT_USER`/`PASS` only apply on a genuinely empty data directory — if this doesn't show your configured user, the volume/bind-mount has stale data from an earlier run and needs clearing.

### Scheduler — confirming jobs actually fire

```bash
docker compose ps inventory-scheduler
docker compose logs -f inventory-scheduler
```

Manually trigger a job without waiting for its tick (useful for testing):

```bash
docker compose exec inventory-service php artisan tinker --execute="
(new App\Jobs\PublishOutboxMessagesJob)->handle();
"
```

### Database — quick state checks

```bash
docker compose exec inventory-db mysql -u root -proot inventory_service -e \
  "SELECT order_id, sku, quantity, status FROM stock_reservations ORDER BY created_at DESC LIMIT 5;"

docker compose exec inventory-db mysql -u root -proot inventory_service -e \
  "SELECT event_type, routing_key, published FROM inventory_outbox ORDER BY created_at DESC LIMIT 5;"
```

---

## Known gaps / next steps

- No `stock_adjustments` audit table yet — `AdjustStockDTO->reason` is accepted and validated but not persisted anywhere
- No test for RabbitMQ message redelivery specifically (the idempotency guards exist and are unit-tested individually, but not exercised via an actual duplicate-delivery simulation)
- Jobs run synchronously via the scheduler rather than a real queue worker — fine at this traffic scale, would need `ShouldQueue` + `queue:work` to scale further
- No `kid` (key ID) claim on issued JWTs — fine for a single active signing key, would be needed to support a key-rotation overlap window
