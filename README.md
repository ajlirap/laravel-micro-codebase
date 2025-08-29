Laravel 12 Microservice API Template

Overview
- Opinionated Laravel 12 skeleton for microservices: security, observability, messaging, and resilient HTTP.
- Focus: fast bootstrap, consistent security, robust observability, RabbitMQ + HTTP comms, production-ready Docker.

> [!TIP]
> This template shows each API endpoint in two flavors: unsecured and secured (JWT Bearer). Use them side‑by‑side to learn how to apply authentication quickly and consistently.

Quick Start
- Requirements: Docker or local PHP 8.3+, Composer, ext-sockets enabled (for RabbitMQ), Redis optional.
- Install: `composer install` then copy `.env` from `.env.example` and update values.
- Run locally with Docker: `docker-compose up --build -d` and visit `http://localhost:8080`.
- Health: `GET http://localhost:8080/api/v1/health`
- Metrics: `GET http://localhost:8080/api/v1/metrics`
- Swagger UI: `GET http://localhost:8080/api/documentation` or `/api/docs` redirect.

> [!NOTE]
> For secured endpoints (under `/api/v1/secure/...`) you must configure JWT settings in `.env` and provide an Authorization header: `Authorization: Bearer <token>`.

Folder Structure Highlights
- Controllers, Services, Repositories: `app/Http/Controllers`, `app/Repositories`
- Resilient HTTP module: `app/Support/Http/*` and typed client example `app/Clients/ExampleApiClient.php`
- Messaging (RabbitMQ): queue connection in `config/queue.php`, example job `app/Jobs/ExampleEventConsumer.php`, publisher `app/Messaging/Publisher.php`
- Security: JWT middleware `app/Http/Middleware/JwtAuthenticate.php`, field encryption `app/Security/FieldEncryption.php`
- Tracing/Logging: Correlation middleware `app/Http/Middleware/CorrelationId.php`, JSON logs via `app/Logging/*` and `config/logging.php`
- Observability: Health/Metrics controllers, Prometheus registry binding in `AppServiceProvider`
- Feature Flags: `app/FeatureFlags/*` with array driver; stubs for others

> [!IMPORTANT]
> OpenAPI (Swagger) annotations are co‑located with controllers for easy discovery. A reusable `bearerAuth` scheme is defined at `app/Http/Controllers/Docs/Security.php`.

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

Authentication (JWT Bearer)
- Scheme: Reusable OpenAPI security scheme `bearerAuth` defined in `app/Http/Controllers/Docs/Security.php`.
- Routes: Secured variants are exposed under `/api/v1/secure/*` and enforced with `auth.jwt` middleware using scope checks.
- Env configuration (`.env`):

```env
AUTH_JWKS_URL=https://issuer.example.com/.well-known/jwks.json
AUTH_EXPECTED_ISS=https://issuer.example.com/
AUTH_EXPECTED_AUD=your-api-audience
AUTH_ACCEPTED_ALGS=RS256
```

- Example curl:

```bash
curl -H "Authorization: Bearer $TOKEN" http://localhost:8080/api/v1/secure/users
```

> [!TIP]
> You can also test unsecured routes without a token (see “API Endpoints” below). This lets you compare secured vs unsecured implementations quickly.

Traceability
- Correlation/Request IDs generated on ingress and added to responses.
- IDs propagate to logs, outbound HTTP headers, and can be added to AMQP headers.
- JSON structured logs with correlation processor; customize in `config/logging.php`.

Observability
- Prometheus metrics endpoint: `/api/v1/metrics` using in-memory registry (swap to Redis/APCu in prod).
- Health: `/api/v1/health` (includes DB connectivity check). Extend as needed.
- CloudWatch / Grafana: integrate via log shipping / exporters; stubs provided via JSON logger and metrics registry.

API Endpoints (Unsecured vs Secured)
- Users (CRUD)
  - Unsecured: `GET/POST /api/v1/users`, `GET/PUT/DELETE /api/v1/users/{id}`
  - Secured: `GET/POST /api/v1/secure/users`, `GET/PUT/DELETE /api/v1/secure/users/{id}`
  - Scopes: `example.read` for read, `example.write` for write
