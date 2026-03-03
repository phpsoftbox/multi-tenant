<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tenant\Provider;

use PhpSoftBox\Config\Config;
use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;

use function array_filter;
use function array_key_exists;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function strtolower;
use function trim;

final class ConfigTenantProvider implements TenantProviderInterface
{
    /** @var list<TenantDefinition>|null */
    private ?array $tenants = null;

    public function __construct(
        private readonly Config $config,
        private readonly string $configPath = 'tenancy.tenants',
        private readonly string $defaultDatabaseConnection = 'tenant',
    ) {
    }

    public function all(bool $onlyEnabled = true): array
    {
        $tenants = $this->load();
        if (!$onlyEnabled) {
            return $tenants;
        }

        return array_values(array_filter(
            $tenants,
            static fn (TenantDefinition $tenant): bool => $tenant->enabled,
        ));
    }

    public function findById(string $id): ?TenantDefinition
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        foreach ($this->load() as $tenant) {
            if ($tenant->id === $id) {
                return $tenant;
            }
        }

        return null;
    }

    public function findByHost(string $host): ?TenantDefinition
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return null;
        }

        foreach ($this->load() as $tenant) {
            foreach ($tenant->domains as $domain) {
                if (strtolower($domain) === $host) {
                    return $tenant;
                }
            }

            if (!is_string($tenant->host) || $tenant->host === '') {
                continue;
            }

            if (strtolower($tenant->host) === $host) {
                return $tenant;
            }
        }

        return null;
    }

    /**
     * @return list<TenantDefinition>
     */
    private function load(): array
    {
        if ($this->tenants !== null) {
            return $this->tenants;
        }

        $raw = $this->config->get($this->configPath, []);
        if (!is_array($raw)) {
            $this->tenants = [];

            return $this->tenants;
        }

        $result = [];
        foreach ($raw as $key => $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = $item['id'] ?? null;
            if (!is_string($id) || trim($id) === '') {
                $id = is_string($key) ? $key : null;
            }

            if (!is_string($id) || trim($id) === '') {
                continue;
            }
            $id = trim($id);

            $name = $item['name'] ?? $id;
            if (!is_string($name) || trim($name) === '') {
                $name = $id;
            }

            $host = $item['host'] ?? null;
            if (!is_string($host) || trim($host) === '') {
                $host = null;
            } else {
                $host = trim($host);
            }

            $connection = $item['database_connection'] ?? ($item['connection'] ?? $this->defaultDatabaseConnection);
            if (!is_string($connection) || trim($connection) === '') {
                $connection = $this->defaultDatabaseConnection;
            }

            $databaseName = $item['database_name'] ?? null;
            if (!is_string($databaseName) || trim($databaseName) === '') {
                $databaseName = null;
            } else {
                $databaseName = trim($databaseName);
            }

            $data = $item['data'] ?? [];
            if (!is_array($data)) {
                $data = [];
            }

            [$domains, $primaryDomain] = $this->resolveDomains($item, $host);
            $this->normalizeTelegramData($item, $data);

            $pushrAppId = $this->resolveString($item, ['pushr', 'app_id'])
                ?? $this->resolveString($item, ['pushr_app_id'])
                ?? $this->resolveString($data, ['pushr', 'app_id'])
                ?? $this->resolveString($data, ['pushr_app_id']);

            $pushrSecret = $this->resolveString($item, ['pushr', 'secret'])
                ?? $this->resolveString($item, ['pushr_secret'])
                ?? $this->resolveString($data, ['pushr', 'secret'])
                ?? $this->resolveString($data, ['pushr_secret']);

            $enabled = (bool) ($item['enabled'] ?? true);

            $result[] = new TenantDefinition(
                id: $id,
                name: trim($name),
                host: $primaryDomain,
                databaseConnection: trim($connection),
                pushrAppId: $pushrAppId,
                pushrSecret: $pushrSecret,
                enabled: $enabled,
                databaseName: $databaseName,
                primaryDomain: $primaryDomain,
                domains: $domains,
                data: $data,
            );
        }

        $this->tenants = $result;

        return $this->tenants;
    }

    /**
     * @param array<string, mixed> $tenantConfig
     * @return array{0:list<string>, 1:?string}
     */
    private function resolveDomains(array $tenantConfig, ?string $host): array
    {
        $domainsRaw = $tenantConfig['domains'] ?? [];

        $domains = [];
        $primary = null;

        if (is_array($domainsRaw)) {
            foreach ($domainsRaw as $domainItem) {
                if (is_string($domainItem)) {
                    $domain = trim($domainItem);
                    if ($domain === '') {
                        continue;
                    }

                    if (!in_array($domain, $domains, true)) {
                        $domains[] = $domain;
                    }

                    continue;
                }

                if (!is_array($domainItem)) {
                    continue;
                }

                $enabled = (bool) ($domainItem['enabled'] ?? true);
                if (!$enabled) {
                    continue;
                }

                $domain = $domainItem['domain'] ?? null;
                if (!is_string($domain) || trim($domain) === '') {
                    continue;
                }
                $domain = trim($domain);

                if (!in_array($domain, $domains, true)) {
                    $domains[] = $domain;
                }

                if ($primary === null && (bool) ($domainItem['is_primary'] ?? false)) {
                    $primary = $domain;
                }
            }
        }

        if ($host !== null && $host !== '' && !in_array($host, $domains, true)) {
            $domains[] = $host;
        }

        if ($primary === null && $host !== null && $host !== '') {
            $primary = $host;
        }

        if ($primary === null && $domains !== []) {
            $primary = $domains[0];
        }

        return [$domains, $primary];
    }

    /**
     * @param array<string, mixed> $tenantConfig
     */
    private function normalizeTelegramData(array $tenantConfig, array &$data): void
    {
        $botsRaw = $data['telegram_bots'] ?? null;
        if (!is_array($botsRaw)) {
            $telegramData = $data['telegram'] ?? null;
            if (is_array($telegramData)) {
                $botsRaw = $telegramData['bots'] ?? null;
            }
        }

        if (!is_array($botsRaw)) {
            $botsRaw = $tenantConfig['telegram_bots'] ?? null;
        }

        if (!is_array($botsRaw)) {
            $telegram = $tenantConfig['telegram'] ?? null;
            if (is_array($telegram)) {
                $botsRaw = $telegram['bots'] ?? null;
            }
        }

        if (!is_array($botsRaw)) {
            return;
        }

        $data['telegram_bots'] = $botsRaw;
    }

    /**
     * @param array<string, mixed> $source
     * @param list<string> $path
     */
    private function resolveString(array $source, array $path): ?string
    {
        if ($path === []) {
            return null;
        }

        $cursor = $source;
        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        if (!is_string($cursor)) {
            return null;
        }

        $cursor = trim($cursor);

        return $cursor !== '' ? $cursor : null;
    }
}
