<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Context\Loader;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Context\TenantRuntimeScope;
use PhpSoftBox\MultiTenant\Contracts\TenantExtensionLoaderInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;

use function array_key_exists;
use function is_array;
use function is_string;
use function trim;

final readonly class TenantPushrCredentialsLoader implements TenantExtensionLoaderInterface
{
    public function __construct(
        private string $extensionKey = 'pushr.credentials',
        private int $extensionPriority = 650,
    ) {
    }

    public function key(): string
    {
        return $this->extensionKey;
    }

    public function supports(TenantRuntimeScope $scope): bool
    {
        return true;
    }

    public function priority(): int
    {
        return $this->extensionPriority;
    }

    public function load(TenantDefinition $tenant, TenantContext $context): mixed
    {
        return [
            'app_id' => $this->resolveAppId($tenant),
            'secret' => $this->resolveSecret($tenant),
        ];
    }

    private function resolveAppId(TenantDefinition $tenant): ?string
    {
        return $this->normalizeString($tenant->pushrAppId)
            ?? $this->dataString($tenant->data, 'pushr', 'app_id')
            ?? $this->dataString($tenant->data, 'pushr_app_id');
    }

    private function resolveSecret(TenantDefinition $tenant): ?string
    {
        return $this->normalizeString($tenant->pushrSecret)
            ?? $this->dataString($tenant->data, 'pushr', 'secret')
            ?? $this->dataString($tenant->data, 'pushr_secret');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function dataString(array $data, string ...$path): ?string
    {
        if ($path === []) {
            return null;
        }

        $cursor = $data;
        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
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
