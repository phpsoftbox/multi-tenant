<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests;

use PhpSoftBox\MultiTenant\Context\TenantContext;
use PhpSoftBox\MultiTenant\Switcher\StoragePathPrefixSwitcher;
use PhpSoftBox\MultiTenant\Tenant\TenantDefinition;
use PhpSoftBox\Storage\FileHelper;
use PhpSoftBox\Storage\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_exists;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;

#[CoversClass(StoragePathPrefixSwitcher::class)]
final class StoragePathPrefixSwitcherTest extends TestCase
{
    #[Test]
    public function testActivateAddsPathPrefixAndDeactivateRestoresRoots(): void
    {
        $root = sprintf('%s/multi-tenant-storage-%s', sys_get_temp_dir(), uniqid('', true));

        try {
            $storage = new Storage([
                'default' => 'local',
                'disks'   => [
                    'local' => [
                        'driver'   => 'local',
                        'rootPath' => $root,
                        'baseUrl'  => '/storage/local',
                    ],
                ],
            ]);

            $context  = $this->context();
            $switcher = new StoragePathPrefixSwitcher($storage, ['local']);

            $switcher->activate('tenant/alpha', $context);

            $storage->disk('local')->put('avatar.txt', 'tenant-data');
            $this->assertTrue(file_exists($root . '/tenant/alpha/avatar.txt'));

            $switcher->deactivate($context);

            $storage->disk('local')->put('root.txt', 'base-data');
            $this->assertTrue(file_exists($root . '/root.txt'));
        } finally {
            FileHelper::deleteDirectory($root);
        }
    }

    private function context(): TenantContext
    {
        return new TenantContext(new TenantDefinition('tenant-alpha', 'Tenant Alpha', null, 'tenant'));
    }
}
