<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Database;

use PhpSoftBox\Config\Config;
use PhpSoftBox\Database\Configurator\DatabaseFactory;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\Exception\ConfigurationException;
use PhpSoftBox\MultiTenant\Contracts\TenantConnectionSwitcherInterface;
use RuntimeException;

use function array_key_exists;
use function array_pop;
use function count;
use function explode;
use function in_array;
use function is_array;
use function is_string;
use function str_contains;
use function str_starts_with;
use function trim;

final class TenantAwareConnectionManager implements ConnectionManagerInterface, TenantConnectionSwitcherInterface
{
    /** @var list<?string> */
    private array $stack = [];

    private ?string $activeTenantDsn = null;

    /** @var array<string, ConnectionInterface> */
    private array $tenantConnections = [];

    public function __construct(
        private readonly ConnectionManagerInterface $baseManager,
        private readonly Config $config,
        private readonly string $tenantConnectionAlias = 'tenant',
    ) {
    }

    public function activate(string $dsn): void
    {
        $dsn = trim($dsn);
        if ($dsn === '') {
            throw new RuntimeException('Tenant DSN не может быть пустым.');
        }

        $this->stack[]           = $this->activeTenantDsn;
        $this->activeTenantDsn   = $dsn;
        $this->tenantConnections = [];
    }

    public function deactivate(): void
    {
        $this->activeTenantDsn   = count($this->stack) > 0 ? array_pop($this->stack) : null;
        $this->tenantConnections = [];
    }

    public function activeDsn(): ?string
    {
        return $this->activeTenantDsn;
    }

    public function connection(string $name = 'default'): ConnectionInterface
    {
        if (!$this->shouldOverride($name)) {
            return $this->baseManager->connection($name);
        }

        return $this->tenantConnection($name);
    }

    public function read(string $name = 'default'): ConnectionInterface
    {
        if (!$this->shouldOverride($name)) {
            return $this->baseManager->read($name);
        }

        try {
            return $this->tenantConnection($name . '.read');
        } catch (ConfigurationException) {
            return $this->tenantConnection($name);
        }
    }

    public function write(string $name = 'default'): ConnectionInterface
    {
        if (!$this->shouldOverride($name)) {
            return $this->baseManager->write($name);
        }

        try {
            return $this->tenantConnection($name . '.write');
        } catch (ConfigurationException) {
            return $this->tenantConnection($name);
        }
    }

    private function shouldOverride(string $name): bool
    {
        if ($this->activeTenantDsn === null) {
            return false;
        }

        $name  = trim($name);
        $alias = trim($this->tenantConnectionAlias);

        if ($name === '' || $alias === '') {
            return false;
        }

        return $name === $alias || str_starts_with($name, $alias . '.');
    }

    private function tenantConnection(string $connectionName): ConnectionInterface
    {
        if ($this->activeTenantDsn === null) {
            throw new RuntimeException('Tenant DSN не активирован.');
        }

        $cacheKey = $connectionName . '|' . $this->activeTenantDsn;
        if (isset($this->tenantConnections[$cacheKey])) {
            return $this->tenantConnections[$cacheKey];
        }

        $factory = new DatabaseFactory(
            $this->runtimeDatabaseConfig($connectionName, $this->activeTenantDsn),
        );

        $this->tenantConnections[$cacheKey] = $factory->create($connectionName);

        return $this->tenantConnections[$cacheKey];
    }

    /**
     * @return array<string, mixed>
     */
    private function runtimeDatabaseConfig(string $connectionName, string $dsn): array
    {
        $databaseConfig = (array) $this->config->get('database', []);
        $connections    = $databaseConfig['connections'] ?? null;
        if (!is_array($connections)) {
            throw new RuntimeException('database.connections должен быть массивом.');
        }

        $resolvedName = $this->resolveConnectionName($connectionName, $connections);

        if (str_contains($resolvedName, '.')) {
            [$group, $role] = explode('.', $resolvedName, 2);

            $groupConfig = $connections[$group] ?? null;
            if (!is_array($groupConfig)) {
                throw new RuntimeException('Не найдена группа подключения: ' . $group);
            }

            $roleConfig = $groupConfig[$role] ?? null;
            if (!is_array($roleConfig)) {
                if (in_array($role, ['read', 'write'], true) && is_string($groupConfig['dsn'] ?? null)) {
                    $roleConfig = [
                        'dsn'      => (string) $groupConfig['dsn'],
                        'readonly' => $role === 'read',
                    ];
                } else {
                    throw new RuntimeException('Не найден channel подключения: ' . $resolvedName);
                }
            }

            $roleConfig['dsn']             = $dsn;
            $groupConfig[$role]            = $roleConfig;
            $connections[$group]           = $groupConfig;
            $databaseConfig['connections'] = $connections;

            return $databaseConfig;
        }

        $connectionConfig = $connections[$resolvedName] ?? null;
        if (!is_array($connectionConfig)) {
            throw new RuntimeException('Не найдено подключение: ' . $resolvedName);
        }

        if (array_key_exists('dsn', $connectionConfig)) {
            $connectionConfig['dsn'] = $dsn;
        } else {
            $changed = false;
            foreach (['read', 'write'] as $role) {
                $roleConfig = $connectionConfig[$role] ?? null;
                if (!is_array($roleConfig)) {
                    continue;
                }

                $roleConfig['dsn']       = $dsn;
                $connectionConfig[$role] = $roleConfig;
                $changed                 = true;
            }

            if (!$changed) {
                throw new RuntimeException('В подключении "' . $resolvedName . '" не найден DSN.');
            }
        }

        $connections[$resolvedName]    = $connectionConfig;
        $databaseConfig['connections'] = $connections;

        return $databaseConfig;
    }

    /**
     * @param array<string, mixed> $connections
     */
    private function resolveConnectionName(string $connectionName, array $connections): string
    {
        if ($connectionName === 'default' && is_string($connections['default'] ?? null)) {
            return (string) $connections['default'];
        }

        if (str_contains($connectionName, '.')) {
            [$group, $role] = explode('.', $connectionName, 2);

            if ($group === 'default' && is_string($connections['default'] ?? null)) {
                return (string) $connections['default'] . '.' . $role;
            }
        }

        return $connectionName;
    }
}
