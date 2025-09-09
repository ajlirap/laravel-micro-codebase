Laravel 12 Microservice API Template

Overview
- Purpose: Practical starter for secure, observable, message‑driven APIs.
- Highlights: JWT auth (resource server), resilient outbound HTTP, RabbitMQ, metrics/health, feature flags, and Swagger docs.

Quick Start
- Requirements: PHP 8.3+, Composer, ext-sockets (for RabbitMQ), Docker optional.
- Install: `composer install` then copy `.env` from `.env.example` and fill values.

> [!WARNING]
> `.env` holds your real configuration and secrets for your environment (dev/staging/prod). The app reads these at boot and via `config/micro.php`.
> - `.env.example` is only a template; copy it to `.env` and replace placeholders with your actual values (JWT issuer/audience, JWKS URL, DB creds, RabbitMQ, etc.).
> - Do not commit `.env` to Git (it’s already ignored). For production, supply values via environment variables or a secret manager; do not bake secrets into images.
> - After changing `.env`, run `php artisan config:clear && php artisan cache:clear` so Laravel reloads the new values.
> - When using Docker, `docker-compose.yml` already points the app to `env_file: .env` for local convenience. In real deployments, pass env vars/secrets from your orchestrator instead.

- Run: `php artisan serve` (or `docker-compose up -d`), open `http://localhost:8000` (or `:8080` via Docker).
- Try endpoints: `GET /api/v1/health`, `GET /api/v1/metrics`, `GET /api/v1/users`.
- Swagger UI: `GET /api/documentation` (or `/api/docs` redirect).

Why These Features
- Security: Protect write/read endpoints with industry‑standard JWT (Bearer) via JWKS; easy to connect to providers like Azure AD.
- Resilience: Outbound HTTP gets timeouts, retries, jitter, circuit breaker, OAuth2, and mTLS so downstream hiccups don’t break you.
- Observability: Health checks and Grafana dashboards (via Prometheus metrics) expose service health; correlation IDs tie logs/requests together.
- Messaging: RabbitMQ integration enables async workflows and decoupling.
- Progressive Delivery: Feature flags let you ship safely (toggle behavior without redeploys).

How To Read This File
- Each capability explains the “why”, config options, and copy‑paste tests.
- If new to these concepts, follow the examples as checklists.

**JWT Authentication**
- Why: Verify who calls your API. The middleware validates signature and claims of `Authorization: Bearer <token>`.
- Routes: Unsecured under `/api/v1/*`; secured duplicates under `/api/v1/secure/*` enforced by `auth.jwt`.
- Files:
  - `app/Http/Middleware/JwtAuthenticate.php`: Verifies JWT via JWKS, checks `iss`/`aud` and optional scopes.
  - `config/micro.php:49`: Settings under `security.jwt`.
- Env Options:
  - `AUTH_JWKS_URL`: URL of JWKS (public keys). Example Azure AD v2: `https://login.microsoftonline.com/<tenant_id>/discovery/v2.0/keys`.
  - `AUTH_EXPECTED_ISS`: Expected issuer. Example: `https://login.microsoftonline.com/<tenant_id>/v2.0`.
  - `AUTH_EXPECTED_AUD`: Expected audience (App ID URI or Client ID). Example: `api://<app_id>` or `<client_id>`.
  - `AUTH_ACCEPTED_ALGS`: Allowed algs (comma‑sep). Default `RS256`.
  - `AUTH_LEEWAY_SECONDS`: Clock skew allowance for `exp/nbf/iat`. Example: `60`.
  - `AUTH_PREFER_EXP_REASON`: If `true`, when signature fails but payload `exp` is in the past, the error description will say “The access token expired” (dev aid).
- Behavior & Errors:
  - Returns `401` with `WWW-Authenticate: Bearer ...` containing `error_description` (e.g., “The access token expired” or “Signature verification failed”).
  - With `APP_DEBUG=true`, response JSON adds `reason` and `error` for easier local debugging.
