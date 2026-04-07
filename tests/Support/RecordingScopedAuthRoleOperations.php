<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests\Support;

use PhpSoftBox\Auth\Authorization\RoleSyncPlan;
use PhpSoftBox\MultiTenant\Auth\ScopedAuthRoleOperationsInterface;

final class RecordingScopedAuthRoleOperations implements ScopedAuthRoleOperationsInterface
{
    /**
     * @var list<string>
     */
    public array $calls = [];

    public function __construct(
        private readonly ?RoleSyncPlan $plan = null,
    ) {
    }

    public function sync(bool $dryRun = false): ?RoleSyncPlan
    {
        $this->calls[] = 'sync:' . ($dryRun ? 'dry-run' : 'run');

        return $this->plan;
    }

    public function assignRole(int|string $userId, string $role): void
    {
        $this->calls[] = 'assign:' . $userId . ':' . $role;
    }

    public function dischargeRole(int|string $userId, string $role): void
    {
        $this->calls[] = 'discharge:' . $userId . ':' . $role;
    }
}
