# Laravel Ecommerce Microservices

An enterprise-grade ecommerce backend built with **Laravel 12** using a **Microservices Architecture**, **RabbitMQ**, **Redis**, **Docker**, **OAuth2 (Laravel Passport)**, **JWT (RS256)**, **Repository Pattern**, **DTOs**, and **Event-Driven Architecture**.

This project demonstrates how a production-ready ecommerce platform can be designed with scalability, security, maintainability, and loose coupling in mind.

---

## Architecture

```
                        +------------------+
                        |      Client      |
                        +--------+---------+
                                 |
                           OAuth2 / JWT
                                 |
                                 v
+------------------------------------------------------------+
|                     Auth Service                           |
|------------------------------------------------------------|
| Laravel Passport (OAuth2)                                  |
| JWT RS256                                                   |
| User Management                                             |
| Roles & Permissions (Spatie)                               |
| Token Issuing                                               |
| Token Revocation                                            |
+----------------------+--------------------------------------+
                       |
                Public Key
                       |
        +--------------+---------------+
        |                              |
        v                              v
+----------------------+      +----------------------+
| Inventory Service    |      | Order Service        |
|----------------------|      |----------------------|
| Products             |      | Orders              |
| Stock Management     |      | Order Processing    |
| Reservation          |      | Outbox Pattern      |
| Redis Cache          |      | Idempotency         |
+-----------+----------+      +----------+----------+
            |                            |
            +------------+---------------+
                         |
                    RabbitMQ
                         |
                  Event Driven System
```

---

# Technology Stack

### Backend

- Laravel 12
- PHP 8.4
- MySQL 8
- Redis
- RabbitMQ
- Docker
- Nginx

### Authentication

- Laravel Passport
- OAuth2
- JWT
- RS256
- Public Key Validation
- Spatie Permission

### Architecture

- Microservices
- Repository Pattern
- Service Layer
- DTO Pattern
- Event Driven Architecture
- Outbox Pattern
- Idempotent APIs
- Dependency Injection
- SOLID Principles

---

# Services

## Auth Service

Responsible for:

- User Registration
- Login
- OAuth2 Token Issuing
- Refresh Tokens
- Logout
- Role Management
- Permission Management
- JWT Signing
- Public Key Distribution

Authentication between services uses **RS256 public key validation**, eliminating the need for runtime calls to the Auth Service.

---

## Inventory Service

Responsible for:

- Product Catalog
- Product Details
- Stock Quantity
- Stock Reservation
- Stock Adjustment
- Stock Release

Inventory validates JWT tokens locally using the Auth Service public key.

Redis is used for:

- Public Key Cache
- Product Cache
- Product Details Cache

---

## Order Service

Responsible for:

- Order Creation
- Order Retrieval
- Order Status Updates
- Order Items
- Idempotent Requests
- Outbox Events

Implements the **Transactional Outbox Pattern** to ensure reliable event publishing.

---

# Authentication Flow

```
Client
   |
Login
   |
   v
Auth Service
   |
Issues JWT (RS256)
   |
   v
Client
   |
Bearer Token
   |
   v
Inventory / Order Service
   |
Validate JWT Signature
using Auth Service Public Key
```

No network request is made to the Auth Service during normal request processing.

---

# Event Driven Architecture

RabbitMQ is used for asynchronous communication.

Example:

```
Order Created

↓

Order Service

↓

OrderCreated Event

↓

RabbitMQ Exchange

↓

Inventory Service

↓

Reserve Stock
```

Future consumers:

- Notification Service
- Analytics Service
- Email Service
- Search Indexer

---

# Outbox Pattern

Order Service stores outgoing events in an `order_outbox` table inside the same database transaction as the order creation.

```
Database Transaction

Create Order

Create Order Items

Insert Outbox Event

Commit

↓

Background Publisher

↓

RabbitMQ
```

This guarantees that events are never lost.

---

# Redis Usage

Redis is used for:

- Public Key Caching
- Product Cache
- Token Metadata Cache
- Rate Limiting
- Distributed Locks
- Frequently Accessed Data

---

# Security

- OAuth2
- JWT
- RS256
- Public Key Validation
- Role Based Access Control
- Permission Based Authorization
- Idempotency Keys
- Request Validation
- DTO Validation

---

# Project Structure

```
laravel-ecommerce-microservices/

├── auth-service/
│
├── inventory-service/
│
├── order-service/
│
├── packages/
│   └── ecomstarter-core/
│
├── docker/
│
├── db_data/
│
├── docker-compose.yml
│
└── README.md
```

---

# Common Package

Shared package used by all services.

Contains:

- Base Repository
- Base DTO
- API Response
- Middleware
- Traits
- Helpers
- Exceptions
- Logging
- Validation Utilities

---

# Design Patterns

- Repository Pattern
- Service Layer
- DTO Pattern
- Dependency Injection
- Factory Pattern
- Outbox Pattern
- Strategy Pattern
- Event Driven Architecture

---

# API Features

### Auth

- Register
- Login
- Refresh Token
- Logout
- User Profile
- Role Management
- Permission Management

---

### Inventory

- List Products
- Product Details
- Reserve Stock
- Release Stock
- Adjust Stock

---

### Orders

- Create Order
- View Orders
- Order Details
- Cancel Order
- Update Status

---

# Reliability Features

- Idempotent Order Creation
- Retryable Event Publishing
- Dead Letter Queue Ready
- Distributed Cache
- Transactional Outbox
- Soft Deletes
- Optimistic Design

---

# Development

Clone the repository

```bash
git clone https://github.com/<your-username>/laravel-ecommerce-microservices.git
```

Start containers

```bash
docker compose up -d
```

Install dependencies

```bash
composer install
```

Run migrations

```bash
php artisan migrate
```

Seed database

```bash
php artisan db:seed
```

Generate Passport keys

```bash
php artisan passport:keys
```

---

# Future Roadmap

- Payment Service
- Notification Service
- Shipping Service
- API Gateway
- Elasticsearch
- OpenTelemetry
- Prometheus
- Grafana
- Kubernetes Deployment
- CI/CD Pipeline
- Saga Pattern
- Event Sourcing
- OpenAPI Documentation
- Contract Testing
- Distributed Tracing

---

# Highlights

- Enterprise Architecture
- Dockerized Microservices
- OAuth2 Authentication
- JWT RS256
- RabbitMQ Messaging
- Redis Caching
- Repository Pattern
- DTO Pattern
- Outbox Pattern
- Idempotency
- SOLID Principles
- Event Driven Design

---

# License

This project is licensed under the MIT License.