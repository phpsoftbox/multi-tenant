<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tenant;

use InvalidArgumentException;
use PhpSoftBox\MultiTenant\Contracts\TenantProviderInterface;
use RuntimeException;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function in_array;
use function trim;

final readonly class TenantSelector
{
    public function __construct(
        private TenantProviderInterface $provider,
    ) {
    }

    /**
     * @return list<TenantDefinition>
     */
    public function select(string $tenant = 'all', bool $onlyEnabled = true): array
    {
        $tenant = trim($tenant);
        if ($tenant === '' || $tenant === 'all') {
            return $this->provider->all($onlyEnabled);
        }

        $ids = array_values(array_filter(
            array_map('trim', explode(',', $tenant)),
            static fn (string $id): bool => $id !== '',
        ));
        if ($ids === []) {
            throw new InvalidArgumentException('Не указан tenant.');
        }

        $out = [];
        foreach ($ids as $id) {
            $resolved = $this->provider->findById($id);
            if ($resolved === null) {
                throw new RuntimeException('Tenant не найден: ' . $id);
            }

            if ($onlyEnabled && !$resolved->enabled) {
                throw new RuntimeException('Tenant отключен: ' . $id);
            }

            if (in_array($resolved, $out, true)) {
                continue;
            }

            $out[] = $resolved;
        }

        return $out;
    }
}
