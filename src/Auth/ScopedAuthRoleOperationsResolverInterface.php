<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Auth;

interface ScopedAuthRoleOperationsResolverInterface
{
    public function core(): ScopedAuthRoleOperationsInterface;

    public function tenant(): ScopedAuthRoleOperationsInterface;
}
