<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Orm\ChangeLog;

interface EntityChangeInitiatorResolverInterface
{
    public function resolve(): int|string|null;
}