- Test Secure Endpoint:
  - No token: `curl -i http://localhost:8000/api/v1/secure/health` → `401` with `WWW-Authenticate` header.
  - Wrong signature: Set `AUTH_JWKS_URL` to a JWKS that does not have the token’s key, call with any RS256 token → `401` signature failure.
  - Expired token: Use a correctly signed token with `exp` in the past → `401` with “The access token expired”. If you don’t have a real IdP, you can:
    - Generate keys: `openssl genrsa -out storage/keys/dev-jwt.key 2048 && openssl rsa -in storage/keys/dev-jwt.key -pubout -out storage/keys/dev-jwt.pub`
    - Create `storage/keys/jwks.json` with your public key (kid `dev-key-1`). Serve it: `php -S 127.0.0.1:8001 -t storage/keys`
    - Set: `AUTH_JWKS_URL=http://127.0.0.1:8001/jwks.json`, `AUTH_EXPECTED_ISS=http://localhost`, `AUTH_EXPECTED_AUD=local-api`.
    - Mint a token (PHP snippet using `firebase/php-jwt`) with `exp` in the past and header `{ "kid": "dev-key-1", "alg": "RS256" }`, then call the secure endpoint with it.
  - Not‑before (`nbf`) test: Mint a token with future `nbf` → `401` with “not yet valid”.
- Scopes:
  - Add middleware params like `->middleware('auth.jwt:example.read')` to a route.
  - Token claims checked: `scope` (space-sep), `scp` (Azure), and `roles`.

Trusted Caller Secret (optional)
- Why: Ensure traffic originates from trusted internal callers (BFF, API Gateway, or other microservices) by requiring a shared secret header in addition to JWT.
- Config:
  - `ACCEPTED_SECRETS`: Comma-separated list of accepted secrets. If empty, enforcement is skipped.
  - `ACCEPTED_SECRET_HEADERS`: Comma-separated header names to check. Default includes `X-Internal-Secret,Accepted-Secret,X-Accepted-Secret`.
- Files:
  - `app/Http/Middleware/GatewaySecret.php`: Validates the header using constant-time compare against any configured secret.
  - `config/micro.php > security.gateway`: `accepted_secrets` and `header_names` read from env.
- Usage:
  - Add `->middleware(['gateway.secret','auth.jwt'])` to routes to require both checks.
  - Example routes provided under `/api/v1/secure-gateway/*`.
  - Examples:
    - Disable: `ACCEPTED_SECRETS=` (no enforcement; rely on JWT/other auth)
    - Single: `ACCEPTED_SECRETS=secret-bff`
    - Multiple: `ACCEPTED_SECRETS=secret-bff,secret-gateway,secret-service-x`
    - Single header standard: `ACCEPTED_SECRET_HEADERS=X-Internal-Secret`

**Resilient HTTP (Outbound)**
- Why: External APIs fail transiently. Retries, jitter, and circuit breaker reduce user‑visible errors.
- Files:
  - `app/Support/Http/ResilientHttpClient.php`: Wrapper around Laravel HTTP with resilience policies.
  - `app/Clients/ExampleApiClient.php`: Example typed wrapper per target.
  - `config/micro.php:9`: Defaults under `http.defaults`; per‑target under `http.targets`.
- Key Options (`config/micro.php > http.defaults`):
  - `timeout_ms`: Per request timeout.
  - `retries`, `retry_base_ms`, `retry_max_ms`, `jitter`: Backoff behavior.
  - `breaker_failure_threshold`, `breaker_reset_ms`: Simple circuit breaker.
  - `hedge.enabled`, `hedge.delay_ms`: Optional parallel hedged requests.
- OAuth2 & Audience (`config/micro.php > http.oauth2`):
  - `OAUTH2_TOKEN_URL`, `OAUTH2_CLIENT_ID`, `OAUTH2_CLIENT_SECRET`, `OAUTH2_SCOPES`, `OAUTH2_AUDIENCE`, cache TTL.
