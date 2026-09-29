<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Switcher;

use PhpSoftBox\Cache\Contracts\CacheServiceInterface;
use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Contracts\CacheNamespaceSwitcherInterface;

use function array_key_exists;
use function array_pop;
use function array_values;
use function is_string;
use function trim;

/**
 * Namespace арендатора для кеша: задаёт контекстный namespace стора в `CacheServiceInterface`, который добавляется к
 * namespace стора из конфигурации во всех операциях (`get`/`set`, `store()`, `pool()`, `simple()`).
 */
final class CacheStoreNamespaceSwitcher implements CacheNamespaceSwitcherInterface
{
    private const string DEFAULT_STORE_KEY = '__default__';

    /** @var list<array<string, string>> */
    private array $stack = [];

    /**
     * @param list<string> $stores
     */
    public function __construct(
        private readonly CacheServiceInterface $cache,
        private readonly array $stores = [],
    ) {
    }

    public function activate(string $namespace, TenantContext $context): void
    {
        $namespace = trim($namespace);
        $snapshot  = [];

        foreach ($this->resolveStores() as $storeName) {
            $key = $storeName ?? self::DEFAULT_STORE_KEY;

            $previous       = $this->cache->contextNamespace($storeName);
            $snapshot[$key] = $previous;

            $next = $namespace;
            if ($previous !== '' && $namespace !== '') {
                $next = $previous . '.' . $namespace;
            } elseif ($namespace === '') {
                $next = $previous;
            }

            $this->cache->setContextNamespace($next, $storeName);
        }

        $this->stack[] = $snapshot;
    }

    public function deactivate(TenantContext $context): void
    {
        $snapshot = $this->stack !== [] ? array_pop($this->stack) : null;
        if ($snapshot === null) {
            return;
        }

        foreach ($snapshot as $storeKey => $namespace) {
            $storeName = $storeKey === self::DEFAULT_STORE_KEY ? null : $storeKey;
            $this->cache->setContextNamespace($namespace, $storeName);
        }
    }

    /**
     * @return list<?string>
     */
    private function resolveStores(): array
    {
        if ($this->stores === []) {
            return [null];
        }

        $resolved = [];
        foreach ($this->stores as $store) {
            if (!is_string($store)) {
                continue;
            }

            $store = trim($store);
            if ($store === '' || array_key_exists($store, $resolved)) {
                continue;
            }

            $resolved[$store] = $store;
        }

        return array_values($resolved);
    }
}
