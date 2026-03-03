<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tenant\Host;

use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;

final readonly class TenantHostResolution
{
    private const string KIND_CENTRAL  = 'central';
    private const string KIND_TENANT   = 'tenant';
    private const string KIND_NOTFOUND = 'not_found';

    private function __construct(
        public string $host,
        public string $kind,
        public ?TenantDefinition $tenant = null,
    ) {
    }

    public static function central(string $host): self
    {
        return new self($host, self::KIND_CENTRAL);
    }

    public static function tenant(string $host, TenantDefinition $tenant): self
    {
        return new self($host, self::KIND_TENANT, $tenant);
    }

    public static function notFound(string $host): self
    {
        return new self($host, self::KIND_NOTFOUND);
    }

    public function isCentral(): bool
    {
        return $this->kind === self::KIND_CENTRAL;
    }

    public function isTenant(): bool
    {
        return $this->kind === self::KIND_TENANT;
    }

    public function isNotFound(): bool
    {
        return $this->kind === self::KIND_NOTFOUND;
    }

    public function shouldBootstrapTenant(): bool
    {
        return $this->isTenant() && $this->tenant !== null && $this->tenant->enabled;
    }
}
