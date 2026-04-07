<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Tests\Support;

use PhpSoftBox\MultiTenant\Auth\ScopedAuthRoleOperationsInterface;
use PhpSoftBox\MultiTenant\Auth\ScopedAuthRoleOperationsResolverInterface;

final readonly class RecordingScopedAuthRoleOperationsResolver implements ScopedAuthRoleOperationsResolverInterface
{
    public function __construct(
        private ScopedAuthRoleOperationsInterface $core,
        private ScopedAuthRoleOperationsInterface $tenant,
    ) {
    }

    public function core(): ScopedAuthRoleOperationsInterface
    {
        return $this->core;
    }

    public function tenant(): ScopedAuthRoleOperationsInterface
    {
        return $this->tenant;
    }
}