- mTLS (`config/micro.php > http.mtls`):
  - `MTLS_CA_PATH`, `MTLS_CERT_PATH`, `MTLS_KEY_PATH`, `MTLS_KEY_PASSPHRASE`.
- Test Quickly:
  - Create a small route or tinker: `php artisan tinker` then instantiate `new App\Support\Http\ResilientHttpClient('example')` and call `$client->get('/path')` after setting `EXAMPLE_API_BASE_URL`.
  - Adjust `HTTP_DEFAULT_RETRIES` and observe logs on intermittent failures.

**Messaging (RabbitMQ)**
- Why: Decouple operations and process asynchronously.
- Files:
  - `config/queue.php`: RabbitMQ connection, DLX/parking‑lot options.
  - `app/Messaging/Publisher.php`: Publishes AMQP messages with headers.
  - `app/Jobs/ExampleEventConsumer.php`: Example consumer job.
- Env Options (excerpt):
  - `RABBITMQ_*`: Host, vhost, exchange, queue, DLX, SSL verification settings.
- Test Locally:
  - Publish via CLI: `php artisan mq:publish-example --producer=amqp` (direct) or `--producer=queue` (queue job).
  - Consume: `php artisan queue:work rabbitmq --sleep=1 --tries=5`.
  - Tip: bind your queue to the exchange with expected routing keys (e.g., `users.*`).

**Observability**
- Why: Know health and performance at a glance.
- Health: `GET /api/v1/health` checks app/DB readiness. Duplicate under `/api/v1/secure/health`.
- Metrics: `GET /api/v1/metrics` returns Prometheus exposition format (scraped by Prometheus, visualized in Grafana) using the in‑memory registry from `AppServiceProvider`.
- Logs & Correlation:
  - Correlation IDs propagate via headers; JSON logs include correlation context.
  - Customize formatters/handlers in `config/logging.php`.

**Feature Flags**
- Why: Gradually roll out changes and toggle behavior safely.
- Files:
  - `app/FeatureFlags/*`: Interface and default array driver.
  - `config/micro.php > feature_flags`: In‑memory flags for dev.
- Test:
  - Set a flag in `config/micro.php` (array driver), e.g., `'example.canary' => true`.
  - Check in code: `app(\App\FeatureFlags\FeatureFlagClient::class)->enabled('example.canary')`.

**Field Encryption**
- Why: Protect sensitive fields at rest with app‑level encryption and key rotation.
- Files:
  - `app/Security/FieldEncryption.php`: Encrypt/decrypt with versioned keys.
  - `config/micro.php > security.field_encryption`: Active version and key list.
- Test:
  - Add a key version in config, set `active_version`, call `FieldEncryption::encrypt('data')` then `decrypt(...)`.

**OpenAPI / Swagger**
- Why: Document your API for discoverability and integration.
- Generate: `php artisan l5-swagger:generate` then open `http://localhost:8000/api/documentation`.
- Security: Click “Authorize” and paste `Bearer <your-jwt>` to try secured endpoints.

**API Endpoints (Map)**
- Users: `GET/POST /api/v1/users`, `GET/PUT/DELETE /api/v1/users/{id}`; secured versions under `/api/v1/secure/...`.
- Health: `GET /api/v1/health` and `/api/v1/secure/health`.
- Metrics: `GET /api/v1/metrics` and `/api/v1/secure/metrics`.
- Samples: `GET /api/v1/widgets/{id}`, `POST /api/v1/test/validation` (+ secured clones).

**Troubleshooting**
- JWT 401:
  - Check `.env` `AUTH_*` values; ensure token `iss`/`aud` match.
  - Inspect `WWW-Authenticate` header for reason; set `APP_DEBUG=true` to include `reason` in JSON.
  - Clear caches after env changes: `php artisan config:clear && php artisan cache:clear`.
- RabbitMQ:
  - Verify exchange/queue bindings and credentials. Ensure `queue:work` is running.
- Swagger UI empty:
  - Regenerate spec: `php artisan l5-swagger:generate` and refresh the page.

