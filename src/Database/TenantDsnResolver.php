<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Database;

use PhpSoftBox\Config\Config;
use PhpSoftBox\Database\Dsn\Dsn;
use PhpSoftBox\Database\Dsn\DsnParser;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use RuntimeException;

use function array_key_exists;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function rawurlencode;
use function trim;

final readonly class TenantDsnResolver
{
    public function __construct(
        private Config $config,
        private string $tenantConnectionAlias = 'tenant',
    ) {
    }

    public function resolve(TenantContext $context): string
    {
        $tenant = $context->tenant();

        $explicitDsn = $this->tenantDataString($tenant, 'database', 'dsn')
            ?? $this->tenantDataString($tenant, 'db', 'dsn')
            ?? $this->tenantDataString($tenant, 'dsn');
        if ($explicitDsn !== null) {
            return $explicitDsn;
        }

        $databaseName = $this->tenantDataString($tenant, 'database', 'name')
            ?? $this->tenantDataString($tenant, 'database_name')
            ?? $this->normalizeString($tenant->databaseName);

        if ($databaseName === null) {
            throw new RuntimeException(
                'Не удалось определить tenant database name: ожидается tenant.databaseName или data.database.name.',
            );
        }

        $baseDsn = new DsnParser()->parse($this->baseTenantDsn());

        return $this->withDatabaseName($baseDsn, $databaseName);
    }

    private function baseTenantDsn(): string
    {
        $connections = $this->config->get('database.connections', []);
        if (!is_array($connections)) {
            throw new RuntimeException('database.connections должен быть массивом.');
        }

        $connection = $connections[$this->tenantConnectionAlias] ?? null;
        if (!is_array($connection)) {
            throw new RuntimeException('Не найдено tenant-подключение: ' . $this->tenantConnectionAlias);
        }

        $dsn = null;

        $writeConfig = $connection['write'] ?? null;
        if (is_array($writeConfig)) {
            $dsn = $writeConfig['dsn'] ?? null;
        }

        if (!is_string($dsn) || trim($dsn) === '') {
            $dsn = $connection['dsn'] ?? null;
        }

        $readConfig = $connection['read'] ?? null;
        if ((!is_string($dsn) || trim($dsn) === '') && is_array($readConfig)) {
            $dsn = $readConfig['dsn'] ?? null;
        }

        if (!is_string($dsn) || trim($dsn) === '') {
            throw new RuntimeException('В tenant-подключении не найден DSN: ' . $this->tenantConnectionAlias);
        }

        return trim($dsn);
    }

    private function withDatabaseName(Dsn $dsn, string $databaseName): string
    {
        if (!in_array($dsn->driver, ['mariadb', 'mysql', 'postgres', 'pgsql'], true)) {
            throw new RuntimeException(
                'Tenant DSN resolver поддерживает только mariadb/mysql/postgres/pgsql. Получен: ' . $dsn->driver,
            );
        }

        if ($dsn->host === null || $dsn->host === '') {
            throw new RuntimeException('Host обязателен в tenant base DSN.');
        }

        $auth = '';
        if ($dsn->user !== null && $dsn->user !== '') {
            $auth = rawurlencode($dsn->user);
            if ($dsn->password !== null) {
                $auth .= ':' . rawurlencode($dsn->password);
            }
            $auth .= '@';
        }

        $port  = $dsn->port !== null ? ':' . $dsn->port : '';
        $query = $this->queryString($dsn->params);

        return $dsn->driver
            . '://'
            . $auth
            . $dsn->host
            . $port
            . '/'
            . rawurlencode($databaseName)
            . $query;
    }

    /**
     * @param array<string, string> $params
     */
    private function queryString(array $params): string
    {
        if ($params === []) {
            return '';
        }

        $pairs = [];
        foreach ($params as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $pairs[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        if ($pairs === []) {
            return '';
        }

        return '?' . implode('&', $pairs);
    }

    private function tenantDataString(TenantDefinition $tenant, string ...$path): ?string
    {
        if ($path === []) {
            return null;
        }

        $cursor = $tenant->data;
        foreach ($path as $key) {
            if (!is_array($cursor) || !array_key_exists($key, $cursor)) {
                return null;
            }

            $cursor = $cursor[$key];
        }

        return $this->normalizeString($cursor);
    }

    private function normalizeString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
