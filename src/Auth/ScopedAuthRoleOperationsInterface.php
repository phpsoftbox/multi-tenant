<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Auth;

use PhpSoftBox\Auth\Authorization\RoleSyncPlan;

interface ScopedAuthRoleOperationsInterface
{
    public function sync(bool $dryRun = false): ?RoleSyncPlan;

    public function assignRole(int|string $userId, string $role): void;

    public function dischargeRole(int|string $userId, string $role): void;
}