**CI/CD Hints**
- Lint: `./vendor/bin/pint`
- Tests: `php artisan test --coverage-text`
- OpenAPI artifact: publish `storage/api-docs` from CI.

Notes
- Keep secrets out of Git. Use environment variables or secret stores.
- For production, consider Redis/APCu for metrics storage and a persistent cache for OAuth2/JWKS.

Docker Usage (All Features)

Run the stack
- Build and start: `docker compose up --build -d`
- App (Nginx): http://localhost:8080
- Logs: `docker compose logs -f app` (PHP-FPM), `docker compose logs -f web` (Nginx)
- Tests: `docker compose exec app php artisan test`
- Tinker REPL: `docker compose exec app php artisan tinker`

RabbitMQ
- Management UI: http://localhost:15672 (guest/guest)
- Publish example (AMQP): `docker compose exec app php artisan mq:publish-example --producer=amqp`
- Publish via queue: `docker compose exec app php artisan mq:publish-example --producer=queue`
- Start worker: `docker compose exec app php artisan queue:work rabbitmq --sleep=1 --tries=5`

OpenAPI / Swagger
- Generate docs: `docker compose exec app php artisan l5-swagger:generate`
- Open UI: http://localhost:8080/api/documentation

Grafana (Monitoring)
- Run Prometheus (scraper): `docker run -p 9090:9090 -v $PWD/prom.yml:/etc/prometheus/prometheus.yml prom/prometheus`
  and Grafana (dashboards): `docker run -p 3000:3000 grafana/grafana`
- Example `prom.yml`:

```
global:
  scrape_interval: 15s
scrape_configs:
  - job_name: 'laravel-service'
    metrics_path: /api/v1/metrics
    static_configs:
      - targets: ['host.docker.internal:8080']
# If Prometheus runs in the same docker-compose network, target nginx by name:
#   - targets: ['web:80']
```

Add custom metrics in code

```
$registry = app(\Prometheus\CollectorRegistry::class);
$counter = $registry->getOrRegisterCounter('app', 'users_created_total', 'Total users created');
$counter->inc();
```

JWT (Local testing)
- Generate RSA keys (host or container):
  - `openssl genrsa -out storage/keys/dev-jwt.key 2048`
  - `openssl rsa -in storage/keys/dev-jwt.key -pubout -out storage/keys/dev-jwt.pub`
- Create `storage/keys/jwks.json` from the public key (`kid` e.g., `dev-key-1`).
- Serve JWKS: `php -S 127.0.0.1:8001 -t storage/keys`
- Configure `.env`:
  - `AUTH_JWKS_URL=http://host.docker.internal:8001/jwks.json`
  - `AUTH_EXPECTED_ISS=http://localhost`
  - `AUTH_EXPECTED_AUD=local-api`
- Call a secured route with an RS256 token:
  - `curl -i -H "Authorization: Bearer <token>" http://localhost:8080/api/v1/secure/health`

Resilient HTTP demo
- Set `EXAMPLE_API_BASE_URL=https://httpbin.org` in `.env`.
- Try via Tinker:
  - `docker compose exec app php artisan tinker`
  - `>>> $c = new App\Support\Http\ResilientHttpClient('example');`
  - `>>> $c->get('/status/200');`

Database
- MySQL container is exposed on `localhost:3307` (user `app`, pass `app`, DB `laravel`).
- Optionally switch from SQLite to MySQL by updating `.env` and running migrations inside the container.

**Feature Flags (How-To)**
- Why: Gradually roll out changes and toggle behavior safely without redeploys.

- What it is:
  - Interface: `app/FeatureFlags/FeatureFlagClient.php` exposes `enabled(string $flag, array $context = []): bool`.
  - Default driver: `app/FeatureFlags/ArrayFeatureFlags.php` reads flags from config (simple on/off).
  - Config root: `config/micro.php > feature_flags`.

