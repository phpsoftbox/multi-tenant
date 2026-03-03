<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tenant;

use function ksort;
use function sort;

final class TenantDefinitionNormalizer
{
    /**
     * @param list<TenantDefinition> $tenants
     * @return array<string, array<string, mixed>>
     */
    public function normalize(array $tenants): array
    {
        $out = [];
        foreach ($tenants as $tenant) {
            $domains = $tenant->domains;
            sort($domains);

            $data = $tenant->data;
            ksort($data);

            $out[$tenant->id] = [
                'id'                  => $tenant->id,
                'name'                => $tenant->name,
                'enabled'             => $tenant->enabled,
                'database_connection' => $tenant->databaseConnection,
                'database_name'       => $tenant->databaseName,
                'primary_domain'      => $tenant->primaryDomain ?? $tenant->host,
                'domains'             => $domains,
                'pushr_app_id'        => $tenant->pushrAppId,
                'pushr_secret'        => $tenant->pushrSecret,
                'data'                => $data,
            ];
        }

        ksort($out);

        return $out;
    }
}
