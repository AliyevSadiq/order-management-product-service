# Product Service

A product catalog microservice built with **Symfony 7.0** and **PHP 8.3+**. Manages products and categories with full-text search via Elasticsearch, Redis caching, and event publishing to Kafka. Integrates with auth-service for JWT validation.

## Architecture Overview

![Product Service Architecture](docs/product-service-architecture.png)

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [API Endpoints](#api-endpoints)
- [Event Publishing](#event-publishing)
- [Search](#search)
- [Caching](#caching)
- [Project Structure](#project-structure)
- [Getting Started](#getting-started)
- [Environment Variables](#environment-variables)
- [Database Schema](#database-schema)
- [Microservice Integration](#microservice-integration)
- [Monitoring & Observability](#monitoring--observability)
- [Testing](#testing)

## Features

- Product CRUD with SKU uniqueness and category validation
- Category management with hierarchical parent-child support
- Full-text search via Elasticsearch (fuzzy matching, price range filters, category filters)
- Automatic Elasticsearch indexing via Doctrine lifecycle listeners
- Redis caching with TTL-based invalidation (1h products, 2h categories)
- Publishes ProductUpdated events to Kafka (consumed by order-service to sync product names)
- JWT token validation via auth-service HTTP call
- Prometheus metrics for HTTP requests and product operations
- OpenAPI documentation (NelmioApiDoc)

## Tech Stack

| Component       | Technology                           |
|-----------------|--------------------------------------|
| Framework       | Symfony 7.0                          |
| Language        | PHP 8.3+                             |
| Database        | PostgreSQL 16 (Doctrine ORM)         |
| Search          | Elasticsearch 8.11 (FOSElasticaBundle) |
| Cache           | Redis 7 (product & category pools)   |
| Message Broker  | Kafka                                |
| Monitoring      | Prometheus + Sentry                  |
| Container       | Docker (PHP 8.4-FPM)                |


## API Endpoints

Write operations (POST/PUT/DELETE) and product listing require `Authorization: Bearer <token>` validated by auth-service. Single product view, categories list, category detail, and search are public.

### Product Endpoints

| Method | Endpoint             | Description                        | Auth Required |
|--------|----------------------|------------------------------------|---------------|
| GET    | `/api/products`      | List products (paginated)          | Yes (Bearer)  |
| POST   | `/api/products`      | Create a product                   | Yes (Bearer)  |
| GET    | `/api/products/{id}` | Get product by ID                  | No            |
| PUT    | `/api/products/{id}` | Update product                     | Yes (Bearer)  |
| DELETE | `/api/products/{id}` | Delete product                     | Yes (Bearer)  |

### Category Endpoints

| Method | Endpoint               | Description              | Auth Required |
|--------|------------------------|--------------------------|---------------|
| GET    | `/api/categories`      | List all categories      | No            |
| POST   | `/api/categories`      | Create a category        | Yes (Bearer)  |
| GET    | `/api/categories/{id}` | Get category by ID       | No            |

### Search Endpoint

| Method | Endpoint      | Description                             | Auth Required |
|--------|---------------|-----------------------------------------|---------------|
| GET    | `/api/search` | Full-text product search (Elasticsearch)| No            |

**Search query parameters**: `query`, `categoryId`, `minPrice`, `maxPrice`, `page`, `limit`

### System Endpoints

| Method | Endpoint            | Description                                   |
|--------|---------------------|-----------------------------------------------|
| GET    | `/health/products`  | Health check (DB, Redis, Elasticsearch, Kafka) |
| GET    | `/metrics`          | Prometheus metrics                             |
| GET    | `/api/doc`          | Swagger/OpenAPI documentation                  |

### Request / Response Examples

**Create Product**
```http
POST /api/products
Authorization: Bearer eyJhbGciOiJSUzI1NiIs...
Content-Type: application/json

{
  "name": "Wireless Mouse",
  "description": "Ergonomic wireless mouse with USB-C receiver",
  "price": 29.99,
  "sku": "WM-001",
  "stock": 150,
  "categoryId": "550e8400-e29b-41d4-a716-446655440000"
}
```
```json
// 201 Created
{
  "id": "660e8400-e29b-41d4-a716-446655440001",
  "name": "Wireless Mouse",
  "description": "Ergonomic wireless mouse with USB-C receiver",
  "price": "29.99",
  "sku": "WM-001",
  "stock": 150,
  "categoryId": "550e8400-e29b-41d4-a716-446655440000",
  "active": true,
  "createdAt": "2026-03-06T12:00:00+00:00",
  "updatedAt": "2026-03-06T12:00:00+00:00"
}
```

**Search Products**
```http
GET /api/search?query=wireless&minPrice=10&maxPrice=50&page=1&limit=10
```

**Create Category**
```http
POST /api/categories
Authorization: Bearer eyJhbGciOiJSUzI1NiIs...
Content-Type: application/json

{
  "name": "Electronics",
  "slug": "electronics",
  "description": "Electronic devices and accessories",
  "parentId": null
}
```

## Event Publishing

Product events are published to **Kafka** (topic: `product-events`). Currently only `ProductUpdated` has an active consumer:

| Event            | Trigger          | Payload                              | Consumer        |
|------------------|------------------|--------------------------------------|-----------------|
| `ProductUpdated` | Product updated  | productId, changedFields, name, price| order-service   |

The order-service subscribes via Kafka consumer group `order-service-group` and uses `ProductUpdated` events to sync product names in existing orders.

## Search

Full-text search is powered by **Elasticsearch 8.11** via FOSElasticaBundle.

### How it works

1. **Automatic indexing**: `ProductIndexListener` hooks into Doctrine lifecycle events (`postPersist`, `postUpdate`, `preRemove`) to keep the Elasticsearch index in sync
2. **Search query**: Boolean query with:
   - Multi-match on `name` (3x weight boost) and `description` with AUTO fuzziness
   - Optional filters: `categoryId`, `minPrice`/`maxPrice` range, `active=true`
3. **Results**: Sorted by relevance score, then by `createdAt` descending

### Index schema

```
products index:
  id         → keyword
  name       → text (standard analyzer)
  description→ text
  price      → float
  sku        → keyword
  stock      → integer
  categoryId → keyword
  active     → boolean
  createdAt  → date
  updatedAt  → date
```

## Caching

Redis-backed caching with two dedicated pools:

| Pool              | TTL    | Keys                              |
|-------------------|--------|-----------------------------------|
| Product cache     | 3600s  | `product_{id}`, `products_list`   |
| Category cache    | 7200s  | `category_{id}`, `categories_list`|

`ProductCacheManager` handles invalidation on create/update/delete operations.

## Project Structure

```
src/
├── Client/             # External service HTTP clients
│   └── AuthServiceClient       # JWT validation via auth-service
├── Contract/           # Service interfaces
├── Controller/
│   └── Api/
│       ├── ProductController   # Product CRUD endpoints
│       ├── CategoryController  # Category endpoints
│       └── SearchController    # Elasticsearch search endpoint
├── DTO/                # Data transfer objects
│   ├── CreateProductRequest
│   ├── UpdateProductRequest
│   ├── CreateCategoryRequest
│   ├── ProductResponse
│   └── CategoryResponse
├── Entity/             # Doctrine entities
│   ├── Product             # products table
│   └── Category            # categories table
├── EventListener/      # Event listeners
│   ├── AuthTokenListener       # JWT validation on requests
│   ├── ExceptionListener       # JSON error responses
│   ├── HttpMetricsListener     # HTTP request metrics
│   ├── ProductMetricsListener  # Product operation metrics
│   └── ProductIndexListener    # Auto Elasticsearch indexing
├── Logger/             # Custom Monolog processor
├── Message/            # Kafka event classes
│   ├── ProductCreated
│   ├── ProductUpdated
│   └── ProductDeleted
├── MessageHandler/     # Event handlers
│   └── ProductEventLogger      # Logs product events
├── Metrics/            # Prometheus metrics registry
├── Repository/         # Doctrine repositories
│   ├── ProductRepository
│   └── CategoryRepository
└── Service/            # Business logic
    ├── ProductService          # Product CRUD + events + cache
    ├── CategoryService         # Category CRUD + cache
    ├── SearchService           # Elasticsearch queries
    ├── ProductCacheManager     # Redis cache management
    ├── HealthCheckService      # Dependency health checks
    └── RequestValidator        # DTO validation
```

## Getting Started

### Prerequisites

- Docker & Docker Compose
- (Optional) PHP 8.3+ and Composer for local development

### Run with Docker

```bash
# Start all services
docker compose up -d

# Run database migrations
docker compose exec product-service php bin/console doctrine:migrations:migrate --no-interaction

# Populate Elasticsearch index from existing data
docker compose exec product-service php bin/console fos:elastica:populate
```

The service will be available at `http://localhost:8082`.

### Run Locally

```bash
composer install

# Configure .env.local with your service credentials

php bin/console doctrine:migrations:migrate --no-interaction
php bin/console fos:elastica:populate

php -S 0.0.0.0:8082 -t public
```

## Environment Variables

| Variable               | Description                        | Default                          |
|------------------------|------------------------------------|----------------------------------|
| `APP_ENV`              | Environment (dev/prod/test)        | `dev`                            |
| `APP_SECRET`           | Application secret key             | --                               |
| `DATABASE_URL`         | PostgreSQL connection string       | --                               |
| `REDIS_URL`            | Redis connection string            | --                               |
| `ELASTICSEARCH_HOST`   | Elasticsearch URL                  | `http://product-elasticsearch:9200` |
| `KAFKA_DSN`            | Kafka broker connection            | `ed+kafka://kafka:9092`          |
| `AUTH_SERVICE_URL`     | Auth service base URL              | `http://auth-service:8080`       |
| `SENTRY_DSN`           | Sentry error tracking DSN          | --                               |

See `.env.example` for the complete list including Docker port mappings.

## Database Schema

**Database**: PostgreSQL 16 with Doctrine ORM (UUID primary keys)

### products

| Column       | Type          | Constraints              |
|-------------|---------------|--------------------------|
| id          | UUID          | Primary Key              |
| name        | VARCHAR(255)  | Not Null                 |
| description | TEXT          | Not Null                 |
| price       | NUMERIC(10,2) | Not Null                 |
| sku         | VARCHAR(100)  | Unique, Not Null         |
| stock       | INTEGER       | Default: 0              |
| category_id | UUID          | Indexed, Nullable (FK)   |
| active      | BOOLEAN       | Indexed, Default: true   |
| created_at  | TIMESTAMP     | Immutable                |
| updated_at  | TIMESTAMP     | Auto-updated             |

### categories

| Column      | Type         | Constraints              |
|------------|--------------|--------------------------|
| id         | UUID         | Primary Key              |
| name       | VARCHAR(255) | Not Null                 |
| slug       | VARCHAR(255) | Unique, Not Null         |
| description| TEXT         | Nullable                 |
| parent_id  | UUID         | Indexed, Nullable        |
| created_at | TIMESTAMP    | Immutable                |

## Microservice Integration

### Auth Service (synchronous HTTP)

```
Client Request ──► AuthTokenListener ──► GET auth-service:8080/api/auth/profile
                                              │
                                    extracts: userId, email, fullName
                                              │
                                         sets request attributes
```

Protects all write operations and product listing.

### Order Service (async via Kafka)

```
ProductService ──dispatch──► Kafka (topic: product-events)
                  (ProductUpdated)
                                     │
                              order-service (consumer)
                              order-kafka-worker
                                     │
                            ProductUpdatedHandler
                                     │
                        syncs product names in order_items
```

When a product name changes, order-service updates the `product_name` field in its `order_items` table to keep historical orders accurate.

## Monitoring & Observability

### Prometheus Metrics (`GET /metrics`)

**HTTP Metrics:**
- `http_requests_total` -- by service, method, endpoint, status
- `http_request_duration_seconds` -- histogram with buckets [0.005s - 10s]

**Product Metrics:**
- `products_created_total`
- `products_updated_total`
- `products_deleted_total`
- `products_listed_total`
- `products_viewed_total`
- `product_search_queries_total` -- by status
- `categories_created_total`

### Health Check (`GET /health/products`)

Returns connectivity status and response times for:
- PostgreSQL database
- Redis cache
- Elasticsearch cluster
- Kafka broker

### Error Tracking

Sentry integration for real-time exception monitoring.

### Logging

Structured JSON logging via Monolog with Logstash TCP output for centralized log aggregation.

## Testing

```bash
# Run tests
php bin/phpunit

# Static analysis
vendor/bin/phpstan analyse src --level=5
```

### Test Coverage

- **Controllers**: ProductController, SearchController
- **Services**: ProductService, CategoryService, ProductCacheManager, RequestValidator
- **Repositories**: ProductRepository, CategoryRepository

CI runs PHPUnit on PHP 8.3 & 8.4 matrix (with PostgreSQL), PHPStan level 5, and Docker build verification.

## Docker Services

| Service               | Image                    | Port  | Purpose                        |
|-----------------------|--------------------------|-------|--------------------------------|
| product-service       | Custom (Dockerfile)      | 8082  | API server                     |
| postgresql            | postgres:16-alpine       | 5434  | Primary database               |
| redis                 | redis:7-alpine           | 6381  | Product & category cache       |
| product-elasticsearch | elasticsearch:8.11.0     | 9201  | Full-text search engine        |
| postgres-exporter     | prom/postgres-exporter   | 9188  | DB metrics for Prometheus      |

## License

This project is part of the Order Management System microservices architecture.