- Use in code:
  - Inject the client (preferred):
    ```php
    use App\FeatureFlags\FeatureFlagClient;

    class UsersController extends Controller
    {
        public function index(FeatureFlagClient $flags)
        {
            if ($flags->enabled('users.new-list-view')) {
                // new behavior
            } else {
                // old behavior
            }
        }
    }
    ```
  - Or resolve on demand:
    ```php
    $flags = app(\App\FeatureFlags\FeatureFlagClient::class);
    if ($flags->enabled('example.canary')) { /* ... */ }
    ```

- Local/Dev (simple on/off):
  - Set driver in `.env`: `FEATURE_FLAGS_DRIVER=array`.
  - Define flags in `config/micro.php` under `feature_flags.drivers.array.flags`, e.g.:
    ```php
    'feature_flags' => [
        'driver' => env('FEATURE_FLAGS_DRIVER', 'array'),
        'drivers' => [
            'array' => [
                'flags' => [
                    'example.canary' => (bool) env('FF_EXAMPLE_CANARY', false),
                    'users.new-list-view' => (bool) env('FF_USERS_NEW_LIST_VIEW', false),
                ],
            ],
        ],
    ],
    ```
  - Toggle via `.env`:
    ```env
    FF_EXAMPLE_CANARY=true
    FF_USERS_NEW_LIST_VIEW=false
    ```
  - Apply changes: `php artisan config:clear` (or `config:cache`).

- QA/Staging (env-driven):
  - Keep `FEATURE_FLAGS_DRIVER=array` and flip flags through environment variables (no code change).
  - Example `.env.staging`:
    ```env
    FEATURE_FLAGS_DRIVER=array
    FF_USERS_NEW_LIST_VIEW=true
    ```

- Production (best practices):
  - Default risky features to off; enable via env during rollout.
  - Add observability around flag usage:
    ```php
    use Illuminate\Support\Facades\Log;
    $reg = app(\Prometheus\CollectorRegistry::class);
    $ctr = $reg->getOrRegisterCounter('app', 'flag_users_new_list_on_total', 'Times new list was used');

    if ($flags->enabled('users.new-list-view')) {
        Log::info('flag.on', ['flag' => 'users.new-list-view']);
        $ctr->inc();
    }
    ```
  - Keep a kill switch flag for quick rollback.

- Testing flags:
  - Override config in tests:
    ```php
    config(['micro.feature_flags.drivers.array.flags.example.canary' => true]);
    ```
  - Or bind a fake client:
    ```php
    $this->app->bind(\App\FeatureFlags\FeatureFlagClient::class, function () {
        return new class implements \App\FeatureFlags\FeatureFlagClient {
            public function enabled(string $flag, array $ctx = []): bool { return $flag === 'example.canary'; }
        };
    });
    ```

- Providers (future: LaunchDarkly/Unleash):
  - Stubs exist in `config/micro.php` for provider settings. To integrate, implement an adapter that satisfies `FeatureFlagClient` and wire it in `AppServiceProvider` based on `FEATURE_FLAGS_DRIVER`.
  - LaunchDarkly env (example):
    ```env
    FEATURE_FLAGS_DRIVER=launchdarkly
    FEATURE_FLAGS_LAUNCHDARKLY_SDK_KEY=your-sdk-key
    ```
  - Unleash env (example):
    ```env
    FEATURE_FLAGS_DRIVER=unleash
    FEATURE_FLAGS_UNLEASH_URL=https://unleash.example.com/
    FEATURE_FLAGS_UNLEASH_TOKEN=your-token
    ```

**JWT Auth (How-To)**
- Why: Authenticate callers using industry‑standard Bearer tokens with public keys (JWKS).
- Configure `.env`:
  - `AUTH_JWKS_URL=https://login.microsoftonline.com/<tenant_id>/discovery/v2.0/keys`
  - `AUTH_EXPECTED_ISS=https://login.microsoftonline.com/<tenant_id>/v2.0`
  - `AUTH_EXPECTED_AUD=<client_id or api://app_id>`
  - Optional: `AUTH_ACCEPTED_ALGS=RS256`, `AUTH_LEEWAY_SECONDS=60`, `AUTH_PREFER_EXP_REASON=false`
