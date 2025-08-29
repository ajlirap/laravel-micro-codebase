<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class DocsAssertCovered extends Command
{
    protected $signature = 'docs:assert-covered {--no-generate : Do not regenerate OpenAPI docs before checking}';
    protected $description = 'Assert that all API controller routes are covered by OpenAPI (Swagger) documentation';

    public function handle(): int
    {
        if (!$this->option('no-generate')) {
            $this->info('Generating OpenAPI docs via l5-swagger:generate ...');
            try {
                Artisan::call('l5-swagger:generate');
            } catch (\Throwable $e) {
                $this->error('Failed to generate OpenAPI docs: '.$e->getMessage());
                return self::FAILURE;
            }
        }

        $specPath = storage_path('api-docs/api-docs.json');
        if (!file_exists($specPath)) {
            $this->error("OpenAPI spec not found at {$specPath}. Run l5-swagger:generate first.");
            return self::FAILURE;
        }

        $json = file_get_contents($specPath);
        $spec = json_decode($json, true);
        if (!is_array($spec)) {
            $this->error('Unable to parse OpenAPI JSON.');
            return self::FAILURE;
        }

        $paths = $spec['paths'] ?? [];
        $oasIndex = [];
        foreach ($paths as $path => $ops) {
            foreach (['get','post','put','patch','delete','options','head'] as $m) {
                if (isset($ops[$m])) {
                    $oasIndex[strtolower($m).' '.$path] = true;
                }
            }
        }

        $routes = app('router')->getRoutes();
        $missing = [];
        foreach ($routes as $route) {
            $uri = $route->uri();
            // Only check API routes
            if (!str_starts_with($uri, 'api/')) {
                continue;
            }

            // Skip known non-business endpoints
            if (in_array($uri, ['api/docs', 'api/documentation', 'api/oauth2-callback'], true)) {
                continue;
            }

            $actionName = method_exists($route, 'getActionName') ? $route->getActionName() : ($route->getAction()['controller'] ?? '');
            if ($actionName === 'Closure' || $actionName === '' || str_contains($actionName, 'Closure')) {
                // Optionally skip closure routes; enforce only controller-based
                continue;
            }

            $methods = array_map('strtolower', $route->methods());
            foreach ($methods as $method) {
                if (in_array($method, ['head','options'], true)) {
                    continue;
                }
                $oasPath = '/'.$uri; // OpenAPI keys are absolute paths
                $key = $method.' '.$oasPath;
                if (!isset($oasIndex[$key])) {
                    $missing[] = [
                        'method' => strtoupper($method),
                        'uri' => $oasPath,
                        'action' => $actionName,
                    ];
                }
            }
        }

        if (!empty($missing)) {
            $this->error('Missing OpenAPI coverage for the following API routes:');
            foreach ($missing as $m) {
                $this->line(sprintf('- [%s] %s -> %s', $m['method'], $m['uri'], $m['action']));
            }
            $this->line('To fix: add @OA annotations to the corresponding controller methods.');
            return self::FAILURE;
        }

        $this->info('All API controller routes are covered by OpenAPI docs.');
        return self::SUCCESS;
    }
}
