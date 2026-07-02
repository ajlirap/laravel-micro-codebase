# Azure and Local Aspire Runtime Plan

This repository uses two runtime targets:

- Local development: Aspire starts Docker containers and emulators.
- Dev, QA, UAT, and production: Azure managed services.

## Local Development

Run local infrastructure with Aspire:

```bash
cd aspire/LaravelMicro.AppHost
dotnet run
```

The AppHost models:

- Laravel API container built from the repository Dockerfile
- PostgreSQL container
- Redis container
- Azurite for Blob Storage
- Azure Service Bus emulator plus its SQL Edge dependency
- Local Aspire secret parameters as the Azure Key Vault substitute
- OpenTelemetry Collector

The local stack intentionally mirrors Azure service categories while keeping all state and traffic on the developer machine.

### Aspire to Azure Resource Map

| Capability | Local Aspire resource | Dev/QA/UAT/Prod Azure resource |
| --- | --- | --- |
| API gateway | Not run locally by default; call Laravel/Nginx directly | Azure API Management |
| App runtime | Laravel container built from `Dockerfile` | Azure Container Apps |
| Image registry | Local Docker image cache | Azure Container Registry |
| Database | PostgreSQL container | Azure Database for PostgreSQL |
| Messaging | Azure Service Bus emulator plus SQL Edge | Azure Service Bus |
| Cache/session | Redis container | Azure Cache for Redis |
| Secrets | Aspire secret parameters | Azure Key Vault |
| Blob storage | Azurite | Azure Blob Storage |
| Metrics/log platform | Aspire dashboard plus OTEL Collector debug exporter | Azure Monitor |
| Application telemetry | Aspire dashboard plus OTEL Collector | Application Insights |
| Central logs | Local container logs plus OTEL Collector | Log Analytics |
| Telemetry pipeline | OpenTelemetry Collector container | OpenTelemetry Collector in Azure Container Apps |

### Local Secret Handling

There is no official local Azure Key Vault emulator. For local development, Aspire secret parameters represent the same values that Azure Key Vault provides in shared environments.

Local secret parameters:

- `app-key`
- `postgres-password`
- `redis-password`
- `storage-connection-string`
- `servicebus-key`
- `servicebus-sql-password`

Keep real local values out of source control by overriding them with user secrets, environment variables, or local Aspire configuration on each developer machine.

## Shared Azure Environments

Dev, QA, UAT, and production should use Azure resources:

- Azure API Management
- Azure Container Apps
- Azure Container Registry
- Azure Database for PostgreSQL
- Azure Service Bus
- Azure Cache for Redis
- Azure Key Vault
- Azure Blob Storage
- Azure Monitor
- Application Insights
- Log Analytics
- OpenTelemetry Collector

Use Infrastructure as Code for these environments. Bicep is a good fit because this is Azure-only infrastructure.

## Azure Key Vault

Each shared environment should have its own Key Vault:

- `kv-laravel-micro-dev`
- `kv-laravel-micro-qa`
- `kv-laravel-micro-uat`
- `kv-laravel-micro-prod`

Container Apps should use managed identity to read secrets from Key Vault. Do not bake secrets into container images, Bicep parameter files, GitHub Actions logs, or committed `.env` files.

Recommended Key Vault secrets:

- `app-key`
- `postgres-password`
- `redis-password`
- `servicebus-connection-string`
- `storage-connection-string`
- `oauth2-client-secret`
- `jwt-accepted-audience`
- `accepted-internal-secrets`
- `field-encryption-key-v1`

Recommended access model:

- Container App API identity: read only the runtime secrets it needs.
- Container App worker identity: read queue, database, Redis, storage, and app secrets.
- CI/CD identity: set/update secrets only where required.
- Human users: least privilege, preferably through groups.

## Environment Contract

The application should keep the same environment variable names across local and Azure environments. Only values change.

```env
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=pgsql
DB_HOST=<postgres-host>
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=<postgres-user>
DB_PASSWORD=<key-vault-secret>
CACHE_STORE=redis
SESSION_DRIVER=redis
REDIS_HOST=<redis-host>
REDIS_PORT=6380
REDIS_PASSWORD=<key-vault-secret>
QUEUE_CONNECTION=azure-servicebus
AZURE_SERVICEBUS_QUEUE=app.queue
AZURE_SERVICEBUS_CONNECTION_STRING=<key-vault-secret-or-managed-identity>
FILESYSTEM_DISK=azure
AZURE_STORAGE_ACCOUNT=<storage-account>
AZURE_STORAGE_CONTAINER=app
AZURE_STORAGE_CONNECTION_STRING=<key-vault-secret-or-managed-identity>
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318
```

## Implementation Notes

- The Laravel app includes an `azure-servicebus` queue connector for Azure Service Bus REST publishing/receiving.
- The Laravel app includes an `azure` filesystem disk backed by the Azure Blob REST API and Azurite local development storage.
- OpenTelemetry spans are emitted for HTTP requests, database queries, and Azure Service Bus queue publish/receive operations.
- The health endpoint is now driver-aware and no longer assumes RabbitMQ for every environment.
- The Docker image includes the Redis PHP extension for Azure Cache for Redis compatibility.
- Aspire is for local orchestration. Dev, QA, UAT, and production should deploy with CI/CD to Azure Container Apps.
