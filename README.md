Laravel 12 Microservice API Template

Overview
- Opinionated Laravel 12 skeleton for microservices: security, observability, messaging, and resilient HTTP.
- Focus: fast bootstrap, consistent security, robust observability, RabbitMQ + HTTP comms, production-ready Docker.

Quick Start
- Requirements: Docker or local PHP 8.3+, Composer, ext-sockets enabled (for RabbitMQ), Redis optional.
- Install: `composer install` then copy `.env` from `.env.example` and update values.
- Run locally with Docker: `docker-compose up --build -d` and visit `http://localhost:8080`.
- Health: `GET http://localhost:8080/api/v1/health`
- Metrics: `GET http://localhost:8080/api/v1/metrics`
- Swagger UI: `GET http://localhost:8080/api/documentation` or `/api/docs` redirect.

Folder Structure Highlights
- Controllers, Services, Repositories: `app/Http/Controllers`, `app/Repositories`
- Resilient HTTP module: `app/Support/Http/*` and typed client example `app/Clients/ExampleApiClient.php`
- Messaging (RabbitMQ): queue connection in `config/queue.php`, example job `app/Jobs/ExampleEventConsumer.php`, publisher `app/Messaging/Publisher.php`
- Security: JWT middleware `app/Http/Middleware/JwtAuthenticate.php`, field encryption `app/Security/FieldEncryption.php`
- Tracing/Logging: Correlation middleware `app/Http/Middleware/CorrelationId.php`, JSON logs via `app/Logging/*` and `config/logging.php`
- Observability: Health/Metrics controllers, Prometheus registry binding in `AppServiceProvider`
- Feature Flags: `app/FeatureFlags/*` with array driver; stubs for others

Service Communication (Async)
- RabbitMQ enabled via `vladimir-yuldashev/laravel-queue-rabbitmq` and `php-amqplib`.
- Configure via `.env` (exchange, DLX, queue). Idempotency helper `App\Support\Idempotency\IdempotencyStore` to dedupe consumers.
- Example consumer: dispatch `App\Jobs\ExampleEventConsumer` onto `rabbitmq` connection.
- Publisher: `App\Messaging\Publisher` publishes raw AMQP messages with headers (e.g., `Idempotency-Key`).

Service Communication (Sync / HTTP)
- `App\Support\Http\ResilientHttpClient` wraps Laravel HTTP with:
  - timeouts, retries (exponential + jitter), circuit breaker, header propagation for correlation ids, optional OAuth2 client-credentials, mTLS.
- Add per-target config in `config/micro.php` under `http.targets` and env stubs in `.env.example`.
- Typed client example: `App\Clients\ExampleApiClient` with DTOs left to implement per team style.

Security
- JWT resource server validation via JWKS: `app/Http/Middleware/JwtAuthenticate.php`.
  - Config in `config/micro.php > security.jwt`. Use route middleware `auth.jwt:scope1,scope2`.
- mTLS (egress): configure certs in `.env` and they are applied in outbound HTTP module.
- Field-level encryption: `App\Security\FieldEncryption` supports key rotation with versioned keys.

Traceability
- Correlation/Request IDs generated on ingress and added to responses.
- IDs propagate to logs, outbound HTTP headers, and can be added to AMQP headers.
- JSON structured logs with correlation processor; customize in `config/logging.php`.

Observability
- Prometheus metrics endpoint: `/api/v1/metrics` using in-memory registry (swap to Redis/APCu in prod).
- Health: `/api/v1/health` (includes DB connectivity check). Extend as needed.
- CloudWatch / Grafana: integrate via log shipping / exporters; stubs provided via JSON logger and metrics registry.

API Documentation & Versioning
- L5 Swagger configured (`config/l5-swagger.php`). Generate with `php artisan l5-swagger:generate`.
- Versioned routes under `/api/v1/...`. Add `/api/v2/...` as needed and include deprecation headers in controllers.

Progressive Delivery
- Feature flags abstraction with `FeatureFlagClient`. Default array driver; add LaunchDarkly/Unleash adapters later.
- Canary examples: gate usage in code with `FeatureFlagClient::enabled('flag')` and route toggles.
- Blue/Green: Docker + health/metrics endpoints ready; add deployment scripts in CI.

Dockerization
- Multi-stage `Dockerfile` (composer vendor + PHP-FPM runtime), non-root, opcache.
- `docker-compose.yml`: app (php-fpm), nginx, mysql, rabbitmq; with healthchecks.
- Place TLS certs in a bind-mount or secrets and reference via `.env`.

Configuration
- Centralized microservice config: `config/micro.php` and `.env.example` for all knobs (HTTP, OAuth2, JWT, mTLS, features, metrics).
- Queue: `config/queue.php` includes `rabbitmq` connection with DLX/parking-lot properties.

Database
- Example Product model + migration + seeder; repository pattern in `app/Repositories`.
- DB health check invoked in `/api/v1/health`.

Testing
- Unit: HTTP client retry test in `tests/Unit/ResilientHttpClientTest.php`.
- Feature: health endpoint test in `tests/Feature/HealthTest.php`.
- Add: consumer idempotency tests, JWT middleware tests, and contract tests (Pact/OpenAPI) as you expand.

CI/CD (example outline)
- Lint: `./vendor/bin/pint`
- Tests: `php artisan test --coverage-text`
- Build: `docker build -t your-org/service:sha-$(git rev-parse --short HEAD) .`
- OpenAPI: `php artisan l5-swagger:generate` and publish `storage/api-docs` as artifact.

Notes
- ext-sockets must be enabled for RabbitMQ. On Windows/XAMPP, uncomment `extension=sockets` in `php.ini`.
- For production, prefer Redis/APCu storage for Prometheus and a persistent cache for OAuth2/JWKS.