- Health
  - Unsecured: `GET /api/v1/health`
  - Secured: `GET /api/v1/secure/health` (requires Bearer)
- Metrics
  - Unsecured: `GET /api/v1/metrics`
  - Secured: `GET /api/v1/secure/metrics` (requires Bearer)
- Samples
  - Unsecured: `GET /api/v1/widgets/{id}`, `POST /api/v1/test/validation`
  - Secured: `GET /api/v1/secure/widgets/{id}`, `POST /api/v1/secure/test/validation`

> [!WARNING]
> Do not expose sensitive write operations without authentication in production. The unsecured endpoints are provided for learning and can be removed or disabled via route groups once teams align on auth enforcement.

API Documentation & Versioning
- L5 Swagger configured (`config/l5-swagger.php`). Generate with `php artisan l5-swagger:generate`.
- Versioned routes under `/api/v1/...`. Add `/api/v2/...` as needed and include deprecation headers in controllers.

OpenAPI / Swagger
- Generate spec/UI:

```bash
php artisan l5-swagger:generate
# Open the UI
open http://localhost:8080/api/documentation
```

- Security:
  - Click “Authorize” in Swagger UI and paste `Bearer <your-jwt>`.
  - Secured paths declare `security: [{ bearerAuth: [] }]` via annotations on their operations.

> [!NOTE]
> A CI helper `php artisan docs:assert-covered` is included to verify routes are covered by the OpenAPI spec. Run it locally to catch undocumented endpoints early.

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

Messaging Examples
- Publish an outbound domain event (from a service/controller):

```php
use App\Messaging\Publisher;
use Illuminate\Support\Str;

$payload = [
    'id' => (string) Str::uuid(),
    'type' => 'users.created',
    'source' => 'svc.users',
    'time' => now()->toIso8601String(),
    'schemaVersion' => '1.0',
    'data' => [
        'userId' => $user->id,
        'email' => $user->email,
    ],
];

$headers = [
    'Idempotency-Key' => $payload['id'],
    'X-Correlation-Id' => app()->bound('correlation_id') ? app('correlation_id') : (string) Str::uuid(),
];

(new Publisher())->publish('users.created', $payload, $headers);
```

- CLI demo (AMQP vs Queue):

```bash
# Publish directly via AMQP
php artisan mq:publish-example --producer=amqp

# Or dispatch a job onto the rabbitmq queue
php artisan mq:publish-example --producer=queue
```

- Consume inbound events (workers):

```bash
php artisan queue:work rabbitmq --sleep=1 --tries=5
```

> [!TIP]
> Bind your queue (e.g., `app.queue`) to the exchange (e.g., `app.exchange`) with specific routing keys (e.g., `users.*`) in RabbitMQ so your worker receives only the events you care about.

> [!IMPORTANT]
> For strong delivery guarantees, consider the Outbox pattern (persist events in the same DB transaction as your write; a background process publishes reliably and marks them sent).

CI/CD (example outline)
- Lint: `./vendor/bin/pint`
- Tests: `php artisan test --coverage-text`
- Build: `docker build -t your-org/service:sha-$(git rev-parse --short HEAD) .`
- OpenAPI: `php artisan l5-swagger:generate` and publish `storage/api-docs` as artifact.

Troubleshooting
- JWT 401 on secured endpoints
  - Check `.env` values for `AUTH_*` settings and token `iss`/`aud` claims.
  - Ensure your Authorization header is `Bearer <token>` (no quotes).
- Queue worker not receiving messages
  - Verify RabbitMQ bindings from queue to exchange with expected routing keys.
  - Confirm `QUEUE_CONNECTION=rabbitmq` and the worker command is running.
- AMQP publish fails
  - Confirm RabbitMQ host/port/creds in `.env` and that the exchange exists.
- Swagger UI shows no security
  - Regenerate docs: `php artisan l5-swagger:generate` and reload the page.

Notes
- ext-sockets must be enabled for RabbitMQ. On Windows/XAMPP, uncomment `extension=sockets` in `php.ini`.
- For production, prefer Redis/APCu storage for Prometheus and a persistent cache for OAuth2/JWKS.
