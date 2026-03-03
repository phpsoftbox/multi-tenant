<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Context;

use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use RuntimeException;

use function array_key_exists;
use function is_object;
use function sprintf;

final class TenantContext
{
    /**
     * @param array<string, mixed> $extensions
     */
    public function __construct(
        private readonly TenantDefinition $tenant,
        private array $extensions = [],
    ) {
    }

    public function tenant(): TenantDefinition
    {
        return $this->tenant;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->extensions);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, $this->extensions)) {
            return $default;
        }

        return $this->extensions[$key];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->extensions;
    }

    public function getTyped(string $class): object
    {
        foreach ($this->extensions as $value) {
            if (is_object($value) && $value instanceof $class) {
                return $value;
            }
        }

        throw new RuntimeException(sprintf('Extension "%s" не найден в tenant context.', $class));
    }

    public function with(string $key, mixed $value): self
    {
        $copy       = $this->extensions;
        $copy[$key] = $value;

        return new self($this->tenant, $copy);
    }
}
