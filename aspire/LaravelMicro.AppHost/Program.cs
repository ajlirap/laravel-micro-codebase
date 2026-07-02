var builder = DistributedApplication.CreateBuilder(args);

// Local Aspire uses secret parameters as the developer-machine substitute for
// Azure Key Vault. Dev, QA, UAT, and production should source these values from
// Azure Key Vault through Container Apps secret references or managed identity.
var appKey = builder.AddParameter("app-key", secret: true);
var postgresPassword = builder.AddParameter("postgres-password", secret: true);
var redisPassword = builder.AddParameter("redis-password", secret: true);
var storageConnectionString = builder.AddParameter("storage-connection-string", secret: true);
var serviceBusKey = builder.AddParameter("servicebus-key", secret: true);
var serviceBusSqlPassword = builder.AddParameter("servicebus-sql-password", secret: true);
var applicationInsightsConnectionString = builder.AddParameter("applicationinsights-connection-string", secret: true);

var postgres = builder.AddContainer("postgres", "postgres", "16-alpine")
    .WithEnvironment("POSTGRES_DB", "laravel")
    .WithEnvironment("POSTGRES_USER", "app")
    .WithEnvironment("POSTGRES_PASSWORD", postgresPassword)
    .WithEndpoint(port: 5432, targetPort: 5432, name: "tcp")
    .WithVolume("laravel-postgres-data", "/var/lib/postgresql/data");

var redis = builder.AddContainer("redis", "redis", "7-alpine")
    .WithEndpoint(port: 6379, targetPort: 6379, name: "tcp")
    .WithVolume("laravel-redis-data", "/data");

var azurite = builder.AddContainer("azurite", "mcr.microsoft.com/azure-storage/azurite", "latest")
    .WithArgs("azurite", "--blobHost", "0.0.0.0", "--queueHost", "0.0.0.0", "--tableHost", "0.0.0.0")
    .WithEndpoint(port: 10000, targetPort: 10000, name: "blob")
    .WithEndpoint(port: 10001, targetPort: 10001, name: "queue")
    .WithEndpoint(port: 10002, targetPort: 10002, name: "table")
    .WithVolume("laravel-azurite-data", "/data");

var sqlEdge = builder.AddContainer("sqledge", "mcr.microsoft.com/azure-sql-edge", "latest")
    .WithEnvironment("ACCEPT_EULA", "Y")
    .WithEnvironment("MSSQL_SA_PASSWORD", serviceBusSqlPassword);

var serviceBus = builder.AddContainer("servicebus-emulator", "mcr.microsoft.com/azure-messaging/servicebus-emulator", "latest")
    .WithBindMount("../../docker/servicebus/config.json", "/ServiceBus_Emulator/ConfigFiles/Config.json", isReadOnly: true)
    .WithEnvironment("SQL_SERVER", "sqledge")
    .WithEnvironment("MSSQL_SA_PASSWORD", serviceBusSqlPassword)
    .WithEnvironment("ACCEPT_EULA", "Y")
    .WithEnvironment("SQL_WAIT_INTERVAL", "15")
    .WithEnvironment("EMULATOR_HTTP_PORT", "5300")
    .WithEndpoint(port: 5672, targetPort: 5672, name: "amqp")
    .WithEndpoint(port: 5300, targetPort: 5300, name: "http")
    .WaitFor(sqlEdge);

var otelCollector = builder.AddContainer("otel-collector", "otel/opentelemetry-collector-contrib", "0.104.0")
    .WithArgs("--config=/etc/otelcol-contrib/config.yaml")
    .WithBindMount("../../docker/otelcol/config.yaml", "/etc/otelcol-contrib/config.yaml", isReadOnly: true)
    .WithEnvironment("APPLICATIONINSIGHTS_CONNECTION_STRING", applicationInsightsConnectionString)
    .WithEndpoint(port: 4317, targetPort: 4317, name: "otlp-grpc")
    .WithEndpoint(port: 4318, targetPort: 4318, name: "otlp-http");

builder.AddDockerfile("laravel-api", "../..")
    .WithEnvironment("APP_ENV", "local")
    .WithEnvironment("APP_DEBUG", "true")
    .WithEnvironment("APP_KEY", appKey)
    .WithEnvironment("APP_URL", "http://localhost:8081")
    .WithEnvironment("LOG_CHANNEL", "stack")
    .WithEnvironment("LOG_STACK", "single")
    .WithEnvironment("DB_CONNECTION", "pgsql")
    .WithEnvironment("DB_HOST", "postgres")
    .WithEnvironment("DB_PORT", "5432")
    .WithEnvironment("DB_DATABASE", "laravel")
    .WithEnvironment("DB_USERNAME", "app")
    .WithEnvironment("DB_PASSWORD", postgresPassword)
    .WithEnvironment("CACHE_STORE", "redis")
    .WithEnvironment("SESSION_DRIVER", "redis")
    .WithEnvironment("REDIS_HOST", "redis")
    .WithEnvironment("REDIS_PASSWORD", redisPassword)
    .WithEnvironment("QUEUE_CONNECTION", "azure-servicebus")
    .WithEnvironment("AZURE_SERVICEBUS_QUEUE", "app.queue")
    .WithEnvironment("AZURE_SERVICEBUS_CONNECTION_STRING", serviceBusKey)
    .WithEnvironment("FILESYSTEM_DISK", "azure")
    .WithEnvironment("AZURE_STORAGE_CONNECTION_STRING", storageConnectionString)
    .WithEnvironment("AZURE_STORAGE_CONTAINER", "app")
    .WithEnvironment("OTEL_ENABLED", "true")
    .WithEnvironment("OTEL_EXPORTER_OTLP_ENDPOINT", "http://otel-collector:4318")
    .WithEnvironment("OTEL_EXPORTER_OTLP_PROTOCOL", "http/protobuf")
    .WithEnvironment("OTEL_TRACES_EXPORTER", "otlp")
    .WithEnvironment("OTEL_PHP_TRACES_PROCESSOR", "batch")
    .WithEnvironment("OTEL_PROPAGATORS", "tracecontext,baggage")
    .WithEnvironment("OTEL_DATABASE_SPANS", "true")
    .WithEnvironment("OTEL_QUEUE_SPANS", "true")
    .WithHttpEndpoint(port: 9000, targetPort: 9000)
    .WaitFor(postgres)
    .WaitFor(redis)
    .WaitFor(azurite)
    .WaitFor(sqlEdge)
    .WaitFor(serviceBus)
    .WaitFor(otelCollector);

builder.Build().Run();
