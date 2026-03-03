<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Provision;

use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;

use function array_key_exists;

final class TenantProvisionContext
{
    /** @var array<string, mixed> */
    private array $state = [];

    public function __construct(
        private readonly TenantDefinition $tenant,
        private readonly TenantDefinition $templateTenant,
        private readonly TenantProvisionPayload $payload,
    ) {
    }

    public function tenant(): TenantDefinition
    {
        return $this->tenant;
    }

    public function templateTenant(): TenantDefinition
    {
        return $this->templateTenant;
    }

    public function payload(): TenantProvisionPayload
    {
        return $this->payload;
    }

    public function with(string $key, mixed $value): self
    {
        $clone              = clone $this;
        $clone->state[$key] = $value;

        return $clone;
    }

    public function set(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->state);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function state(): array
    {
        return $this->state;
    }
}
