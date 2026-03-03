<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Switcher;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Contracts\StorageNamespaceSwitcherInterface;
use PhpSoftBox\Storage\Drivers\Local\LocalStorage;
use PhpSoftBox\Storage\Drivers\S3\S3Storage;
use PhpSoftBox\Storage\Storage;

use function array_pop;
use function array_values;
use function is_string;
use function ltrim;
use function rtrim;
use function trim;

final class StoragePathPrefixSwitcher implements StorageNamespaceSwitcherInterface
{
    private const string DEFAULT_DISK_KEY = '__default__';

    /** @var list<array<string, array<string, mixed>>> */
    private array $stack = [];

    /**
     * @param list<string> $disks
     */
    public function __construct(
        private readonly Storage $storage,
        private readonly array $disks = [],
        private readonly bool $prefixBaseUrl = true,
    ) {
    }

    public function activate(string $pathPrefix, TenantContext $context): void
    {
        $pathPrefix = trim($pathPrefix, " \t\n\r\0\x0B/");
        $snapshot   = [];

        if ($pathPrefix === '') {
            $this->stack[] = $snapshot;

            return;
        }

        foreach ($this->resolveDisks() as $diskName) {
            $diskKey = $diskName ?? self::DEFAULT_DISK_KEY;
            $disk    = $this->storage->disk($diskName);

            if ($disk instanceof LocalStorage) {
                $rootPath = $disk->rootPath();
                $baseUrl  = $disk->baseUrl();

                $snapshot[$diskKey] = [
                    'driver'   => 'local',
                    'rootPath' => $rootPath,
                    'baseUrl'  => $baseUrl,
                ];

                $disk->setRootPath($this->joinPath($rootPath, $pathPrefix));
                if ($this->prefixBaseUrl && is_string($baseUrl) && $baseUrl !== '') {
                    $disk->setBaseUrl($this->joinUrl($baseUrl, $pathPrefix));
                }

                continue;
            }

            if ($disk instanceof S3Storage) {
                $prefix  = $disk->prefix();
                $baseUrl = $disk->baseUrl();

                $snapshot[$diskKey] = [
                    'driver'  => 's3',
                    'prefix'  => $prefix,
                    'baseUrl' => $baseUrl,
                ];

                $disk->setPrefix($this->joinPath((string) $prefix, $pathPrefix));
                if ($this->prefixBaseUrl && is_string($baseUrl) && $baseUrl !== '') {
                    $disk->setBaseUrl($this->joinUrl($baseUrl, $pathPrefix));
                }
            }
        }

        $this->stack[] = $snapshot;
    }

    public function deactivate(TenantContext $context): void
    {
        $snapshot = $this->stack !== [] ? array_pop($this->stack) : null;
        if ($snapshot === null) {
            return;
        }

        foreach ($snapshot as $diskKey => $state) {
            $diskName = $diskKey === self::DEFAULT_DISK_KEY ? null : $diskKey;
            $disk     = $this->storage->disk($diskName);
            $driver   = $state['driver'] ?? null;

            if ($driver === 'local' && $disk instanceof LocalStorage) {
                $disk->setRootPath((string) ($state['rootPath'] ?? ''));
                $disk->setBaseUrl(is_string($state['baseUrl'] ?? null) ? $state['baseUrl'] : null);

                continue;
            }

            if ($driver === 's3' && $disk instanceof S3Storage) {
                $disk->setPrefix((string) ($state['prefix'] ?? ''));
                $disk->setBaseUrl(is_string($state['baseUrl'] ?? null) ? $state['baseUrl'] : null);
            }
        }
    }

    /**
     * @return list<?string>
     */
    private function resolveDisks(): array
    {
        if ($this->disks !== []) {
            $out = [];
            foreach ($this->disks as $disk) {
                if (!is_string($disk)) {
                    continue;
                }

                $disk = trim($disk);
                if ($disk === '') {
                    continue;
                }

                $out[$disk] = $disk;
            }

            return array_values($out);
        }

        $disks = $this->storage->diskNames();
        if ($disks === []) {
            return [null];
        }

        return array_values($disks);
    }

    private function joinPath(string $base, string $suffix): string
    {
        $base   = rtrim($base, '/');
        $suffix = ltrim($suffix, '/');

        if ($base === '') {
            return $suffix;
        }

        if ($suffix === '') {
            return $base;
        }

        return $base . '/' . $suffix;
    }

    private function joinUrl(string $base, string $suffix): string
    {
        $base   = rtrim($base, '/');
        $suffix = ltrim($suffix, '/');

        if ($suffix === '') {
            return $base;
        }

        return $base . '/' . $suffix;
    }
}
