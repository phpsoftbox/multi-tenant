<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Mongo;

use MongoDB\Collection;
use MongoDB\Database;
use PhpSoftBox\Config\Config;
use PhpSoftBox\MongoDb\Connection\MongoConnectionManagerInterface;
use PhpSoftBox\MultiTenant\Context\TenantContextResolver;
use RuntimeException;

use function is_array;
use function is_string;
use function trim;

final readonly class TenantMongoDatabaseProvider
{
    public function __construct(
        private MongoConnectionManagerInterface $mongo,
        private TenantContextResolver $tenantResolver,
        private Config $config,
    ) {
    }

    public function database(): Database
    {
        return $this->mongo
            ->client($this->resolveConnectionName())
            ->selectDatabase($this->resolveTenantDatabaseName());
    }

    public function collection(string $collection): Collection
    {
        $name = trim($collection);
        if ($name === '') {
            throw new RuntimeException('Mongo collection name must be non-empty string.');
        }

        return $this->database()->selectCollection($name);
    }

    private function resolveConnectionName(): string
    {
        $tenant = $this->tenantResolver->getOrFail();
        $data   = $tenant->data;

        if (is_array($data['mongo'] ?? null) && is_string($data['mongo']['connection'] ?? null)) {
            $connection = trim((string) $data['mongo']['connection']);
            if ($connection !== '') {
                return $connection;
            }
        }

        $tenantConfig = $this->config->get('mongo.tenant', []);
        if (is_array($tenantConfig) && is_string($tenantConfig['template_connection'] ?? null)) {
            $templateConnection = trim((string) $tenantConfig['template_connection']);
            if ($templateConnection !== '') {
                return $templateConnection;
            }
        }

        $connections = (array) $this->config->get('mongo.connections', []);
        $default     = $connections['default'] ?? null;
        if (is_string($default) && trim($default) !== '') {
            return trim($default);
        }

        return 'default';
    }

    private function resolveTenantDatabaseName(): string
    {
        $tenant = $this->tenantResolver->getOrFail();
        $data   = $tenant->data;

        if (is_array($data['mongo'] ?? null) && is_string($data['mongo']['database'] ?? null)) {
            $database = trim((string) $data['mongo']['database']);
            if ($database !== '') {
                return $database;
            }
        }

        $database = trim((string) ($tenant->databaseName ?? ''));
        if ($database !== '') {
            return $database;
        }

        $tenantId = trim((string) ($tenant->id ?? ''));
        if ($tenantId !== '') {
            return $tenantId;
        }

        throw new RuntimeException('Не удалось определить Mongo database для tenant.');
    }
}
