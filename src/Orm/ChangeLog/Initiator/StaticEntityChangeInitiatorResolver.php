<?php

declare(strict_types=1);

namespace PhpSoftBox\MultiTenant\Orm\ChangeLog\Initiator;

use PhpSoftBox\MultiTenant\Orm\ChangeLog\EntityChangeInitiatorResolverInterface;

final class StaticEntityChangeInitiatorResolver implements EntityChangeInitiatorResolverInterface
{
    public function __construct(
        private readonly int|string|null $initiatorId,
    ) {
    }

    public function resolve(): int|string|null
    {
        return $this->initiatorId;
    }
}
