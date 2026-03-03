<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Switcher;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Contracts\StorageNamespaceSwitcherInterface;
use PhpSoftBox\Storage\Drivers\Local\LocalStorage;
use PhpSoftBox\Storage\Drivers\S3\S3Storage;
use PhpSoftBox\Storage\Storage;
use ReflectionProperty;

use function array_keys;
use function array_pop;
use function array_values;
use function is_array;
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
                $rootPath = $this->readProperty($disk, 'rootPath');
                $baseUrl  = $this->readProperty($disk, 'baseUrl');

                $snapshot[$diskKey] = [
                    'driver'   => 'local',
                    'rootPath' => $rootPath,
                    'baseUrl'  => $baseUrl,
                ];

                $this->writeProperty($disk, 'rootPath', $this->joinPath($rootPath, $pathPrefix));
                if ($this->prefixBaseUrl && is_string($baseUrl) && $baseUrl !== '') {
                    $this->writeProperty($disk, 'baseUrl', $this->joinUrl($baseUrl, $pathPrefix));
                }

                continue;
            }

            if ($disk instanceof S3Storage) {
                $prefix  = $this->readProperty($disk, 'prefix');
                $baseUrl = $this->readProperty($disk, 'baseUrl');

                $snapshot[$diskKey] = [
                    'driver'  => 's3',
                    'prefix'  => $prefix,
                    'baseUrl' => $baseUrl,
                ];

                $this->writeProperty($disk, 'prefix', $this->joinPath((string) $prefix, $pathPrefix));
                if ($this->prefixBaseUrl && is_string($baseUrl) && $baseUrl !== '') {
                    $this->writeProperty($disk, 'baseUrl', $this->joinUrl($baseUrl, $pathPrefix));
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
                $this->writeProperty($disk, 'rootPath', (string) ($state['rootPath'] ?? ''));
                $this->writeProperty($disk, 'baseUrl', $state['baseUrl'] ?? null);

                continue;
            }

            if ($driver === 's3' && $disk instanceof S3Storage) {
                $this->writeProperty($disk, 'prefix', (string) ($state['prefix'] ?? ''));
                $this->writeProperty($disk, 'baseUrl', $state['baseUrl'] ?? null);
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

        $config = $this->readStorageConfig();
        $disks  = $config['disks'] ?? null;
        if (!is_array($disks) || $disks === []) {
            return [null];
        }

        return array_values(array_keys($disks));
    }

    /**
     * @return array<string, mixed>
     */
    private function readStorageConfig(): array
    {
        $property = new ReflectionProperty(Storage::class, 'config');

        $property->setAccessible(true);

        $value = $property->getValue($this->storage);

        return is_array($value) ? $value : [];
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

    private function readProperty(object $object, string $name): mixed
    {
        $property = new ReflectionProperty($object::class, $name);

        $property->setAccessible(true);

        return $property->getValue($object);
    }

    private function writeProperty(object $object, string $name, mixed $value): void
    {
        $property = new ReflectionProperty($object::class, $name);

        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}
