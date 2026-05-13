# Event-Driven Notification System

A scalable, event-driven notification API handling SMS, Email, and Push channels with priority queuing and rate limiting. Built with Laravel 11.

## Tech Stack
- PHP 8.4 / Laravel 11
- Redis (Priority Queues, Rate Limiting)
- MySQL 8.0
- Docker & Docker Compose

## Core Features
- **Priority Queues:** Workers process queues in high -> normal -> low
- **Rate Limiting:** Strict 1000 msgs/sec per-channel limits utilizing Redis throttling.
- **Idempotency:** Safe handling of duplicate requests via idempotency_key without returning 422s.
- **Atomic Cancellation:** In-flight status checks prevent race conditions when canceling pending jobs.
- **Observability:** X-Correlation-ID header tracking across all layers, structured logging, and dedicated health/metric endpoints.

## Quick Start
```bash
cp .env.example .env

Add the following to the .env file:
WEBHOOK_URL=https://webhook.site/your-uuid-here

docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate

Testing run it with a single command:
docker compose exec app php artisan test

API Documentation
OpenAPI including all request/response schemas in it.
```
