<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tenant;

use function array_filter;
use function array_values;

final readonly class TenantDefinition
{
    /**
     * @param list<string> $domains
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $host,
        public string $databaseConnection,
        public ?string $pushrAppId = null,
        public ?string $pushrSecret = null,
        public bool $enabled = true,
        public ?string $databaseName = null,
        public ?string $primaryDomain = null,
        public array $domains = [],
        public array $data = [],
    ) {
    }

    public function hasPushrCredentials(): bool
    {
        return $this->pushrAppId !== null
            && $this->pushrAppId !== ''
            && $this->pushrSecret !== null
            && $this->pushrSecret !== '';
    }

    /**
     * @return list<string>
     */
    public function mirrorDomains(): array
    {
        if ($this->primaryDomain === null || $this->primaryDomain === '') {
            return $this->domains;
        }

        return array_values(array_filter(
            $this->domains,
            fn (string $domain): bool => $domain !== $this->primaryDomain,
        ));
    }

}
