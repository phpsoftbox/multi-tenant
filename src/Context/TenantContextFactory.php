<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Context;

use PhpSoftBox\MultiTenant\Contracts\TenantExtensionLoaderInterface;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use RuntimeException;

use function is_string;
use function str_contains;
use function trim;
use function usort;

final class TenantContextFactory
{
    /** @var list<TenantExtensionLoaderInterface> */
    private array $loaders;

    /**
     * @param iterable<TenantExtensionLoaderInterface> $loaders
     */
    public function __construct(iterable $loaders = [])
    {
        $normalized = [];
        foreach ($loaders as $loader) {
            $normalized[] = $loader;
        }

        usort(
            $normalized,
            static fn (TenantExtensionLoaderInterface $left, TenantExtensionLoaderInterface $right): int => $right->priority() <=> $left->priority(),
        );

        $this->loaders = $normalized;
    }

    public function create(
        TenantDefinition $tenant,
        TenantRuntimeScope $scope = TenantRuntimeScope::Cli,
    ): TenantContext {
        $context = new TenantContext($tenant);

        foreach ($this->loaders as $loader) {
            if (!$loader->supports($scope)) {
                continue;
            }

            $key = $loader->key();
            if (!is_string($key) || trim($key) === '') {
                throw new RuntimeException('Loader key должен быть непустой строкой: ' . $loader::class);
            }
            $key = trim($key);

            if ($context->has($key)) {
                throw new RuntimeException('Дублирующийся extension key: ' . $key);
            }

            $value   = $loader->load($tenant, $context);
            $context = $context->with(
                $key,
                $value,
            );

            $classKey = $loader::class;
            if (str_contains($classKey, '@anonymous')) {
                continue;
            }

            if ($classKey === $key) {
                continue;
            }

            if ($context->has($classKey)) {
                throw new RuntimeException('Дублирующийся extension key: ' . $classKey);
            }

            $context = $context->with($classKey, $value);
        }

        return $context;
    }
}