- Apply and test:
  - `php artisan config:clear && php artisan cache:clear`
  - No token: `curl -i http://localhost:8080/api/v1/secure/health` → 401 with `WWW-Authenticate`
  - Valid token: `curl -i -H "Authorization: Bearer <token>" http://localhost:8080/api/v1/secure/health` → 200
- Scopes on routes:
  - Example: `Route::get('/secure/users', ...)->middleware('auth.jwt:example.read');`
  - Token claims checked: `scope` (space‑sep), `scp` (Azure), and `roles`.
- Troubleshooting:
  - Enable `APP_DEBUG=true`; check `storage/logs/laravel.log` for “JWT header parsed”, “JWKS loaded”, and failure details including `exp/nbf/iat`.

**Resilient HTTP (How-To)**
- Why: Make outbound HTTP calls robust with timeouts, retries, jitter, breaker, OAuth2, and mTLS.
- Configure defaults (`.env`):
  - `HTTP_DEFAULT_TIMEOUT_MS=2000`, `HTTP_DEFAULT_RETRIES=2`, `HTTP_DEFAULT_RETRY_BASE_MS=200`, `HTTP_DEFAULT_RETRY_MAX_MS=1500`, `HTTP_DEFAULT_JITTER=true`
  - `HTTP_DEFAULT_BREAKER_FAILURE_THRESHOLD=5`, `HTTP_DEFAULT_BREAKER_RESET_MS=30000`
- Per‑target example (`.env`):
  - `EXAMPLE_API_BASE_URL=https://httpbin.org`
  - Optional: `EXAMPLE_API_TIMEOUT_MS`, `EXAMPLE_API_RETRIES`, `EXAMPLE_API_SCOPES`
- Use in code:
  - Generic: `new App\Support\Http\ResilientHttpClient('example')`
  - Typed: `new App\Clients\ExampleApiClient()`
  - Tinker:
    - `docker compose exec app php artisan tinker`
    - `>>> $c = new App\Support\Http\ResilientHttpClient('example');`
    - `>>> $c->get('/status/200');`
- OAuth2 client credentials:
  - Set: `OAUTH2_TOKEN_URL`, `OAUTH2_CLIENT_ID`, `OAUTH2_CLIENT_SECRET`, `OAUTH2_SCOPES` (e.g., `api://target/.default`)
  - The client fetches/caches the token and sends `Authorization: Bearer <token>`.
- mTLS:
  - Set: `MTLS_CA_PATH`, `MTLS_CERT_PATH`, `MTLS_KEY_PATH`, `MTLS_KEY_PASSPHRASE` to use client certificates.

**Messaging (How-To)**
- Why: Publish/consume events asynchronously via RabbitMQ.
- Configure `.env`:
  - `QUEUE_CONNECTION=rabbitmq` and `RABBITMQ_*` (host, port, vhost, exchange, routing key, queue).
- Start and open UI:
  - `docker compose up -d` and open http://localhost:15672 (guest/guest)
- Publish examples:
  - Direct AMQP: `docker compose exec app php artisan mq:publish-example --producer=amqp`
  - Queue job: `docker compose exec app php artisan mq:publish-example --producer=queue`
- Consume:
  - `docker compose exec app php artisan queue:work rabbitmq --sleep=1 --tries=5`
- Publish in code:
  - `(new App\Messaging\Publisher())->publish('users.created', $payload, $headers);`
- Tip: Bind your queue to the exchange with routing keys (e.g., `users.*`).

**Observability (How-To)**
- Health:
  - `curl http://localhost:8080/api/v1/health` → checks app/DB readiness.
  - Extend checks in `app/Http/Controllers/HealthController.php`.
