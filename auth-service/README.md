# Auth Service

Sole issuer of access tokens for the ecomstarter platform. Every other service (inventory, order, notification) validates tokens **locally** against this service's RS256 public key rather than calling back here per-request — Auth Service is only ever hit directly for login, token issuance, and (occasionally) key retrieval.

---

## Contents

- [Key architectural decisions](#key-architectural-decisions)
- [Tech stack](#tech-stack)
- [Permissions / scopes](#permissions--scopes)
- [Roles](#roles)
- [API reference](#api-reference)
- [JWT claims](#jwt-claims)
- [Public key distribution](#public-key-distribution)
- [Setup](#setup)
- [Known gaps / to verify](#known-gaps--to-verify)

---

## Key architectural decisions

- **`users.id` is a UUID primary key**, not auto-increment — this is the identifier every other service references (e.g. `orders.user_id`, cross-service, no FK constraint)
- **Passport's OAuth tables were patched** — `user_id` columns changed to `uuid` type to match
- **Token issued via `$user->createToken()` directly** — no internal HTTP round-trip for issuance
- **RS256 asymmetric signing** — private key stays here; public key is fetched and cached by every other service, never distributed as a file/volume mount (see [Public key distribution](#public-key-distribution))
- **Permission names are dot-notation and shared identically between Spatie permissions and Passport OAuth scopes** — e.g. `products.create`, `inventory.reserve`. There is no separate coarse-grained OAuth-scope convention (an earlier planning doc sketched `inventory:read`/`inventory:admin`-style colon-separated scopes — that was superseded; the dot-notation permission list below is what's actually implemented)

---

## Tech stack

PHP 8.3, Laravel 13, Laravel Passport 13 (RS256), Spatie Laravel Permission 8, MySQL 8, shared `ecomstarter/core` package.

---

## Permissions / scopes

Defined once, used as both Passport `tokensCan()` scopes and Spatie permission names — kept in sync manually between `AuthServiceProvider::boot()` and the permissions seeder config:

```
users.view          users.create         users.update         users.delete
roles.view          roles.create         roles.update          roles.delete
products.view       products.create      products.update       products.delete
inventory.view       inventory.create     inventory.reserve     inventory.release
orders.view          orders.create        orders.update         orders.cancel
notifications.send
oauth-clients.manage
```

21 permissions total.

---

## Roles

| Role       | Permissions                                                          |
| ---------- | -------------------------------------------------------------------- |
| `customer` | subset — order placement, order/inventory read access, notifications |
| `admin`    | all 21 permissions                                                   |

**⚠ Unverified — worth confirming in code:** whether `User::getClaims()` actually filters the embedded JWT permissions to the user's _assigned_ Spatie permissions, or embeds the full 21-item list regardless of role. An earlier debugging session against inventory-service showed a token's `scopes` claim containing all 21 permissions — consistent with an `admin` token, but this hasn't been explicitly confirmed against a `customer`-role token. If it turns out `customer` tokens also carry all 21 permissions, that's a real access-control bug, not just a naming issue — a `customer` could then satisfy `inventory.create` and hit endpoints they shouldn't. **Check `User::getClaims()` and confirm it filters by `$this->getAllPermissions()` (the user's actual assigned set), not a hardcoded/static full list.**

---

## API reference

| Method | Path                      | Auth                                 | Notes                                                                                                                         |
| ------ | ------------------------- | ------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------- |
| `POST` | `/api/v1/auth/register`   | public                               |                                                                                                                               |
| `POST` | `/api/v1/auth/login`      | public                               |                                                                                                                               |
| `GET`  | `/api/v1/auth/public-key` | public                               | Consumed by every other service, cached in Redis — see below                                                                  |
| `POST` | `/api/v1/auth/logout`     | `auth:api` + active                  |                                                                                                                               |
| `GET`  | `/api/v1/auth/me`         | `auth:api` + active                  |                                                                                                                               |
| `PUT`  | `/api/v1/auth/me`         | `auth:api` + active                  |                                                                                                                               |
| `POST` | `/api/v1/auth/introspect` | `auth:api` + `inventory:admin` scope | ⚠ scope name here predates the dot-notation switch — verify this still matches an actual permission, likely should be updated |
| `GET`  | `/api/v1/health`          | public                               |                                                                                                                               |

---

## JWT claims

Embedded in every issued token:

```json
{
    "sub": "uuid",
    "uuid": "uuid",
    "name": "...",
    "email": "...",
    "role": "customer",
    "email_verified": true,
    "is_active": true,
    "scopes": ["products.view", "orders.create", "..."]
}
```

`scopes` is what downstream services (`ValidateJwt` middleware in inventory-service, and eventually order-service) check permissions against.

---

## Public key distribution

**Not** distributed via a shared Docker volume — each consuming service fetches it over HTTP from `GET /api/v1/auth/public-key` and caches it in Redis (24h TTL), with both a proactive hourly refresh and a reactive refresh-on-signature-failure fallback. This was a deliberate choice over a volume-mount approach: volume-mounting a key file between services couples their deployment topology (same host/orchestrator, shared filesystem), which works for local Docker Compose but breaks the "independently deployable" property microservices are supposed to have. See inventory-service's `ValidateJwt` middleware and `RefreshAuthPublicKeyJob` for the consumer-side implementation.

**⚠ Verify the actual response shape** of this endpoint matches what consumers expect — inventory-service's fetch code currently checks `data.public_key` first, falling back to a bare `public_key` key. Confirm which one `TokenController@publicKey` actually returns and drop the fallback if it's not needed.

---

## Setup

```bash
composer install
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
php artisan db:seed --class=AdminUserSeeder   # admin@ecomstarter.com / password — change for anything beyond local dev

php artisan passport:keys   # generates the RS256 key pair
```

RS256 keys are generated locally to this service; the public key is served via the `/public-key` endpoint above rather than shared as a file.

---

## Known gaps / to verify

- `getClaims()` permission-filtering by role — see [Roles](#roles) above, this is the highest-priority item to confirm
- `/introspect` route's required scope (`inventory:admin`) looks like a leftover from before the dot-notation permission naming was adopted — confirm and update
- Public key endpoint's response field name — confirm against actual controller code
- No automated tests referenced yet for this service (inventory-service has a Pest suite covering its own logic; auth-service's test coverage hasn't come up in this conversation)

# Create password grant client for web/mobile users

docker exec auth-service php artisan passport:client \
 --password \
 --name="Ecomstarter Web Client"

# Create client credentials client for Order Service → Inventory Service calls

docker exec auth-service php artisan passport:client \
 --client \
 --name="Order Service"

# Create client credentials client for any future service

docker exec auth-service php artisan passport:client \
 --client \
 --name="Inventory Service"