- Grafana & Prometheus:
  - Run: `docker run -p 9090:9090 -v $PWD/prom.yml:/etc/prometheus/prometheus.yml prom/prometheus`
  - prom.yml:
    ```
    global:
      scrape_interval: 15s
    scrape_configs:
      - job_name: 'laravel-service'
        metrics_path: /api/v1/metrics
        static_configs:
          - targets: ['host.docker.internal:8080']
    ```
  - Custom metric:
    ```php
    $reg = app(\Prometheus\CollectorRegistry::class);
    $ctr = $reg->getOrRegisterCounter('app', 'users_created_total', 'Total users created');
    $ctr->inc();
    ```
  - In Grafana: add Prometheus as a data source (URL: your Prometheus server), then create a dashboard using the metric `app_users_created_total`.
- Logging:
  - Set `LOG_CHANNEL=stack`, `LOG_STACK=stderr,daily`, `LOG_LEVEL=info` in prod.
  - Format options via `LOG_FORMAT`:
    - `json` (default): structured JSON, best for log aggregation.
    - `line`: human-friendly, Serilog-style single-line format.
  - Example `LOG_FORMAT=line` output:
    ```
    [2025-03-21 13:49:49.782 -07:00|INF|abc123 (req-42)|app] NEW PROCESS STARTING ----------
    [2025-03-21 13:49:53.375 -07:00|WRN|abc123 ()|Host] Log incoming http headers? 'true'.
    [2025-03-21 13:49:54.001 -07:00|ERR|abc123 (req-42)|Http] Downstream call failed {"status":502,"target":"example"}
    ```
    - Format: `[timestamp|LEVEL|correlation_id (request_id)|channel] message {context}`
    - `timestamp`: honors `APP_TIMEZONE` (e.g., `America/Los_Angeles`).
    - `LEVEL`: short codes DBG/INF/NOT/WRN/ERR/CRT/ALR/EMG.
    - `correlation_id` and `request_id`: injected by `CorrelationProcessor` for traceability.
    - `channel`: Monolog channel (often the Laravel channel or a logical source).
    - `{context}`: optional JSON-encoded context appended when present.
  - Use: `Log::emergency|alert|critical|error|warning|notice|info|debug()`.
  - Implementation: `App\Logging\TapJsonFormatter` selects JSON vs line and attaches correlation extras.
  - Tip: set `APP_TIMEZONE` for correct offset, and keep `LOG_LEVEL=info` (or `warning`) in production to reduce noise.
  - Request/Response logging:
    - Enabled globally for API routes via middleware `App\Http\Middleware\RequestResponseLogger`.
    - Env toggles:
      - `LOG_HTTP_ALL_ROUTES=false` (set true to also log non-API routes)
      - `LOG_HTTP_REQUEST_BODY=false` (log small textual request bodies)
      - `LOG_HTTP_RESPONSE_BODY=false` (log small textual response bodies)
      - `LOG_HTTP_MAX_BODY=2048` (max bytes to log for bodies)
    - Each request logs `http.request` with method, path, headers (safely redacted), and optional body; response logs `http.response` with status and duration_ms.

**OpenAPI / Swagger (How-To)**
- Generate docs/UI:
  - `docker compose exec app php artisan l5-swagger:generate`
  - Open http://localhost:8080/api/documentation, click “Authorize” to test secured endpoints.
- Annotate endpoints with `@OA\Get`, `@OA\Post`, etc. Secured operations include `security={{"bearerAuth": {}}}`.

**Field Encryption (How-To)**
- Why: Encrypt sensitive fields with key rotation.
- Configure keys in `config/micro.php`:
  ```php
  'field_encryption' => [
      'active_version' => env('PII_KEY_ACTIVE_VERSION', 'v1'),
      'keys' => [
          'v1' => env('PII_KEY_V1'),
          // 'v2' => env('PII_KEY_V2'),
      ],
  ],
  ```
- Use in code:
  ```php
  use App\Security\FieldEncryption;
  $cipher = FieldEncryption::encrypt('secret data');
  $plain = FieldEncryption::decrypt($cipher);
  ```
- Tips:
  - Store encrypted values as `TEXT`/`VARCHAR`; keep historical keys configured to decrypt legacy data after rotation.
